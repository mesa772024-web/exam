<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('editor');

$textFields = landing_text_fields();
$mediaFields = landing_media_fields();

// Group text fields by their group label, preserving order.
$grouped = [];
foreach ($textFields as $key => $field) {
    $grouped[$field[0]][$key] = $field;
}

render_admin_start($admin, 'الصفحة الرئيسية', 'landing');
?>
<div class="admin-heading">
  <div><span>LANDING PAGE CONTENT</span><h1>تحرير الصفحة الرئيسية</h1><p>عدّل نصوص الواجهة (عربي/إنجليزي) والفيديوهات والصور. كل تعديل يظهر مباشرة على الموقع فور الحفظ.</p></div>
  <a class="admin-primary" target="_blank" rel="noopener" href="<?= h(site_url('index.php')) ?>">معاينة الموقع ↗</a>
</div>

<?php if (isset($_GET['ok'])): ?><div class="admin-note admin-note-ok" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#f5e7ee;color:#691237;font-size:.82rem">تم الحفظ بنجاح.</div><?php endif; ?>
<?php if (isset($_GET['error'])): ?><div class="admin-note admin-note-err" style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#fbeef0;color:#9c2b3e;font-size:.82rem"><?= h((string) $_GET['error']) ?></div><?php endif; ?>

<!-- ===== TEXT ===== -->
<form class="admin-form" method="post" action="<?= h(admin_url('api.php')) ?>" style="display:block">
  <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
  <input type="hidden" name="action" value="save_landing">
  <input type="hidden" name="fallback" value="landing.php">
  <?php foreach ($grouped as $groupName => $fields): ?>
  <section class="admin-card full" style="margin-bottom:18px">
    <div class="card-title"><div><span>SECTION</span><h2><?= h($groupName) ?></h2></div><em><?= count($fields) ?> حقل</em></div>
    <?php foreach ($fields as $key => $field):
        $labelAr = $field[1];
        $valueAr = lc($key, 'ar');
        $valueEn = lc($key, 'en');
    ?>
    <div class="admin-field full" style="border-top:1px solid var(--admin-line,#ede3e8);padding-top:14px;margin-top:6px">
      <span style="font-weight:700"><?= h($labelAr) ?> <small style="color:#a18a94;font-weight:400">(<?= h($key) ?>)</small></span>
    </div>
    <label class="admin-field"><span>النص العربي</span><textarea name="text_<?= h($key) ?>_ar" rows="2"><?= h($valueAr) ?></textarea></label>
    <label class="admin-field"><span>English text</span><textarea name="text_<?= h($key) ?>_en" rows="2" dir="ltr"><?= h($valueEn) ?></textarea></label>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
  <div class="admin-form-actions" style="position:sticky;bottom:0;background:#fff;padding:14px 0;z-index:5"><button class="admin-primary" type="submit">حفظ كل النصوص</button></div>
</form>

<!-- ===== MEDIA ===== -->
<section class="admin-card full" style="margin-top:8px">
  <div class="card-title"><div><span>MEDIA</span><h2>الفيديوهات والصور</h2></div><em><?= count($mediaFields) ?> عنصر</em></div>
  <p style="font-size:.78rem;color:#7a6870;margin:0 0 16px">ارفع ملفاً جديداً، أو الصق مساراً/رابطاً مباشراً. الفيديوهات بصيغة MP4/WebM حتى 40MB، والصور JPG/PNG/WebP حتى 10MB.</p>
  <div class="landing-media-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">
    <?php foreach ($mediaFields as $key => $field):
        [$label, $default, $type, $hint] = $field;
        $current = lc_media($key);
    ?>
    <form class="admin-form landing-media-card" method="post" enctype="multipart/form-data" action="<?= h(admin_url('api.php')) ?>" style="border:1px solid #ede3e8;border-radius:16px;padding:16px;display:block">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_landing_media">
      <input type="hidden" name="fallback" value="landing.php">
      <input type="hidden" name="media_key" value="<?= h($key) ?>">
      <div style="font-weight:700;font-size:.9rem;margin-bottom:4px"><?= h($label) ?></div>
      <div style="font-size:.72rem;color:#a18a94;margin-bottom:10px"><?= h($hint) ?></div>
      <div class="media-preview" style="margin-bottom:10px;border-radius:12px;overflow:hidden;background:#f9f3f6;min-height:110px;display:grid;place-items:center">
        <?php if ($type === 'image'): ?>
          <img src="<?= h(site_url($current)) ?>" alt="" style="max-height:150px;object-fit:contain">
        <?php else: ?>
          <video src="<?= h(site_url($current)) ?>" muted playsinline style="max-height:150px;max-width:100%" controls preload="metadata"></video>
        <?php endif; ?>
      </div>
      <div dir="ltr" style="font-size:.68rem;color:#a18a94;word-break:break-all;margin-bottom:10px"><?= h($current) ?></div>
      <label class="admin-field full"><span>رفع ملف جديد</span><input name="media_file" type="file" accept="<?= $type === 'image' ? 'image/jpeg,image/png,image/webp,image/gif' : 'video/mp4,video/webm' ?>"></label>
      <label class="admin-field full"><span>أو مسار/رابط مباشر</span><input name="media_path" value="<?= h($current) ?>" dir="ltr" placeholder="assets/... أو https://..."></label>
      <div class="admin-form-actions"><button class="admin-primary" type="submit">حفظ</button></div>
    </form>
    <?php endforeach; ?>
  </div>
</section>

<section class="admin-card full" style="margin-top:18px">
  <div class="card-title"><div><span>PARTNERS</span><h2>شعارات الشركاء</h2></div><em><?= count(content_data('partners')) ?> شريك</em></div>
  <p style="font-size:.88rem;color:#7a6870;margin:0 0 14px">أضف الشعارات أو بدّلها أو أرشفها أو احذفها ورتّبها من صفحة الإعدادات.</p><a class="admin-secondary" href="<?= h(admin_url('appearance.php#partners')) ?>">إدارة شعارات الشركاء</a>
</section>
<?php render_admin_end(); ?>
