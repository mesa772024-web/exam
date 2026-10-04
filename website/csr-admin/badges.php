<?php
require_once __DIR__ . '/inc/layout.php';
require_super();
require_once dirname(__DIR__) . '/app/badge.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'save') {
        $tpl = json_decode((string)($_POST['template'] ?? '{}'), true);
        if (!is_array($tpl)) json_out(['ok' => false, 'msg' => 'قالب غير صالح'], 422);
        // تنقية
        $clean = [
            'w' => max(40, min(200, (int)($tpl['w'] ?? 90))),
            'h' => max(40, min(300, (int)($tpl['h'] ?? 130))),
            'unit' => 'mm',
            'bg' => preg_replace('/[^a-zA-Z0-9\/\._-]/', '', (string)($tpl['bg'] ?? '')),
            'bg_color' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $tpl['bg_color'] ?? '') ? $tpl['bg_color'] : '#ffffff',
            'accent' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $tpl['accent'] ?? '') ? $tpl['accent'] : '#B08A3A',
            'elements' => [],
        ];
        foreach (array_slice((array)($tpl['elements'] ?? []), 0, 30) as $el) {
            $clean['elements'][] = [
                'type'  => in_array($el['type'] ?? '', ['text', 'qr', 'barcode'], true) ? $el['type'] : 'text',
                'field' => preg_replace('/[^a-z_]/', '', (string)($el['field'] ?? 'custom')),
                'text'  => clean_text($el['text'] ?? '', 120),
                'x'     => max(0, min(100, (float)($el['x'] ?? 50))),
                'y'     => max(0, min(100, (float)($el['y'] ?? 50))),
                'size'  => max(4, min(60, (float)($el['size'] ?? 12))),
                'color' => preg_match('/^#[0-9a-fA-F]{3,8}$/', $el['color'] ?? '') ? $el['color'] : '#0b0e26',
                'align' => in_array($el['align'] ?? '', ['start', 'center', 'end'], true) ? $el['align'] : 'center',
                'bold'  => !empty($el['bold']) ? 1 : 0,
            ];
        }
        setting_set('badge_template', json_encode($clean, JSON_UNESCAPED_UNICODE));
        setting_set('barcode_mode', ($_POST['barcode_mode'] ?? 'code') === 'info' ? 'info' : 'code');
        $bf = json_decode((string)($_POST['barcode_fields'] ?? '[]'), true);
        if (is_array($bf)) setting_set('barcode_fields', json_encode(array_slice($bf, 0, 10), JSON_UNESCAPED_UNICODE));
        admin_audit('badge_save');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$tpl = badge_template();
$barcodeMode = setting('barcode_mode', 'code');
$barcodeFields = json_decode(setting('barcode_fields', '[]'), true) ?: ['full_name', 'phone', 'code'];
$fieldOpts = badge_fields();
$sample = ['full_name' => 'أحمد محمد الجبوري', 'job' => 'خبير تحول رقمي', 'org' => 'وزارة الاتصالات', 'sector' => 'القطاع العام', 'city' => 'بغداد', 'age' => '34', 'gender' => 'male', 'phone' => '9647701234567', 'email' => 'name@example.com', 'code' => 'CSR26-ABC123'];
$presets = badge_presets();

