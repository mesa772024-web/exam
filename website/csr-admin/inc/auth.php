<?php
/**
 * حارس لوحة التحكم — يُحمَّل في بداية كل صفحة إدارية.
 * تحقق: الجلسة، الصلاحية، المهلة، القائمة البيضاء للعناوين، تطابق مسار المجلد.
 */
require_once dirname(__DIR__, 2) . '/app/guard.php';
require_once dirname(__DIR__, 2) . '/app/csrf.php';
require_once dirname(__DIR__, 2) . '/app/helpers.php';

/* تطابق اسم المجلد مع الإعداد — حماية من نسخ اللوحة لمسار آخر */
if (basename(dirname(__DIR__)) !== ADMIN_DIR) {
    http_response_code(404);
    exit;
}

guard_boot(['admin' => true, 'waf_exempt' => ['blocks', 'raw_code', 'wa_template_ar', 'wa_template_en', 'value', 'reg_intro_ar', 'reg_intro_en']]);

/* القائمة البيضاء للعناوين (إن فُعّلت) */
function admin_ip_allowed(): bool
{
    $wl = trim(setting('admin_ip_whitelist', ''));
    if ($wl === '') return true;
    $ips = array_filter(array_map('trim', explode(',', $wl)));
    if (!$ips) return true;
    $me = client_ip();
    foreach ($ips as $ip) {
        if ($ip === $me) return true;
        // دعم البادئات مثل 192.168.
        if (substr($ip, -1) === '.' && strpos($me, $ip) === 0) return true;
    }
    return false;
}

if (!admin_ip_allowed()) {
    sec_log('admin_ip_denied', '', 'high');
    http_response_code(404);
    exit;
}

function admin_user(): ?array
{
    if (empty($_SESSION['admin_id'])) return null;
    /* مهلة الخمول */
    $idle = time() - (int)($_SESSION['admin_last'] ?? 0);
    if ($idle > SESSION_IDLE_MIN * 60) {
        admin_logout();
        return null;
    }
    /* العمر الأقصى */
    $age = time() - (int)($_SESSION['admin_start'] ?? 0);
    if ($age > SESSION_ABS_HOURS * 3600) {
        admin_logout();
        return null;
    }
    $_SESSION['admin_last'] = time();
    return [
        'id'   => (int)$_SESSION['admin_id'],
        'name' => (string)($_SESSION['admin_name'] ?? ''),
        'user' => (string)($_SESSION['admin_user'] ?? ''),
        'role' => (string)($_SESSION['admin_role'] ?? 'super'),
    ];
}

/** هل الحساب الحالي مدير كامل الصلاحية؟ */
function is_super(): bool
{
    $u = admin_user();
    return $u !== null && ($u['role'] ?? 'super') === 'super';
}

/** يتطلب صلاحية مدير كامل — يعيد موظف التسجيل لصفحة القاعة */
function require_super(): array
{
    $u = admin_require();
    if (($u['role'] ?? 'super') !== 'super') {
        sec_log('desk_denied', $_SERVER['SCRIPT_NAME'] ?? '', 'medium');
        header('Location: checkin.php');
        exit;
    }
    return $u;
}

function admin_logout(): void
{
    unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_user'], $_SESSION['admin_last'], $_SESSION['admin_start']);
}

function admin_require(): array
{
    $u = admin_user();
    if ($u === null) {
        $login = base_url() . '/' . ADMIN_DIR . '/';
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            json_out(['ok' => false, 'auth' => false, 'msg' => 'انتهت الجلسة'], 401);
        }
        header('Location: ' . $login);
        exit;
    }
    return $u;
}

/** تسجيل إجراء إداري في السجل */
function admin_audit(string $action, string $detail = ''): void
{
    $u = admin_user();
    sec_log('admin:' . $action, ($u ? $u['user'] . ' — ' : '') . $detail, 'info');
}
