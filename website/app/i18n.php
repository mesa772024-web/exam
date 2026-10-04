<?php
require_once __DIR__ . '/helpers.php';

/** نصوص الواجهة الثابتة */
function tr_map(): array
{
    return [
        'nav_home'       => ['ar' => 'الرئيسية',            'en' => 'Home'],
        'nav_about'      => ['ar' => 'عن المنتدى',          'en' => 'About'],
        'nav_sectors'    => ['ar' => 'القطاعات',            'en' => 'Sectors'],
        'nav_agenda'     => ['ar' => 'الأجندة',             'en' => 'Agenda'],
        'nav_speakers'   => ['ar' => 'المتحدثون',           'en' => 'Speakers'],
        'nav_partners'   => ['ar' => 'الشركاء',             'en' => 'Partners'],
        'nav_sponsors'   => ['ar' => 'الرعاة',              'en' => 'Sponsors'],
        'nav_edition1'   => ['ar' => 'النسخ السابقة',       'en' => 'Past Editions'],
        'nav_recs'       => ['ar' => 'التوصيات',            'en' => 'Recommendations'],
        'nav_register'   => ['ar' => 'سجّل الآن',           'en' => 'Register'],
        'hero_cta'       => ['ar' => 'سجّل حضورك الآن',     'en' => 'Register Now'],
        'hero_cta2'      => ['ar' => 'اكتشف الأجندة',       'en' => 'Explore Agenda'],
        'days'           => ['ar' => 'يوم',                 'en' => 'Days'],
        'hours'          => ['ar' => 'ساعة',                'en' => 'Hours'],
        'minutes'        => ['ar' => 'دقيقة',               'en' => 'Minutes'],
        'seconds'        => ['ar' => 'ثانية',               'en' => 'Seconds'],
        'countdown_to'   => ['ar' => 'العد التنازلي لانطلاق المنتدى', 'en' => 'Countdown to the Forum'],
        'sec_about'      => ['ar' => 'عن المنتدى',          'en' => 'About the Forum'],
        'sec_objectives' => ['ar' => 'الأهداف',             'en' => 'Objectives'],
        'sec_vision'     => ['ar' => 'الرؤية',              'en' => 'Vision'],
        'sec_sectors'    => ['ar' => 'قطاعات الاقتصاد الرقمي', 'en' => 'Digital-Economy Sectors'],
        'sec_agenda'     => ['ar' => 'برنامج المنتدى',      'en' => 'Forum Program'],
        'sec_speakers'   => ['ar' => 'المتحدثون',           'en' => 'Speakers'],
        'sec_partners'   => ['ar' => 'الشركاء',             'en' => 'Partners'],
        'sec_sponsors'   => ['ar' => 'الرعاة',              'en' => 'Sponsors'],
        'sec_organizers' => ['ar' => 'الإدارة والتنظيم',    'en' => 'Organized By'],
        'sec_edition1'   => ['ar' => 'النسخ السابقة',        'en' => 'Previous Editions'],
        'sec_intl'       => ['ar' => 'المشاركات الدولية',   'en' => 'International Participation'],
        'sec_participants'=> ['ar' => 'المشاركون في المنتدى','en' => 'Who Participates'],
        'sec_why'        => ['ar' => 'أهمية المنتدى',       'en' => 'Why It Matters'],
        'sec_sponsor_cta'=> ['ar' => 'كن راعياً للحدث',     'en' => 'Become a Sponsor'],
        'sec_register'   => ['ar' => 'التسجيل في المنتدى',  'en' => 'Forum Registration'],
        'sec_contact'    => ['ar' => 'تواصل معنا',          'en' => 'Contact Us'],
        'speakers_soon'  => ['ar' => 'سيتم الإعلان عن قائمة المتحدثين قريباً', 'en' => 'Speakers will be announced soon'],
        'sponsors_soon'  => ['ar' => 'انضم إلى قائمة رعاة النسخة الثالثة', 'en' => 'Join the third edition sponsors'],
        'f_name'         => ['ar' => 'الاسم الكامل',        'en' => 'Full Name'],
        'f_gender'       => ['ar' => 'الجنس',               'en' => 'Gender'],
        'f_male'         => ['ar' => 'ذكر',                 'en' => 'Male'],
        'f_female'       => ['ar' => 'أنثى',                'en' => 'Female'],
        'f_age'          => ['ar' => 'العمر',               'en' => 'Age'],
        'f_phone'        => ['ar' => 'رقم الواتساب',        'en' => 'WhatsApp Number'],
        'f_email'        => ['ar' => 'البريد الإلكتروني',   'en' => 'Email'],
        'f_org'          => ['ar' => 'الجهة / المؤسسة',     'en' => 'Organization'],
        'f_job'          => ['ar' => 'المسمى الوظيفي',      'en' => 'Job Title'],
        'f_sector'       => ['ar' => 'القطاع',              'en' => 'Sector'],
        'f_city'         => ['ar' => 'المحافظة / المدينة',  'en' => 'Governorate / City'],
        'f_captcha'      => ['ar' => 'رمز التحقق',          'en' => 'Verification Code'],
        'f_captcha_ph'   => ['ar' => 'أدخل الرمز الظاهر في الصورة', 'en' => 'Enter the code shown in the image'],
        'f_refresh'      => ['ar' => 'تحديث الرمز',         'en' => 'Refresh code'],
        'f_choose'       => ['ar' => 'اختر...',             'en' => 'Choose...'],
        'f_optional'     => ['ar' => 'اختياري',             'en' => 'Optional'],
        'f_submit'       => ['ar' => 'إرسال طلب التسجيل',   'en' => 'Submit Registration'],
        'f_sending'      => ['ar' => 'جارٍ الإرسال...',     'en' => 'Submitting...'],
        'reg_note'       => ['ar' => 'سيتم التواصل مع المسجّلين لتأكيد الحضور عبر واتساب.', 'en' => 'Registrants will be contacted via WhatsApp to confirm attendance.'],
        'reg_success_t'  => ['ar' => 'تم استلام طلبك بنجاح', 'en' => 'Registration Received'],
        'reg_success_d'  => ['ar' => 'رمز التسجيل الخاص بك:', 'en' => 'Your registration code:'],
        'reg_success_n'  => ['ar' => 'احتفظ بهذا الرمز — ستصلك رسالة تأكيد على واتساب بعد قبول طلبك.', 'en' => 'Keep this code — you will receive a WhatsApp confirmation once approved.'],
        'reg_closed'     => ['ar' => 'التسجيل مغلق حالياً', 'en' => 'Registration is currently closed'],
        'read_more'      => ['ar' => 'المزيد',              'en' => 'Read more'],
        'contact_phones' => ['ar' => 'أرقام التواصل',       'en' => 'Phone Numbers'],
        'contact_email'  => ['ar' => 'البريد الإلكتروني',   'en' => 'Email'],
        'contact_addr'   => ['ar' => 'العنوان',             'en' => 'Address'],
        'event_date'     => ['ar' => 'تاريخ الحدث',         'en' => 'Event Date'],
        'footer_rights'  => ['ar' => 'جميع الحقوق محفوظة',  'en' => 'All rights reserved'],
        'lang_switch'    => ['ar' => 'English',             'en' => 'العربية'],
        'back_home'      => ['ar' => 'العودة للرئيسية',     'en' => 'Back to Home'],
        'visit_site'     => ['ar' => 'زيارة الموقع',        'en' => 'Visit website'],
    ];
}

function tr(string $key): string
{
    $m = tr_map();
    if (!isset($m[$key])) return $key;
    return tt($m[$key]['ar'], $m[$key]['en'], 'i18n:' . $key);
}
