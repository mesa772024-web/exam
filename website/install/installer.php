<?php
/**
 * أداة التنصيب — تُنشئ الجداول وتزرع المحتوى الأساسي وحساب المدير.
 * تعمل من سطر الأوامر:  php installer.php user pass
 * أو عبر install/index.php (بمفتاح التنصيب) على الاستضافة.
 */

require_once dirname(__DIR__) . '/app/config.php';

function installer_connect_server(): PDO
{
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    return $pdo;
}

function installer_run(string $adminUser, string $adminPass): array
{
    $pdo = installer_connect_server();

    // 1) الجداول
    $schema = file_get_contents(dirname(__DIR__) . '/app/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $stmt) {
        if ($stmt !== '' && stripos($stmt, 'CREATE TABLE') !== false) {
            $pdo->exec($stmt);
        }
    }

    // 2) حساب المدير
    $exists = $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ((int)$exists === 0) {
        $st = $pdo->prepare('INSERT INTO admins (username, pass_hash, display_name) VALUES (?,?,?)');
        $st->execute([$adminUser, password_hash($adminPass, PASSWORD_BCRYPT, ['cost' => 12]), 'مدير النظام']);
    }

    // 3) الإعدادات
    $S = installer_settings();
    $st = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (?,?)');
    foreach ($S as $k => $v) {
        $st->execute([$k, $v]);
    }

    // 4) المحتوى
    installer_seed_orgs($pdo);
    installer_seed_agenda($pdo);

    // 5) الجداول والإعدادات الإضافية (المعرض، الأقسام، حقول التسجيل، الباج)
    require_once dirname(__DIR__) . '/app/migrate.php';
    scf_migrate();

    // 6) قفل التنصيب
    if (file_put_contents(SCF_STORAGE . '/installed.lock', date('c'), LOCK_EX) === false) {
        throw new RuntimeException('Unable to lock the installer');
    }

    return ['ok' => true];
}

