<?php
declare(strict_types=1);

function admin_icon(string $name): string
{
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7.5" height="9" rx="1.8"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="1.8"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="1.8"/><rect x="3" y="15" width="7.5" height="6" rx="1.8"/>',
        'landing' => '<path d="M3 10.7 12 3l9 7.7"/><path d="M5.5 9.5V21h13V9.5"/><path d="M10 21v-5h4v5"/>',
        'content' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'editor' => '<path d="m4 20 4.2-1 10.4-10.4a2.1 2.1 0 0 0-3-3L5.2 16Z"/><path d="m13.8 7.4 3 3M4 20h5"/>',
        'messages' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18M7 14h2M11 14h2M15 14h2M7 17h2M11 17h2"/>',
        'appearance' => '<circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 1 0 18Z"/>',
        'maintenance' => '<path d="M20 7v5h-5M4 17v-5h5"/><path d="M18.4 9A7 7 0 0 0 6.2 6.5L4 9M5.6 15A7 7 0 0 0 17.8 17.5L20 15"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/><path d="M19 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h6"/>',
        'logout' => '<path d="M10 5H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h5M14 8l4 4-4 4M8 12h10"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? $paths['content']) . '</svg>';
}

function render_admin_start(array $admin, string $title, string $active): void
{
    $items = [
        'dashboard' => ['index.php', 'نظرة عامة', 'dashboard'],
        'landing' => ['landing.php', 'الصفحة الرئيسية', 'landing'],
        'content' => ['content.php', 'الصفحات والمحتوى', 'content'],
        'editor' => ['editor.php', 'المحرر البصري', 'editor'],
        'messages' => ['speakup.php', 'الرسائل الواردة', 'messages'],
        'calendar' => ['calendar.php', 'الحجوزات والتقويم', 'calendar'],
        'appearance' => ['appearance.php', 'الهوية والإعدادات', 'appearance'],
        'maintenance' => ['maintenance.php', 'النسخ والتحديث', 'maintenance'],
    ];
    $newMessages = 0;
    try { $newMessages = (int) db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn(); } catch (Throwable) { $newMessages = 0; }
    ?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="theme-color" content="#1f0a13"><title><?= h($title) ?> · إدارة بغداد الحياة</title><link rel="stylesheet" href="<?= h(site_url('assets/css/admin-v2.css?v=3.1.0')) ?>"></head><body class="admin-v2">
<div class="admin-shell">
  <aside class="admin-side" id="admin-navigation">
    <a class="admin-brand" href="<?= h(admin_url('index.php')) ?>"><img src="<?= h(site_url(setting('logoPath', 'assets/images/logo.png'))) ?>" alt="مكتب بغداد الحياة"><span>الإدارة · V2</span></a>
    <nav aria-label="التنقل في لوحة الإدارة"><?php foreach ($items as $key => [$href, $label, $icon]): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= h(admin_url($href)) ?>" aria-label="<?= h($label) ?>" title="<?= h($label) ?>"><i><?= admin_icon($icon) ?></i><span class="admin-nav-label"><?= h($label) ?></span><?php if ($key === 'messages' && $newMessages > 0): ?><em class="admin-nav-badge"><?= $newMessages > 99 ? '99+' : $newMessages ?></em><?php endif; ?></a><?php endforeach; ?></nav>
    <div class="admin-profile"><b><?= h(mb_substr($admin['display_name'], 0, 1)) ?></b><div><strong><?= h($admin['display_name']) ?></strong><span><?= h(role_label($admin['role'])) ?></span></div></div>
  </aside>
  <button class="admin-side-overlay" type="button" data-admin-overlay aria-label="إغلاق قائمة الإدارة"></button>
  <main class="admin-main">
    <header class="admin-top"><div><button class="admin-icon-action" type="button" data-admin-menu aria-label="فتح قائمة الإدارة" aria-expanded="false" aria-controls="admin-navigation"><?= admin_icon('menu') ?></button><p><?= h($title) ?></p><span><?= h(date('Y-m-d · H:i')) ?></span></div><div><a class="admin-icon-action" href="<?= h(site_url('index.php')) ?>" target="_blank" rel="noopener" aria-label="عرض الموقع" title="عرض الموقع"><?= admin_icon('external') ?><b class="sr-only">عرض الموقع</b></a><form method="post" action="<?= h(admin_url('logout.php')) ?>"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="admin-icon-action" type="submit" aria-label="تسجيل الخروج" title="تسجيل الخروج"><?= admin_icon('logout') ?><b class="sr-only">تسجيل الخروج</b></button></form></div></header>
    <div class="admin-content">
      <?php if (!empty($_GET['ok'])): ?><div class="admin-notice">تم حفظ التغييرات بنجاح.</div><?php endif; ?>
      <?php if (!empty($_GET['error'])): ?><div class="admin-notice error"><?= h(clean_text($_GET['error'], 240)) ?></div><?php endif; ?>
<?php
}

function render_admin_end(): void
{
    ?>
    </div>
  </main>
</div>
<script src="<?= h(site_url('assets/js/admin-v2.js?v=3.0.0')) ?>" defer></script>
</body></html>
<?php
}

function admin_metric(string $label, string|int $value, string $detail, string $tone = ''): void
{
    ?><article class="metric-card <?= h($tone) ?>"><span><?= h($label) ?></span><strong><?= h((string) $value) ?></strong><small><?= h($detail) ?></small></article><?php
}
