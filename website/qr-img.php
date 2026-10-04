<?php
/**
 * صورة باركود QR لمسجّل — عامة لكن محمية بتوكن (ليجلبها واتساب API).
 * ?id=..&t=TOKEN   حيث TOKEN = أول 24 حرفاً من hmac(id, SECRET)
 */
require_once __DIR__ . '/app/guard.php';
require_once __DIR__ . '/app/badge.php';
require_once __DIR__ . '/app/qrlib.php';

// لا حاجة لجلسة — نقطة عامة، لكن guard للحماية العامة
guard_boot();

$id = (int)($_GET['id'] ?? 0);
$tok = (string)($_GET['t'] ?? '');
$expected = substr(hash_hmac('sha256', 'qr:' . $id, SCF_SECRET), 0, 24);
if ($id <= 0 || !hash_equals($expected, $tok)) {
    http_response_code(403);
    exit;
}

$r = q_one('SELECT * FROM registrants WHERE id = ?', [$id]);
if ($r === null) { http_response_code(404); exit; }

$payload = barcode_payload($r);
$size = max(4, min(16, (int)($_GET['s'] ?? 10)));

try {
    $qr = QRCode::getMinimumQRCode($payload, QR_ERROR_CORRECT_LEVEL_M);
    $img = $qr->createImage($size, 4, 0x24275F, 0xFFFFFF);
    header('Content-Type: image/png');
    header('Cache-Control: private, max-age=3600');
    imagepng($img);
    imagedestroy($img);
} catch (Throwable $e) {
    http_response_code(500);
    exit;
}
