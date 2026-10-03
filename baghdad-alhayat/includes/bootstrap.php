<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_PATH', APP_ROOT . DIRECTORY_SEPARATOR . 'storage');
define('UPLOAD_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'uploads');
define('BACKUP_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'backups');
define('UPDATE_PATH', STORAGE_PATH . DIRECTORY_SEPARATOR . 'updates');
define('ADMIN_SLUG', 'hyt-so-admin-a9');

foreach ([STORAGE_PATH, UPLOAD_PATH, BACKUP_PATH, UPDATE_PATH] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare the application storage directory.');
    }
}

$configFile = STORAGE_PATH . DIRECTORY_SEPARATOR . 'config.php';
$defaultConfig = [
    'app_url' => '',
    'database' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'name' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
$loadedConfig = is_file($configFile) ? require $configFile : [];
$GLOBALS['app_config'] = array_replace_recursive($defaultConfig, is_array($loadedConfig) ? $loadedConfig : []);

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$segments = array_values(array_filter(explode('/', trim($scriptName, '/'))));
$baseSegments = $segments;
if ($baseSegments !== []) {
    array_pop($baseSegments);
}
if (in_array('api', $baseSegments, true) || in_array(ADMIN_SLUG, $baseSegments, true) || in_array('install', $baseSegments, true)) {
    array_pop($baseSegments);
}
$detectedBase = $baseSegments === [] ? '' : '/' . implode('/', $baseSegments);
$configuredBase = trim((string) ($GLOBALS['app_config']['app_url'] ?? ''));
define('BASE_URL', rtrim($configuredBase !== '' ? $configuredBase : $detectedBase, '/'));

session_name('HAYAT_V2_SESSION');
session_set_cookie_params([
    'lifetime' => 0,
    'httponly' => true,
    'secure' => request_is_https(),
    'samesite' => 'Lax',
    'path' => BASE_URL !== '' ? parse_url(BASE_URL, PHP_URL_PATH) . '/' : '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once APP_ROOT . '/includes/content.php';
require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/security.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/cms.php';
require_once APP_ROOT . '/includes/landing-content.php';

apply_security_headers();

function app_config(?string $key = null, mixed $default = null): mixed
{
    $config = $GLOBALS['app_config'] ?? [];
    return $key === null ? $config : ($config[$key] ?? $default);
}

function site_url(string $path = ''): string
{
    $base = BASE_URL;
    return $base . '/' . ltrim($path, '/');
}

function admin_url(string $path = ''): string
{
    return site_url(ADMIN_SLUG . '/' . ltrim($path, '/'));
}

function site_language_mode(): string
{
    try {
        $mode = setting('siteLanguages', 'both');
    } catch (Throwable) {
        $mode = 'both';
    }
    return in_array($mode, ['both', 'en'], true) ? $mode : 'both';
}

function language_switching_enabled(): bool
{
    return site_language_mode() === 'both';
}

function current_locale(): string
{
    if (site_language_mode() === 'en') {
        $_SESSION['locale'] = 'en';
        return 'en';
    }
    $locale = $_GET['lang'] ?? $_POST['locale'] ?? $_SESSION['locale'] ?? 'ar';
    $locale = $locale === 'en' ? 'en' : 'ar';
    $_SESSION['locale'] = $locale;
    return $locale;
}

function app_key(): string
{
    static $key;
    if (is_string($key)) {
        return $key;
    }
    $path = STORAGE_PATH . DIRECTORY_SEPARATOR . 'app.key';
    if (!is_file($path)) {
        file_put_contents($path, bin2hex(random_bytes(32)), LOCK_EX);
        @chmod($path, 0600);
    }
    $key = trim((string) file_get_contents($path));
    if (strlen($key) < 64) {
        throw new RuntimeException('The application key is invalid.');
    }
    return $key;
}

function request_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function is_public_page_request(): bool
{
    if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return false;
    }
    $uri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    foreach (['/' . ADMIN_SLUG . '/', '/api/', '/assets/', '/storage/', '/media.php'] as $blocked) {
        if (str_contains($uri, $blocked)) {
            return false;
        }
    }
    return !str_contains(strtolower($uri), '/install/');
}

if (is_public_page_request()) {
    try {
        record_visit_event();
    } catch (Throwable) {
        // Analytics must never interrupt a public page.
    }
}
