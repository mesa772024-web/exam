<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/maintenance.php';
$admin = require_admin('super_admin');
$type = ($_GET['type'] ?? '') === 'files' ? 'files' : 'database';
enforce_rate_limit('backup_download', 6, 3600, (string)$admin['id']);
$path = $type === 'files' ? create_files_backup() : create_database_backup();
write_audit((int)$admin['id'],$admin['display_name'],'backup.download',$type,basename($path));
header('Content-Type: '.($type==='files'?'application/zip':'application/sql; charset=UTF-8'));
header('Content-Length: '.filesize($path));
header('Content-Disposition: attachment; filename="'.basename($path).'"');
header('Cache-Control: no-store');
readfile($path);
