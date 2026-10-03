<?php
declare(strict_types=1);

function render_v2_head(string $locale, string $title = '', string $description = '', string $scope = 'site'): void
{
    $isAr = $locale === 'ar';
    $siteName = setting($isAr ? 'siteNameAr' : 'siteNameEn', $isAr ? 'مكتب بغداد الحياة العلمي' : 'Baghdad Al Hayat Scientific Office');
    $pageTitle = $title !== '' ? $title . ' | ' . $siteName : $siteName;
    $description = $description !== '' ? $description : ($isAr ? 'مكتب بغداد الحياة العلمي — منظومة متكاملة لتسجيل وتخزين وتوزيع الدواء في العراق.' : 'Baghdad Al Hayat Scientific Office — an integrated system for pharmaceutical registration, storage and distribution in Iraq.');
    ?>
<!doctype html>
<html lang="<?= h($locale) ?>" dir="<?= $isAr ? 'rtl' : 'ltr' ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#ffffff">
  <meta name="description" content="<?= h($description) ?>">
  <meta property="og:title" content="<?= h($pageTitle) ?>">
  <meta property="og:description" content="<?= h($description) ?>">
  <meta property="og:type" content="website">
  <meta property="og:image" content="<?= h(site_url('assets/og.png')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= h(site_url('assets/og.png')) ?>">
  <title><?= h($pageTitle) ?></title>
  <link rel="icon" href="<?= h(site_url('assets/logo.png')) ?>">
  <link rel="stylesheet" href="<?= h(site_url('assets/css/site.css')) ?>">
  <link rel="stylesheet" href="<?= h(site_url('assets/css/landing-fonts.css')) ?>">
  <link rel="stylesheet" href="<?= h(site_url('assets/css/v2.css')) ?>">
  <link rel="stylesheet" href="<?= h(site_url('assets/css/landing-pages.css?v=3.0.0')) ?>">
  <style>:root{--brand:<?= h(setting('primaryColor','#7a1440')) ?>;--accent:<?= h(setting('accentColor','#b5344d')) ?>;--soft:<?= h(setting('surfaceColor','#f7f2f5')) ?>}</style>
  <?php $customStyles = element_styles_css($scope); if ($customStyles !== ''): ?><style><?= $customStyles ?></style><?php endif; ?>
</head>
<body class="v2-body landing-inner-page" data-lang="<?= h($locale) ?>" data-page-scope="<?= h($scope) ?>" data-customization-api="<?= h(site_url('api/customizations.php')) ?>">
<?php
}

