<?php
/** صورة رمز التحقق — GD، الرمز مخزن مشفراً في الجلسة */
require_once dirname(__DIR__) . '/app/guard.php';
guard_boot();

if (!rate_limit('captcha', 30, 300)) {
    http_response_code(429);
    exit;
}

$alphabet = 'ABDEFHJKMNPRTUWXY34678';
$code = '';
for ($i = 0; $i < 5; $i++) {
    $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}
$_SESSION['captcha_hash'] = password_hash(strtoupper($code), PASSWORD_BCRYPT);
$_SESSION['captcha_time'] = time();

$w = 190; $h = 62;
$img = imagecreatetruecolor($w, $h);
$bg  = imagecolorallocate($img, 14, 17, 48);
$fg  = imagecolorallocate($img, 40, 163, 219);
$fg2 = imagecolorallocate($img, 120, 200, 240);
$ln  = imagecolorallocate($img, 36, 39, 95);
imagefilledrectangle($img, 0, 0, $w, $h, $bg);

for ($i = 0; $i < 7; $i++) {
    imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $ln);
}
for ($i = 0; $i < 60; $i++) {
    imagesetpixel($img, random_int(0, $w - 1), random_int(0, $h - 1), $ln);
}
// رسم الأحرف بمقياس مكبر مع إزاحات عشوائية
$x = 18;
for ($i = 0; $i < strlen($code); $i++) {
    $c = $code[$i];
    $size = 5;
    $cw = imagefontwidth($size);
    $ch = imagefontheight($size);
    $tmp = imagecreatetruecolor($cw + 2, $ch + 2);
    imagefilledrectangle($tmp, 0, 0, $cw + 2, $ch + 2, $bg);
    imagestring($tmp, $size, 1, 1, $c, ($i % 2 === 0) ? $fg : $fg2);
    $scale = 2;
    $dw = ($cw + 2) * $scale;
    $dh = ($ch + 2) * $scale;
    $dy = random_int(2, $h - $dh - 2);
    imagecopyresized($img, $tmp, $x, $dy, 0, 0, $dw, $dh, $cw + 2, $ch + 2);
    imagedestroy($tmp);
    $x += $dw + random_int(0, 5);
}

header('Content-Type: image/png');
header('Cache-Control: no-store, no-cache, must-revalidate');
imagepng($img);
imagedestroy($img);
