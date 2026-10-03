<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }
verify_csrf($_POST['csrf_token'] ?? null);
$admin = current_admin();
if ($admin) write_audit((int) $admin['id'], $admin['display_name'], 'auth.logout', 'admin_user', (string) $admin['id']);
clear_admin_session();
header('Location: ' . admin_url('login.php'));
exit;
