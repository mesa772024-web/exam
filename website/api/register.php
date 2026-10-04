<?php
/**
 * نقطة استقبال طلبات التسجيل — POST فقط.
 * الحقول والإلزام يُقرآن من إعدادات نموذج التسجيل (reg_fields).
 * حماية: CSRF + رمز تحقق + فخ + فخ زمني + تقييد معدل + تحقق مدخلات صارم.
 */
require_once dirname(__DIR__) . '/app/guard.php';
require_once dirname(__DIR__) . '/app/csrf.php';
require_once dirname(__DIR__) . '/app/helpers.php';
require_once dirname(__DIR__) . '/app/i18n.php';

guard_boot();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_out(['ok' => false, 'msg' => '405'], 405);
}

$L = lang() === 'en';

if (!scf_event_concluded() && setting('reg_open', '1') !== '1') {
    json_out(['ok' => false, 'msg' => $L ? 'Registration is currently closed.' : 'التسجيل مغلق حالياً.'], 403);
}

if (scf_event_concluded()) {
    json_out(['ok' => false, 'code' => 'registration_closed', 'msg' => lang() === 'en' ? 'The Forum has concluded. Registration is closed.' : 'اختُتم المنتدى. التسجيل مغلق.'], 403);
}

csrf_require();

if (!rate_limit('register', 6, 3600, 60)) {
    json_out(['ok' => false, 'msg' => $L ? 'Too many attempts. Try again later.' : 'محاولات كثيرة، أعد المحاولة لاحقاً.'], 429);
}

/* حقل الفخ */
if (trim((string)($_POST['website'] ?? '')) !== '') {
    sec_log('honeypot', 'register bot', 'medium');
    json_out(['ok' => true, 'code' => 'CSR26-OK', 'show_code' => false]);
}

/* الفخ الزمني */
$ft = (int)($_POST['_ft'] ?? 0);
if ($ft > 0 && (time() - $ft) < 3) {
    sec_log('timetrap', 'register too fast', 'medium');
    json_out(['ok' => false, 'msg' => $L ? 'Please review your data and resubmit.' : 'يرجى مراجعة البيانات وإعادة الإرسال.'], 400);
}

/* رمز التحقق (إن كان مفعّلاً) */
$captchaOn = setting('reg_captcha_on', '1') === '1';
$recaptchaSecret = setting('recaptcha_secret', '');
if (!$captchaOn) {
    // الكابتشا معطّلة — نكتفي بالفخ والفخ الزمني وتقييد المعدل
    unset($_SESSION['captcha_hash'], $_SESSION['captcha_time']);
} elseif ($recaptchaSecret !== '' && isset($_POST['g-recaptcha-response'])) {
    $ok = false;
    try {
        $ctx = stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['secret' => $recaptchaSecret, 'response' => (string)$_POST['g-recaptcha-response'], 'remoteip' => client_ip()]),
            'timeout' => 8,
        ]]);
        $r = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
        $j = $r ? json_decode($r, true) : null;
        $ok = !empty($j['success']);
    } catch (Throwable $e) {
    }
    if (!$ok) json_out(['ok' => false, 'msg' => $L ? 'Captcha verification failed.' : 'فشل التحقق، أعد المحاولة.'], 400);
} else {
    $cap = strtoupper(trim((string)($_POST['captcha'] ?? '')));
    $hash = $_SESSION['captcha_hash'] ?? '';
    $ctime = (int)($_SESSION['captcha_time'] ?? 0);
    unset($_SESSION['captcha_hash'], $_SESSION['captcha_time']);
    $valid = $cap !== '' && $hash !== '' && (time() - $ctime) < 600 && password_verify($cap, $hash);
    if (!$valid) json_out(['ok' => false, 'field' => 'captcha', 'msg' => $L ? 'Incorrect verification code.' : 'رمز التحقق غير صحيح.'], 400);
}

/* ---------- الحقول حسب الإعدادات ---------- */
$fields = registration_fields();
$enabled = [];
foreach ($fields as $f) {
    if (!empty($f['on'])) $enabled[$f['key']] = $f;
}
$titleChoice = (string)($_POST['title'] ?? '');
$knownTitles = ['dr', 'eng', 'mr', 'mrs', 'prof'];
$resolvedTitle = in_array($titleChoice, $knownTitles, true)
    ? $titleChoice
    : ($titleChoice === 'other' ? clean_text($_POST['title_other'] ?? '', 120) : '');

$sectorChoice = clean_text($_POST['sector'] ?? '', 120);
$sectorsOk = array_merge(
    json_decode(setting('reg_sectors_ar', '[]'), true) ?: [],
    json_decode(setting('reg_sectors_en', '[]'), true) ?: []
);
$resolvedSector = in_array($sectorChoice, $sectorsOk, true)
    ? (is_other_choice($sectorChoice) ? clean_text($_POST['sector_other'] ?? '', 120) : $sectorChoice)
    : '';