function installer_settings(): array
{
    $j = function ($arr) { return json_encode($arr, JSON_UNESCAPED_UNICODE); };
    return [
        'site_name_ar'    => 'منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي',
        'site_name_en'    => 'The Iraqi Forum for Corporate Social Responsibility and Business Integrity',
        'edition_ar'      => 'بغداد ٢٠٢٦',
        'edition_en'      => 'Baghdad 2026',
        'tagline_ar'      => 'الأعمال المسؤولة في العراق: شراكات من أجل النمو المستدام',
        'tagline_en'      => 'Responsible Business in Iraq: Partnerships for Sustainable Growth',
        'hero_text_ar'    => 'من المسؤولية إلى الأثر المستدام',
        'hero_text_en'    => 'From Responsibility to Sustainable Impact',
        'event_dates_ar'  => 'الأحد ١ تشرين الثاني ٢٠٢٦',
        'event_dates_en'  => 'Sunday · 01 November 2026',
        'reg_dates_ar'    => 'الأحد ١ تشرين الثاني ٢٠٢٦',
        'reg_dates_en'    => 'Sunday · 01 November 2026',
        'venue_ar'        => 'بغداد – جمهورية العراق',
        'venue_en'        => 'Baghdad · Republic of Iraq',
        'event_time_ar'   => '',
        'event_time_en'   => '',
        'countdown_target'=> '2026-11-01 09:00:00',
        'reg_open'        => '1',
        'speakup_on'      => '0',

        'about_ar' => "يُعدّ منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي منصة وطنية بأبعاد دولية، تهدف إلى تعزيز دور القطاع الخاص العراقي كشريك فاعل في تحقيق التنمية المستدامة، من خلال ترسيخ مبادئ النزاهة والحوكمة وممارسات الأعمال المسؤولة، وتعزيز المسؤولية الاجتماعية والبيئية، وبناء شراكات مستدامة تسهم في رفع التنافسية وتحقيق أثر إيجابي على الاقتصاد والمجتمع والبيئة. كما يسعى المنتدى إلى الانتقال بالمسؤولية الاجتماعية من مبادرات متفرقة إلى برامج استراتيجية مستدامة ذات أثر قابل للقياس.\n\nوسيجمع المنتدى القيادات الحكومية ومؤسسات القطاع الخاص والمنظمات الدولية والجامعات والخبراء وممثلي المجتمع المدني تحت سقف واحد، وسيُعقد في بغداد يوم الأحد، الأول من تشرين الثاني ٢٠٢٦، في إطار توجه يهدف إلى تعزيز التعاون بين مختلف القطاعات، ودعم ممارسات الاستدامة والحوكمة في مجتمع الأعمال العراقي.",
        'about_en' => "The Iraqi Forum for Corporate Social Responsibility and Business Integrity is a national platform with an international dimension. It aims to strengthen the role of Iraq's private sector as an active partner in sustainable development by embedding the principles of integrity, governance and responsible business practice, advancing social and environmental responsibility, and building lasting partnerships that raise competitiveness and create a positive impact on the economy, society and the environment. The forum also seeks to move social responsibility from scattered initiatives to sustainable strategic programmes with measurable impact.\n\nThe forum will bring together government leaders, private-sector institutions, international organisations, universities, experts and civil-society representatives under one roof. It will be held in Baghdad on Sunday, 1 November 2026, as part of a drive to strengthen cross-sector cooperation and support sustainability and governance practices in Iraq's business community.",

        'vision_ar' => '', 'vision_en' => '',
        'importance_ar' => 'يقدّم المنتدى فرصاً للمؤسسات الراغبة في دعم المسؤولية الاجتماعية وتعزيز حضورها المؤسسي أمام نخبة من صنّاع القرار والشركاء.',
        'importance_en' => 'The forum offers institutions that wish to support social responsibility an opportunity to strengthen their corporate presence before a select group of decision-makers and partners.',
        'previous_ar' => '', 'previous_en' => '',
        'roadmap_ar' => '', 'roadmap_en' => '',

        /* الأهداف: «العنوان | الوصف» لكل سطر */
        'objectives_ar' => $j([
            'ترسيخ النزاهة والحوكمة المسؤولة | إبراز دور النزاهة والحوكمة والممارسات البيئية والاجتماعية في تعزيز استدامة الشركات العراقية، وبناء الثقة، ورفع التنافسية وتحسين فرص الوصول إلى الاستثمار والأسواق.',
            'ترجمة الالتزامات إلى ممارسات عملية | دعم الشركات العراقية في تبني وتطبيق الأطر والمعايير الوطنية والدولية ذات الصلة، ودمج مبادئ النزاهة والحوكمة والممارسات البيئية والاجتماعية في سياساتها وعملياتها اليومية.',
            'تعزيز المسؤولية الاجتماعية والأثر الإيجابي | ترسيخ ثقافة المسؤولية الاجتماعية وتشجيع الشركات على الإسهام الفاعل في التنمية المستدامة وتحقيق أثر إيجابي للمجتمعات.',
            'بناء شراكات مستدامة | تعزيز التعاون بين المؤسسات الحكومية والقطاع الخاص والمجتمع المدني، وبناء أطر وشراكات تدعم تبادل الخبرات والعمل الجماعي.',
        ]),
        'objectives_en' => $j([
            'Embedding integrity and responsible governance | Highlighting the role of integrity, governance and environmental and social practices in strengthening the sustainability of Iraqi companies, building trust, raising competitiveness and improving access to investment and markets.',
            'Turning commitments into practice | Supporting Iraqi companies in adopting and applying the relevant national and international frameworks and standards, and integrating integrity, governance and environmental and social practices into their policies and daily operations.',
            'Advancing social responsibility and positive impact | Embedding a culture of social responsibility and encouraging companies to contribute actively to sustainable development and create a positive impact for communities.',
            'Building sustainable partnerships | Strengthening cooperation between government institutions, the private sector and civil society, and building frameworks and partnerships that support the exchange of expertise and collective action.',
        ]),

        /* الفئات المشاركة: «الفئة | عنصر، عنصر، …» */
        'sectors_ar' => $j([
            'الجهات الحكومية | الوزارات، الهيئات الرسمية، المؤسسات الحكومية',
            'القطاع الخاص | الشركات الكبرى، المصارف، شركات الطاقة، شركات الاتصالات، المؤسسات الصناعية',
            'المؤسسات الدولية | منظمات الأمم المتحدة، المنظمات التنموية، الغرف التجارية الدولية',
            'القطاع الأكاديمي | الجامعات، مراكز البحوث، الخبراء',
            'المجتمع المدني والإعلام | منظمات المجتمع المدني، المبادرات التطوعية، وسائل الإعلام',
        ]),
        'sectors_en' => $j([
            'Government | Ministries, Official bodies, Government institutions',
            'Private sector | Major companies, Banks, Energy companies, Telecom companies, Industrial enterprises',
            'International institutions | UN organisations, Development organisations, International chambers of commerce',
            'Academia | Universities, Research centres, Experts',
            'Civil society & media | Civil-society organisations, Volunteer initiatives, Media',
        ]),
        'target_sectors_ar' => $j([]), 'target_sectors_en' => $j([]),

        'stats' => $j([
            ['num' => '3', 'ar' => 'جلسات حوارية', 'en' => 'Dialogue sessions'],
            ['num' => '4', 'ar' => 'جهات منظّمة', 'en' => 'Organising bodies'],
            ['num' => '5', 'ar' => 'فئات مشاركة', 'en' => 'Participant groups'],
        ]),
        'activities' => $j([]),

        'sponsor_why_ar' => 'تُصمَّم باقات مخصّصة وفق احتياجات الشركاء، وللاستفسار يُرجى التواصل مع اللجنة المنظمة للمنتدى.',
        'sponsor_why_en' => 'Bespoke packages are tailored to partners’ needs. For enquiries, please contact the forum’s organising committee.',
        /* فئات الرعاية: «الفئة | الاسم | ميزة؛ ميزة؛ …» */
        'why_ar' => $j([
            'الفئة الأولى | الشريك الاستراتيجي | ظهور رئيسي للعلامة التجارية في جميع مواد المنتدى؛ مشاركة قيادية في الجلسة الافتتاحية والجلسات الحوارية؛ جناح خاص في معرض المنتدى؛ تغطية إعلامية موسّعة؛ دعوات حضور لكبار الشخصيات (VIP)',
            'الفئة الثانية | الراعي البلاتيني | شعار رئيسي في المواد الرسمية؛ مشاركة متحدث في جلسات المنتدى؛ مساحة عرض في المعرض',
            'الفئة الثالثة | الراعي الذهبي | ظهور إعلامي؛ شعار في المواد الرسمية؛ دعوات خاصة لحضور المنتدى',
        ]),
        'why_en' => $j([
            'Tier one | Strategic Partner | Lead brand visibility across all forum materials; Leadership role in the opening and dialogue sessions; Dedicated pavilion at the forum exhibition; Extended media coverage; VIP invitations',
            'Tier two | Platinum Sponsor | Prominent logo on official materials; A speaker in the forum sessions; Exhibition space',
            'Tier three | Gold Sponsor | Media visibility; Logo on official materials; Special invitations to attend the forum',
        ]),
        'participants_ar' => $j([]), 'participants_en' => $j([]),
        'sponsor_cards' => $j([]),
        'intl_ar' => '', 'intl_en' => '',
        'edition1_ar' => '', 'edition1_en' => '',

        'contact_phones'  => $j([]),
        'contact_email'   => '',
        'contact_addr_ar' => 'بغداد – جمهورية العراق',
        'contact_addr_en' => 'Baghdad · Republic of Iraq',
        'social_facebook' => '',
        'social_instagram'=> '',
        'social_linkedin' => '',
        'site_domain'     => '',

        'footer_note_ar' => '',
        'footer_note_en' => '',

        'wa_template_ar' => "مرحباً {name} 👋\nيسعدنا تأكيد تسجيلكم في منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي.\n📅 الأحد ١ تشرين الثاني ٢٠٢٦\n📍 بغداد – جمهورية العراق\n🎫 رمز الدخول: {code}\nيرجى إبراز هذا الرمز عند بوابة الدخول.\nنتشرف بحضوركم.",
        'wa_template_en' => "Hello {name} 👋\nWe are pleased to confirm your registration for the Iraqi Forum for Corporate Social Responsibility and Business Integrity.\n📅 Sunday · 01 November 2026\n📍 Baghdad · Republic of Iraq\n🎫 Entry code: {code}\nPlease present this code at the entrance gate.\nWe look forward to welcoming you.",

        'wa_api_url'   => '',
        'wa_api_token' => '',
        'recaptcha_site'   => '',
        'recaptcha_secret' => '',
        'admin_ip_whitelist' => '',
    ];
}