function render_v2_nav(string $locale, bool $transparent = false): void
{
    $isAr = $locale === 'ar';
    $other = $isAr ? 'en' : 'ar';
    $query = $_GET;
    $query['lang'] = $other;
    $languageUrl = basename((string) ($_SERVER['PHP_SELF'] ?? 'index.php')) . '?' . http_build_query($query);
    $aboutLinks = [
        ['about.php?lang=' . $locale . '#story', $isAr ? 'قصة بغداد الحياة' : 'Our story'],
        ['about.php?lang=' . $locale . '#who', $isAr ? 'من نحن' : 'Who we are'],
        ['about.php?lang=' . $locale . '#scale', $isAr ? 'قدراتنا اليوم' : 'Our scale'],
        ['about.php?lang=' . $locale . '#organisation', $isAr ? 'الهيكل التنظيمي' : 'Organisation'],
        ['about.php?lang=' . $locale . '#leadership', $isAr ? 'القيادة' : 'Leadership'],
        ['about.php?lang=' . $locale . '#why', $isAr ? 'لماذا بغداد الحياة؟' : 'Why Baghdad Al Hayat'],
        ['about.php?lang=' . $locale . '#milestones', $isAr ? 'محطاتنا' : 'Milestones'],
        ['about.php?lang=' . $locale . '#team', $isAr ? 'فريقنا' : 'Our team'],
    ];
    $serviceLinks = [
        ['services.php?lang=' . $locale . '#regulatory-affairs', $isAr ? 'التسجيل الدوائي' : 'Registration'],
        ['services.php?lang=' . $locale . '#warehousing', $isAr ? 'المخازن والتبريد' : 'Warehousing'],
        ['services.php?lang=' . $locale . '#distribution', $isAr ? 'التوزيع الوطني' : 'Distribution'],
        ['services.php?lang=' . $locale . '#customer-relations', $isAr ? 'علاقات العملاء' : 'Customer relations'],
        ['services.php?lang=' . $locale . '#pharmacovigilance', $isAr ? 'اليقظة الدوائية' : 'Pharmacovigilance'],
        ['services.php?lang=' . $locale . '#compliance', $isAr ? 'الامتثال والجودة' : 'Compliance'],
    ];
    ?>
<nav class="nav internal-nav<?= $transparent ? ' is-transparent' : '' ?>" data-main-nav aria-label="<?= $isAr ? 'التنقل الرئيسي' : 'Main navigation' ?>">
  <div class="nav-inner">
    <a class="nav-logo" href="<?= h(site_url('index.php?lang=' . $locale)) ?>" aria-label="<?= h(setting($isAr ? 'siteNameAr' : 'siteNameEn')) ?>"><img src="<?= h(site_url(setting('logoPath', 'assets/logo.png'))) ?>" alt="<?= h(setting($isAr ? 'siteNameAr' : 'siteNameEn')) ?>"></a>
    <div class="nav-links" data-desktop-nav>
      <a href="<?= h(site_url('index.php?lang=' . $locale)) ?>"><?= $isAr ? 'الرئيسية' : 'Home' ?></a>
      <div class="nav-group"><a href="<?= h(site_url('about.php?lang=' . $locale)) ?>"><?= $isAr ? 'من نحن' : 'About' ?> <small>⌄</small></a><div class="nav-dropdown"><?php foreach ($aboutLinks as [$path, $label]): ?><a href="<?= h(site_url($path)) ?>"><?= h($label) ?></a><?php endforeach; ?></div></div>
      <div class="nav-group"><a href="<?= h(site_url('services.php?lang=' . $locale)) ?>"><?= $isAr ? 'الخدمات' : 'Services' ?> <small>⌄</small></a><div class="nav-dropdown nav-dropdown-wide"><?php foreach ($serviceLinks as [$path, $label]): ?><a href="<?= h(site_url($path)) ?>"><?= h($label) ?></a><?php endforeach; ?></div></div>
      <a href="<?= h(site_url('partners.php?lang=' . $locale)) ?>"><?= $isAr ? 'الشركاء' : 'Partners' ?></a>
      <a href="<?= h(site_url('quality.php?lang=' . $locale)) ?>"><?= $isAr ? 'الجودة' : 'Quality' ?></a>
      <a href="<?= h(site_url('blog.php?lang=' . $locale)) ?>"><?= $isAr ? 'الأخبار' : 'News' ?></a>
      <a href="<?= h(site_url('messages.php?lang=' . $locale)) ?>"><?= $isAr ? 'تواصل' : 'Speak Up' ?></a>
    </div>
    <div class="nav-actions">
      <?php if (language_switching_enabled()): ?><a class="lang" href="<?= h($languageUrl) ?>" aria-label="<?= $isAr ? 'English' : 'العربية' ?>"><?= $isAr ? 'EN' : 'ع' ?></a><?php endif; ?>
      <button class="menu-btn" type="button" data-menu-button aria-label="<?= $isAr ? 'فتح القائمة' : 'Open menu' ?>" aria-expanded="false"><span></span></button>
    </div>
  </div>
</nav>
<div class="mobile-menu" data-mobile-menu aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?= $isAr ? 'قائمة التنقل' : 'Navigation menu' ?>">
  <div class="mobile-menu-top">
    <a class="mobile-menu-logo" href="<?= h(site_url('index.php?lang=' . $locale)) ?>"><img src="<?= h(site_url(setting('logoPath', 'assets/logo.png'))) ?>" alt="<?= h(setting($isAr ? 'siteNameAr' : 'siteNameEn')) ?>"></a>
    <button class="mobile-close" type="button" data-menu-button aria-label="<?= $isAr ? 'إغلاق' : 'Close' ?>"><span aria-hidden="true"></span></button>
  </div>
  <div class="mobile-menu-content">
    <nav class="mobile-menu-primary" aria-label="<?= $isAr ? 'الصفحات الرئيسية' : 'Main pages' ?>">
      <a class="mobile-link" href="<?= h(site_url('index.php?lang=' . $locale)) ?>"><?= $isAr ? 'الرئيسية' : 'Home' ?><small>01</small></a>
      <a class="mobile-link" href="<?= h(site_url('partners.php?lang=' . $locale)) ?>"><?= $isAr ? 'الشركاء' : 'Partners' ?><small>02</small></a>
      <a class="mobile-link" href="<?= h(site_url('quality.php?lang=' . $locale)) ?>"><?= $isAr ? 'الجودة' : 'Quality' ?><small>03</small></a>
      <a class="mobile-link" href="<?= h(site_url('blog.php?lang=' . $locale)) ?>"><?= $isAr ? 'الأخبار' : 'News' ?><small>04</small></a>
      <a class="mobile-link" href="<?= h(site_url('messages.php?lang=' . $locale)) ?>"><?= $isAr ? 'تواصل معنا' : 'Speak Up' ?><small>05</small></a>
    </nav>
    <div class="mobile-menu-groups">
      <section class="mobile-menu-group">
        <div class="mobile-section-label"><span><?= $isAr ? 'من نحن' : 'About' ?></span><b>BAGHDAD AL HAYAT</b></div>
        <div class="mobile-subnav"><?php foreach ($aboutLinks as [$path, $label]): ?><a class="mobile-sublink" href="<?= h(site_url($path)) ?>"><?= h($label) ?><span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
      </section>
      <section class="mobile-menu-group">
        <div class="mobile-section-label"><span><?= $isAr ? 'خدماتنا' : 'Services' ?></span><b>06 SERVICES</b></div>
        <div class="mobile-subnav"><?php foreach ($serviceLinks as [$path, $label]): ?><a class="mobile-sublink" href="<?= h(site_url($path)) ?>"><?= h($label) ?><span aria-hidden="true">↗</span></a><?php endforeach; ?></div>
      </section>
    </div>
  </div>
  <div class="mobile-menu-footer"><span><?= $isAr ? 'بغداد · العراق · منذ 1996' : 'Baghdad · Iraq · Since 1996' ?></span><a href="<?= h(site_url('messages.php?lang=' . $locale)) ?>"><?= $isAr ? 'ابدأ شراكة' : 'Start a partnership' ?><span aria-hidden="true">↗</span></a></div>
</div>
<?php
}

