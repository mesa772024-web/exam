<?php
declare(strict_types=1);

const ROLE_LEVELS = [
    'viewer' => 20,
    'reviewer' => 40,
    'editor' => 60,
    'admin' => 80,
    'super_admin' => 100,
];

function admin_count(): int
{
    return (int) db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
}

function current_admin(): ?array
{
    static $admin;
    static $loaded = false;
    if ($loaded) {
        return $admin;
    }
    $loaded = true;

    $id = (int) ($_SESSION['admin_id'] ?? 0);
    if ($id < 1) {
        return null;
    }
    $now = time();
    $issued = (int) ($_SESSION['admin_issued_at'] ?? 0);
    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
    $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($issued < $now - 28800 || $lastSeen < $now - 1800 || !hash_equals((string) ($_SESSION['admin_fingerprint'] ?? ''), $fingerprint)) {
        clear_admin_session();
        return null;
    }
    if ((int) ($_SESSION['admin_regenerated_at'] ?? 0) < $now - 900) {
        session_regenerate_id(true);
        $_SESSION['admin_regenerated_at'] = $now;
    }
    $_SESSION['admin_last_seen'] = $now;

    $statement = db()->prepare('SELECT id, username, display_name, role, active FROM admin_users WHERE id = :id');
    $statement->execute(['id' => $id]);
    $record = $statement->fetch();
    if (!$record || !(bool) $record['active']) {
        clear_admin_session();
        return null;
    }
    $admin = $record;
    return $admin;
}

function require_admin(string $minimumRole = 'viewer'): array
{
    $admin = current_admin();
    if (!$admin) {
        if (request_expects_json()) {
            json_response([
                'ok' => false,
                'message' => 'انتهت جلسة الإدارة. سجّل الدخول مجدداً ثم أعد المحاولة.',
                'login_url' => admin_url('login.php'),
            ], 401);
        }
        $return = clean_text($_SERVER['REQUEST_URI'] ?? '', 300);
        header('Location: ' . admin_url('login.php?return=' . rawurlencode($return)));
        exit;
    }
    if (!admin_can($admin, $minimumRole)) {
        security_event('admin.permission_denied', 'warning', ['required' => $minimumRole]);
        if (request_expects_json()) {
            json_response(['ok' => false, 'message' => 'غير مصرح لك بتنفيذ هذا الإجراء.'], 403);
        }
        http_response_code(403);
        exit('غير مصرح لك بتنفيذ هذا الإجراء.');
    }
    return $admin;
}

function admin_can(array $admin, string $minimumRole): bool
{
    return (ROLE_LEVELS[$admin['role']] ?? 0) >= (ROLE_LEVELS[$minimumRole] ?? PHP_INT_MAX);
}

function attempt_admin_login(string $username, string $password): bool
{
    $username = mb_strtolower(trim($username), 'UTF-8');
    enforce_rate_limit('admin_login', 8, 900, $username);
    $statement = db()->prepare('SELECT * FROM admin_users WHERE username = :username AND active = 1 LIMIT 1');
    $statement->execute(['username' => $username]);
    $admin = $statement->fetch();
    $dummy = '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    if (!$admin) {
        password_verify($password, $dummy);
        security_event('admin.login_failed', 'warning', ['username_hash' => hash('sha256', $username)]);
        return false;
    }
    if (!empty($admin['locked_until']) && strtotime((string) $admin['locked_until']) > time()) {
        password_verify($password, $admin['password_hash']);
        security_event('admin.account_locked', 'warning', ['admin_id' => (int) $admin['id']]);
        return false;
    }
    if (!password_verify($password, $admin['password_hash'])) {
        $failures = (int) $admin['failed_login_count'] + 1;
        $lock = $failures >= 5 ? gmdate('Y-m-d H:i:s', time() + 900) : null;
        $update = db()->prepare('UPDATE admin_users SET failed_login_count = :count, locked_until = :locked, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute(['count' => $failures, 'locked' => $lock, 'id' => $admin['id']]);
        security_event('admin.login_failed', $lock ? 'critical' : 'warning', ['admin_id' => (int) $admin['id'], 'failures' => $failures]);
        return false;
    }
    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE admin_users SET password_hash = :hash WHERE id = :id')->execute([
            'hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $admin['id'],
        ]);
    }
    session_regenerate_id(true);
    $now = time();
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_issued_at'] = $now;
    $_SESSION['admin_last_seen'] = $now;
    $_SESSION['admin_regenerated_at'] = $now;
    $_SESSION['admin_fingerprint'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    db()->prepare('UPDATE admin_users SET failed_login_count = 0, locked_until = NULL, last_login_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE id = :id')->execute(['id' => $admin['id']]);
    write_audit((int) $admin['id'], $admin['display_name'], 'auth.login', 'admin_user', (string) $admin['id']);
    return true;
}

function verify_admin_password(array $admin, string $password): bool
{
    $statement = db()->prepare('SELECT password_hash FROM admin_users WHERE id = :id AND active = 1');
    $statement->execute(['id' => $admin['id']]);
    $hash = $statement->fetchColumn();
    return is_string($hash) && password_verify($password, $hash);
}

function clear_admin_session(): void
{
    foreach (['admin_id', 'admin_issued_at', 'admin_last_seen', 'admin_regenerated_at', 'admin_fingerprint'] as $key) {
        unset($_SESSION[$key]);
    }
    session_regenerate_id(true);
}

function write_audit(int $adminId, string $actor, string $action, string $entityType, string $entityId, array $details = []): void
{
    $statement = db()->prepare('INSERT INTO audit_logs(admin_id, actor_name, action, entity_type, entity_id, details_json, ip_hash) VALUES(:admin_id, :actor, :action, :entity_type, :entity_id, :details, :ip)');
    $statement->execute([
        'admin_id' => $adminId,
        'actor' => clean_text($actor, 100),
        'action' => clean_text($action, 100),
        'entity_type' => clean_text($entityType, 60),
        'entity_id' => clean_text($entityId, 100),
        'details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'ip' => ip_hash(),
    ]);
}

function role_label(string $role): string
{
    return [
        'viewer' => 'مشاهد',
        'reviewer' => 'مراجع',
        'editor' => 'محرر',
        'admin' => 'مدير',
        'super_admin' => 'مدير أعلى',
    ][$role] ?? $role;
}
