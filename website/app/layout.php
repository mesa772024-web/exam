<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/slots.php';

function lang_url(string $to): string
{
    $qs = $_GET;
    $qs['lang'] = $to;
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    return $path . '?' . http_build_query($qs);
}

/** أرقام عربية-هندية للواجهة العربية */
function num_l(string $n): string
{
    return lang() === 'ar' ? strtr($n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']) : $n;
}

/** نجمة ثمانية (عنصر الهوية) — SVG مضمّن */
function csr_star(string $cls = '', bool $inner = true): string
{
    $o = '50.0,4.0 63.5,17.5 82.5,17.5 82.5,36.5 96.0,50.0 82.5,63.5 82.5,82.5 63.5,82.5 50.0,96.0 36.5,82.5 17.5,82.5 17.5,63.5 4.0,50.0 17.5,36.5 17.5,17.5 36.5,17.5';
    $i = '50.0,21.0 58.5,29.5 70.5,29.5 70.5,41.5 79.0,50.0 70.5,58.5 70.5,70.5 58.5,70.5 50.0,79.0 41.5,70.5 29.5,70.5 29.5,58.5 21.0,50.0 29.5,41.5 29.5,29.5 41.5,29.5';
    return '<svg class="' . e($cls) . '" viewBox="0 0 100 100" aria-hidden="true"><polygon class="st-o" points="' . $o . '"/>'
        . ($inner ? '<polygon class="st-i" points="' . $i . '"/><polygon class="st-c" points="50,43 57,50 50,57 43,50"/>' : '') . '</svg>';
}

function site_header(string $pageTitle = ''): void
{
    $L = lang();
    $rtl = is_rtl();
    $editing = scf_edit_mode();
    if ($editing) ob_start('scf_edit_postprocess');
    $siteName = setting_l('site_name');
    $slogan = setting_l('tagline');
    $title = ($pageTitle !== '' ? $pageTitle . ' | ' : '') . $siteName;
    $nav = [
        ['#about', tt('عن المنتدى', 'About')],
        ['#objectives', tt('الأهداف', 'Objectives')],
        ['#participants', tt('المشاركون', 'Participants')],
        ['#agenda', tt('البرنامج', 'Programme')],
        ['#partnership', tt('الشراكات', 'Partnership')],
        ['#partners', tt('الجهات', 'Partners')],
    ];
    $regOpen = setting('reg_open', '1') === '1';
    $home = base_url() . '/';
    $isHome = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'index.php';
    $prefix = $isHome ? '' : $home;
    $navHref = function (string $t) use ($home, $prefix) {
        if ($t[0] === '@') return $home . substr($t, 1);
        return $prefix . $t;
    };
?>
<!DOCTYPE html>
<html lang="<?= $L ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($slogan) ?>">
<meta name="theme-color" content="#06241B">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($slogan) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= e(slot_url('hero_photo')) ?>">
<link rel="icon" type="image/svg+xml" href="<?= e(asset('img/csr-mark.svg')) ?>">
<link rel="preload" href="<?= e(asset('fonts/thmanyah/thm-serif-bold.woff2')) ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>?v=6">
<link rel="stylesheet" href="<?= e(asset('css/recap.css')) ?>?v=20260914e">
<link rel="stylesheet" href="<?= e(asset('css/csr.css')) ?>?v=20261004">
<?php if ($editing): ?><link rel="stylesheet" href="<?= e(asset('css/site-editor.css')) ?>?v=20260914c"><?php endif; ?>
<noscript><style>[data-reveal],[data-split] .ln-in,.c-loader{opacity:1!important;transform:none!important;clip-path:none!important}.c-loader{display:none}</style></noscript>
</head>
<body class="csr <?= $rtl ? 'rtl' : 'ltr' ?><?= $isHome ? ' is-home' : ' is-inner' ?><?= $editing ? ' scf-editing' : '' ?>">
<a class="skip-link" href="#main"><?= e(tt('تخطي إلى المحتوى', 'Skip to content')) ?></a>
<?php if ($isHome && !$editing): ?>
<div class="c-loader" id="cLoader" aria-hidden="true">
  <div class="c-loader-in"><?= csr_star('c-loader-star') ?><span class="c-loader-txt"><?= e(setting_l('edition')) ?></span></div>
</div>
<?php endif; ?>

<header class="c-head" id="cHead">
  <div class="c-wrap c-head-in">
    <a class="c-brand" href="<?= e($home) ?>" aria-label="<?= e($siteName) ?>">
      <img src="<?= e(slot_url('brand_logo')) ?>"<?= slot_attr('brand_logo') ?> alt="" class="c-brand-mark" width="40" height="40">
      <span class="c-brand-txt"><b><?= e(tt('منتدى المسؤولية الاجتماعية', 'Iraqi CSR & Business')) ?></b><i><?= e(tt('واستدامة الأعمال العراقي', 'Integrity Forum')) ?></i></span>
    </a>
    <nav class="c-nav" aria-label="<?= e(tt('القائمة الرئيسية', 'Main')) ?>">
      <?php foreach ($nav as $n): ?><a href="<?= e($navHref($n[0])) ?>"><?= e($n[1]) ?></a><?php endforeach; ?>
      <?php foreach (get_pages_nav() as $p): ?><a href="<?= e($home . 'page.php?s=' . urlencode($p['slug'])) ?>"><?= e(bl($p, 'title')) ?></a><?php endforeach; ?>
    </nav>
    <div class="c-head-act">
      <a class="c-lang" href="<?= e(lang_url($rtl ? 'en' : 'ar')) ?>" rel="nofollow" lang="<?= $rtl ? 'en' : 'ar' ?>"><?= $rtl ? 'EN' : 'ع' ?></a>
      <?php if ($regOpen): ?><a class="c-btn c-btn--gold c-btn--sm" href="<?= e($home . 'register.php') ?>" data-magnetic><span><?= e(tt('سجّل الآن', 'Register')) ?></span></a><?php endif; ?>
      <button class="c-burger" id="cBurger" aria-label="<?= e(tt('القائمة', 'Menu')) ?>" aria-controls="cMenu" aria-expanded="false"><span></span><span></span></button>
    </div>
  </div>
</header>

<div class="c-menu" id="cMenu" aria-hidden="true">
  <div class="c-menu-bg" aria-hidden="true"><?= csr_star('c-menu-star', false) ?></div>
  <nav class="c-menu-nav">
    <?php foreach ($nav as $i => $n): ?><a href="<?= e($navHref($n[0])) ?>" style="--i:<?= $i ?>"><em><?= num_l(str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT)) ?></em><?= e($n[1]) ?></a><?php endforeach; ?>
    <?php if ($regOpen): ?><a class="c-menu-cta" href="<?= e($home . 'register.php') ?>" style="--i:<?= count($nav) ?>"><?= e(tt('سجّل حضورك', 'Register now')) ?></a><?php endif; ?>
  </nav>
  <div class="c-menu-foot"><span><?= e(setting_l('event_dates')) ?></span><span><?= e(setting_l('venue')) ?></span></div>
</div>
<main id="main">
<?php
}

