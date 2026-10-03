<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('editor');
$locale = ($_GET['lang'] ?? 'ar') === 'en' ? 'en' : 'ar';

$targets = [
    'home' => ['title' => 'الصفحة الرئيسية · Landing', 'url' => 'index.php'],
    'about' => ['title' => 'من نحن · قصة الحياة', 'url' => 'about.php'],
    'partners' => ['title' => 'الشركاء', 'url' => 'partners.php'],
    'quality' => ['title' => 'الجودة والامتثال', 'url' => 'quality.php'],
    'blog' => ['title' => 'الأخبار والمستجدات', 'url' => 'blog.php'],
    'messages' => ['title' => 'المراسلات والحجوزات', 'url' => 'messages.php'],
    'service-regulatory-affairs' => ['title' => 'الخدمة · التسجيل الدوائي', 'url' => 'service.php?slug=regulatory-affairs'],
    'service-warehousing' => ['title' => 'الخدمة · المخازن والتبريد', 'url' => 'service.php?slug=warehousing'],
    'service-distribution' => ['title' => 'الخدمة · التوزيع الوطني', 'url' => 'service.php?slug=distribution'],
    'service-customer-relations' => ['title' => 'الخدمة · علاقات العملاء', 'url' => 'service.php?slug=customer-relations'],
    'service-pharmacovigilance' => ['title' => 'الخدمة · اليقظة الدوائية', 'url' => 'service.php?slug=pharmacovigilance'],
    'service-compliance' => ['title' => 'الخدمة · الامتثال والجودة', 'url' => 'service.php?slug=compliance'],
];

$cmsPages = db()->query("SELECT p.slug, COALESCE(t.title,p.slug) title FROM pages p LEFT JOIN page_translations t ON t.page_id=p.id AND t.locale='ar' WHERE p.status!='archived' ORDER BY p.updated_at DESC")->fetchAll();
foreach ($cmsPages as $page) {
    $slug = clean_slug($page['slug'] ?? '');
    if ($slug !== '' && !isset($targets[$slug])) $targets[$slug] = ['title' => 'صفحة المحتوى · ' . $page['title'], 'url' => 'page.php?slug=' . rawurlencode($slug)];
}

$scope = clean_slug($_GET['scope'] ?? 'home') ?: 'home';
if (!isset($targets[$scope])) $scope = 'home';
$target = $targets[$scope];
$separator = str_contains($target['url'], '?') ? '&' : '?';
$preview = site_url($target['url'] . $separator . 'lang=' . $locale . '&hyt_edit=1');

render_admin_start($admin, 'المحرر البصري الشامل', 'editor');
?>
<div class="admin-heading">
  <div><span>UNIVERSAL CLICK-TO-EDIT</span><h1>اضغط على أي عنصر وعدّله</h1><p>النصوص والصور والروابط والأزرار والبطاقات والأقسام قابلة للاختيار، مع خصائص آمنة للحجم واللون والمسافات والموضع.</p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a class="admin-secondary" href="<?= h(admin_url('editor.php?scope=' . $scope . '&lang=' . ($locale === 'ar' ? 'en' : 'ar'))) ?>"><?= $locale === 'ar' ? 'English preview' : 'المعاينة العربية' ?></a>
    <select onchange="location.href=this.value" style="border:1px solid #dce6df;border-radius:999px;padding:8px 12px;max-width:280px">
      <?php foreach ($targets as $targetScope => $item): ?><option value="<?= h(admin_url('editor.php?scope=' . $targetScope . '&lang=' . $locale)) ?>" <?= $scope === $targetScope ? 'selected' : '' ?>><?= h($item['title']) ?></option><?php endforeach; ?>
    </select>
  </div>
