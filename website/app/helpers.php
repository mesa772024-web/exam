<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/event-status.php';

/* ---------- Output escaping ---------- */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** تعقيم نص مدخل: إزالة الوسوم وضبط الطول */
function clean_text($v, int $max = 500): string
{
    $v = trim(strip_tags((string)$v));
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $v);
    return mb_substr($v, 0, $max);
}

/** هل تم اختيار خيار "أخرى / Other" من قائمة ثنائية اللغة؟ */
function is_other_choice(string $value): bool
{
    $value = mb_strtolower(trim($value), 'UTF-8');
    return in_array($value, ['other', 'أخرى', 'اخرى', 'أُخرى'], true);
}

/** الاسم المقروء للقب؛ القيم المخصصة تُعرض كما حُفظت. */
function registrant_title_label(string $value, string $language = 'ar'): string
{
    $ar = ['dr' => 'الدكتور', 'eng' => 'المهندس', 'mr' => 'السيد', 'mrs' => 'السيدة', 'prof' => 'الأستاذ'];
    $en = ['dr' => 'Dr.', 'eng' => 'Engineer', 'mr' => 'Mr.', 'mrs' => 'Mrs.', 'prof' => 'Professor'];
    $map = $language === 'en' ? $en : $ar;
    return $map[$value] ?? $value;
}

/* ---------- Dynamic registration fields ---------- */
function registration_fields(): array
{
    $rows = json_decode(setting('reg_fields', '[]'), true);
    return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
}

function registration_field_is_custom(array $field): bool
{
    return !empty($field['custom']) && preg_match('/^custom_[a-f0-9]{12}$/', (string)($field['key'] ?? '')) === 1;
}

function registration_custom_fields(bool $enabledOnly = false): array
{
    return array_values(array_filter(registration_fields(), function ($field) use ($enabledOnly) {
        return registration_field_is_custom($field) && (!$enabledOnly || !empty($field['on']));
    }));
}

function registration_extra_decode($value): array
{
    if (is_array($value)) return $value;
    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function registration_field_options(array $field): array
{
    $out = [];
    foreach (($field['options'] ?? []) as $option) {
        if (!is_array($option)) continue;
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($option['id'] ?? ''));
        if ($id === '') continue;
        $out[] = [
            'id' => substr($id, 0, 32),
            'ar' => clean_text($option['ar'] ?? '', 160),
            'en' => clean_text($option['en'] ?? '', 160),
        ];
    }
    return $out;
}

function registration_option_label(array $field, string $id, string $language = 'ar'): string
{
    foreach (registration_field_options($field) as $option) {
        if ($option['id'] !== $id) continue;
        $label = trim((string)($option[$language] ?? ''));
        if ($label === '') $label = trim((string)($option[$language === 'ar' ? 'en' : 'ar'] ?? ''));
        return $label !== '' ? $label : $id;
    }
    return '';
}

function registration_custom_raw_value(array $extra, string $key): string
{
    $stored = $extra[$key] ?? '';
    if (is_array($stored)) return clean_text($stored['value'] ?? '', 2000);
    return clean_text($stored, 2000);
}

function registration_custom_value_label(array $field, $stored, string $language = 'ar'): string
{
    $value = is_array($stored) ? clean_text($stored['value'] ?? '', 2000) : clean_text($stored, 2000);
    $savedLabel = is_array($stored) ? clean_text($stored['label'] ?? '', 2000) : '';
    $type = (string)($field['type'] ?? 'text');
    if ($type === 'yesno') {
        if ($value === 'yes') return $language === 'en' ? 'Yes' : 'نعم';
        if ($value === 'no') return $language === 'en' ? 'No' : 'لا';
        return '';
    }
    if ($type === 'select') {
        if ($value === '__other__') return $savedLabel;
        return registration_option_label($field, $value, $language) ?: $savedLabel ?: $value;
    }
    return $value;
}