function installer_seed_orgs(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM orgs')->fetchColumn();
    if ($count > 0) return;
    $rows = [
        // الجهات المنظمة
        ['organizer', 'وزارة التجارة', 'Ministry of Trade', 'Ministry_of_Trade_Iraq.png', 10],
        ['organizer', 'اتحاد الغرف التجارية العراقية', 'Federation of Iraqi Chambers of Commerce', 'Federation_of_Iraqi_Chambers_of_Commerce.png', 20],
        ['organizer', 'غرفة التجارة الدولية – العراق', 'International Chamber of Commerce – Iraq', 'ICC_Iraq.png', 30],
        ['organizer', 'برنامج الأمم المتحدة الإنمائي', 'United Nations Development Programme', 'UNDP.png', 40],
        // الجهات الداعمة وضيوف المنتدى
        ['partner', 'اتحاد الغرف العربية', 'Union of Arab Chambers', 'Union_of_Arab_Chambers.png', 10],
        ['partner', 'الاتحاد الدولي للمسؤولية الاجتماعية', 'International Union for Social Responsibility', 'IUSR.png', 20],
        ['partner', 'منظمة الأمم المتحدة للتنمية الصناعية', 'United Nations Industrial Development Organization', 'UNIDO.png', 30],
        ['partner', 'CSR Accreditation', 'CSR Accreditation', 'CSR_Accreditation.png', 40],
        // بدعم من
        ['support', 'وبدعم من الاتحاد الأوروبي', 'With the support of the European Union', 'European_Union.png', 10],
    ];
    $st = $pdo->prepare('INSERT INTO orgs (kind, name_ar, name_en, logo, sort, active) VALUES (?,?,?,?,?,1)');
    foreach ($rows as $r) {
        $st->execute([$r[0], $r[1], $r[2], 'assets:partners/' . $r[3], $r[4]]);
    }
}

