<?php
/**
 * طبقة النصوص القابلة للتعديل — كل نص ظاهر في الواجهة يمر من هنا.
 *
 *  tt('نص عربي', 'English text')  → يعيد النص بلغة الزائر، أو التعديل المحفوظ إن وُجد.
 *  في «وضع التحرير» (مدير كامل + ?edit=1) تُحاط النصوص بعلامات غير مرئية، ثم يحوّلها
 *  scf_edit_postprocess() إلى <span data-tk> قابلة للتحرير (وتُزال من داخل الوسوم والسمات).
 *
 * مفاتيح النصوص:
 *   t:xxxxxxxxxxxx    نص ثابت من القوالب (يحفظ في جدول site_texts)
 *   i18n:KEY          نص من قاموس الواجهة tr()
 *   s:BASE            إعداد ثنائي اللغة BASE_ar / BASE_en
 *   db:TABLE:ID:FIELD حقل من قاعدة البيانات (الأجندة، الشركاء، المتحدثون…)
 *   post:ID:FIELD     عنوان/تصنيف منشور
 */
require_once __DIR__ . '/helpers.php';

const SCF_TK_OPEN = "\u{E000}";
const SCF_TK_MID = "\u{E001}";
const SCF_TK_CLOSE = "\u{E002}";

/** جداول وحقول قاعدة البيانات المسموح تحريرها من الواجهة (الحقل ثنائي اللغة: FIELD_ar / FIELD_en) */
function scf_tk_db_fields(): array
{
    return [
        'agenda_days'     => ['title', 'sub'],
        'agenda_items'    => ['title', 'desc'],
        'orgs'            => ['name', 'desc'],
        'speakers'        => ['name', 'title', 'bio'],
        'gallery'         => ['cap'],
        'custom_sections' => ['title', 'body'],
    ];
}

/** إعدادات لا تُحرَّر من الواجهة أبداً */
function scf_tk_setting_blocked(string $base): bool
{
    return (bool)preg_match('/(secret|token|key|pass|whitelist|recaptcha|wa_|api|smtp|captcha|reg_fields|order|template|json|badge|event_dates)/i', $base);
}

