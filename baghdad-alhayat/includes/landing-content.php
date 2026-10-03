<?php
declare(strict_types=1);

/**
 * Landing page content registry (single source of truth).
 *
 * Every string / media path below is the *default*. When an admin edits a field
 * from the admin panel (الصفحة الرئيسية) the value is stored as an override in the
 * `content_overrides` table (text) or the `settings` table (media) and takes
 * precedence over the default here. The landing page (index.php) always reads
 * through lc()/lc_media(), so an edit in the admin appears live on the site.
 *
 * Keys are namespaced with the "home." prefix (text) / "home." (settings) and
 * never start with "auto-", so they never collide with the click-to-edit visual
 * editor keys.
 */

/**
 * Editable text fields, grouped for the admin UI.
 * key => [group, label(ar), default(ar), default(en)]
 */
function landing_text_fields(): array
{
    return [
        // ── المقدمة السينمائية ──────────────────────────────────────────
        'scene0_eyebrow'   => ['المقدمة السينمائية', 'المشهد 1 · الشارة العلوية', 'مكتب بغداد الحياة العلمي · بغداد', 'Baghdad Al Hayat Scientific Office · Baghdad'],
        'scene0_title'     => ['المقدمة السينمائية', 'المشهد 1 · الشعار الرئيسي', 'نعمل من أجل وصول الرعاية الصحية إلى كل مريض', 'We work to bring healthcare to every patient'],
        'scene1_title'     => ['المقدمة السينمائية', 'المشهد 2 · العنوان', 'من المُصنّع إلى المريض', 'From manufacturer to patient'],
        'scene1_body'      => ['المقدمة السينمائية', 'المشهد 2 · وصف القيمة والخدمات', 'ربط الشركاء بالسوق العراقي عبر تسجيل منظّم، وتخزين مراقب الحرارة، وشبكة توزيع تغطّي المحافظات.', 'We connect partners to the Iraqi market through regulated registration, temperature-controlled storage, and a distribution network across Iraq’s provinces.'],
        'scene3_eyebrow'   => ['المقدمة السينمائية', 'المشهد 4 · الشارة (القيمة)', 'حيث المريض أولويتنا', 'Where the patient is our priority'],
        'scene3_title'     => ['المقدمة السينمائية', 'المشهد 4 · عبارة التغطية', 'شبكة توزيع تغطي محافظات العراق', 'A nationwide distribution network'],

        // ── شريط الأرقام ────────────────────────────────────────────────
        'stats_title'      => ['شريط الأرقام والقدرات', 'العنوان الموحّد للشريط', 'منظومة متكاملة تربط جميع مراحل رحلة المنتج الدوائي', 'An integrated system connecting every stage of the pharmaceutical journey'],
        'stat1_label'      => ['شريط الأرقام والقدرات', 'المقياس 1 · النص (القيمة: 30+)', 'عامًا من الخبرة', 'Years of experience'],
        'stat2_label'      => ['شريط الأرقام والقدرات', 'المقياس 2 · النص (القيمة: 18)', 'محافظة ضمن شبكة التوزيع', 'Governorates in the distribution network'],
        'stat3_label'      => ['شريط الأرقام والقدرات', 'المقياس 3 · النص (القيمة: 24/7)', 'مراقبة سلسلة التبريد', 'Cold-chain monitoring'],
        'stat4_label'      => ['شريط الأرقام والقدرات', 'المقياس 4 · النص (القيمة: GDP)', 'التزام بمعايير الجودة', 'GDP-standard compliance'],

        // ── الهيرو الرئيسي ──────────────────────────────────────────────
        'hero_title'       => ['الهيرو الرئيسي', 'العنوان الرئيسي', 'نعمل من أجل وصول الرعاية الصحية إلى كل مريض', 'We work to bring healthcare to every patient'],
        'hero_sub'         => ['الهيرو الرئيسي', 'العنوان الفرعي', 'منظومة دوائية عراقية متكاملة: تسجيل، تخزين مراقب الحرارة، وتوزيع وطني.', 'An integrated Iraqi pharma system: registration, cold-chain storage, and nationwide distribution.'],
        'hero_card_title'  => ['الهيرو الرئيسي', 'بطاقة الهيرو · العنوان', 'حيث المريض أولويتنا.', 'Where the patient is our priority.'],
        'hero_card_copy'   => ['الهيرو الرئيسي', 'بطاقة الهيرو · النص', 'نربط المُصنّعين بالمرضى عبر تسجيل منظّم وتخزين آمن وشبكة توزيع تغطّي محافظات العراق.', 'We connect manufacturers to patients through regulated registration, safe storage, and a nationwide distribution network.'],

        // ── شبكة التوزيع ───────────────────────────────────────────────
        'network_title'    => ['شبكة التوزيع', 'عنوان القسم', 'من بغداد إلى محافظات العراق.', 'From Baghdad across Iraq’s provinces.'],

        // ── تواصل معنا ─────────────────────────────────────────────────
        'contact_title'    => ['تواصل معنا', 'العنوان', 'أرسل رسالتك إلى مكتب بغداد الحياة العلمي', 'Send a message to Baghdad Al Hayat Scientific Office'],
    ];
}

/**
 * Editable media fields (stored in the settings table).
 * key => [label(ar), default path, type(image|video), hint]
 */
function landing_media_fields(): array
{
    return [
        'intro_video'  => ['فيديو المقدمة (المشهدان 1 و 3)', 'assets/intro-scroll.mp4', 'video', 'يظهر خلف المشهد الأول والثالث في المقدمة السينمائية.'],
        'dark_video'   => ['فيديو المقدمة الليلي (المشهدان 2 و 4)', 'assets/after-dark.mp4', 'video', 'يظهر خلف المشهد الثاني والرابع.'],
        'hero_image'   => ['صورة بطاقة الهيرو', 'assets/intro-poster.jpg', 'image', 'الصورة الكبيرة داخل بطاقة الهيرو.'],
        'story_image'  => ['صورة قصة بغداد الحياة', 'assets/images/headquarters.jpg', 'image', 'صورة قسم «قصة بغداد الحياة».'],
        'proof_image'  => ['صورة الجودة في المخزن', 'assets/images/warehouse-team.jpg', 'image', 'صورة قسم «الجودة في كل خطوة».'],
        'network_image'=> ['صورة شبكة التوزيع', 'assets/night-poster.jpg', 'image', 'صورة قسم «شبكة التوزيع».'],
    ];
}

/**
 * Return an editable landing text value for the given locale, falling back to
 * the registry default. Safe to call before/without a database (returns default).
 */
function lc(string $key, string $locale): string
{
    static $fields;
    $fields ??= landing_text_fields();
    $default = '';
    if (isset($fields[$key])) {
        $default = $locale === 'en' ? (string) $fields[$key][3] : (string) $fields[$key][2];
    }
    try {
        return editable_content('home.' . $key, $locale === 'en' ? 'en' : 'ar', $default);
    } catch (Throwable) {
        return $default;
    }
}

/** Return an editable landing media path, falling back to the registry default. */
function lc_media(string $key): string
{
    static $fields;
    $fields ??= landing_media_fields();
    $default = isset($fields[$key]) ? (string) $fields[$key][1] : '';
    try {
        $value = setting('home.' . $key, $default);
    } catch (Throwable) {
        $value = $default;
    }
    return $value !== '' ? $value : $default;
}
