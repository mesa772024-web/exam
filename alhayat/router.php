<?php
declare(strict_types=1);

$uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$blocked = ['/includes/', '/storage/', '/source-materials/'];
foreach ($blocked as $prefix) {
    if (str_contains($uri, $prefix)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
$path = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if ($uri !== '/' && is_file($path)) return false;
if ($uri === '/' || $uri === '') { require __DIR__ . '/index.php'; return true; }
if ($uri === '/install' || $uri === '/install/') { require __DIR__ . '/install/index.php'; return true; }
if (preg_match('#^/(?:p|news)/([^/]+)/?$#u', $uri, $match)) {
    $_GET['slug'] = $match[1];
    require __DIR__ . '/page.php';
    return true;
}
// Serve a directory's index.php (e.g. /hyt-so-admin-a9/ → its index.php)
if (is_dir($path)) {
    $dirIndex = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    if (is_file($dirIndex)) { require $dirIndex; return true; }
}
http_response_code(404);
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/v2-layout.php';
$locale = current_locale();
render_v2_head($locale, $locale === 'ar' ? 'غير موجود' : 'Not found');
render_v2_nav($locale);
echo '<main class="page-hero"><div class="v2-shell"><div class="section-label"><span>404</span>NOT FOUND</div><h1>' . ($locale === 'ar' ? 'الصفحة غير موجودة.' : 'Page not found.') . '</h1></div></main>';
render_v2_footer($locale);
return true;
