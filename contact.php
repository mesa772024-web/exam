<?php
/**
 * مكتب بغداد الحياة العلمي — معالج نموذج «تواصل معنا»
 * Receives the contact form (contact.html) and emails it to the office.
 * If PHP / mail() is not available on the host, the page falls back to the
 * visitor's email app automatically (see assets/js/main.js).
 */
declare(strict_types=1);

const MAIL_TO   = 'info@baghdadalhayat.com';          // ← البريد الذي تصله الرسائل
const MAIL_FROM = 'no-reply@baghdadalhayat.com';      // ← بريد على نفس النطاق (مطلوب لدى أغلب الاستضافات)

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function reply(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'Method not allowed', 'error_ar' => 'طريقة الطلب غير مسموحة']);
}

// honeypot — bots fill the hidden "website" field
if (trim((string) ($_POST['website'] ?? '')) !== '') {
    reply(200, ['ok' => true]);
}

$clean = static function (string $key, int $max): string {
    $v = trim((string) ($_POST[$key] ?? ''));
    $v = str_replace(["\r", "\0"], '', $v);
    return mb_substr($v, 0, $max);
};
$oneLine = static fn(string $v): string => preg_replace('/\s+/u', ' ', $v) ?? '';

$name    = $oneLine($clean('name', 120));
$email   = $oneLine($clean('email', 160));
$phone   = $oneLine($clean('phone', 40));
$subject = $oneLine($clean('subject', 160));
$message = $clean('message', 5000);

if ($name === '' || $message === '') {
    reply(422, ['ok' => false, 'error' => 'Please enter your name and a message.', 'error_ar' => 'يرجى إدخال الاسم ونص الرسالة.']);
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    reply(422, ['ok' => false, 'error' => 'The email format is invalid — or leave it empty.', 'error_ar' => 'صيغة البريد الإلكتروني غير صحيحة، أو اتركه فارغاً.']);
}

$title = $subject !== '' ? $subject : 'رسالة من موقع مكتب بغداد الحياة العلمي';
$body  = "الاسم / Name: {$name}\n"
       . ($email !== '' ? "البريد / Email: {$email}\n" : '')
       . ($phone !== '' ? "الهاتف / Phone: {$phone}\n" : '')
       . "الموضوع / Subject: {$title}\n"
       . "التاريخ / Date: " . date('Y-m-d H:i') . "\n"
       . str_repeat('-', 40) . "\n\n"
       . $message . "\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: =?UTF-8?B?' . base64_encode('موقع بغداد الحياة') . '?= <' . MAIL_FROM . '>',
];
if ($email !== '') {
    $headers[] = 'Reply-To: ' . $email;
}

$sent = function_exists('mail')
    && @mail(MAIL_TO, '=?UTF-8?B?' . base64_encode($title) . '?=', $body, implode("\r\n", $headers));

if (!$sent) {
    // let the browser fall back to the visitor's email app
    reply(503, ['ok' => false]);
}
reply(200, ['ok' => true]);
