<?php
require_once __DIR__ . '/config.php';

/** رمز CSRF لكل جلسة مع تدوير كل ساعتين */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['_csrf']) || (time() - ($_SESSION['_csrf_t'] ?? 0)) > 7200) {
        $_SESSION['_csrf']   = bin2hex(random_bytes(32));
        $_SESSION['_csrf_t'] = time();
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function csrf_verify(): bool
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $ok = is_string($sent) && $sent !== '' && !empty($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $sent);
    if (!$ok && function_exists('sec_log')) {
        sec_log('csrf_fail', '', 'medium');
    }
    return $ok;
}

function csrf_require(): void
{
    if (!csrf_verify()) {
        http_response_code(419);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['ok' => false, 'msg' => 'انتهت صلاحية الجلسة، حدّث الصفحة وأعد المحاولة']));
    }
}
