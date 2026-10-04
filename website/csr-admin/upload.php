<?php
/**
 * رفع الصور — فحص صارم: نوع MIME حقيقي + إعادة ترميز عبر GD + اسم عشوائي.
 * يمنع أي ملف غير صوري مهما كان امتداده.
 */
require_once __DIR__ . '/inc/auth.php';

require_super();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false], 405);
csrf_require();

if (!rate_limit('admin_upload', 60, 3600)) {
    json_out(['ok' => false, 'msg' => 'تجاوزت حد الرفع، انتظر قليلاً'], 429);
}

$dirKey = (string)($_POST['dir'] ?? 'misc');
$allowedDirs = ['speakers', 'orgs', 'pages', 'gallery', 'edition1', 'badges', 'misc'];
if (!in_array($dirKey, $allowedDirs, true)) $dirKey = 'misc';

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_out(['ok' => false, 'msg' => 'لم يصل أي ملف'], 400);
}
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'msg' => 'فشل الرفع (' . (int)$f['error'] . ')'], 400);
if ($f['size'] > 5 * 1024 * 1024) json_out(['ok' => false, 'msg' => 'الحد الأقصى 5MB'], 400);

/* النوع الحقيقي من محتوى الملف وليس الامتداد */
$info = @getimagesize($f['tmp_name']);
if ($info === false) {
    sec_log('upload_reject', 'not an image: ' . $f['name'], 'medium');
    json_out(['ok' => false, 'msg' => 'الملف ليس صورة صالحة'], 400);
}
$mime = $info['mime'];
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'png'];
if (!isset($allowed[$mime])) {
    sec_log('upload_reject', 'mime=' . $mime, 'medium');
    json_out(['ok' => false, 'msg' => 'نوع الصورة غير مدعوم (JPG/PNG/WebP)'], 400);
}

/* إعادة الترميز تفكك أي حمولة مخبأة داخل الصورة */
switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($f['tmp_name']); break;
    case 'image/png':  $src = @imagecreatefrompng($f['tmp_name']);  break;
    case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($f['tmp_name']) : false; break;
    case 'image/gif':  $src = @imagecreatefromgif($f['tmp_name']);  break;
    default: $src = false;
}
if ($src === false) json_out(['ok' => false, 'msg' => 'تعذر معالجة الصورة'], 400);

/* تحجيم لأقصى 1600px */
$w = imagesx($src); $h = imagesy($src);
$max = 1600;
if ($w > $max || $h > $max) {
    $ratio = min($max / $w, $max / $h);
    $nw = (int)($w * $ratio); $nh = (int)($h * $ratio);
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    $src = $dst;
}

$ext = ($mime === 'image/jpeg') ? 'jpg' : 'png';
$name = $dirKey . '/' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
$path = SCF_UPLOADS . '/' . $name;
if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);

$ok = ($ext === 'jpg') ? imagejpeg($src, $path, 88) : imagepng($src, $path, 8);
imagedestroy($src);
if (!$ok) json_out(['ok' => false, 'msg' => 'تعذر حفظ الصورة'], 500);

admin_audit('upload', $name);
json_out(['ok' => true, 'path' => $name, 'url' => upload_url($name)]);
