<?php
/**
 * ترقية قاعدة البيانات — تضيف الجداول والإعدادات الجديدة (idempotent).
 * تعمل من CLI: php app/migrate.php   أو تُستدعى تلقائياً من الأدمن.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/badge.php';
require_once __DIR__ . '/sections_catalog.php';

function scf_migrate(): array
{
    $pdo = db();
    $done = [];

    // 1) جدول معرض الصور (السلايدر)
    $pdo->exec("CREATE TABLE IF NOT EXISTS gallery (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        image VARCHAR(255) NOT NULL DEFAULT '',
        cap_ar VARCHAR(255) NOT NULL DEFAULT '',
        cap_en VARCHAR(255) NOT NULL DEFAULT '',
        sort INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done[] = 'gallery';

    // 2) تكبير حقول المسجّلين لتخزين بيانات إضافية (custom fields JSON)
    $col = $pdo->query("SHOW COLUMNS FROM registrants LIKE 'extra'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE registrants ADD COLUMN extra MEDIUMTEXT NULL AFTER notes");
        $done[] = 'registrants.extra';
    }

    // 2g) حقول الفورما المطورة
    $col = $pdo->query("SHOW COLUMNS FROM registrants LIKE 'title'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE registrants ADD COLUMN title VARCHAR(120) NOT NULL DEFAULT '' AFTER code");
        $done[] = 'registrants.title';
    } elseif (stripos((string)$col['Type'], 'varchar(120)') === false) {
        $pdo->exec("ALTER TABLE registrants MODIFY title VARCHAR(120) NOT NULL DEFAULT ''");
        $done[] = 'registrants.title:120';
    }
    $col = $pdo->query("SHOW COLUMNS FROM registrants LIKE 'sector'")->fetch();
    if ($col && stripos((string)$col['Type'], 'varchar(160)') === false) {
        $pdo->exec("ALTER TABLE registrants MODIFY sector VARCHAR(160) NOT NULL DEFAULT ''");
        $done[] = 'registrants.sector:160';
    }
    $col = $pdo->query("SHOW COLUMNS FROM registrants LIKE 'country'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE registrants ADD COLUMN country VARCHAR(100) NOT NULL DEFAULT '' AFTER city");
        $done[] = 'registrants.country';
    }

    // 2b) دور حساب المدير: super (كامل) | desk (منصة تسجيل فقط)
    $col = $pdo->query("SHOW COLUMNS FROM admins LIKE 'role'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'super' AFTER display_name");
        $done[] = 'admins.role';
    }

    // 2c) تتبّع الحضور في القاعة (seat + وقت الطباعة)
    $col = $pdo->query("SHOW COLUMNS FROM registrants LIKE 'seat_no'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE registrants ADD COLUMN seat_no INT NULL AFTER attended_at");
        $pdo->exec("ALTER TABLE registrants ADD COLUMN badge_printed TINYINT(1) NOT NULL DEFAULT 0 AFTER seat_no");
        $done[] = 'registrants.seat/badge';
    }

    // 2d) نوع الصفحة: blocks (بلوكات) | full (HTML كامل داخل iframe)
    $col = $pdo->query("SHOW COLUMNS FROM pages LIKE 'mode'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE pages ADD COLUMN mode VARCHAR(10) NOT NULL DEFAULT 'blocks' AFTER blocks");
        $pdo->exec("ALTER TABLE pages ADD COLUMN raw_code MEDIUMTEXT NULL AFTER mode");
        $done[] = 'pages.mode/raw_code';
    }

    // 2e) إزالة قسم النسخة الأولى من الصفحة الرئيسية (صار صفحة مستقلة)
    $so = $pdo->query("SELECT value FROM settings WHERE name='sections_order'")->fetchColumn();
    if ($so) {
        $arr = json_decode($so, true);
        if (is_array($arr)) {
            $filtered = array_values(array_filter($arr, function ($s) { return ($s['key'] ?? '') !== 'edition1'; }));
            if (count($filtered) !== count($arr)) {
                $st = $pdo->prepare("UPDATE settings SET value=? WHERE name='sections_order'");
                $st->execute([json_encode($filtered, JSON_UNESCAPED_UNICODE)]);
                $done[] = 'sections_order:edition1-removed';
            }
        }
    }

    // 2f) الأقسام المخصّصة (نص / HTML كامل / كاروسيل)
    $pdo->exec("CREATE TABLE IF NOT EXISTS custom_sections (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(15) NOT NULL DEFAULT 'text',
        title_ar VARCHAR(200) NOT NULL DEFAULT '',
        title_en VARCHAR(200) NOT NULL DEFAULT '',
        body_ar MEDIUMTEXT NULL,
        body_en MEDIUMTEXT NULL,
        raw_code MEDIUMTEXT NULL,
        data MEDIUMTEXT NULL,
        bg VARCHAR(10) NOT NULL DEFAULT 'light',
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done[] = 'custom_sections';

    // 2h) قائمة انتظار مشتركة بين الهاتف وحاسبة الطباعة
    $pdo->exec("CREATE TABLE IF NOT EXISTS hall_queue (
        registrant_id INT UNSIGNED NOT NULL PRIMARY KEY,
        added_by INT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_hq_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done[] = 'hall_queue';

    // 2i) منشئ صفحة النسخة الأولى
    $pdo->exec("CREATE TABLE IF NOT EXISTS edition1_blocks (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(20) NOT NULL DEFAULT 'text',
        title_ar VARCHAR(200) NOT NULL DEFAULT '',
        title_en VARCHAR(200) NOT NULL DEFAULT '',
        body_ar MEDIUMTEXT NULL,
        body_en MEDIUMTEXT NULL,
        image VARCHAR(255) NOT NULL DEFAULT '',
        sort INT NOT NULL DEFAULT 0,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_ed1_sort (sort, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if ((int)$pdo->query('SELECT COUNT(*) FROM edition1_blocks')->fetchColumn() === 0) {
        $seed = $pdo->prepare('INSERT INTO edition1_blocks (type, title_ar, title_en, image, sort, active) VALUES (?,?,?,?,?,1)');
        $seed->execute(['logos', '', '', '@asset/img/photos/edition1-logos-top.png', 10]);
        $seed->execute(['logos', '', '', '@asset/img/photos/edition1-logos-middle.png', 20]);
        $seed->execute(['logos', '', '', '@asset/img/photos/edition1-logos-bottom.png', 30]);
        $done[] = 'edition1_blocks:seed';
    }
    $done[] = 'edition1_blocks';

    // 2j) سجل تحديثات النظام
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_updates (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        version VARCHAR(60) NOT NULL,
        previous_version VARCHAR(60) NOT NULL DEFAULT '',
        package_name VARCHAR(255) NOT NULL DEFAULT '',
        file_count INT UNSIGNED NOT NULL DEFAULT 0,
        backup_path VARCHAR(255) NOT NULL DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'installed',
        admin_id INT UNSIGNED NULL,
        detail VARCHAR(500) NOT NULL DEFAULT '',
        installed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_update_version (version),
        KEY idx_update_date (installed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done[] = 'system_updates';

    // 3) الإعدادات الجديدة (لا تُكتب إن وُجدت)
    $defaults = migrate_default_settings();
    $st = $pdo->prepare('INSERT IGNORE INTO settings (name, value) VALUES (?,?)');
    foreach ($defaults as $k => $v) {
        $st->execute([$k, $v]);
    }
    $done[] = 'settings(' . count($defaults) . ')';

    scf_apply_release_20260809($pdo, $defaults, $done);

    require_once __DIR__ . '/speakup.php';
    speakup_ensure_table();
    $done[] = 'speakups';

    require_once __DIR__ . '/posts.php';
    scf_posts_ensure();
    $done[] = 'posts';
    scf_texts_ensure_table();
    $done[] = 'site_texts';

    return $done;
}

/** ترقية هذه الحزمة للمواقع المثبتة سابقاً دون حذف المحتوى أو الحسابات. */
function scf_merge_agenda_to_two_days(PDO $pdo, array &$done): void
{
    $days = $pdo->query('SELECT id FROM agenda_days ORDER BY sort, id')->fetchAll(PDO::FETCH_COLUMN);
    if (count($days) <= 2) return;
    $target = (int)$days[1];
    $maxSort = (int)($pdo->query('SELECT COALESCE(MAX(sort),0) FROM agenda_items WHERE day_id=' . $target)->fetchColumn() ?: 0);
    $select = $pdo->prepare('SELECT id FROM agenda_items WHERE day_id=? ORDER BY sort,id');
    $move = $pdo->prepare('UPDATE agenda_items SET day_id=?, sort=? WHERE id=?');
    $delete = $pdo->prepare('DELETE FROM agenda_days WHERE id=?');
    $moved = 0;
    foreach (array_slice($days, 2) as $oldDay) {
        $select->execute([(int)$oldDay]);
        foreach ($select->fetchAll(PDO::FETCH_COLUMN) as $itemId) {
            $maxSort += 10;
            $move->execute([$target, $maxSort, (int)$itemId]);
            $moved++;
        }
        $delete->execute([(int)$oldDay]);
    }
    $done[] = 'agenda:merged-to-two-days(' . $moved . ')';
}

