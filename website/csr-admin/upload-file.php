<?php
/**
 * رفع ملفات PDF للصفحات — تحقق صارم (نوع + توقيع %PDF) واسم عشوائي.
 */
require_once __DIR__ . '/inc/auth.php';

require_super();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false], 405);
csrf_require();

if (!rate_limit('admin_upload_file', 40, 3600)) {
    json_out(['ok' => false, 'msg' => 'تجاوزت حد الرفع، انتظر قليلاً'], 429);
}

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    json_out(['ok' => false, 'msg' => 'لم يصل أي ملف'], 400);
}
$f = $_FILES['file'];
if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'msg' => 'فشل الرفع'], 400);
if ($f['size'] > 25 * 1024 * 1024) json_out(['ok' => false, 'msg' => 'الحد الأقصى 25MB'], 400);

/* التحقق من نوع PDF عبر التوقيع */
$fh = fopen($f['tmp_name'], 'rb');
$head = $fh ? fread($fh, 5) : '';
if ($fh) fclose($fh);
$finfo = function_exists('finfo_open') ? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';

if (strpos($head, '%PDF-') !== 0 || ($finfo && $finfo !== 'application/pdf')) {
    sec_log('upload_file_reject', 'not pdf: ' . $f['name'], 'medium');
    json_out(['ok' => false, 'msg' => 'الملف ليس PDF صالحاً'], 400);
}

$name = 'pages/' . date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.pdf';
$path = SCF_UPLOADS . '/' . $name;
if (!is_dir(dirname($path))) @mkdir(dirname($path), 0755, true);
if (!move_uploaded_file($f['tmp_name'], $path)) {
    json_out(['ok' => false, 'msg' => 'تعذر حفظ الملف'], 500);
}
admin_audit('upload_pdf', $name);
json_out(['ok' => true, 'path' => $name, 'url' => upload_url($name), 'label' => $f['name']]);
