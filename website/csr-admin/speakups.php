<?php
require_once __DIR__ . '/inc/layout.php';
require_once dirname(__DIR__) . '/app/speakup.php';
require_super();
speakup_ensure_table();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'read') {
        q('UPDATE speakups SET is_read = ? WHERE id = ?', [(int)!empty($_POST['v']), $id]);
        json_out(['ok' => true]);
    }
    if ($act === 'toggle_on') {
        setting_set('speakup_on', !empty($_POST['v']) ? '1' : '0');
        admin_audit('speakup_toggle', (string)(int)!empty($_POST['v']));
        json_out(['ok' => true]);
    }
    if ($act === 'readall') {
        q('UPDATE speakups SET is_read = 1 WHERE is_read = 0');
        json_out(['ok' => true]);
    }
    if ($act === 'delete') {
        q('DELETE FROM speakups WHERE id = ?', [$id]);
        admin_audit('speakup_delete', 'id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

/* تصدير CSV (يفتح في Excel) */
if (isset($_GET['export'])) {
    $rows = q_all('SELECT * FROM speakups ORDER BY id DESC');
    $cell = function ($v) {
        $v = (string)$v;
        if ($v !== '' && strpos('=+-@', $v[0]) !== false) $v = "'" . $v; // منع حقن الصيغ
        return '"' . str_replace('"', '""', $v) . '"';
    };
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="speakup-' . date('Ymd-His') . '.csv"');
    echo "\xEF\xBB\xBF";
    echo implode(',', array_map($cell, ['#', 'التاريخ', 'الاسم', 'الهاتف', 'البريد', 'اللغة', 'الرسالة'])) . "\r\n";
    foreach ($rows as $r) {
        echo implode(',', array_map($cell, [$r['id'], $r['created_at'], $r['name'], $r['phone'], $r['email'], $r['lang'], $r['message']])) . "\r\n";
    }
    admin_audit('speakup_export', count($rows) . ' rows');
    exit;
}

$f = ($_GET['f'] ?? '') === 'unread' ? 'unread' : '';
$rows = q_all('SELECT * FROM speakups ' . ($f === 'unread' ? 'WHERE is_read = 0 ' : '') . 'ORDER BY id DESC LIMIT 300');
$total = (int)q_val('SELECT COUNT(*) FROM speakups');
$unread = (int)q_val('SELECT COUNT(*) FROM speakups WHERE is_read = 0');

admin_header('آراء الزوار · Speak Up', 'speakups');
?>
<style>
.sp-list{display:flex;flex-direction:column;gap:12px}
.sp-card{border:1px solid #e3eaf2;border-radius:12px;padding:16px 18px;background:#fff;position:relative}
.sp-card.unread{border-inline-start:4px solid #B08A3A;background:#f7fbfe}
.sp-meta{display:flex;flex-wrap:wrap;gap:6px 18px;align-items:center;font-size:13px;color:#5b6b80;margin-bottom:10px}
.sp-meta b{color:#24275F;font-size:15px}
.sp-meta a{color:#196598}
.sp-msg{white-space:pre-wrap;line-height:1.9;font-size:15px;color:#1f2a44;overflow-wrap:anywhere}
.sp-actions{display:flex;gap:8px;margin-top:12px}
.sp-new{background:#B08A3A;color:#fff;border-radius:20px;padding:1px 9px;font-size:11px}
</style>
<div class="content-grid">
<div class="panel">
  <div class="panel-head">
    <h2>الرسائل (<?= $total ?>) <?php if ($unread): ?><span class="sp-new"><?= $unread ?> جديدة</span><?php endif; ?></h2>
    <div class="ftabs">
      <a class="ftab <?= $f === '' ? 'on' : '' ?>" href="speakups.php">الكل</a>
      <a class="ftab <?= $f === 'unread' ? 'on' : '' ?>" href="?f=unread">غير المقروءة</a>
      <?php if ($unread): ?><button class="ftab" type="button" id="readAll">تعليم الكل كمقروء</button><?php endif; ?>
      <?php if ($total): ?><a class="ftab" href="?export=1">تصدير Excel (CSV)</a><?php endif; ?>
    </div>
  </div>
  <div class="sp-switch-row" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;background:<?= scf_speakup_on() ? '#e3f6ec' : '#fff4e5' ?>;border-radius:12px;padding:12px 16px;margin-bottom:12px">
    <label class="chkline big" style="margin:0"><input type="checkbox" id="spOn" <?= scf_speakup_on() ? 'checked' : '' ?>> <b>تفعيل «شاركنا رأيك · Speak Up» في الموقع</b></label>
    <span class="hint" style="margin:0"><?= scf_speakup_on() ? 'مفعّل: يظهر القسم في الرئيسية والقائمة والتذييل.' : 'متوقف حالياً: لا يظهر في الموقع ولا يقبل رسائل.' ?></span>
  </div>
  <p class="hint">رسائل «شاركنا رأيك» من الموقع. الاسم والهاتف والبريد اختيارية؛ الرسالة نص فقط.</p>
  <div class="sp-list">
    <?php foreach ($rows as $r): ?>
    <article class="sp-card<?= $r['is_read'] ? '' : ' unread' ?>" data-id="<?= (int)$r['id'] ?>">
      <div class="sp-meta">
        <b><?= $r['name'] !== '' ? e($r['name']) : 'زائر (بدون اسم)' ?></b>
        <?php if ($r['phone'] !== ''): ?><a dir="ltr" href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a><?php endif; ?>
        <?php if ($r['email'] !== ''): ?><a dir="ltr" href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a><?php endif; ?>
        <span class="mono"><?= e(date('Y/m/d H:i', strtotime($r['created_at']))) ?></span>
        <span><?= $r['lang'] === 'en' ? 'EN' : 'AR' ?></span>
      </div>
      <div class="sp-msg"><?= e($r['message']) ?></div>
      <div class="sp-actions">
        <button class="act" type="button" data-read="<?= $r['is_read'] ? 0 : 1 ?>"><?= $r['is_read'] ? 'تعليم كغير مقروءة' : 'تعليم كمقروءة' ?></button>
        <button class="act" type="button" data-del style="color:#c0392b">حذف</button>
      </div>
    </article>
    <?php endforeach; ?>
    <?php if (!$rows): ?><p class="empty">لا توجد رسائل<?= $f === 'unread' ? ' غير مقروءة' : ' بعد' ?>.</p><?php endif; ?>
  </div>
</div>
</div>
<script>
function postSp(data, cb) {
  var fd = new FormData();
  Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
  fd.append('_csrf', window.CSRF);
  fetch('speakups.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
    .then(function (r) { return r.json(); }).then(cb)
    .catch(function () { window.toast('تعذر الاتصال', 1); });
}
document.querySelectorAll('[data-read]').forEach(function (b) {
  b.addEventListener('click', function () {
    postSp({act: 'read', id: b.closest('[data-id]').dataset.id, v: b.dataset.read}, function (j) { if (j.ok) location.reload(); });
  });
});
document.querySelectorAll('[data-del]').forEach(function (b) {
  b.addEventListener('click', function () {
    if (!confirm('حذف هذه الرسالة نهائياً؟')) return;
    var card = b.closest('[data-id]');
    postSp({act: 'delete', id: card.dataset.id}, function (j) { if (j.ok) { card.remove(); window.toast('تم الحذف'); } });
  });
});
document.getElementById('spOn').addEventListener('change', function () { postSp({act: 'toggle_on', v: this.checked ? 1 : 0}, function (j) { if (j.ok) location.reload(); }); });
var ra = document.getElementById('readAll');
if (ra) ra.addEventListener('click', function () { postSp({act: 'readall'}, function (j) { if (j.ok) location.reload(); }); });
</script>
<?php admin_footer(); ?>