function scf_texts_ensure_table(): void
{
    static $done = false;
    if ($done) return;
    db()->exec("CREATE TABLE IF NOT EXISTS site_texts (
        tkey VARCHAR(120) NOT NULL PRIMARY KEY,
        def_ar TEXT NULL,
        def_en TEXT NULL,
        val_ar TEXT NULL,
        val_en TEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

/** كل التعديلات المحفوظة (استعلام واحد لكل طلب) */
function scf_text_overrides(): array
{
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    try {
        foreach (q_all('SELECT tkey, val_ar, val_en FROM site_texts WHERE val_ar IS NOT NULL OR val_en IS NOT NULL') as $r) {
            $map[$r['tkey']] = ['ar' => $r['val_ar'], 'en' => $r['val_en']];
        }
    } catch (Throwable $e) {
        // الجدول غير موجود بعد — نستخدم النصوص الافتراضية
    }
    return $map;
}

/** وضع التحرير: مدير كامل، صفحة عامة (ليست لوحة التحكم أو API) */
function scf_edit_mode(): bool
{
    static $on = null;
    if ($on !== null) return $on;
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (PHP_SAPI === 'cli' || strpos($script, '/' . ADMIN_DIR . '/') !== false || strpos($script, '/api/') !== false) return $on = false;
    if (session_status() !== PHP_SESSION_ACTIVE) return $on = false;
    $isSuper = !empty($_SESSION['admin_id']) && ($_SESSION['admin_role'] ?? 'super') === 'super';
    if (!$isSuper) return $on = false;
    if (isset($_GET['edit'])) $_SESSION['scf_edit'] = $_GET['edit'] !== '0' ? 1 : 0;
    return $on = !empty($_SESSION['scf_edit']);
}

/** يحيط النص بعلامات التحرير (في وضع التحرير فقط) */
function scf_tk_wrap(string $key, string $text, bool $rich = false): string
{
    if (!scf_edit_mode()) return $text;
    return SCF_TK_OPEN . $key . ($rich ? '*' : '') . SCF_TK_MID . $text . SCF_TK_CLOSE;
}

function scf_tk_key(string $ar, string $en): string
{
    return 't:' . substr(md5($ar . "\x1f" . $en), 0, 12);
}

/** سجل النصوص التي ظهرت في هذه الصفحة (لتظهر لاحقاً في صفحة «النصوص») */
function scf_tk_registry(?string $key = null, string $ar = '', string $en = ''): array
{
    static $reg = [];
    if ($key !== null) $reg[$key] = [$ar, $en];
    return $reg;
}

/** النص الثابت ثنائي اللغة */
function tt(string $ar, string $en, string $key = ''): string
{
    $key = $key !== '' ? $key : scf_tk_key($ar, $en);
    $lang = lang();
    $text = $lang === 'ar' ? $ar : $en;
    $ov = scf_text_overrides()[$key][$lang] ?? null;
    if ($ov !== null && $ov !== '') $text = $ov;
    if (scf_edit_mode()) {
        scf_tk_registry($key, $ar, $en);
        return scf_tk_wrap($key, $text, strpos($ar . $en, '<') !== false);
    }
    return $text;
}

/** تنظيف النص المحفوظ: نص عادي، أو وسوم تنسيق بسيطة للنصوص الغنية */
function scf_tk_clean(string $value, bool $rich): string
{
    $value = str_replace(["\r", SCF_TK_OPEN, SCF_TK_MID, SCF_TK_CLOSE, "\u{200B}"], '', $value);
    if ($rich) {
        $value = preg_replace('#<\s*(br|div|p)\b[^>]*>#i', '<br>', $value);
        $value = preg_replace('#</\s*(div|p)\s*>#i', '', $value);
        $value = strip_tags($value, '<br><em><strong><b>');
        $value = preg_replace('#<(em|strong|b)\b[^>]*>#i', '<$1>', $value);
        $value = preg_replace('#(<br>\s*){3,}#i', '<br><br>', $value);
        $value = preg_replace('#^(<br>\s*)+|(<br>\s*)+$#i', '', trim($value));
    } else {
        $value = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);
        $value = trim(preg_replace("/[ \t]*\n[ \t]*/", "\n", $value));
    }
    return mb_substr($value, 0, 20000);
}

/**
 * يحفظ تعديلاً واحداً حسب نوع المفتاح. يعيد النص المحفوظ.
 * @throws RuntimeException
 */
function scf_tk_save(string $key, string $value, string $lang): string
{
    $lang = $lang === 'en' ? 'en' : 'ar';
    $rich = substr($key, -1) === '*';
    if ($rich) $key = substr($key, 0, -1);

    if (preg_match('/^(t:[a-f0-9]{12}|i18n:[a-z0-9_]{1,60})$/', $key)) {
        scf_texts_ensure_table();
        $clean = scf_tk_clean($value, $rich);
        q("INSERT INTO site_texts (tkey, val_$lang) VALUES (?, ?) ON DUPLICATE KEY UPDATE val_$lang = VALUES(val_$lang)", [$key, $clean]);
        return $clean;
    }
    if (preg_match('/^s:([a-z0-9_]{1,60})$/', $key, $m)) {
        if (scf_tk_setting_blocked($m[1])) throw new RuntimeException('هذا الإعداد لا يُعدّل من الواجهة');
        $clean = scf_tk_clean($value, false);
        setting_set($m[1] . '_' . $lang, $clean);
        return $clean;
    }
    if (preg_match('/^db:([a-z_]+):(\d+):([a-z_]+)$/', $key, $m)) {
        $fields = scf_tk_db_fields();
        if (!isset($fields[$m[1]]) || !in_array($m[3], $fields[$m[1]], true)) throw new RuntimeException('حقل غير مسموح');
        $clean = scf_tk_clean($value, false);
        q('UPDATE `' . $m[1] . '` SET `' . $m[3] . '_' . $lang . '` = ? WHERE id = ?', [$clean, (int)$m[2]]);
        return $clean;
    }
    if (preg_match('/^post:(\d+):(title|label)$/', $key, $m)) {
        require_once __DIR__ . '/posts.php';
        $clean = scf_tk_clean($value, false);
        scf_posts_ensure();
        q('UPDATE posts SET `' . $m[2] . '_' . $lang . '` = ? WHERE id = ?', [$clean, (int)$m[1]]);
        return $clean;
    }
    throw new RuntimeException('مفتاح غير معروف');
}

/** يعيد نصاً ثابتاً إلى الافتراضي */
function scf_tk_reset(string $key, string $lang = ''): void
{
    scf_texts_ensure_table();
    if ($lang === 'ar' || $lang === 'en') q("UPDATE site_texts SET val_$lang = NULL WHERE tkey = ?", [$key]);
    else q('UPDATE site_texts SET val_ar = NULL, val_en = NULL WHERE tkey = ?', [$key]);
}

/** مخرجات الصفحة في وضع التحرير: العلامات ← عناصر قابلة للتحرير */
function scf_edit_postprocess(string $html): string
{
    // 1) إزالة العلامات من داخل الوسوم/السمات ومن العناصر النصية الخام
    $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) return $html;
    $raw = '';
    $strip = function (string $s): string {
        return (string)preg_replace('/' . SCF_TK_OPEN . '[^' . SCF_TK_MID . ']*' . SCF_TK_MID . '|' . SCF_TK_CLOSE . '/u', '', $s);
    };
    foreach ($parts as $i => $part) {
        if ($part === '') continue;
        if ($part[0] === '<') {
            $parts[$i] = $strip($part);
            if (preg_match('#^<\s*(title|script|style|textarea|option)\b#i', $part, $m)) $raw = strtolower($m[1]);
            elseif ($raw !== '' && preg_match('#^<\s*/\s*' . $raw . '\b#i', $part)) $raw = '';
        } elseif ($raw !== '') {
            $parts[$i] = $strip($part);
        }
    }
    $html = implode('', $parts);
    // 2) الأزواج الكاملة ← span قابلة للتحرير
    $html = (string)preg_replace_callback(
        '/' . SCF_TK_OPEN . '([A-Za-z0-9:_.\-]+)(\*?)' . SCF_TK_MID . '(.*?)' . SCF_TK_CLOSE . '/su',
        function ($m) {
            return '<span class="tk" data-tk="' . htmlspecialchars($m[1] . $m[2], ENT_QUOTES, 'UTF-8') . '"' . ($m[2] ? ' data-rich="1"' : '') . '>' . $m[3] . '</span>';
        },
        $html
    );
    // 3) أي بقايا
    $html = $strip($html);
    // 4) تسجيل النصوص الجديدة لصفحة «النصوص»
    $reg = scf_tk_registry();
    if ($reg) {
        try {
            scf_texts_ensure_table();
            $st = db()->prepare('INSERT IGNORE INTO site_texts (tkey, def_ar, def_en) VALUES (?,?,?)');
            foreach ($reg as $k => $d) $st->execute([$k, $d[0], $d[1]]);
        } catch (Throwable $e) {}
    }
    return $html;
}