function scf_apply_release_20260809(PDO $pdo, array $defaults, array &$done): void
{
    $version = (string)($pdo->query("SELECT value FROM settings WHERE name='app_release'")->fetchColumn() ?: '');
    if ($version === '2026.08.09-r6') return;

    $upsert = $pdo->prepare('INSERT INTO settings (name, value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');

    $unlockFields = function () use ($pdo, $upsert, &$done): void {
        $rawFields = $pdo->query("SELECT value FROM settings WHERE name='reg_fields'")->fetchColumn();
        $savedFields = json_decode((string)$rawFields, true);
        if (!is_array($savedFields)) return;
        foreach ($savedFields as &$field) $field['lock'] = 0;
        unset($field);
        $upsert->execute(['reg_fields', json_encode($savedFields, JSON_UNESCAPED_UNICODE)]);
        $done[] = 'reg_fields:unlocked';
    };

    if ($version === '2026.08.09-r5') {
        $unlockFields();
        $upsert->execute(['app_release', '2026.08.09-r6']);
        $upsert->execute(['code_release', '2026.08.09-r6']);
        $done[] = 'release:2026.08.09-r6';
        return;
    }

    /* r4 وما بعده: نحافظ على الفورما والمحتوى، وندمج اليوم الثالث في الثاني فقط. */
    if ($version === '2026.08.09-r4') {
        scf_merge_agenda_to_two_days($pdo, $done);
        $unlockFields();
        $upsert->execute(['app_release', '2026.08.09-r6']);
        $upsert->execute(['code_release', '2026.08.09-r6']);
        $done[] = 'release:2026.08.09-r6';
        return;
    }

    /* الانتقال من r3: فك قفل اللقب فقط مع الحفاظ على تشغيله/إيقافه الحالي. */
    if ($version === '2026.08.08-r3') {
        $rawFields = $pdo->query("SELECT value FROM settings WHERE name='reg_fields'")->fetchColumn();
        $savedFields = json_decode((string)$rawFields, true);
        if (is_array($savedFields)) {
            foreach ($savedFields as &$field) {
                if (($field['key'] ?? '') === 'title') $field['lock'] = 0;
            }
            unset($field);
            $upsert->execute(['reg_fields', json_encode($savedFields, JSON_UNESCAPED_UNICODE)]);
        }
        scf_merge_agenda_to_two_days($pdo, $done);
        $unlockFields();
        $upsert->execute(['app_release', '2026.08.09-r6']);
        $upsert->execute(['code_release', '2026.08.09-r6']);
        $done[] = 'release:2026.08.09-r6';
        return;
    }

    /* الفورما المعتمدة والقطاعات حسب ملف الفورما المطوّرة. */
    foreach (['reg_fields', 'reg_sectors_ar', 'reg_sectors_en'] as $key) {
        $upsert->execute([$key, $defaults[$key]]);
    }

    /* ضمان ظهور المشاركات الدولية في النسخة الحالية مع الحفاظ على ترتيب الأدمن. */
    $rawOrder = $pdo->query("SELECT value FROM settings WHERE name='sections_order'")->fetchColumn();
    $order = json_decode((string)$rawOrder, true);
    if (!is_array($order)) $order = json_decode($defaults['sections_order'], true) ?: [];
    $found = false;
    foreach ($order as &$section) {
        if (($section['key'] ?? '') === 'international') {
            $section['on'] = 1; $found = true;
        }
    }
    unset($section);
    if (false) {
        $newSection = null;
        $insertAt = count($order);
        foreach ($order as $i => $section) {
            if (($section['key'] ?? '') === 'why') { $insertAt = $i; break; }
        }
        array_splice($order, $insertAt, 0, [$newSection]);
    }
    $upsert->execute(['sections_order', json_encode($order, JSON_UNESCAPED_UNICODE)]);

    /* إزالة العبارة القديمة من Slogan المخزن فعلياً. */
    foreach (['tagline_ar' => 'ar', 'tagline_en' => 'en'] as $key => $language) {
        $value = (string)($pdo->query("SELECT value FROM settings WHERE name=" . $pdo->quote($key))->fetchColumn() ?: '');
        if ($value !== '') $upsert->execute([$key, sanitize_tagline_value($value, $language)]);
    }

    /* إضافة حقول الفورما الجديدة إلى قالب الباج الحالي من دون مسح تصميم المستخدم. */
    $rawBadge = $pdo->query("SELECT value FROM settings WHERE name='badge_template'")->fetchColumn();
    $badge = json_decode((string)$rawBadge, true);
    if (!is_array($badge) || empty($badge['elements'])) $badge = badge_default_template();
    $badgeFields = array_column($badge['elements'], 'field');
    if (!in_array('title', $badgeFields, true)) {
        $badge['elements'][] = ['type' => 'text', 'field' => 'title', 'x' => 50, 'y' => 43, 'size' => 3.8, 'color' => '#B08A3A', 'align' => 'center', 'bold' => 1, 'text' => ''];
    }
    if (!in_array('country', $badgeFields, true)) {
        $badge['elements'][] = ['type' => 'text', 'field' => 'country', 'x' => 50, 'y' => 72, 'size' => 3.6, 'color' => '#5b6478', 'align' => 'center', 'bold' => 0, 'text' => ''];
    }
    $upsert->execute(['badge_template', json_encode($badge, JSON_UNESCAPED_UNICODE)]);

    scf_merge_agenda_to_two_days($pdo, $done);
    $unlockFields();
    $upsert->execute(['app_release', '2026.08.09-r6']);
    $upsert->execute(['code_release', '2026.08.09-r6']);
    $done[] = 'release:2026.08.09-r6';
}

