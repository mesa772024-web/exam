<?php
/**
 * طباعة الباج — يُركّب الباج كصورة (canvas) ويتيح:
 *  • وضع ملصق: صفحة = حجم الباج تماماً (لطابعات الملصقات).
 *  • وضع A4: يضع الباج على ورقة A4 مع علامات قص ليُقتَص باليد.
 *  • تنزيل الباج صورة PNG.
 * ?id=..&auto=1  → طباعة تلقائية (وضع الملصق) — تُستخدم من شاشة القاعة.
 */
require_once __DIR__ . '/inc/auth.php';
require_once dirname(__DIR__) . '/app/badge.php';
admin_require();

$id = (int)($_GET['id'] ?? 0);
$r = q_one('SELECT * FROM registrants WHERE id = ?', [$id]);
if ($r === null) { http_response_code(404); exit('not found'); }
$tpl = badge_template();
$tpl['bgUrl'] = $tpl['bg'] ? upload_url($tpl['bg']) : '';
$data = badge_registrant_json($r);
$consts = [
    'site' => setting('site_name_ar', ''),
    'edition' => setting('edition_ar', ''),
    'dates' => setting('event_dates_ar', ''),
];
$auto = isset($_GET['auto']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>باج <?= e($r['code']) ?></title>
<link rel="stylesheet" href="../assets/css/fonts.css">
<style>
:root{--bw:<?= (float)$tpl['w'] ?>mm;--bh:<?= (float)$tpl['h'] ?>mm}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'IBM Plex Sans Arabic',sans-serif;background:#525659;color:#fff;min-height:100vh}
.toolbar{position:fixed;top:0;inset-inline:0;background:#2b2f36;padding:12px 18px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;z-index:20;justify-content:center}
.toolbar b{font-size:14px}
.toolbar select,.toolbar button{font-family:inherit;font-weight:800;border:0;border-radius:9px;padding:9px 16px;font-size:13.5px;cursor:pointer}
.toolbar select{background:#3a3f47;color:#fff}
.tb-print{background:#B08A3A;color:#fff}
.tb-dl{background:#fff;color:#24275F}
.stage{padding:80px 20px 40px;display:flex;justify-content:center}
/* شاشة العرض */
.sheet{background:#fff;box-shadow:0 12px 40px rgba(0,0,0,.4)}
.sheet.label{width:var(--bw);height:var(--bh)}
.sheet.a4{width:210mm;min-height:297mm;position:relative;display:grid;place-content:center;gap:14mm;padding:16mm;grid-template-columns:1fr}
.sheet.a4.cols2{grid-template-columns:1fr 1fr;place-content:start center;padding-top:20mm}
.sheet.a4.cols4{grid-template-columns:1fr 1fr;place-content:start center;padding-top:20mm}
.badge-img{width:var(--bw);height:var(--bh);display:block}
.a4 .cell{position:relative;width:var(--bw);height:var(--bh)}
/* علامات القص */
.a4 .cell::before,.a4 .cell::after{content:"";position:absolute;pointer-events:none}
.crop{position:absolute;width:4mm;height:4mm;border:.3mm solid #999}
.crop.tl{top:-5mm;inset-inline-start:-5mm;border-inline-end:0;border-bottom:0}
.crop.tr{top:-5mm;inset-inline-end:-5mm;border-inline-start:0;border-bottom:0}
.crop.bl{bottom:-5mm;inset-inline-start:-5mm;border-inline-end:0;border-top:0}
.crop.br{bottom:-5mm;inset-inline-end:-5mm;border-inline-start:0;border-top:0}
@media print{
  body{background:#fff}
  .toolbar{display:none}
  .stage{padding:0}
  .sheet{box-shadow:none}
  @page{margin:0}
}
/* حجم الصفحة حسب الوضع (يُضبط ديناميكياً) */
</style>
</head>
<body>
<div class="toolbar">
  <b>وضع الطباعة:</b>
  <select id="mode">
    <option value="label">ملصق بحجم الباج (طابعة ملصقات)</option>
    <option value="a4-1">A4 — باج واحد (مع علامات قص)</option>
    <option value="a4-2">A4 — باجان</option>
    <option value="a4-4">A4 — أربعة باجات</option>
  </select>
  <button class="tb-print" onclick="doPrint()">طباعة</button>
  <button class="tb-dl" onclick="downloadPng()">تنزيل صورة PNG</button>
  <span id="tbHint" style="font-size:12px;color:#c9cfd8"></span>
</div>
<div class="stage" id="stage"></div>

<script src="../assets/js/qrcode.min.js"></script>
<script src="assets/badge-render.js?v=1"></script>
<script>
var TPL=<?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
var DATA=<?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>;
var CONSTS=<?= json_encode($consts, JSON_UNESCAPED_UNICODE) ?>;
var badgePng='';
var stage=document.getElementById('stage'), modeSel=document.getElementById('mode');

function buildSheet(){
  var mode=modeSel.value;
  var count = mode==='a4-2'?2 : (mode==='a4-4'?4 : 1);
  var isLabel = mode==='label';
  var sheet=document.createElement('div');
  sheet.className='sheet '+(isLabel?'label':'a4'+(count===2?' cols2':(count===4?' cols4':'')));
  if(isLabel){
    sheet.appendChild(imgEl());
  } else {
    for(var i=0;i<count;i++){
      var cell=document.createElement('div'); cell.className='cell';
      cell.appendChild(imgEl());
      ['tl','tr','bl','br'].forEach(function(c){var m=document.createElement('span');m.className='crop '+c;cell.appendChild(m);});
      sheet.appendChild(cell);
    }
  }
  stage.innerHTML=''; stage.appendChild(sheet);
  // ضبط حجم الصفحة للطباعة
  setPageSize(isLabel);
}
function imgEl(){ var im=new Image(); im.className='badge-img'; im.src=badgePng; return im; }
function setPageSize(isLabel){
  var st=document.getElementById('pageStyle'); if(st) st.remove();
  var css=isLabel ? '@page{size:'+TPL.w+'mm '+TPL.h+'mm;margin:0}' : '@page{size:A4;margin:0}';
  var s=document.createElement('style'); s.id='pageStyle'; s.textContent=css; document.head.appendChild(s);
}
function doPrint(){ window.print(); }
function downloadPng(){
  var a=document.createElement('a'); a.download='badge-'+DATA.code+'.png'; a.href=badgePng; a.click();
}
modeSel.addEventListener('change', buildSheet);

// ركّب صورة الباج ثم اعرض
renderBadgeCanvas(TPL, DATA, CONSTS, function(canvas){
  badgePng=canvas.toDataURL('image/png');
  buildSheet();
  document.getElementById('tbHint').textContent='الباج جاهز — اختر الوضع ثم اطبع أو نزّل';
  <?php if ($auto): ?>setTimeout(function(){ modeSel.value='label'; buildSheet(); setTimeout(function(){window.print();},250); },350);<?php endif; ?>
});
</script>
</body>
</html>