admin_header('تصميم الباجات والطباعة', 'badges');
?>
<div class="badge-studio">
  <aside class="badge-side">
    <div class="panel">
      <div class="panel-head"><h2>قوالب جاهزة</h2></div>
      <p class="hint" style="margin-bottom:10px">اختر قالباً ثم عدّله كما تشاء.</p>
      <div class="preset-btns">
        <?php foreach ($presets as $pk => $pv): ?>
          <button type="button" class="btn-s preset-btn" data-preset="<?= e($pk) ?>"><?= icon('badge-id') ?> <?= e($pv['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel">
      <div class="panel-head"><h2>أبعاد الباج</h2></div>
      <div class="grid2">
        <label>العرض (ملم)<input id="bW" type="number" min="40" max="200" value="<?= (int)$tpl['w'] ?>"></label>
        <label>الارتفاع (ملم)<input id="bH" type="number" min="40" max="300" value="<?= (int)$tpl['h'] ?>"></label>
        <label>لون الخلفية<input id="bBgColor" type="color" value="<?= e($tpl['bg_color']) ?>"></label>
        <label>اللون المميز<input id="bAccent" type="color" value="<?= e($tpl['accent']) ?>"></label>
      </div>
      <label style="margin-top:10px">صورة خلفية (اختياري)</label>
      <div class="photo-drop" id="bgDrop">
        <img id="bgPrev" <?= $tpl['bg'] ? '' : 'hidden' ?> src="<?= $tpl['bg'] ? e(upload_url($tpl['bg'])) : '' ?>" alt="">
        <span id="bgHint" <?= $tpl['bg'] ? 'hidden' : '' ?>><?= icon('image') ?> رفع خلفية</span>
        <input type="file" id="bgFile" accept="image/*" hidden>
      </div>
      <input type="hidden" id="bBg" value="<?= e($tpl['bg']) ?>">
      <?php if ($tpl['bg']): ?><button class="btn-s btn-block" id="bgClear" style="margin-top:8px"><?= icon('trash') ?> إزالة الخلفية</button><?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>العناصر</h2><button class="btn-s" id="addEl"><?= icon('plus') ?> عنصر</button></div>
      <div id="elList" class="el-list"></div>
    </div>

    <div class="panel">
      <div class="panel-head"><h2>محتوى الباركود</h2></div>
      <label class="radio-line"><input type="radio" name="bcmode" value="code" <?= $barcodeMode !== 'info' ? 'checked' : '' ?>> رمز الدخول فقط (CSR26-XXXX)</label>
      <label class="radio-line"><input type="radio" name="bcmode" value="info" <?= $barcodeMode === 'info' ? 'checked' : '' ?>> معلومات المسجّل (يحدّدها أدناه)</label>
      <div id="bcFields" class="bc-fields" <?= $barcodeMode === 'info' ? '' : 'hidden' ?>>
        <?php foreach (['full_name', 'phone', 'email', 'org', 'job', 'city', 'sector', 'code'] as $bf): ?>
          <label class="chkchip"><input type="checkbox" class="bcf" value="<?= $bf ?>" <?= in_array($bf, $barcodeFields, true) ? 'checked' : '' ?>> <?= e($fieldOpts[$bf] ?? $bf) ?></label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="panel">
      <button class="btn-p btn-block" id="saveBadge"><?= icon('save') ?> حفظ التصميم</button>
      <a class="btn-s btn-block" href="registrants.php" style="margin-top:8px"><?= icon('printer') ?> اذهب للطباعة من قائمة المسجّلين</a>
    </div>
  </aside>

  <div class="badge-canvas-wrap">
    <div class="badge-toolbar">
      <span class="hint" style="margin:0"><?= icon('info') ?> اسحب العناصر لتغيير مواقعها. المعاينة ببيانات تجريبية.</span>
    </div>
    <div class="badge-stage" id="stage">
      <div class="badge-card" id="badgeCard"></div>
    </div>
  </div>
</div>

<script src="../assets/js/qrcode.min.js"></script>
<script>
window.BADGE = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
window.SAMPLE = <?= json_encode($sample, JSON_UNESCAPED_UNICODE) ?>;
window.FIELD_OPTS = <?= json_encode($fieldOpts, JSON_UNESCAPED_UNICODE) ?>;
window.PRESETS = <?= json_encode(array_map(function ($p) { return $p['tpl']; }, $presets), JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="assets/badge-designer.js?v=2"></script>
<link rel="stylesheet" href="assets/badge.css?v=2">
<?php admin_footer(); ?>