function migrate_default_settings(): array
{
    $j = function ($a) { return json_encode($a, JSON_UNESCAPED_UNICODE); };

    // ترتيب أقسام الصفحة الرئيسية + إظهارها
    $sections = $j(idef_sections_catalog());

    // حقول نموذج التسجيل — قابلة للتفعيل والإلزام
    $regFields = $j([
        ['key' => 'title',     'ar' => 'اللقب',              'en' => 'Title',          'type' => 'title',  'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'full_name', 'ar' => 'الاسم الكامل',       'en' => 'Full Name',      'type' => 'text',   'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'gender',    'ar' => 'الجنس',              'en' => 'Sex',            'type' => 'gender', 'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'country',   'ar' => 'الدولة',             'en' => 'Country',        'type' => 'text',   'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'phone',     'ar' => 'رقم الواتساب',       'en' => 'WhatsApp Number','type' => 'phone',  'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'email',     'ar' => 'البريد الإلكتروني',  'en' => 'Email Address',  'type' => 'email',  'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'sector',    'ar' => 'قطاع العمل',         'en' => 'Sector',         'type' => 'sector', 'on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'org',       'ar' => 'اسم المؤسسة / الجهة','en' => 'Organization / Institution','type' => 'text','on' => 1, 'req' => 1, 'lock' => 0],
        ['key' => 'job',       'ar' => 'المسمى الوظيفي',     'en' => 'Job Title',      'type' => 'text',   'on' => 1, 'req' => 1, 'lock' => 0],
    ]);

    // قالب الباج الافتراضي (أبعاد بالملم عند الطباعة، والعناصر بنسب %)
    $badge = $j(badge_default_template());

    return [
        'sections_order'   => $sections,
        'reg_fields'       => $regFields,
        'reg_show_code'    => '1',      // هل يُعرض الرمز للمستخدم بعد التسجيل
        'reg_captcha_on'   => '1',      // تفعيل رمز التحقق في التسجيل
        'reg_intro_ar'     => 'يرجى ملء النموذج للتسجيل في منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي. سيتم التواصل معكم لتأكيد الحضور.',
        'reg_intro_en'     => 'Please fill in the form to register for the Iraqi Forum for Corporate Social Responsibility and Business Integrity. You will be contacted to confirm attendance.',
        'reg_sectors_ar'   => $j(['القطاع العام','القطاع الخاص','البعثات الدبلوماسية','منظمات','المؤسسات الأكاديمية','وسائل الإعلام','أخرى']),
        'reg_sectors_en'   => $j(['Public Sector','Private Sector','Diplomatic Missions','Organizations','Academic Institutions','Media','Other']),
        'badge_template'   => $badge,
        'barcode_mode'     => 'code',   // code | info
        'barcode_fields'   => $j(['full_name', 'phone', 'org']),
        'gallery_title_ar' => 'لقطات من المنتدى',
        'speakup_on'       => '0',
        'gallery_title_en' => 'Forum Moments',
        'gallery_autoplay' => '5',      // ثوانٍ (0 = إيقاف)

        // قاعة المؤتمر
        'hall_capacity'    => '150',
        'hall_name_ar'     => 'القاعة الرئيسية لإقامة المنتدى',
        'print_on_admit'   => '1',      // طباعة الباج تلقائياً عند الإدخال للقاعة

        // واتساب API (اختياري)
        'wa_api_provider'  => '',       // ultramsg | meta | custom
        'wa_api_url'       => '',
        'wa_api_token'     => '',
        'wa_api_instance'  => '',
        'wa_api_sender'    => '',
        'wa_send_barcode'  => '1',      // إرسال صورة الباركود مع الرسالة عبر API
    ];
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    print_r(scf_migrate());
    echo "Migration done\n";
}