/**
 * فهرس كل النصوص الثابتة في القوالب (بتحليل الشيفرة) — لصفحة «النصوص» في لوحة التحكم.
 * @return array<string,array{ar:string,en:string,file:string}>
 */
function scf_text_catalog(): array
{
    $files = ['index.php', 'edition1.php', 'edition2.php', 'register.php', 'page.php', 'app/layout.php', 'app/edition2.php', 'app/sections_render.php'];
    $out = [];
    $lit = function (string $s): string {
        $q = $s[0];
        $body = substr($s, 1, -1);
        return $q === "'" ? str_replace(["\\\\", "\\'"], ["\\", "'"], $body) : stripcslashes($body);
    };
    foreach ($files as $f) {
        $path = SCF_ROOT . '/' . $f;
        if (!is_file($path)) continue;
        $tokens = array_values(array_filter(token_get_all((string)file_get_contents($path)), function ($t) {
            return !(is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true));
        }));
        $n = count($tokens);
        for ($i = 0; $i < $n - 5; $i++) {
            $t = $tokens[$i];
            if (!is_array($t) || $t[0] !== T_STRING || !in_array($t[1], ['tt', 'scf_archive_text'], true)) continue;
            if ($tokens[$i + 1] !== '(') continue;
            $a = $tokens[$i + 2]; $c = $tokens[$i + 3]; $b = $tokens[$i + 4];
            if (!is_array($a) || $a[0] !== T_CONSTANT_ENCAPSED_STRING || $c !== ',' || !is_array($b) || $b[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
            $ar = $lit($a[1]); $en = $lit($b[1]);
            $out[scf_tk_key($ar, $en)] = ['ar' => $ar, 'en' => $en, 'file' => $f];
        }
    }
    if (function_exists('tr_map')) {
        foreach (tr_map() as $k => $v) $out['i18n:' . $k] = ['ar' => $v['ar'], 'en' => $v['en'], 'file' => 'app/i18n.php'];
    }
    return $out;
}
