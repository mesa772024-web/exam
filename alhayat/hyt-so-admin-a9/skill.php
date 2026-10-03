<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin('editor');
$path = APP_ROOT . '/SKILL.md';
if (!is_file($path)) {
    http_response_code(404);
    exit('SKILL.md not found');
}
write_audit((int) $admin['id'], $admin['display_name'], 'skill.download', 'file', 'SKILL.md');
header('Content-Type: text/markdown; charset=UTF-8');
header('Content-Disposition: attachment; filename="SKILL.md"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store');
readfile($path);
