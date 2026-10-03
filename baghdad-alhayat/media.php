<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$id = clean_text($_GET['id'] ?? '', 50);
if (!preg_match('/^[a-f0-9-]{36}$/', $id)) { http_response_code(404); exit; }
$statement = db()->prepare('SELECT a.*, c.visitor_id AS owner_id FROM attachments a JOIN conversations c ON c.id = a.conversation_id WHERE a.id = :id');
$statement->execute(['id' => $id]);
$attachment = $statement->fetch();
if (!$attachment) { http_response_code(404); exit; }
$admin = current_admin();
$visitorId = current_visitor_id();
if (!$admin && (!$visitorId || !hash_equals((string) $attachment['owner_id'], $visitorId))) { http_response_code(403); exit; }
$relative = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $attachment['file_path']);
$path = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . $relative);
$root = realpath(UPLOAD_PATH);
if (!$path || !$root || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !is_file($path) || !hash_equals((string) $attachment['sha256'], hash_file('sha256', $path))) { security_event('media.integrity_failed', 'critical', ['attachment' => $id]); http_response_code(404); exit; }
$inline = str_starts_with($attachment['mime_type'], 'image/') || str_starts_with($attachment['mime_type'], 'audio/') || str_starts_with($attachment['mime_type'], 'video/');
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename*=UTF-8\'\'' . rawurlencode($attachment['original_name']));
header('Cache-Control: private, max-age=300');
readfile($path);
