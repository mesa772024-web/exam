<?php
/**
 * تعديل المسجّل لبياناته بنفسه — يتحقق من كوكي الرمز (scf_reg) لمطابقة الهوية.
 * POST فقط + CSRF + تقييد معدل. لا يمكن تعديل الحالة أو الرمز.
 */
require_once dirname(__DIR__) . '/app/guard.php';
require_once dirname(__DIR__) . '/app/csrf.php';
require_once dirname(__DIR__) . '/app/helpers.php';

guard_boot();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false], 405);
if (scf_event_concluded()) {
    json_out(['ok' => false, 'code' => 'registration_closed', 'msg' => lang() === 'en' ? 'The Forum has concluded. Registration is closed.' : 'اختُتم المنتدى. التسجيل مغلق.'], 403);
}

csrf_require();

$L = lang() === 'en';
if (!rate_limit('reg_self_edit', 8, 3600)) {
    json_out(['ok' => false, 'msg' => $L ? 'Too many attempts.' : 'محاولات كثيرة، حاول لاحقاً.'], 429);
}

/* الهوية من الكوكي */
$myCode = preg_replace('/[^A-Z0-9\-]/', '', strtoupper((string)($_COOKIE['scf_reg'] ?? '')));
if ($myCode === '') json_out(['ok' => false, 'msg' => $L ? 'Session expired.' : 'انتهت الجلسة، سجّل من جديد.'], 403);
$r = q_one('SELECT * FROM registrants WHERE code = ?', [$myCode]);
if ($r === null) json_out(['ok' => false, 'msg' => 'غير موجود'], 404);

$fields = registration_fields();
$enabled = [];
foreach ($fields as $f) if (!empty($f['on'])) $enabled[$f['key']] = $f;

$name  = !empty($enabled['full_name']) ? clean_text($_POST['full_name'] ?? '', 160) : (string)$r['full_name'];
$titleChoice = (string)($_POST['title'] ?? '');
$titleEnabled = !empty($enabled['title']);
$title = !$titleEnabled ? (string)$r['title'] : (in_array($titleChoice, ['dr','eng','mr','mrs','prof'], true)
    ? $titleChoice
    : ($titleChoice === 'other' ? clean_text($_POST['title_other'] ?? '', 120) : ''));
$phone = !empty($enabled['phone']) ? normalize_phone((string)($_POST['phone'] ?? '')) : (string)$r['phone'];
$email = !empty($enabled['email']) ? strtolower(clean_text($_POST['email'] ?? '', 160)) : (string)$r['email'];
$gender= !empty($enabled['gender']) ? (in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : '') : (string)$r['gender'];
$age   = !empty($enabled['age']) ? (trim((string)($_POST['age'] ?? '')) === '' ? null : (int)$_POST['age']) : $r['age'];
$country = !empty($enabled['country']) ? clean_text($_POST['country'] ?? '', 100) : (string)$r['country'];
$city = !empty($enabled['city']) ? clean_text($_POST['city'] ?? '', 80) : (string)$r['city'];
$org = !empty($enabled['org']) ? clean_text($_POST['org'] ?? '', 200) : (string)$r['org'];
$job = !empty($enabled['job']) ? clean_text($_POST['job'] ?? '', 200) : (string)$r['job'];

$errors = [];
$oldExtra = registration_extra_decode($r['extra'] ?? '');
foreach ($fields as $field) {
    if (registration_field_is_custom($field) && !empty($field['on'])) unset($oldExtra[$field['key']]);
}
$newExtra = registration_collect_custom_values($fields, $_POST, $errors, $L);
$mergedExtra = array_merge($oldExtra, $newExtra);
if ($titleEnabled && $titleChoice === 'other' && $title === '') $errors['title_other'] = $L ? 'Enter your title.' : 'اكتب اللقب.';
if (!empty($enabled['full_name']) && $name !== '' && (mb_strlen($name) < 3 || !preg_match('/^[\p{Arabic}\p{L}\s\.\'-]+$/u', $name))) $errors['full_name'] = $L ? 'Enter your name.' : 'أدخل الاسم الكامل.';
if (!empty($enabled['phone']) && $phone !== '' && !preg_match('/^\d{10,15}$/', $phone)) $errors['phone'] = $L ? 'Invalid number.' : 'رقم غير صحيح.';
if (!empty($enabled['email']) && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = $L ? 'Invalid email.' : 'بريد غير صحيح.';
if (!empty($enabled['age']) && $age !== null && ($age < 10 || $age > 99)) $errors['age'] = $L ? 'Invalid age.' : 'العمر غير صحيح.';
foreach (['title' => $title, 'full_name' => $name, 'gender' => $gender, 'age' => $age, 'country' => $country, 'city' => $city, 'phone' => $phone, 'email' => $email, 'org' => $org, 'job' => $job] as $key => $value) {
    if (!empty($enabled[$key]['req']) && trim((string)$value) === '') $errors[$key] = $L ? 'This field is required.' : 'هذا الحقل مطلوب.';
}

$sectorsOk = array_merge(json_decode(setting('reg_sectors_ar', '[]'), true) ?: [], json_decode(setting('reg_sectors_en', '[]'), true) ?: []);
$sectorChoice = !empty($enabled['sector']) ? clean_text($_POST['sector'] ?? '', 120) : '';
$sector = empty($enabled['sector']) ? (string)$r['sector'] : (in_array($sectorChoice, $sectorsOk, true)
    ? (is_other_choice($sectorChoice) ? clean_text($_POST['sector_other'] ?? '', 120) : $sectorChoice)
    : '');
if (!empty($enabled['sector']) && is_other_choice($sectorChoice) && $sector === '') $errors['sector_other'] = $L ? 'Enter your sector.' : 'اكتب قطاع العمل.';
if (!empty($enabled['sector']['req']) && $sector === '') $errors['sector'] = $L ? 'This field is required.' : 'هذا الحقل مطلوب.';
if ($errors) json_out(['ok' => false, 'errors' => $errors, 'msg' => $L ? 'Please correct the fields.' : 'صحّح الحقول المحددة.'], 422);

/* تفادي التكرار عند توفر هاتف أو بريد. */
$dup = null;
if ($phone !== '' && $email !== '') $dup = q_one('SELECT id FROM registrants WHERE id<>? AND (phone=? OR (email<>\'\' AND email=?)) LIMIT 1', [(int)$r['id'], $phone, $email]);
elseif ($phone !== '') $dup = q_one('SELECT id FROM registrants WHERE id<>? AND phone=? LIMIT 1', [(int)$r['id'], $phone]);
elseif ($email !== '') $dup = q_one('SELECT id FROM registrants WHERE id<>? AND email=? LIMIT 1', [(int)$r['id'], $email]);
if ($dup !== null) json_out(['ok' => false, 'msg' => $L ? 'Phone or email already used.' : 'الهاتف أو البريد مستخدم لحساب آخر.'], 409);

q('UPDATE registrants SET title=?, full_name=?, gender=?, age=?, phone=?, email=?, org=?, job=?, sector=?, city=?, country=?, extra=? WHERE id=?', [
    $title, $name, $gender, $age, $phone, $email,
    $org, $job, $sector, $city, $country,
    json_encode($mergedExtra, JSON_UNESCAPED_UNICODE), (int)$r['id'],
]);
sec_log('reg_self_edit', 'code=' . $myCode, 'info');
json_out(['ok' => true, 'msg' => $L ? 'Your data was updated.' : 'تم تحديث بياناتك بنجاح.']);
