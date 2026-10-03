<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require APP_ROOT . '/includes/v2-layout.php';
$locale = current_locale();
$isAr = $locale === 'ar';
$statement = db()->prepare("SELECT p.slug, p.published_at, t.title, t.excerpt FROM pages p JOIN page_translations t ON t.page_id = p.id AND t.locale = :locale WHERE p.status = 'published' AND p.is_blog = 1 ORDER BY COALESCE(p.published_at, p.created_at) DESC");
$statement->execute(['locale' => $locale]);
$posts = $statement->fetchAll();
render_v2_head($locale, $isAr ? 'المستجدات' : 'Insights', $isAr ? 'أخبار ورؤى مكتب الحياة العلمي.' : 'News and insights from Al Hayat Scientific Office.', 'blog');
render_v2_nav($locale);
?>
<main><header class="page-hero"><div class="v2-shell"><div class="section-label"><span>NEWS</span><?= $isAr ? 'المستجدات' : 'Insights' ?></div><h1><?= $isAr ? 'ما الجديد في مكتب الحياة؟' : "What's new at Al Hayat?" ?></h1><p><?= $isAr ? 'مقالات وأخبار وتحديثات تُنشر مباشرة من نظام المحتوى.' : 'Articles, news and updates published directly from the content system.' ?></p></div></header>
<section class="blog-grid">
<?php if (!$posts): ?><article class="blog-card"><time><?= date('Y-m-d') ?></time><h2><?= $isAr ? 'قسم المستجدات جاهز للنشر' : 'The insights section is ready' ?></h2><p><?= $isAr ? 'يمكن لفريق الإدارة إنشاء المقال الأول باللغتين من لوحة المحتوى.' : 'The administration team can publish the first bilingual article from the content panel.' ?></p></article><?php endif; ?>
<?php foreach ($posts as $post): ?><a class="blog-card" href="<?= h(site_url('page.php?slug=' . rawurlencode($post['slug']) . '&lang=' . $locale)) ?>"><time><?= h(substr((string) $post['published_at'], 0, 10)) ?></time><h2><?= h($post['title']) ?></h2><p><?= h($post['excerpt']) ?></p><span><?= $isAr ? 'اقرأ المقال ↗' : 'Read article ↗' ?></span></a><?php endforeach; ?>
</section></main>
<?php render_v2_footer($locale); ?>