$vals = [
    'title'     => $resolvedTitle,
    'full_name' => clean_text($_POST['full_name'] ?? '', 160),
    'gender'    => in_array($_POST['gender'] ?? '', ['male', 'female'], true) ? $_POST['gender'] : '',
    'age'       => trim((string)($_POST['age'] ?? '')) === '' ? null : (int)$_POST['age'],
    'phone'     => normalize_phone((string)($_POST['phone'] ?? '')),
    'email'     => strtolower(clean_text($_POST['email'] ?? '', 160)),
    'org'       => clean_text($_POST['org'] ?? '', 200),
    'job'       => clean_text($_POST['job'] ?? '', 200),
    'sector'    => $resolvedSector,
    'city'      => clean_text($_POST['city'] ?? '', 80),
    'country'   => clean_text($_POST['country'] ?? '', 100),
];

$errors = [];
$customValues = registration_collect_custom_values($fields, $_POST, $errors, $L);
$msgReq = $L ? 'This field is required.' : 'هذا الحقل مطلوب.';
if ($titleChoice === 'other' && $resolvedTitle === '') {
    $errors['title_other'] = $L ? 'Enter your title.' : 'اكتب اللقب.';
}
if (is_other_choice($sectorChoice) && $resolvedSector === '') {
    $errors['sector_other'] = $L ? 'Enter your sector.' : 'اكتب قطاع العمل.';
}

foreach ($enabled as $key => $f) {
    $req = !empty($f['req']);
    $v = $vals[$key] ?? '';
    if ($key === 'full_name') {
        if ($req && $vals['full_name'] === '') $errors['full_name'] = $msgReq;
        elseif ($vals['full_name'] !== '' && (mb_strlen($vals['full_name']) < 3 || !preg_match('/^[\p{Arabic}\p{L}\s\.\'-]+$/u', $vals['full_name']))) {
            $errors['full_name'] = $L ? 'Enter your full name.' : 'أدخل الاسم الكامل.';
        }
    } elseif ($key === 'title') {
        if ($req && $vals['title'] === '') $errors['title'] = $L ? 'Select a title.' : 'اختر اللقب.';
    } elseif ($key === 'phone') {
        if ($req && $vals['phone'] === '') $errors['phone'] = $msgReq;
        elseif ($vals['phone'] !== '' && !preg_match('/^\d{10,15}$/', $vals['phone'])) $errors['phone'] = $L ? 'Enter a valid WhatsApp number.' : 'أدخل رقم واتساب صحيحاً.';
    } elseif ($key === 'email') {
        if ($vals['email'] !== '' && !filter_var($vals['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = $L ? 'Invalid email.' : 'بريد غير صحيح.';
        elseif ($req && $vals['email'] === '') $errors['email'] = $msgReq;
    } elseif ($key === 'gender') {
        if ($req && $vals['gender'] === '') $errors['gender'] = $L ? 'Select gender.' : 'اختر الجنس.';
    } elseif ($key === 'age') {
        if ($vals['age'] !== null && ($vals['age'] < 10 || $vals['age'] > 99)) $errors['age'] = $L ? 'Invalid age.' : 'العمر غير صحيح.';
        elseif ($req && $vals['age'] === null) $errors['age'] = $msgReq;
    } else {
        if ($req && trim((string)$v) === '') $errors[$key] = $msgReq;
    }
}

if ($errors) {
    json_out(['ok' => false, 'errors' => $errors, 'msg' => $L ? 'Please correct the highlighted fields.' : 'يرجى تصحيح الحقول المحددة.'], 422);
}

$showCode = setting('reg_show_code', '1') === '1';

/** تذكّر المسجّل في المتصفح (لعرض بياناته عند العودة) */
function set_reg_cookie(string $code): void
{
    setcookie('scf_reg', $code, [
        'expires'  => time() + 86400 * 180,
        'path'     => '/',
        'samesite' => 'Lax',
        'httponly' => false,
    ]);
}

/* التكرار — بحسب الحقول المفعلة وغير الفارغة فقط. */
$dup = null;
if ($vals['phone'] !== '' && $vals['email'] !== '') $dup = q_one('SELECT code FROM registrants WHERE phone = ? OR (email <> \'\' AND email = ?) LIMIT 1', [$vals['phone'], $vals['email']]);
elseif ($vals['phone'] !== '') $dup = q_one('SELECT code FROM registrants WHERE phone = ? LIMIT 1', [$vals['phone']]);
elseif ($vals['email'] !== '') $dup = q_one('SELECT code FROM registrants WHERE email = ? LIMIT 1', [$vals['email']]);
if ($dup !== null) {
    set_reg_cookie($dup['code']);
    json_out([
        'ok' => true, 'dup' => true, 'show_code' => $showCode,
        'code' => $showCode ? $dup['code'] : '',
        'msg' => $L ? 'You are already registered.' : 'أنت مسجّل مسبقاً.',
    ]);
}

$code = gen_reg_code();
q('INSERT INTO registrants (code, title, full_name, gender, age, phone, email, org, job, sector, city, country, extra, lang, ip)
   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
    $code, $vals['title'], $vals['full_name'], $vals['gender'], $vals['age'], $vals['phone'], $vals['email'],
    $vals['org'], $vals['job'], $vals['sector'], $vals['city'], $vals['country'],
    json_encode($customValues, JSON_UNESCAPED_UNICODE), lang(), client_ip(),
]);
sec_log('register_ok', 'code=' . $code, 'info');
set_reg_cookie($code);

json_out(['ok' => true, 'code' => $showCode ? $code : '', 'show_code' => $showCode]);