function site_footer(): void
{
    $rtl = is_rtl();
    $home = base_url() . '/';
    $phones = json_decode(setting('contact_phones', '[]'), true) ?: [];
    $email = setting('contact_email');
    $socials = array_filter(['Facebook' => setting('social_facebook'), 'Instagram' => setting('social_instagram'), 'LinkedIn' => setting('social_linkedin')]);
?>
</main>
<footer class="c-foot" id="contact">
  <div class="c-foot-pattern" aria-hidden="true"></div>
  <div class="c-wrap">
    <div class="c-foot-top">
      <div class="c-foot-brand">
        <img src="<?= e(slot_url('logo_white')) ?>"<?= slot_attr('logo_white') ?> alt="" width="64" height="64">
        <b><?= e(setting_l('site_name')) ?></b>
        <p><?= e(setting_l('tagline')) ?></p>
      </div>
      <div class="c-foot-col">
        <h3><?= e(tt('موعد الانعقاد', 'When & where')) ?></h3>
        <p><?= e(setting_l('event_dates')) ?></p>
        <p><?= e(setting_l('venue')) ?></p>
      </div>
      <div class="c-foot-col">
        <h3><?= e(tt('تواصل معنا', 'Contact')) ?></h3>
        <?php foreach ($phones as $ph): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $ph)) ?>" dir="ltr"><?= e($ph) ?></a><?php endforeach; ?>
        <?php if ($email): ?><a href="mailto:<?= e($email) ?>" dir="ltr"><?= e($email) ?></a><?php endif; ?>
        <?php if (!$phones && !$email): ?><p><?= e(tt('اللجنة المنظمة للمنتدى', 'The forum’s organising committee')) ?></p><?php endif; ?>
        <?php if ($socials): ?><div class="c-foot-soc"><?php foreach ($socials as $n => $u): ?><a href="<?= e($u) ?>" target="_blank" rel="noopener"><?= e($n) ?></a><?php endforeach; ?></div><?php endif; ?>
      </div>
      <?php if (setting('reg_open', '1') === '1'): ?>
      <div class="c-foot-col c-foot-cta">
        <h3><?= e(tt('التسجيل مفتوح', 'Registration is open')) ?></h3>
        <a class="c-btn c-btn--gold" href="<?= e($home . 'register.php') ?>" data-magnetic><span><?= e(tt('سجّل حضورك الآن', 'Register now')) ?></span></a>
      </div>
      <?php endif; ?>
    </div>
    <?php if (setting_l('footer_note') !== ''): ?><p class="c-foot-note"><?= e(setting_l('footer_note')) ?></p><?php endif; ?>
    <div class="c-foot-bottom">
      <span>© <?= num_l(date('Y')) ?> <?= e(setting_l('site_name')) ?></span>
      <a href="<?= e(lang_url($rtl ? 'en' : 'ar')) ?>" rel="nofollow"><?= $rtl ? 'English' : 'العربية' ?></a>
    </div>
  </div>
