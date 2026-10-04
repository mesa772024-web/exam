<?php
require_once __DIR__ . '/inc/layout.php';
$me = admin_require(); // متاح للمدير الكامل وموظف التسجيل

/* حفظ سعة القاعة (المدير الكامل فقط) */
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    if (($_POST['act'] ?? '') === 'set_capacity' && is_super()) {
        setting_set('hall_capacity', (string)max(1, min(5000, (int)($_POST['capacity'] ?? 150))));
        setting_set('print_on_admit', !empty($_POST['print_on_admit']) ? '1' : '0');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$cap = (int)setting('hall_capacity', '150');
$entered = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
$printOnAdmit = setting('print_on_admit', '1') === '1';
$zones = json_decode(setting('hall_zones', '{}'), true);
if (!is_array($zones)) $zones = [];
$occupied = q_all('SELECT id, seat_no, full_name, org FROM registrants WHERE attended = 1 AND seat_no IS NOT NULL ORDER BY seat_no');
$occMap = [];
foreach ($occupied as $o) { $occMap[(int)$o['seat_no']] = ['name' => $o['full_name'], 'org' => $o['org'], 'id' => (int)$o['id']]; }
$sharedQueue = q_all("SELECT r.id, r.code, r.full_name name, r.org FROM hall_queue hq JOIN registrants r ON r.id=hq.registrant_id WHERE r.attended=0 AND r.status='approved' ORDER BY hq.created_at, r.id");

admin_header('قاعة المؤتمر (التسجيل)', 'checkin');
?>
<div class="hall-wrap">
  <!-- لوحة التحكم -->
  <aside class="hall-panel">
    <div class="hp-block scan-block">
      <h3 class="hp-title"><?= icon('qr') ?> مسح الباركود</h3>
      <div class="hp-row">
        <input type="text" id="scanInput" placeholder="امسح الباركود أو اكتب الرمز ثم Enter" dir="ltr" autofocus autocomplete="off">
        <button class="btn-p" id="scanBtn"><?= icon('qr') ?></button>
      </div>
      <button type="button" class="btn-s btn-block scan-camera-btn" id="cameraBtn"><?= icon('camera') ?> فتح كاميرا الهاتف وقراءة الباركود</button>
      <p class="hp-hint" id="scanMsg"></p>
    </div>

    <div class="hp-block queue-block" id="queueBlock">
      <h3 class="hp-title"><?= icon('users') ?> قائمة الانتظار <span class="q-count" id="qCount">0</span></h3>
      <div class="queue-list" id="queueList"><p class="empty" id="queueEmpty">لا أحد بالانتظار — امسح باركود مسجّل مقبول</p></div>
    </div>

    <div class="hall-stats">
      <div class="hs"><span class="hs-num" id="stTotal"><?= $cap ?></span><span class="hs-lbl">السعة</span></div>
      <div class="hs"><span class="hs-num hs-green" id="stRemain"><?= $cap - $entered ?></span><span class="hs-lbl">متبقٍّ</span></div>
      <div class="hs"><span class="hs-num hs-amber" id="stWait">0</span><span class="hs-lbl">بالانتظار</span></div>
      <div class="hs"><span class="hs-num hs-teal" id="stIn"><?= $entered ?></span><span class="hs-lbl">دخلوا</span></div>
    </div>
    <button class="btn-s btn-block" id="showListBtn"><?= icon('list') ?> عرض جدول الحاضرين والمنتظرين</button>

    <?php if (is_super()): ?>
    <details class="hp-block hp-details">
      <summary class="hp-title"><?= icon('settings') ?> إعدادات القاعة</summary>
      <label class="hp-label">عدد المقاعد</label>
      <div class="hp-row">
        <input type="number" id="capInput" value="<?= $cap ?>" min="1" max="5000">
        <button class="btn-s" id="capSave"><?= icon('save') ?> حفظ</button>
      </div>
      <label class="chkline" style="margin-top:10px"><input type="checkbox" id="printChk" <?= $printOnAdmit ? 'checked' : '' ?>> طباعة الباج تلقائياً عند الإدخال</label>
    </details>
    <?php endif; ?>

    <div class="hp-block">
      <h3 class="hp-title"><?= icon('search') ?> البحث بالاسم</h3>
      <div class="hp-row">
        <input type="text" id="nameInput" placeholder="اكتب اسم المسجّل..." autocomplete="off">
        <button class="btn-s" id="nameBtn"><?= icon('search') ?></button>
      </div>
      <div class="name-results" id="nameResults"></div>
    </div>

    <?php if (is_super()): ?>
    <div class="hp-block">
      <h3 class="hp-title"><?= icon('grid') ?> تخصيص المقاعد <small class="sub">(اختياري)</small></h3>
      <label class="chkline"><input type="checkbox" id="zoneMode"> وضع تلوين المقاعد</label>
      <div class="zone-palette" id="zonePalette" hidden>
        <button class="zc zc-erase" data-color="" title="مسح اللون">×</button>
        <button class="zc" data-color="#f5b301" title="ذهبي / VIP" style="background:#f5b301"></button>
        <button class="zc" data-color="#7ac943" title="أخضر" style="background:#7ac943"></button>
        <button class="zc" data-color="#e0455e" title="أحمر / محجوز" style="background:#e0455e"></button>
        <button class="zc" data-color="#a06adf" title="بنفسجي" style="background:#a06adf"></button>
      </div>
      <div class="zone-scope" id="zoneScope" hidden>
        <button type="button" class="zs selected" data-scope="seat">مقعد</button>
        <button type="button" class="zs" data-scope="row">سطر أفقي</button>
        <button type="button" class="zs" data-scope="column">سطر عمودي</button>
        <button type="button" class="zs" data-scope="all-clear">إلغاء كل التلوين</button>
      </div>
      <p class="hp-hint">أو اسحب أي مقعد مشغول إلى مقعد فارغ لنقل الجالس.</p>
    </div>
    <?php endif; ?>

    <div class="hp-block">
      <button class="btn-danger btn-block" id="newSession"><?= icon('refresh') ?> بدء جلسة جديدة (إخلاء القاعة)</button>
    </div>
  </aside>

  <!-- منطقة القاعة -->
  <div class="hall-venue">
    <div class="waiting-zone" id="waitingZone"></div>
    <div class="hall-layout">
      <div class="seating-grid" id="seatGrid"></div>
      <div class="stage-area">STAGE · المنصة</div>
    </div>
    <p class="hall-caption"><?= e(setting('hall_name_ar', 'القاعة الرئيسية لإقامة المنتدى')) ?></p>
  </div>
</div>

<!-- قارئ كاميرا الهاتف؛ USB يعمل مباشرة في حقل المسح أعلاه -->
<div class="modal" id="cameraModal" hidden>
  <div class="modal-card camera-card">
    <button class="modal-x" type="button" data-camera-close><?= icon('close') ?></button>
    <h3><?= icon('camera') ?> قراءة الباركود بالكاميرا</h3>
    <div class="camera-permission" id="cameraPermission"><?= icon('info') ?> عند الضغط على فتح الكاميرا سيعرض الهاتف نافذة إذن. اختر «سماح أثناء استخدام الموقع».</div>
    <video id="cameraVideo" playsinline muted></video>
    <p class="hp-hint" id="cameraMsg">وجّه الكاميرا نحو الباركود أو QR.</p>
    <button type="button" class="btn-s btn-block" data-camera-close>إغلاق الكاميرا</button>
  </div>
</div>

<!-- جدول الحاضرين والمنتظرين -->
<div class="modal" id="listModal" hidden>
  <div class="modal-card modal-wide">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3><?= icon('users') ?> جدول القاعة</h3>
    <div class="list-tabs">
      <button class="lt on" data-lt="in">الحاضرون (<span id="cIn"><?= $entered ?></span>)</button>
      <button class="lt" data-lt="wait">المنتظرون (<span id="cWait">0</span>)</button>
    </div>
    <div class="tbl-scroll" style="max-height:56vh">
      <table class="tbl" id="inTable"><thead><tr><th>المقعد</th><th>الاسم</th><th>الجهة</th></tr></thead><tbody></tbody></table>
      <table class="tbl" id="waitTable" hidden><thead><tr><th>#</th><th>الاسم</th><th>الرمز</th></tr></thead><tbody></tbody></table>
    </div>
  </div>
</div>

<!-- إطار طباعة مخفي -->
<iframe id="printFrame" style="position:fixed;width:0;height:0;border:0;left:-9999px" title="print"></iframe>

<link rel="stylesheet" href="assets/hall.css?v=7">
<script>
window.HALL = {
  cap: <?= $cap ?>,
  entered: <?= $entered ?>,
  occMap: <?= json_encode($occMap, JSON_UNESCAPED_UNICODE) ?>,
  waiting: <?= json_encode($sharedQueue, JSON_UNESCAPED_UNICODE) ?>,
  zones: <?= json_encode($zones, JSON_UNESCAPED_UNICODE) ?>,
  printOnAdmit: <?= $printOnAdmit ? 'true' : 'false' ?>,
  isSuper: <?= is_super() ? 'true' : 'false' ?>
};
</script>
<script src="assets/hall.js?v=7"></script>
<?php admin_footer(); ?>
