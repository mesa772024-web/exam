<?php
/**
 * Speak Up — استقبال رسالة زائر. POST فقط.
 * حماية: CSRF + فخ + فخ زمني + تقييد معدل + تحقق مدخلات. الرسالة نص فقط (تُنظّف من الوسوم).
 */
require_once dirname(__DIR__) . '/app/guard.php';
require_once dirname(__DIR__) . '/app/csrf.php';
require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/speakup.php';

guard_boot();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'msg' => '405'], 405);
}

$en = lang() === 'en';
$t = function (string $ar, string $e) use ($en) { return $en ? $e : $ar; };

if (!scf_speakup_on()) json_out(['ok' => false, 'msg' => $t('خدمة «شاركنا رأيك» متوقفة حالياً.', 'Speak Up is currently turned off.')], 403);

csrf_require();

if (!rate_limit('speakup', 5, 3600, 0)) {
    json_out(['ok' => false, 'msg' => $t('أرسلت عدة رسائل خلال وقت قصير، حاول لاحقاً.', 'You have sent several messages recently. Please try again later.')], 429);
}

/* حقل الفخ: الروبوتات تملؤه */
if (trim((string)($_POST['website'] ?? '')) !== '') {
    sec_log('honeypot', 'speakup bot', 'medium');
    json_out(['ok' => true]);
}

/* الفخ الزمني */
$ft = (int)($_POST['_ft'] ?? 0);
if ($ft > 0 && (time() - $ft) < 3) {
    sec_log('timetrap', 'speakup too fast', 'medium');
    json_out(['ok' => false, 'msg' => $t('يرجى مراجعة الرسالة وإعادة الإرسال.', 'Please review your message and send it again.')], 400);
}

$name    = clean_text($_POST['name'] ?? '', 80);
$phone   = clean_text($_POST['phone'] ?? '', 24);
$email   = clean_text($_POST['email'] ?? '', 160);
$message = trim(strip_tags((string)($_POST['message'] ?? '')));
$message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $message);
$message = preg_replace("/\n{3,}/", "\n\n", str_replace("\r", '', (string)$message));

$errors = [];
if ($phone !== '' && !preg_match('/^\+?[0-9 ()\-]{6,20}$/', $phone)) {
    $errors['phone'] = $t('رقم الهاتف غير صحيح', 'Invalid phone number');
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = $t('البريد الإلكتروني غير صحيح', 'Invalid email address');
}
$len = mb_strlen($message);
if ($len < 5) {
    $errors['message'] = $t('اكتب رسالتك (5 أحرف على الأقل)', 'Please write your message (at least 5 characters)');
} elseif ($len > 2000) {
    $errors['message'] = $t('الرسالة طويلة جداً (2000 حرف كحد أقصى)', 'Your message is too long (2000 characters maximum)');
}
if ($errors) {
    json_out(['ok' => false, 'errors' => $errors, 'msg' => reset($errors)], 422);
}

try {
    speakup_ensure_table();
    q('INSERT INTO speakups (name, phone, email, message, lang, ip) VALUES (?,?,?,?,?,?)', [
        $name, $phone, mb_strtolower($email), $message, $en ? 'en' : 'ar', client_ip(),
    ]);
} catch (Throwable $e) {
    error_log('SCF speakup insert failed: ' . $e->getMessage());
    json_out(['ok' => false, 'msg' => $t('تعذّر الحفظ الآن، حاول لاحقاً.', 'We could not save your message right now. Please try again later.')], 500);
}

json_out(['ok' => true, 'msg' => $t('شكراً لك! وصلتنا رسالتك.', 'Thank you! Your message has been received.')]);