function render_v2_footer(string $locale): void
{
    $isAr = $locale === 'ar';
    ?>
<footer class="landing-footer">
  <div class="landing-wrap">
    <div class="landing-footer-top">
      <div class="landing-footer-brand"><img src="<?= h(site_url(setting('logoPath', 'assets/logo.png'))) ?>" alt="<?= h(setting($isAr ? 'siteNameAr' : 'siteNameEn')) ?>"><p><?= $isAr ? 'حي بابل، محلة 929، شارع 19، بناية مكتب بغداد الحياة العلمي — بغداد، العراق.' : 'Hay Babel, District 929, St. 19, Baghdad Al Hayat Scientific Office Building — Baghdad, Iraq.' ?></p></div>
      <div class="landing-footer-col"><h3><?= $isAr ? 'استكشف' : 'Explore' ?></h3><a href="<?= h(site_url('about.php?lang=' . $locale)) ?>"><?= $isAr ? 'من نحن' : 'About' ?></a><a href="<?= h(site_url('services.php?lang=' . $locale)) ?>"><?= $isAr ? 'الخدمات' : 'Services' ?></a><a href="<?= h(site_url('quality.php?lang=' . $locale)) ?>"><?= $isAr ? 'الجودة والامتثال' : 'Quality & compliance' ?></a><a href="<?= h(site_url('partners.php?lang=' . $locale)) ?>"><?= $isAr ? 'الشركاء' : 'Partners' ?></a></div>
      <div class="landing-footer-col"><h3><?= $isAr ? 'تواصل' : 'Connect' ?></h3><a href="<?= h(site_url('messages.php?lang=' . $locale)) ?>"><?= $isAr ? 'تواصل معنا' : 'Speak Up' ?></a><a href="<?= h(site_url('blog.php?lang=' . $locale)) ?>"><?= $isAr ? 'الأخبار' : 'News' ?></a><a href="mailto:info@baghdadalhayat.com">info@baghdadalhayat.com</a><a dir="ltr" href="tel:+9647823360920">+964 782 3360 920</a></div>
    </div>
    <div class="landing-footer-bottom"><span>© <?= date('Y') ?> BAGHDAD AL HAYAT SCIENTIFIC OFFICE</span><span><?= $isAr ? 'حيث المريض أولويتنا · بغداد، العراق' : 'Where the patient is our priority · Baghdad, Iraq' ?></span></div>
  </div>
</footer>
<script src="<?= h(site_url('assets/js/v2.js?v=3.0.0')) ?>" defer></script>
</body></html>
<?php
}
