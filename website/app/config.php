<?php
/**
 * منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي — الإعدادات الأساسية
 * Iraqi Forum for Corporate Social Responsibility and Business Integrity — Core configuration
 *
 * عند الرفع على الاستضافة: عدّل بيانات قاعدة البيانات أدناه،
 * وغيّر ADMIN_DIR بعد إعادة تسمية مجلد لوحة التحكم، وغيّر مفاتيح الأمان.
 */

if (defined('SCF_CONFIG')) { return; }
define('SCF_CONFIG', 1);

$scfRuntimeFile = dirname(__DIR__) . '/storage/database.php';
$scfRuntime = is_file($scfRuntimeFile) ? require $scfRuntimeFile : [];
if (!is_array($scfRuntime)) $scfRuntime = [];

/* ---------- Database ---------- */
define('DB_HOST', getenv('SCF_DB_HOST') ?: ($scfRuntime['host'] ?? '127.0.0.1'));
define('DB_NAME', getenv('SCF_DB_NAME') ?: ($scfRuntime['name'] ?? 'csrforum'));
define('DB_USER', getenv('SCF_DB_USER') ?: ($scfRuntime['user'] ?? 'root'));
define('DB_PASS', getenv('SCF_DB_PASS') !== false ? getenv('SCF_DB_PASS') : ($scfRuntime['pass'] ?? ''));

/* ---------- Paths ---------- */
define('SCF_ROOT', dirname(__DIR__));
define('SCF_STORAGE', SCF_ROOT . '/storage');
define('SCF_UPLOADS', SCF_ROOT . '/uploads');

/*
 * اسم مجلد لوحة التحكم (المسار المخفي).
 * إذا أعدت تسمية المجلد يجب تعديل هذه القيمة لتطابق الاسم الجديد.
 */
define('ADMIN_DIR', 'csr-admin');

/*
 * مفاتيح سرية — غيّرها إلى قيم عشوائية طويلة عند الرفع على الاستضافة.
 */
define('SCF_SECRET', $scfRuntime['secret'] ?? '69421c68bad933c37dc29de651794c4456a4152b6dbe3cfd');
define('INSTALL_KEY', 'ins-0a599a58dcf051c480a943f3');

/* ---------- Session ---------- */
define('SESSION_NAME', 'CSRSID');
define('SESSION_IDLE_MIN', 30);      // مهلة الخمول للوحة التحكم (دقائق)
define('SESSION_ABS_HOURS', 8);      // العمر الأقصى للجلسة (ساعات)

/* ---------- Security thresholds ---------- */
define('LOGIN_MAX_FAILS', 4);        // محاولات دخول فاشلة قبل الحظر المؤقت
define('LOGIN_LOCK_MIN', 15);        // مدة الحظر (دقائق)
define('WAF_STRIKES_BAN', 3);        // إنذارات الحقن قبل حظر العنوان
define('WAF_BAN_HOURS', 24);         // مدة حظر العنوان (ساعات)

/* ---------- Misc ---------- */
define('SCF_TZ', 'Asia/Baghdad');
define('SCF_DEBUG', false);          // لا تفعّلها على الاستضافة

date_default_timezone_set(SCF_TZ);
mb_internal_encoding('UTF-8');

if (SCF_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', SCF_STORAGE . '/logs/php-errors.log');
}
