<?php
/**
 * مساعد الباجات — قالب الباج، محتوى الباركود، وقيم الحقول لكل مسجّل.
 */
require_once __DIR__ . '/helpers.php';

/** القالب الافتراضي (الأبعاد والأحجام بالملم) */
function badge_default_template(): array
{
    return [
        'w' => 90, 'h' => 130, 'unit' => 'mm',
        'bg' => '', 'bg_color' => '#ffffff', 'accent' => '#B08A3A',
        'elements' => [
            ['type' => 'text', 'field' => 'site',      'x' => 50, 'y' => 12, 'size' => 4,  'color' => '#196598', 'align' => 'center', 'bold' => 1, 'text' => ''],
            ['type' => 'text', 'field' => 'title',     'x' => 50, 'y' => 43, 'size' => 3.8,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
            ['type' => 'text', 'field' => 'full_name', 'x' => 50, 'y' => 50, 'size' => 8,  'color' => '#0b0e26', 'align' => 'center', 'bold' => 1, 'text' => ''],
            ['type' => 'text', 'field' => 'job',       'x' => 50, 'y' => 60, 'size' => 4.5,'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
            ['type' => 'text', 'field' => 'org',       'x' => 50, 'y' => 66, 'size' => 4,  'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
            ['type' => 'text', 'field' => 'country',   'x' => 50, 'y' => 72, 'size' => 3.6,'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
            ['type' => 'qr',   'field' => 'code',      'x' => 50, 'y' => 84, 'size' => 32, 'color' => '#24275F', 'align' => 'center', 'bold' => 0, 'text' => ''],
            ['type' => 'text', 'field' => 'code',      'x' => 50, 'y' => 102,'size' => 5,  'color' => '#24275F', 'align' => 'center', 'bold' => 1, 'text' => ''],
        ],
    ];
}

function badge_template(): array
{
    $t = json_decode(setting('badge_template', '{}'), true);
    if (!is_array($t) || empty($t['elements'])) {
        $t = badge_default_template();
    }
    return $t;
}

/** قالبان جاهزان يمكن للأدمن تحميلهما وتعديلهما */
function badge_presets(): array
{
    return [
        'classic' => [
            'name' => 'كلاسيكي (اسم + باركود)',
            'tpl' => [
                'w' => 90, 'h' => 130, 'unit' => 'mm', 'bg' => '', 'bg_color' => '#ffffff', 'accent' => '#B08A3A',
                'elements' => [
                    ['type' => 'text', 'field' => 'site',      'x' => 50, 'y' => 13, 'size' => 4,  'color' => '#196598', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'edition',   'x' => 50, 'y' => 20, 'size' => 3.4,'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'title',     'x' => 50, 'y' => 41, 'size' => 3.7,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'full_name', 'x' => 50, 'y' => 48, 'size' => 8,  'color' => '#0b0e26', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'job',       'x' => 50, 'y' => 58, 'size' => 4.5,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'org',       'x' => 50, 'y' => 64, 'size' => 4,  'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'country',   'x' => 50, 'y' => 70, 'size' => 3.6,'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'qr',   'field' => 'code',      'x' => 50, 'y' => 84, 'size' => 34, 'color' => '#24275F', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'code',      'x' => 50, 'y' => 104,'size' => 5,  'color' => '#24275F', 'align' => 'center', 'bold' => 1, 'text' => ''],
                ],
            ],
        ],
        'modern' => [
            'name' => 'عصري (شريط علوي ملوّن)',
            'tpl' => [
                'w' => 100, 'h' => 140, 'unit' => 'mm', 'bg' => '', 'bg_color' => '#f5f8fc', 'accent' => '#24275F',
                'elements' => [
                    ['type' => 'text', 'field' => 'site',      'x' => 50, 'y' => 10, 'size' => 4.6,'color' => '#24275F', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'dates',     'x' => 50, 'y' => 17, 'size' => 3.4,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'qr',   'field' => 'code',      'x' => 50, 'y' => 44, 'size' => 40, 'color' => '#24275F', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'title',     'x' => 50, 'y' => 67, 'size' => 3.7,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'full_name', 'x' => 50, 'y' => 74, 'size' => 8.5,'color' => '#0b0e26', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'job',       'x' => 50, 'y' => 82, 'size' => 4.5,'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''],
                    ['type' => 'text', 'field' => 'org',       'x' => 50, 'y' => 88, 'size' => 4,  'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'country',   'x' => 50, 'y' => 94, 'size' => 3.6,'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''],
                    ['type' => 'text', 'field' => 'code',      'x' => 50, 'y' => 101,'size' => 4.6,'color' => '#24275F', 'align' => 'center', 'bold' => 1, 'text' => ''],
                ],
            ],
        ],
    ];
}

/** قائمة الحقول المتاحة لعناصر الباج */
function badge_fields(): array
{
    return [
        'full_name' => 'الاسم الكامل',
        'title'     => 'اللقب',
        'job'       => 'المسمى الوظيفي',
        'org'       => 'الجهة',
        'sector'    => 'القطاع',
        'country'   => 'الدولة',
        'city'      => 'المدينة',
        'age'       => 'العمر',
        'gender'    => 'الجنس',
        'phone'     => 'الهاتف',
        'email'     => 'البريد',
        'code'      => 'رمز الدخول',
        'site'      => 'اسم المنتدى',
        'edition'   => 'النسخة',
        'dates'     => 'التاريخ',
        'custom'    => 'نص ثابت',
    ];
}

/** قيمة حقل لمسجّل معيّن (لعرض الباج) */
function badge_value(array $el, array $r): string
{
    $f = $el['field'] ?? '';
    switch ($f) {
        case 'full_name': return (string)($r['full_name'] ?? '');
        case 'title':
            return registrant_title_label((string)($r['title'] ?? ''), ($r['lang'] ?? '') === 'en' ? 'en' : 'ar');
        case 'job':       return (string)($r['job'] ?? '');
        case 'org':       return (string)($r['org'] ?? '');
        case 'sector':    return (string)($r['sector'] ?? '');
        case 'country':   return (string)($r['country'] ?? '');
        case 'city':      return (string)($r['city'] ?? '');
        case 'age':       return $r['age'] ? (string)$r['age'] : '';
        case 'gender':    return $r['gender'] === 'male' ? 'ذكر' : ($r['gender'] === 'female' ? 'أنثى' : '');
        case 'phone':     return $r['phone'] ? '+' . $r['phone'] : '';
        case 'email':     return (string)($r['email'] ?? '');
        case 'code':      return (string)($r['code'] ?? '');
        case 'site':      return setting('site_name_ar', 'منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي');
        case 'edition':   return setting('edition_ar', '');
        case 'dates':     return setting('event_dates_ar', '');
        case 'custom':    return (string)($el['text'] ?? '');
    }
    return '';
}

/** محتوى الباركود/QR حسب الإعدادات (رمز فقط أو معلومات المسجّل) */
function barcode_payload(array $r): string
{
    $mode = setting('barcode_mode', 'code');
    if ($mode !== 'info') {
        return (string)($r['code'] ?? '');
    }
    $fields = json_decode(setting('barcode_fields', '[]'), true) ?: ['full_name', 'phone', 'code'];
    $labels = [
        'title' => 'اللقب', 'full_name' => 'الاسم', 'phone' => 'الهاتف', 'email' => 'البريد',
        'org' => 'الجهة', 'job' => 'الوظيفة', 'city' => 'المدينة',
        'sector' => 'القطاع', 'country' => 'الدولة', 'code' => 'الرمز', 'age' => 'العمر',
    ];
    $lines = [];
    foreach ($fields as $f) {
        $v = badge_value(['field' => $f], $r);
        if ($v !== '') $lines[] = ($labels[$f] ?? $f) . ': ' . $v;
    }
    return implode("\n", $lines);
}

/** توكن صورة الباركود العامة (HMAC) */
function qr_img_token(int $id): string
{
    return substr(hash_hmac('sha256', 'qr:' . $id, SCF_SECRET), 0, 24);
}

/** الرابط العام لصورة الباركود (يجلبها واتساب API) */
function qr_img_url(int $id): string
{
    return base_url() . '/qr-img.php?id=' . $id . '&t=' . qr_img_token($id);
}

/** بيانات المسجّل اللازمة للطباعة على شكل مصفوفة آمنة */
function badge_registrant_json(array $r): array
{
    return [
        'id'    => (int)$r['id'],
        'code'  => $r['code'],
        'title' => $r['title'],
        'full_name' => $r['full_name'],
        'job'   => $r['job'],
        'org'   => $r['org'],
        'sector'=> $r['sector'],
        'country'=> $r['country'],
        'city'  => $r['city'],
        'age'   => $r['age'],
        'gender'=> $r['gender'],
        'phone' => $r['phone'],
        'email' => $r['email'],
        'qr'    => barcode_payload($r),
    ];
}