</footer>
<script src="<?= e(asset('js/site.js')) ?>?v=20260913"></script>
<script src="<?= e(asset('js/recap.js')) ?>?v=20260914c"></script>
<script src="<?= e(asset('js/csr.js')) ?>?v=20261004"></script>
<?php if (scf_edit_mode()): ?>
<div class="se-bar" id="seBar" dir="rtl">
  <span class="se-badge"><?= icon('edit') ?> وضع التحرير</span>
  <span class="se-lang">تحرير النص: <b><?= $rtl ? 'العربي' : 'الإنكليزي' ?></b></span>
  <span class="se-count" id="seCount">لا تغييرات</span>
  <button type="button" class="se-btn se-save" id="seSave" disabled><?= icon('save') ?> حفظ</button>
  <button type="button" class="se-btn" id="seUndo" disabled>تراجع</button>
  <a class="se-btn" href="<?= e(lang_url($rtl ? 'en' : 'ar')) ?>" data-se-nav><?= icon('globe') ?> <?= $rtl ? 'تحرير الإنكليزي' : 'تحرير العربي' ?></a>
  <button type="button" class="se-btn" id="seSections" aria-pressed="false"><?= icon('layout') ?> ترتيب الأقسام</button>
  <a class="se-btn" href="<?= e(base_url() . '/' . ADMIN_DIR . '/dashboard.php') ?>" data-se-nav><?= icon('grid') ?> لوحة التحكم</a>
  <a class="se-btn se-exit" href="?edit=0" data-se-nav><?= icon('close') ?> خروج</a>
  <button type="button" class="se-btn se-help" id="seHelp" aria-label="مساعدة">؟</button>
</div>
<div class="se-help-box" id="seHelpBox" hidden dir="rtl">
  <b>كيف أعدّل؟</b>
  <ul>
    <li>مرّر على أي نص ← يظهر إطار متقطّع ← اضغط واكتب مباشرة.</li>
    <li>Enter لإنهاء التعديل · Esc لإلغاء تعديل هذا النص.</li>
    <li>زر «تحرير الإنكليزي» يفتح الصفحة نفسها لتعديل النصوص الإنكليزية.</li>
    <li>مرّر على الصور أو الفيديو ← «استبدال» لرفع بديل.</li>
    <li>«ترتيب الأقسام» يعرض أزرار التقديم والتأخير والإخفاء لكل قسم.</li>
    <li>المنشورات: زر «تعديل النص الكامل والصور» تحت عنوان كل منشور.</li>
  </ul>
</div>
<script>window.SCF_EDIT = <?= json_encode([
    'csrf' => csrf_token(),
    'admin' => base_url() . '/' . ADMIN_DIR,
    'lang' => lang(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= e(asset('js/site-editor.js')) ?>?v=20260914c"></script>
<?php elseif (!empty($_SESSION['admin_id']) && ($_SESSION['admin_role'] ?? 'super') === 'super'): ?>
<a class="se-fab" href="?edit=1" dir="rtl"><?= icon('edit') ?> تعديل الموقع مباشرة</a>
<style>.se-fab{position:fixed;bottom:22px;left:22px;z-index:9000;display:inline-flex;align-items:center;gap:8px;background:#06241B;color:#fff;padding:12px 18px;border-radius:999px;font-weight:700;font-size:14px;box-shadow:0 14px 34px -10px rgba(38,38,94,.6)}.se-fab .ic{width:17px;height:17px}.se-fab:hover{background:#12664A}</style>
<?php endif; ?>
</body>
</html>
<?php
}