/** @return array<string,mixed> */
function registration_collect_custom_values(array $fields, array $source, array &$errors, bool $english = false): array
{
    $out = [];
    $requiredMessage = $english ? 'This field is required.' : 'هذا الحقل مطلوب.';
    foreach ($fields as $field) {
        if (!registration_field_is_custom($field) || empty($field['on'])) continue;
        $key = (string)$field['key'];
        $type = in_array($field['type'] ?? '', ['text', 'textarea', 'yesno', 'select'], true) ? $field['type'] : 'text';
        $raw = clean_text($source[$key] ?? '', $type === 'textarea' ? 2000 : 500);
        $stored = $raw;
        if ($type === 'yesno') {
            $stored = in_array($raw, ['yes', 'no'], true) ? $raw : '';
        } elseif ($type === 'select') {
            $valid = [];
            foreach (registration_field_options($field) as $option) $valid[] = $option['id'];
            if ($raw === '__other__' && !empty($field['allow_other'])) {
                $other = clean_text($source[$key . '_other'] ?? '', 500);
                $stored = $other === '' ? '' : ['value' => '__other__', 'label' => $other];
                if ($other === '') $errors[$key . '_other'] = $requiredMessage;
            } elseif (in_array($raw, $valid, true)) {
                $stored = ['value' => $raw, 'label' => registration_option_label($field, $raw, $english ? 'en' : 'ar')];
            } else {
                $stored = '';
            }
        }
        $empty = is_array($stored) ? trim((string)($stored['value'] ?? '')) === '' : trim((string)$stored) === '';
        if (!empty($field['req']) && $empty) $errors[$key] = $requiredMessage;
        if (!$empty) $out[$key] = $stored;
    }
    return $out;
}

/* ---------- Settings ---------- */
/** إزالة كلمة "آمنة / Safe" من الشعار النصي القديم، بما في ذلك قواعد البيانات المثبتة سابقاً. */
function sanitize_tagline_value(string $value, string $language): string
{
    $value = trim($value);
    if ($language === 'ar') {
        $value = preg_replace('/\s*(?:و\s*)?آمنة\b/u', '', $value);
        $value = preg_replace('/\s*(?:و\s*)?امنة\b/u', '', $value);
        $value = preg_replace('/مستدامة\s*(?:[.،,\-–—]+|و)?\s*ذكية/u', 'مستدامة و ذكية', $value);
    } else {
        $value = preg_replace('/\s*,?\s*(?:and\s+)?safe\b/i', '', $value);
        $value = preg_replace('/Sustainable\s*(?:[,.\-–—]+|and)?\s*Smart/iu', 'Sustainable and Smart', $value);
    }
    $value = preg_replace('/[ \t]{2,}/u', ' ', (string)$value);
    $value = preg_replace('/\s+([،,])/u', '$1', (string)$value);
    return trim((string)$value);
}

function settings_all(): array
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (q_all('SELECT name, value FROM settings') as $r) {
                $cache[$r['name']] = $r['value'];
            }
            /* ترقية صامتة لمواقع الإصدارات السابقة: تحديث القيمة القديمة مرة واحدة فقط. */
            foreach (['tagline_ar' => 'ar', 'tagline_en' => 'en'] as $key => $language) {
                if (!array_key_exists($key, $cache)) continue;
                $clean = sanitize_tagline_value((string)$cache[$key], $language);
                if ($clean !== $cache[$key]) {
                    q('UPDATE settings SET value = ? WHERE name = ?', [$clean, $key]);
                    $cache[$key] = $clean;
                }
            }
        } catch (Throwable $e) {
        }
    }
    return $cache;
}

function setting(string $name, string $default = ''): string
{
    if ($name === 'reg_open' && scf_event_concluded()) return '0';
    $all = settings_all();
    return array_key_exists($name, $all) ? $all[$name] : $default;
}

