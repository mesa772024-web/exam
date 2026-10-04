<?php
/** طباعة الباجات — بالأبعاد الحقيقية (ملم) مع QR، وطباعة تلقائية */
require_once __DIR__ . '/inc/auth.php';
require_once dirname(__DIR__) . '/app/badge.php';

admin_require();

$tpl = badge_template();

/* اختيار المسجّلين: ids أو status أو الكل المقبول */
$ids = [];
if (!empty($_GET['ids'])) {
    $ids = array_slice(array_filter(array_map('intval', explode(',', (string)$_GET['ids']))), 0, 500);
}
$status = in_array($_GET['status'] ?? '', ['approved', 'pending', 'all'], true) ? $_GET['status'] : '';

if ($ids) {
    $in = rtrim(str_repeat('?,', count($ids)), ',');
    $rows = q_all("SELECT * FROM registrants WHERE id IN ($in) ORDER BY full_name", $ids);
} elseif ($status === 'all') {
    $rows = q_all('SELECT * FROM registrants ORDER BY full_name');
} elseif ($status) {
    $rows = q_all('SELECT * FROM registrants WHERE status = ? ORDER BY full_name', [$status]);
} else {
    $rows = q_all("SELECT * FROM registrants WHERE status = 'approved' ORDER BY full_name");
}

$data = array_map('badge_registrant_json', $rows);
admin_audit('badge_print', 'count=' . count($data));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>طباعة الباجات (<?= count($data) ?>)</title>
<link rel="stylesheet" href="../assets/css/fonts.css">
<style>
:root{--w:<?= (float)$tpl['w'] ?>mm;--h:<?= (float)$tpl['h'] ?>mm}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'IBM Plex Sans Arabic',sans-serif;background:#eef2f7;color:#0b0e26}
.print-bar{position:sticky;top:0;z-index:10;background:#0e1130;color:#fff;padding:14px 22px;display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap}
.print-bar b{font-size:16px}
.print-bar .actions{display:flex;gap:10px}
.pb-btn{border:0;border-radius:10px;padding:11px 22px;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit}
.pb-print{background:#B08A3A;color:#fff}
.pb-back{background:rgba(255,255,255,.14);color:#fff}
.sheet{display:flex;flex-wrap:wrap;gap:8mm;justify-content:center;padding:10mm}
.badge{position:relative;width:var(--w);height:var(--h);background:#fff;overflow:hidden;
  box-shadow:0 6px 20px rgba(11,14,38,.18);border-radius:2mm;flex:none;
  <?= $tpl['bg'] ? "background:center/cover no-repeat url('" . e(upload_url($tpl['bg'])) . "');" : "background:" . e($tpl['bg_color']) . ";" ?>}
.badge .be{position:absolute;transform:translate(-50%,-50%);line-height:1.15;white-space:nowrap;max-width:96%}
.badge .be.text{overflow:hidden;text-overflow:ellipsis}
.badge .be canvas,.badge .be img{display:block}
.empty{padding:60px;text-align:center;color:#5b6478;font-weight:700}
@media print{
  @page{size:auto;margin:6mm}
  body{background:#fff}
  .print-bar{display:none}
  .sheet{gap:6mm;padding:0}
  .badge{box-shadow:none;border:1px solid #e3e9f2;page-break-inside:avoid}
}
</style>
</head>
<body>
<div class="print-bar">
  <b>طباعة الباجات — <?= count($data) ?> باج</b>
  <div class="actions">
    <button class="pb-btn pb-print" onclick="window.print()">طباعة الآن</button>
    <a class="pb-btn pb-back" href="registrants.php">رجوع</a>
  </div>
</div>
<?php if (!$data): ?>
  <div class="empty">لا يوجد مسجّلون للطباعة</div>
<?php else: ?>
<div class="sheet" id="sheet"></div>
<?php endif; ?>

<script src="../assets/js/qrcode.min.js"></script>
<script>
var TPL = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
var DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
var LABELS = {full_name:'',job:'',org:'',sector:'',city:'',age:'',gender:'',phone:'',email:'',code:''};

function val(el, r){
  var f = el.field;
  if(f==='custom') return el.text||'';
  if(f==='site') return <?= json_encode(setting('site_name_ar', ''), JSON_UNESCAPED_UNICODE) ?>;
  if(f==='edition') return <?= json_encode(setting('edition_ar', ''), JSON_UNESCAPED_UNICODE) ?>;
  if(f==='dates') return <?= json_encode(setting('event_dates_ar', ''), JSON_UNESCAPED_UNICODE) ?>;
  if(f==='gender') return r.gender==='male'?'ذكر':(r.gender==='female'?'أنثى':'');
  if(f==='phone') return r.phone?('+'+r.phone):'';
  return r[f]!=null?String(r[f]):'';
}

var sheet = document.getElementById('sheet');
if (sheet) {
  DATA.forEach(function(r){
    var b = document.createElement('div');
    b.className = 'badge';
    (TPL.elements||[]).forEach(function(el){
      var node = document.createElement('div');
      node.className = 'be ' + el.type;
      node.style.left = el.x + '%';
      node.style.top = el.y + '%';
      node.style.color = el.color;
      node.style.textAlign = el.align;
      node.style.fontWeight = el.bold ? '800' : '500';
      if (el.type === 'qr') {
        var px = Math.round(el.size * 96 / 25.4 * 2); // high-res
        try { new QRCode(node, {text: r.qr || r.code, width: px, height: px, colorDark: el.color, colorLight: '#ffffff', correctLevel: QRCode.CorrectLevel.M}); } catch(e){}
        node.style.width = el.size + 'mm';
        var c = node.querySelector('canvas,img'); if(c){c.style.width=el.size+'mm';c.style.height=el.size+'mm';}
      } else {
        node.style.fontSize = el.size + 'mm';
        node.textContent = val(el, r);
      }
      b.appendChild(node);
    });
    sheet.appendChild(b);
  });
  // auto print after QR render
  setTimeout(function(){ window.print(); }, 600);
}
</script>
</body>
</html>
