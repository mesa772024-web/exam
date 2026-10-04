<?php
/** أقسام الصفحة الرئيسية (الترتيب الافتراضي — قابل للتغيير من لوحة التحكم ومن وضع التحرير) */
function idef_sections_catalog(): array
{
    return [
        ['key' => 'stats',        'ar' => 'أرقام المنتدى',              'en' => 'Stats',            'on' => 1],
        ['key' => 'about',        'ar' => 'نبذة عامة',                  'en' => 'About',            'on' => 1],
        ['key' => 'objectives',   'ar' => 'الأهداف الاستراتيجية',       'en' => 'Objectives',       'on' => 1],
        ['key' => 'participants', 'ar' => 'الفئات المشاركة',            'en' => 'Participants',     'on' => 1],
        ['key' => 'agenda',       'ar' => 'برنامج المنتدى',             'en' => 'Programme',        'on' => 1],
        ['key' => 'speakers',     'ar' => 'المتحدثون',                  'en' => 'Speakers',         'on' => 1],
        ['key' => 'partnership',  'ar' => 'الشراكات والرعاية',          'en' => 'Partnership',      'on' => 1],
        ['key' => 'partners',     'ar' => 'الجهات المنظمة والداعمة',    'en' => 'Partners',         'on' => 1],
        ['key' => 'gallery',      'ar' => 'معرض الصور',                 'en' => 'Gallery',          'on' => 1],
        ['key' => 'final',        'ar' => 'الختام والتسجيل',            'en' => 'Closing CTA',      'on' => 1],
    ];
}

/** يضيف أي قسم جديد غير موجود في الترتيب المحفوظ (مرة واحدة) */
function idef_sections_order(array $order): array
{
    $keys = array_column($order, 'key');
    $missing = array_values(array_filter(idef_sections_catalog(), function ($r) use ($keys) { return !in_array($r['key'], $keys, true); }));
    if (!$missing && $order) return $order;
    $order = $order ? array_merge($order, $missing) : idef_sections_catalog();
    try { setting_set('sections_order', json_encode($order, JSON_UNESCAPED_UNICODE)); } catch (Throwable $e) {}
    return $order;
}
