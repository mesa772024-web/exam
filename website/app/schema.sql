-- منتدى الاقتصاد الرقمي العراقي — مخطط قاعدة البيانات
-- Iraqi Digital Economy Forum — database schema (MySQL / MariaDB, utf8mb4)

CREATE TABLE IF NOT EXISTS settings (
  name  VARCHAR(80) NOT NULL PRIMARY KEY,
  value MEDIUMTEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(60) NOT NULL UNIQUE,
  pass_hash     VARCHAR(255) NOT NULL,
  display_name  VARCHAR(120) NOT NULL DEFAULT '',
  failed_count  INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME NULL,
  last_login    DATETIME NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS speakers (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name_ar   VARCHAR(160) NOT NULL DEFAULT '',
  name_en   VARCHAR(160) NOT NULL DEFAULT '',
  title_ar  VARCHAR(255) NOT NULL DEFAULT '',
  title_en  VARCHAR(255) NOT NULL DEFAULT '',
  bio_ar    TEXT NULL,
  bio_en    TEXT NULL,
  photo     VARCHAR(255) NOT NULL DEFAULT '',
  sort      INT NOT NULL DEFAULT 0,
  active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- kind: partner | sponsor | organizer
CREATE TABLE IF NOT EXISTS orgs (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  kind      VARCHAR(20) NOT NULL DEFAULT 'partner',
  name_ar   VARCHAR(200) NOT NULL DEFAULT '',
  name_en   VARCHAR(200) NOT NULL DEFAULT '',
  desc_ar   TEXT NULL,
  desc_en   TEXT NULL,
  tier      VARCHAR(30) NOT NULL DEFAULT '',
  url       VARCHAR(255) NOT NULL DEFAULT '',
  logo      VARCHAR(255) NOT NULL DEFAULT '',
  sort      INT NOT NULL DEFAULT 0,
  active    TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_kind (kind, active, sort)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_days (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  day_date  DATE NULL,
  title_ar  VARCHAR(200) NOT NULL DEFAULT '',
  title_en  VARCHAR(200) NOT NULL DEFAULT '',
  sub_ar    VARCHAR(255) NOT NULL DEFAULT '',
  sub_en    VARCHAR(255) NOT NULL DEFAULT '',
  sort      INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS agenda_items (
  id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  day_id    INT UNSIGNED NOT NULL,
  time_txt  VARCHAR(40) NOT NULL DEFAULT '',
  title_ar  VARCHAR(255) NOT NULL DEFAULT '',
  title_en  VARCHAR(255) NOT NULL DEFAULT '',
  desc_ar   TEXT NULL,
  desc_en   TEXT NULL,
  sort      INT NOT NULL DEFAULT 0,
  KEY idx_day (day_id, sort),
  CONSTRAINT fk_ai_day FOREIGN KEY (day_id) REFERENCES agenda_days(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug       VARCHAR(80) NOT NULL UNIQUE,
  title_ar   VARCHAR(200) NOT NULL DEFAULT '',
  title_en   VARCHAR(200) NOT NULL DEFAULT '',
  blocks     MEDIUMTEXT NULL,
  in_nav     TINYINT(1) NOT NULL DEFAULT 0,
  published  TINYINT(1) NOT NULL DEFAULT 0,
  sort       INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS registrants (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(20) NOT NULL UNIQUE,
  title      VARCHAR(120) NOT NULL DEFAULT '',
  full_name  VARCHAR(160) NOT NULL,
  gender     VARCHAR(10) NOT NULL DEFAULT '',
  age        SMALLINT UNSIGNED NULL,
  phone      VARCHAR(20) NOT NULL,
  email      VARCHAR(160) NOT NULL DEFAULT '',
  org        VARCHAR(200) NOT NULL DEFAULT '',
  job       VARCHAR(200) NOT NULL DEFAULT '',
  sector     VARCHAR(160) NOT NULL DEFAULT '',
  city       VARCHAR(80) NOT NULL DEFAULT '',
  country    VARCHAR(100) NOT NULL DEFAULT '',
  status     VARCHAR(15) NOT NULL DEFAULT 'pending',
  wa_sent    TINYINT(1) NOT NULL DEFAULT 0,
  wa_sent_at DATETIME NULL,
  attended   TINYINT(1) NOT NULL DEFAULT 0,
  attended_at DATETIME NULL,
  notes      TEXT NULL,
  lang       VARCHAR(4) NOT NULL DEFAULT 'ar',
  ip         VARCHAR(45) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_status (status),
  KEY idx_phone (phone),
  KEY idx_email (email),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS edition1_blocks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  type       VARCHAR(20) NOT NULL DEFAULT 'text',
  title_ar   VARCHAR(200) NOT NULL DEFAULT '',
  title_en   VARCHAR(200) NOT NULL DEFAULT '',
  body_ar    MEDIUMTEXT NULL,
  body_en    MEDIUMTEXT NULL,
  image      VARCHAR(255) NOT NULL DEFAULT '',
  sort       INT NOT NULL DEFAULT 0,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ed1_sort (sort, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_updates (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  version          VARCHAR(60) NOT NULL,
  previous_version VARCHAR(60) NOT NULL DEFAULT '',
  package_name     VARCHAR(255) NOT NULL DEFAULT '',
  file_count       INT UNSIGNED NOT NULL DEFAULT 0,
  backup_path      VARCHAR(255) NOT NULL DEFAULT '',
  status           VARCHAR(20) NOT NULL DEFAULT 'installed',
  admin_id         INT UNSIGNED NULL,
  detail           VARCHAR(500) NOT NULL DEFAULT '',
  installed_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_update_version (version),
  KEY idx_update_date (installed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- قائمة انتظار مشتركة بين أجهزة المسح والطباعة في القاعة
CREATE TABLE IF NOT EXISTS hall_queue (
  registrant_id INT UNSIGNED NOT NULL PRIMARY KEY,
  added_by      INT UNSIGNED NULL,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_hq_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip           VARCHAR(45) NOT NULL,
  action       VARCHAR(40) NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME NOT NULL,
  UNIQUE KEY uq_ip_action (ip, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS banned_ips (
  id       INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ip       VARCHAR(45) NOT NULL UNIQUE,
  reason   VARCHAR(255) NOT NULL DEFAULT '',
  until_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS security_log (
  id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ts       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ip       VARCHAR(45) NOT NULL DEFAULT '',
  ua       VARCHAR(500) NOT NULL DEFAULT '',
  action   VARCHAR(60) NOT NULL DEFAULT '',
  path     VARCHAR(255) NOT NULL DEFAULT '',
  detail   TEXT NULL,
  severity VARCHAR(10) NOT NULL DEFAULT 'info',
  KEY idx_ts (ts),
  KEY idx_ip (ip),
  KEY idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
