<?php
/**
 * خانات الصور والوسائط القابلة للاستبدال (الشعار، فيديو الواجهة، الغلاف…).
 * الاستبدال يُحفظ في الإعداد slot_{KEY} = uploads/site/… ؛ «استعادة الأصل» تحذف الإعداد.
 */
require_once __DIR__ . '/helpers.php';

function scf_slots(): array
{
    return [
        'brand_logo'  => ['label' => 'شعار المنتدى (الرأس والمتصفح)', 'default' => 'img/csr-mark.svg', 'type' => 'image', 'png' => true],
        'logo_white'  => ['label' => 'الشعار الفاتح (التذييل والخلفيات الداكنة)', 'default' => 'img/csr-mark-white.svg', 'type' => 'image', 'png' => true],
        'stage_logo'  => ['label' => 'شعار صفحة التسجيل', 'default' => 'img/csr-mark.svg', 'type' => 'image', 'png' => true],
        'hero_photo'  => ['label' => 'صورة الواجهة الرئيسية', 'default' => 'img/photos/baghdad-tigris.jpg', 'type' => 'image'],
        'about_photo' => ['label' => 'صورة قسم «نبذة عامة»', 'default' => 'img/photos/hands-flag.jpg', 'type' => 'image'],
        'goals_photo' => ['label' => 'صورة قسم «الأهداف الاستراتيجية»', 'default' => 'img/photos/hands-seedling.jpg', 'type' => 'image'],
        'groups_photo'=> ['label' => 'صورة قسم «الفئات المشاركة»', 'default' => 'img/photos/participants.jpg', 'type' => 'image'],
        'group_1'     => ['label' => 'الفئة ١ — الجهات الحكومية', 'default' => 'img/photos/cat-1.jpg', 'type' => 'image'],
        'group_2'     => ['label' => 'الفئة ٢ — القطاع الخاص', 'default' => 'img/photos/cat-2.jpg', 'type' => 'image'],
        'group_3'     => ['label' => 'الفئة ٣ — المؤسسات الدولية', 'default' => 'img/photos/cat-3.jpg', 'type' => 'image'],
        'group_4'     => ['label' => 'الفئة ٤ — القطاع الأكاديمي', 'default' => 'img/photos/cat-4.jpg', 'type' => 'image'],
        'group_5'     => ['label' => 'الفئة ٥ — المجتمع المدني والإعلام', 'default' => 'img/photos/cat-5.jpg', 'type' => 'image'],
        'agenda_photo'=> ['label' => 'صورة قسم «برنامج المنتدى»', 'default' => 'img/photos/conference.jpg', 'type' => 'image'],
        'final_photo' => ['label' => 'صورة الختام', 'default' => 'img/photos/hands-flag.jpg', 'type' => 'image'],
    ];
}

/** رابط الخانة: البديل المرفوع إن وُجد، وإلا الملف الأصلي */
function slot_url(string $key): string
{
    $slots = scf_slots();
    $over = setting('slot_' . $key, '');
    if ($over !== '' && strpos($over, 'uploads/') === 0 && is_file(SCF_ROOT . '/' . $over)) return base_url() . '/' . $over;
    return asset($slots[$key]['default'] ?? '');
}

/** سمة data-slot تظهر زر «استبدال» في وضع التحرير */
function slot_attr(string $key): string
{
    return function_exists('scf_edit_mode') && scf_edit_mode() ? ' data-slot="' . e($key) . '"' : '';
}
