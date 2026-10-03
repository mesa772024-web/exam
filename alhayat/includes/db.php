<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) return $pdo;

    $database = $GLOBALS['app_config']['database'] ?? [];
    if (($database['driver'] ?? 'mysql') !== 'mysql') {
        throw new RuntimeException('هذه النسخة تتطلب MySQL أو MariaDB.');
    }

    $host = (string) ($database['host'] ?? 'localhost');
    $port = (int) ($database['port'] ?? 3306);
    $name = (string) ($database['name'] ?? '');
    $username = (string) ($database['username'] ?? '');
    $password = (string) ($database['password'] ?? '');
    if ($name === '' || $username === '') {
        throw new RuntimeException('لم يتم إعداد اتصال قاعدة البيانات بعد. افتح مجلد install أولاً.');
    }

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("SET time_zone = '+00:00'");
    initialize_database($pdo);
    return $pdo;
}

function database_driver(): string
{
    return 'mysql';
}

function initialize_database(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS settings (
            setting_key VARCHAR(191) PRIMARY KEY,
            setting_value LONGTEXT NOT NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS content_overrides (
            content_key VARCHAR(191) NOT NULL,
            locale CHAR(2) NOT NULL,
            content_value LONGTEXT NOT NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY(content_key, locale)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS registrations (
            id CHAR(36) PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(254) NOT NULL,
            phone VARCHAR(50) NULL,
            organization VARCHAR(190) NOT NULL,
            interest_type VARCHAR(100) NOT NULL,
            message LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'new',
            consent_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_registrations_status_created(status, created_at),
            KEY idx_registrations_email(email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id CHAR(36) PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(254) NOT NULL,
            phone VARCHAR(50) NULL,
            subject VARCHAR(190) NOT NULL,
            message LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_contacts_status_created(status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS admin_users (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(80) NOT NULL UNIQUE,
            display_name VARCHAR(160) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(30) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
            locked_until DATETIME NULL,
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            admin_id BIGINT UNSIGNED NULL,
            actor_name VARCHAR(160) NOT NULL,
            action VARCHAR(120) NOT NULL,
            entity_type VARCHAR(80) NOT NULL,
            entity_id VARCHAR(191) NOT NULL,
            details_json LONGTEXT NOT NULL,
            ip_hash CHAR(64) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_audit_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS rate_limits (
            limit_key CHAR(64) PRIMARY KEY,
            window_started BIGINT UNSIGNED NOT NULL,
            hit_count INT UNSIGNED NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS pages (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(191) NOT NULL UNIQUE,
            template VARCHAR(80) NOT NULL DEFAULT 'editorial',
            status VARCHAR(30) NOT NULL DEFAULT 'draft',
            is_blog TINYINT(1) NOT NULL DEFAULT 0,
            created_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            published_at DATETIME NULL,
            KEY idx_pages_status(status, published_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS page_translations (
            page_id BIGINT UNSIGNED NOT NULL,
            locale CHAR(2) NOT NULL,
            title VARCHAR(255) NOT NULL,
            excerpt LONGTEXT NOT NULL,
            seo_title VARCHAR(255) NOT NULL,
            seo_description LONGTEXT NOT NULL,
            PRIMARY KEY(page_id, locale),
            CONSTRAINT fk_page_translations_page FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS content_blocks (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_id BIGINT UNSIGNED NOT NULL,
            block_type VARCHAR(80) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            settings_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_blocks_page_sort(page_id, sort_order),
            CONSTRAINT fk_content_blocks_page FOREIGN KEY(page_id) REFERENCES pages(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS content_block_translations (
            block_id BIGINT UNSIGNED NOT NULL,
            locale CHAR(2) NOT NULL,
            content_json LONGTEXT NOT NULL,
            PRIMARY KEY(block_id, locale),
            CONSTRAINT fk_block_translations_block FOREIGN KEY(block_id) REFERENCES content_blocks(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS element_styles (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            page_scope VARCHAR(191) NOT NULL,
            element_key VARCHAR(191) NOT NULL,
            styles_json LONGTEXT NOT NULL,
            updated_by BIGINT UNSIGNED NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_element_style(page_scope, element_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS media_library (
            id CHAR(36) PRIMARY KEY,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            title VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            width INT UNSIGNED NOT NULL,
            height INT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            uploaded_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_media_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS visitors (
            id CHAR(36) PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            email VARCHAR(254) NOT NULL,
            phone VARCHAR(50) NOT NULL,
            company VARCHAR(190) NOT NULL,
            details LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversations (
            id CHAR(36) PRIMARY KEY,
            visitor_id CHAR(36) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'open',
            assigned_admin_id BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_conversations_status_last(status, last_message_at),
            CONSTRAINT fk_conversations_visitor FOREIGN KEY(visitor_id) REFERENCES visitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS bookings (
            id CHAR(36) PRIMARY KEY,
            conversation_id CHAR(36) NOT NULL,
            visitor_id CHAR(36) NOT NULL,
            title VARCHAR(255) NOT NULL,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            timezone VARCHAR(80) NOT NULL DEFAULT 'Asia/Baghdad',
            location VARCHAR(500) NOT NULL,
            notes LONGTEXT NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'requested',
            image_attachment_id CHAR(36) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_bookings_starts(starts_at, status),
            CONSTRAINT fk_bookings_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            CONSTRAINT fk_bookings_visitor FOREIGN KEY(visitor_id) REFERENCES visitors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS calendar_events (
            id CHAR(36) PRIMARY KEY,
            event_date DATE NOT NULL,
            end_date DATE NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'green',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_events_date(event_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS conversation_messages (
            id CHAR(36) PRIMARY KEY,
            conversation_id CHAR(36) NOT NULL,
            sender_type VARCHAR(20) NOT NULL,
            sender_id VARCHAR(64) NULL,
            body LONGTEXT NOT NULL,
            message_type VARCHAR(30) NOT NULL DEFAULT 'text',
            booking_id CHAR(36) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_messages_conversation_created(conversation_id, created_at),
            CONSTRAINT fk_messages_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
            CONSTRAINT fk_messages_booking FOREIGN KEY(booking_id) REFERENCES bookings(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS attachments (
            id CHAR(36) PRIMARY KEY,
            message_id CHAR(36) NULL,
            conversation_id CHAR(36) NOT NULL,
            visitor_id CHAR(36) NULL,
            file_path VARCHAR(500) NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL,
            sha256 CHAR(64) NOT NULL,
            width INT UNSIGNED NULL,
            height INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_attachments_message FOREIGN KEY(message_id) REFERENCES conversation_messages(id) ON DELETE CASCADE,
            CONSTRAINT fk_attachments_conversation FOREIGN KEY(conversation_id) REFERENCES conversations(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS visit_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_hash CHAR(64) NOT NULL,
            path VARCHAR(500) NOT NULL,
            page_key VARCHAR(191) NOT NULL,
            locale CHAR(2) NOT NULL DEFAULT 'ar',
            referrer VARCHAR(1000) NOT NULL,
            user_agent VARCHAR(1000) NOT NULL,
            device_type VARCHAR(30) NOT NULL DEFAULT 'desktop',
            ip_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_visit_created(created_at),
            KEY idx_visit_session(session_hash, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS security_events (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(160) NOT NULL,
            severity VARCHAR(30) NOT NULL DEFAULT 'notice',
            ip_hash CHAR(64) NOT NULL,
            details_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_security_created(created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS database_migrations (
            version INT UNSIGNED PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS update_history (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            version VARCHAR(80) NOT NULL,
            status VARCHAR(30) NOT NULL,
            details_json LONGTEXT NOT NULL,
            applied_by BIGINT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];

    foreach ($statements as $sql) $pdo->exec($sql);

    $defaults = [
        'primaryColor' => '#0f6b48',
        'accentColor' => '#b5344d',
        'surfaceColor' => '#f2f7f3',
        'layoutPreset' => 'cinematic',
        'logoPath' => 'assets/logo.png',
        'siteNameAr' => 'مكتب الحياة العلمي',
        'siteNameEn' => 'Al Hayat Scientific Office',
        'siteLanguages' => 'en',
        'fontFamily' => 'IBM Plex Sans Arabic',
        'chat_audio_enabled' => '1',
        'chat_files_enabled' => '1',
        'booking_enabled' => '1',
        'maintenance_mode' => '0',
        'site_version' => '2.1.0',
    ];
    $statement = $pdo->prepare('INSERT IGNORE INTO settings(setting_key, setting_value) VALUES(:key, :value)');
    foreach ($defaults as $key => $value) $statement->execute(['key' => $key, 'value' => $value]);
    $pdo->prepare('INSERT IGNORE INTO database_migrations(version, name) VALUES(?, ?)')->execute([210, 'Al Hayat V2 MySQL core schema']);
}

function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!preg_match('/^[a-z_]+$/', $table) || !preg_match('/^[a-z_]+$/', $column)) {
        throw new InvalidArgumentException('Invalid database identifier.');
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column');
    $statement->execute(['table' => $table, 'column' => $column]);
    if ((int) $statement->fetchColumn() === 0) $pdo->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
}

function setting(string $key, string $default = ''): string
{
    static $values = [];
    if (!array_key_exists($key, $values)) {
        $statement = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :key');
        $statement->execute(['key' => $key]);
        $value = $statement->fetchColumn();
        $values[$key] = is_string($value) ? $value : $default;
    }
    return (string) $values[$key];
}

function save_setting(string $key, string $value, ?int $adminId = null): void
{
    $statement = db()->prepare('INSERT INTO settings(setting_key, setting_value, updated_by, updated_at) VALUES(:key, :value, :admin, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    $statement->execute(['key' => $key, 'value' => $value, 'admin' => $adminId]);
}

function editable_content(string $key, string $locale, string $default): string
{
    static $cache = [];
    $cacheKey = $key . ':' . $locale;
    if (!array_key_exists($cacheKey, $cache)) {
        $statement = db()->prepare('SELECT content_value FROM content_overrides WHERE content_key = :key AND locale = :locale');
        $statement->execute(['key' => $key, 'locale' => $locale]);
        $cache[$cacheKey] = $statement->fetchColumn();
    }
    return is_string($cache[$cacheKey]) && $cache[$cacheKey] !== '' ? $cache[$cacheKey] : $default;
}

function save_editable_content(string $key, string $locale, string $value, ?int $adminId = null): void
{
    $statement = db()->prepare('INSERT INTO content_overrides(content_key, locale, content_value, updated_by, updated_at) VALUES(:key, :locale, :value, :admin, CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    $statement->execute(['key' => $key, 'locale' => $locale, 'value' => $value, 'admin' => $adminId]);
}
