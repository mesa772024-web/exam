<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/v2-layout.php';

$locale = current_locale();
$slug = clean_slug($_GET['slug'] ?? '');
$editing = isset($_GET['hyt_edit']) && current_admin() !== null;
$page = $slug !== '' ? cms_page_by_slug($slug, $locale, $editing) : null;
if (!$page) {
    http_response_code(404);
    render_v2_head($locale, $locale === 'ar' ? 'الصفحة غير موجودة' : 'Page not found');
    render_v2_nav($locale);
    ?><main class="page-hero"><div class="v2-shell"><div class="section-label"><span>404</span><?= $locale === 'ar' ? 'غير موجود' : 'Not found' ?></div><h1><?= $locale === 'ar' ? 'لم نعثر على هذه الصفحة.' : 'We could not find this page.' ?></h1></div></main><?php
    render_v2_footer($locale);
    exit;
}

render_v2_head($locale, $page['seo_title'] ?: $page['title'], $page['seo_description'] ?: $page['excerpt'], $slug);
render_v2_nav($locale);
?>
<main>
  <header class="page-hero" data-edit-key="page-title-<?= (int) $page['id'] ?>"><div class="v2-shell"><div class="section-label"><span><?= $page['is_blog'] ? 'NEWS' : 'PAGE' ?></span><?= h($page['template']) ?></div><h1><?= h($page['title']) ?></h1><?php if ($page['excerpt']): ?><p><?= h($page['excerpt']) ?></p><?php endif; ?></div></header>
  <?php foreach (cms_page_blocks((int) $page['id'], $locale) as $block) render_cms_block($block, $locale, $editing); ?>
</main>
<?php render_v2_footer($locale); ?>