function setting_set(string $name, string $value): void
{
    if ($name === 'tagline_ar') $value = sanitize_tagline_value($value, 'ar');
    if ($name === 'tagline_en') $value = sanitize_tagline_value($value, 'en');
    q('INSERT INTO settings (name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$name, $value]);
}

/* ---------- Language ---------- */
function lang(): string
{
    static $lang = null;
    if ($lang === null) {
        $l = $_GET['lang'] ?? ($_COOKIE['scf_lang'] ?? 'ar');
        $lang = ($l === 'en') ? 'en' : 'ar';
        if (isset($_GET['lang'])) {
            setcookie('scf_lang', $lang, [
                'expires'  => time() + 86400 * 180,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
        }
    }
    return $lang;
}

function is_rtl(): bool { return lang() === 'ar'; }

/** اختيار الحقل الثنائي اللغة من صف قاعدة بيانات */
function bl(array $row, string $field): string
{
    $k = $field . '_' . lang();
    $fallback = $field . '_' . (lang() === 'ar' ? 'en' : 'ar');
    $v = trim((string)($row[$k] ?? ''));
    return $v !== '' ? $v : trim((string)($row[$fallback] ?? ''));
}

/** مثل bl() لكنه قابل للتحرير من الواجهة (وضع التحرير) */
function ble(string $table, array $row, string $field): string
{
    $v = bl($row, $field);
    return function_exists('scf_tk_wrap') && isset($row['id']) ? scf_tk_wrap('db:' . $table . ':' . (int)$row['id'] . ':' . $field, $v) : $v;
}

/** إعداد ثنائي اللغة: setting('x') يخزن JSON {"ar":..,"en":..} أو مفتاحين */
function setting_l(string $base, string $default = ''): string
{
    if ($base === 'event_dates' && setting('event_dates_' . lang(), '') === '') {
        $generated = conference_event_dates(lang());
        if ($generated !== '') return $generated;
    }
    $v = setting($base . '_' . lang(), '');
    if ($v === '') $v = setting($base . '_' . (lang() === 'ar' ? 'en' : 'ar'), '');
    $v = $v !== '' ? $v : $default;
    if ($v !== '' && function_exists('scf_tk_wrap') && !scf_tk_setting_blocked($base)) $v = scf_tk_wrap('s:' . $base, $v);
    return $v;
}

/** تاريخ المؤتمر المعروض يُبنى من يومَي الأجندة اللذين يحددهما الأدمن. */
function conference_event_dates(string $language = 'ar'): string
{
    try {
        $rows = q_all("SELECT day_date FROM agenda_days WHERE day_date IS NOT NULL ORDER BY sort, id LIMIT 2");
    } catch (Throwable $e) {
        return '';
    }
    $dates = [];
    foreach ($rows as $row) {
        $ts = strtotime((string)$row['day_date']);
        if ($ts) $dates[] = $ts;
    }
    if (!$dates) return '';
    if ($language === 'en') {
        if (count($dates) === 1) return date('F j, Y', $dates[0]);
        if (date('Y-m', $dates[0]) === date('Y-m', $dates[1])) return date('F j', $dates[0]) . '–' . date('j, Y', $dates[1]);
        if (date('Y', $dates[0]) === date('Y', $dates[1])) return date('F j', $dates[0]) . ' – ' . date('F j, Y', $dates[1]);
        return date('F j, Y', $dates[0]) . ' – ' . date('F j, Y', $dates[1]);
    }
    $months = [1 => 'كانون الثاني', 'شباط', 'آذار', 'نيسان', 'أيار', 'حزيران', 'تموز', 'آب', 'أيلول', 'تشرين الأول', 'تشرين الثاني', 'كانون الأول'];
    $one = function (int $ts, bool $year = true) use ($months): string {
        return (int)date('j', $ts) . ' ' . $months[(int)date('n', $ts)] . ($year ? ' ' . date('Y', $ts) : '');
    };
    if (count($dates) === 1) return $one($dates[0]);
    if (date('Y-m', $dates[0]) === date('Y-m', $dates[1])) return (int)date('j', $dates[0]) . ' – ' . $one($dates[1]);
    if (date('Y', $dates[0]) === date('Y', $dates[1])) return $one($dates[0], false) . ' – ' . $one($dates[1]);
    return $one($dates[0]) . ' – ' . $one($dates[1]);
}

function conference_sync_countdown_target(): void
{
    try {
        $date = (string)(q_val("SELECT day_date FROM agenda_days WHERE day_date IS NOT NULL ORDER BY sort, id LIMIT 1") ?: '');
        if ($date === '') return;
        $old = setting('countdown_target', '');
        $time = preg_match('/\b(\d{2}:\d{2}(?::\d{2})?)\b/', $old, $m) ? $m[1] : '09:00:00';
        if (strlen($time) === 5) $time .= ':00';
        setting_set('countdown_target', $date . ' ' . $time);
    } catch (Throwable $e) {
    }
}

/* ---------- URLs ---------- */
function base_url(): string
{
    static $u = null;
    if ($u === null) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $dir  = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        // الصفحات داخل مجلدات فرعية (admin/api) تعود لجذر المشروع
        foreach (['/api', '/' . ADMIN_DIR, '/install'] as $sub) {
            $pos = strrpos($dir, $sub);
            if ($pos !== false && $pos === strlen($dir) - strlen($sub)) {
                $dir = substr($dir, 0, $pos);
            }
        }
        $u = ($https ? 'https://' : 'http://') . $host . $dir;
    }
    return $u;
}

function asset(string $path): string
{
    return base_url() . '/assets/' . ltrim($path, '/');
}

function upload_url(string $path): string
{
    return base_url() . '/uploads/' . ltrim($path, '/');
}

/* ---------- Registrant codes ---------- */
function gen_reg_code(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $s = '';
        for ($i = 0; $i < 6; $i++) {
            $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $code = 'CSR26-' . $s;
        $dup = q_one('SELECT id FROM registrants WHERE code = ?', [$code]);
    } while ($dup !== null);
    return $code;
}

/** توحيد رقم الهاتف: أرقام فقط بصيغة دولية عراقية عند الإمكان */
function normalize_phone(string $p): string
{
    $p = preg_replace('/[^0-9+]/', '', $p);
    $p = ltrim($p, '+');
    $p = preg_replace('/^00/', '', $p);
    if (preg_match('/^07\d{9}$/', $p)) {
        $p = '964' . substr($p, 1);
    }
    return substr($p, 0, 15);
}

/* ---------- Content queries ---------- */
function get_speakers(bool $activeOnly = true): array
{
    $w = $activeOnly ? 'WHERE active = 1' : '';
    return q_all("SELECT * FROM speakers $w ORDER BY sort, id");
}

function get_orgs(string $kind, bool $activeOnly = true): array
{
    $w = $activeOnly ? 'AND active = 1' : '';
    return q_all("SELECT * FROM orgs WHERE kind = ? $w ORDER BY sort, id", [$kind]);
}

function get_agenda(): array
{
    $days = q_all('SELECT * FROM agenda_days ORDER BY sort, id');
    foreach ($days as &$d) {
        $d['items'] = q_all('SELECT * FROM agenda_items WHERE day_id = ? ORDER BY sort, id', [$d['id']]);
    }
    return $days;
}

function get_pages_nav(): array
{
    try {
        return q_all('SELECT slug, title_ar, title_en FROM pages WHERE published = 1 AND in_nav = 1 ORDER BY sort, id');
    } catch (Throwable $e) {
        return [];
    }
}

/* ---------- JSON responses ---------- */
function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode($data, JSON_UNESCAPED_UNICODE));
}

/** تشغيل ترقية آمنة مرة واحدة عند رفع الحزمة فوق موقع مثبت مسبقاً. */
function scf_auto_upgrade(): void
{
    static $ran = false;
    if ($ran || !is_file(SCF_STORAGE . '/installed.lock')) return;
    $ran = true;
    try {
        $version = (string)(q_val("SELECT value FROM settings WHERE name='app_release'") ?: '');
        if ($version === '2026.08.09-r6') return;
        if ((int)q_val("SELECT GET_LOCK('scforum_release_upgrade', 8)") !== 1) return;
        try {
            /* قد يكون طلب آخر أكمل الترقية أثناء انتظار القفل. */
            $version = (string)(q_val("SELECT value FROM settings WHERE name='app_release'") ?: '');
            if ($version === '2026.08.09-r6') return;
            require_once __DIR__ . '/migrate.php';
            scf_migrate();
        } finally {
            q_val("SELECT RELEASE_LOCK('scforum_release_upgrade')");
        }
    } catch (Throwable $e) {
        error_log('SCF automatic upgrade failed: ' . $e->getMessage());
    }
}

scf_auto_upgrade();

require_once __DIR__ . '/texts.php';