function installer_seed_agenda(PDO $pdo): void
{
    $count = (int)$pdo->query('SELECT COUNT(*) FROM agenda_days')->fetchColumn();
    if ($count > 0) return;

    $d = $pdo->prepare('INSERT INTO agenda_days (day_date, title_ar, title_en, sub_ar, sub_en, sort) VALUES (?,?,?,?,?,?)');
    $i = $pdo->prepare('INSERT INTO agenda_items (day_id, time_txt, title_ar, title_en, desc_ar, desc_en, sort) VALUES (?,?,?,?,?,?,?)');

    $d->execute(['2026-11-01', 'الأحد ١ تشرين الثاني ٢٠٢٦', 'Sunday · 01 November 2026', 'بغداد – جمهورية العراق', 'Baghdad · Republic of Iraq', 10]);
    $day = (int)$pdo->lastInsertId();
    /* الوصف: سطر لكل عنصر. للجلسات الحوارية: السطر الأول تسمية الجلسة. */
    $items = [
        ['', 'الجلسة الافتتاحية', 'Opening Session',
            ['كلمات الجهات المنظّمة', 'كلمات الجهات الداعمة', 'الكلمة الرئيسية'],
            ['Remarks by the organising bodies', 'Remarks by the supporting bodies', 'Keynote address']],
        ['', 'الجدوى الاقتصادية للنزاهة في عالم سريع التغيّر.', 'The business case for integrity in a fast-changing world.',
            ['الجلسة الأولى'], ['Session one']],
        ['', 'من المبادئ إلى الممارسة – إدماج النزاهة والممارسات البيئية والاجتماعية والحوكمة في الأعمال.', 'From principles to practice – integrating integrity and ESG into business.',
            ['الجلسة الثانية'], ['Session two']],
        ['', 'من البيئة التمكينية إلى الأثر – تعزيز الأعمال المستدامة والمسؤولية الاجتماعية للشركات في العراق.', 'From an enabling environment to impact – advancing sustainable business and CSR in Iraq.',
            ['الجلسة الثالثة'], ['Session three']],
        ['', 'إطلاق التحالف الأخضر لنزاهة الأعمال', 'Launch of the Green Alliance for Business Integrity',
            ['الإطلاق الرسمي لمنصة تتيح للشركات العراقية ترجمة مبادئ المنتدى إلى التزامات مستدامة في مجالات النزاهة، والممارسات البيئية والاجتماعية والحوكمة.'],
            ['The official launch of a platform that enables Iraqi companies to turn the forum’s principles into lasting commitments on integrity and environmental, social and governance practices.']],
    ];
    $s = 10;
    foreach ($items as $it) { $i->execute([$day, $it[0], $it[1], $it[2], implode("\n", $it[3]), implode("\n", $it[4]), $s]); $s += 10; }
}

/* ---------- CLI ---------- */
if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'installer.php') {
    $user = $argv[1] ?? 'csradmin';
    $pass = $argv[2] ?? bin2hex(random_bytes(8));
    $r = installer_run($user, $pass);
    echo "Install OK\nAdmin user: $user\nAdmin pass: $pass\n";
}