</div>
<section class="visual-editor">
  <div class="visual-frame"><iframe data-visual-frame src="<?= h($preview) ?>" title="معاينة الموقع القابلة للتحرير"></iframe></div>
  <aside class="visual-panel">
    <h2>خصائص العنصر</h2>
    <p class="visual-empty" data-visual-empty>مرّر المؤشر داخل المعاينة، ثم اضغط العنصر المطلوب. سيبقى العنصر المحدد بإطار أخضر.</p>
    <form class="admin-form" data-visual-form hidden method="post" action="<?= h(admin_url('api.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_visual">
      <input type="hidden" name="response_format" value="json">
      <input type="hidden" name="page_scope" value="<?= h($scope) ?>">
      <input type="hidden" name="element_key">
      <input type="hidden" name="element_tag">
      <input type="hidden" name="locale" value="<?= h($locale) ?>">
      <p class="admin-notice full" data-visual-selection></p>
      <label class="admin-field full" data-visual-text-field><span>النص</span><textarea name="content_value" rows="5" maxlength="5000"></textarea></label>
      <label class="admin-field full" data-visual-link-field hidden><span>رابط الزر أو الرابط</span><input name="link_url" maxlength="500" dir="ltr" placeholder="https://... أو page.php"></label>
      <label class="admin-field full" data-visual-media-field hidden><span>مسار الصورة أو الفيديو</span><input name="media_url" maxlength="500" dir="ltr" placeholder="assets/images/example.jpg"></label>
      <label class="admin-field full" data-visual-alt-field hidden><span>النص البديل للصورة</span><input name="alt_text" maxlength="300"></label>

      <label class="admin-field"><span>لون النص</span><input name="color" type="color" value="#10231a"></label>
      <label class="admin-field"><span>لون الخلفية</span><input name="background_color" type="color" value="#ffffff"><small style="display:block;margin-top:7px"><input name="use_background" type="checkbox" value="1"> تطبيق اللون على الخلفية</small></label>
      <label class="admin-field"><span>حجم الخط px</span><input name="font_size" type="number" min="8" max="160" value="16"></label>
      <label class="admin-field"><span>سماكة الخط</span><select name="font_weight"><option value="300">300</option><option value="400" selected>400</option><option value="500">500</option><option value="600">600</option><option value="700">700</option><option value="800">800</option></select></label>
      <label class="admin-field"><span>ارتفاع السطر</span><input name="line_height" type="number" min="0.8" max="3" step="0.1" value="1.5"></label>
      <label class="admin-field"><span>المحاذاة</span><select name="text_align"><option value="start">البداية</option><option value="center">الوسط</option><option value="end">النهاية</option><option value="left">يسار</option><option value="right">يمين</option></select></label>
      <label class="admin-field"><span>العرض</span><input name="width" value="" dir="ltr" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>أقصى عرض</span><input name="max_width" value="" dir="ltr" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>الارتفاع</span><input name="height" value="" dir="ltr" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>المسافة الداخلية px</span><input name="padding" type="number" min="0" max="160" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>هامش علوي px</span><input name="margin_top" type="number" min="-100" max="240" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>هامش سفلي px</span><input name="margin_bottom" type="number" min="-100" max="240" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>استدارة الحواف px</span><input name="border_radius" type="number" min="0" max="120" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>سمك الإطار px</span><input name="border_width" type="number" min="0" max="12" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>لون الإطار</span><input name="border_color" type="color" value="#dce7df"></label>
      <label class="admin-field"><span>شفافية العنصر</span><input name="opacity" type="number" min="0" max="1" step="0.05" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>ملاءمة الصورة</span><select name="object_fit"><option value="">بدون تغيير</option><option value="cover">Cover</option><option value="contain">Contain</option><option value="fill">Fill</option><option value="scale-down">Scale down</option></select></label>
      <label class="admin-field"><span>طريقة العرض</span><select name="display"><option value="">بدون تغيير</option><option value="block">Block</option><option value="inline-block">Inline block</option><option value="flex">Flex</option><option value="grid">Grid</option><option value="none">إخفاء</option></select></label>
      <label class="admin-field"><span>تحريك أفقي px</span><input name="translate_x" type="number" min="-300" max="300" value="" placeholder="بدون تغيير"></label>
      <label class="admin-field"><span>تحريك عمودي px</span><input name="translate_y" type="number" min="-300" max="300" value="" placeholder="بدون تغيير"></label>
      <div class="admin-form-actions"><button class="admin-primary" type="submit">حفظ وتحديث المعاينة</button><p data-visual-status style="font-size:.68rem;color:#0f6b48"></p></div>
    </form>
  </aside>
</section>
<?php render_admin_end(); ?>
