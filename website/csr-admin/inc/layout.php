<?php
require_once __DIR__ . '/auth.php';
require_once dirname(__DIR__, 2) . '/app/speakup.php';

function admin_header(string $title, string $active = ''): void
{
    $u = admin_require();
    $super = ($u['role'] ?? 'super') === 'super';
    // العنصر: [ملف, أيقونة, اسم, super-only]
    $allItems = [
        'checkin'     => ['checkin.php',     'ticket',   'قاعة المؤتمر (التسجيل)', false],
        'dashboard'   => ['dashboard.php',   'grid',     'الرئيسية',             true],
        'live'        => ['../?edit=1',      'edit',     'تعديل الموقع مباشرة ↗', true],
        'posts'       => ['posts.php',       'image',    'النسخ السابقة (المنشورات)', true],
        'texts'       => ['texts.php',       'doc',      'نصوص الواجهة',          true],
        'media'       => ['media.php',       'camera',   'الصور والفيديو',        true],
        'registrants' => ['registrants.php', 'users',    'المسجّلون',            true],
        'reports'     => ['reports.php',     'chart',    'التقارير',             true],
        'speakups'    => ['speakups.php',    'mail',     'آراء الزوار · Speak Up', true],
        'badges'      => ['badges.php',      'badge-id', 'تصميم الباجات',         true],
        'regform'     => ['regform.php',     'doc',      'نموذج التسجيل',         true],
        'sections'    => ['sections.php',    'layout',   'أقسام الموقع',         true],
        'speakers'    => ['speakers.php',    'mic',      'المتحدثون',            true],
        'orgs'        => ['orgs.php',        'handshake','الشركاء والرعاة',       true],
        'agenda'      => ['agenda.php',      'calendar', 'الأجندة',              true],
        'gallery'     => ['gallery.php',     'image',    'معرض الصور',           true],
        'pages'       => ['pages.php',       'doc',      'بناء الصفحات',         true],
        'content'     => ['content.php',     'edit',     'محتوى الموقع',         true],
        'admins'      => ['admins.php',      'users',    'حسابات المديرين',      true],
        'backups'     => ['backups.php',     'database', 'النسخ الاحتياطي',       true],
        'updates'     => ['updates.php',     'refresh',  'التحديثات والتنزيلات',  true],
        'security'    => ['security.php',    'shield',   'الأمان والمراقبة',      true],
    ];
    $items = [];
    foreach ($allItems as $k => $it) {
        if ($super || !$it[3]) $items[$k] = $it;
    }
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> — لوحة التحكم</title>
<link rel="stylesheet" href="../assets/css/fonts.css">
<link rel="stylesheet" href="assets/admin.css?v=14">
</head>
<body>
<div class="admin-shell">
<aside class="side" id="side">
  <div class="side-brand">
    <img src="../assets/img/csr-mark-white.png" alt="">
    <div><b>منتدى المسؤولية الاجتماعية</b><span>لوحة التحكم</span></div>
  </div>
  <nav class="side-nav">
    <?php foreach ($items as $key => $it): ?>
      <a href="<?= e($it[0]) ?>" class="<?= $key === $active ? 'on' : '' ?><?= $key === 'live' ? ' side-live' : '' ?>"<?= $key === 'live' ? ' target="_blank"' : '' ?>><i><?= icon($it[1]) ?></i><span><?= e($it[2]) ?></span><?php if ($key === 'speakups' && ($spN = speakup_unread_count()) > 0): ?><em style="margin-inline-start:auto;background:#28A3DB;color:#fff;border-radius:20px;padding:0 8px;font-size:11px;font-style:normal;line-height:1.7"><?= $spN ?></em><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="../" target="_blank" class="side-view"><?= icon('external') ?> عرض الموقع</a>
    <a href="logout.php" class="side-out">خروج · <?= e($u['name']) ?></a>
  </div>
</aside>
<button class="side-backdrop" id="sideBackdrop" type="button" aria-label="إغلاق القائمة" hidden></button>
<div class="main-col">
  <header class="adm-top">
    <button class="side-toggle" id="sideToggle" aria-label="القائمة"><?= icon('menu') ?></button>
    <button class="side-collapse" id="sideCollapse" aria-label="طيّ القائمة" title="طيّ/فتح القائمة"><?= icon('menu') ?></button>
    <h1><?= e($title) ?></h1>
    <div class="adm-top-actions" id="topActions"></div>
  </header>
  <main class="adm-main">
<?php
}

function admin_footer(): void
{
?>
  </main>
</div>
</div>
<div class="toast" id="toast"></div>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="../assets/js/qrcode.min.js"></script>
<script src="assets/admin.js?v=9"></script>
</body>
</html>
<?php
}
