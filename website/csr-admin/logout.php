<?php
require_once __DIR__ . '/inc/auth.php';
admin_audit('logout');
admin_logout();
session_regenerate_id(true);
header('Location: index.php');
exit;
