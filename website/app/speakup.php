<?php
/** Speak Up — رسائل وتجارب الزوار (الاسم والهاتف والبريد اختيارية، الرسالة نص فقط). */
require_once __DIR__ . '/helpers.php';

function speakup_ensure_table(): void
{
    static $done = false;
    if ($done) return;
    db()->exec("CREATE TABLE IF NOT EXISTS speakups (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(80) NOT NULL DEFAULT '',
        phone VARCHAR(24) NOT NULL DEFAULT '',
        email VARCHAR(160) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        lang CHAR(2) NOT NULL DEFAULT 'ar',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_speak_read (is_read),
        KEY idx_speak_date (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $done = true;
}

function speakup_unread_count(): int
{
    try {
        speakup_ensure_table();
        return (int)q_val('SELECT COUNT(*) FROM speakups WHERE is_read = 0');
    } catch (Throwable $e) {
        return 0;
    }
}
