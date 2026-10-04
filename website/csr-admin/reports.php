<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

/* أيام المؤتمر من الأجندة */
$days = q_all('SELECT day_date, title_ar FROM agenda_days WHERE day_date IS NOT NULL ORDER BY sort, id');

$tot   = (int)q_val('SELECT COUNT(*) FROM registrants');
$appr  = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='approved'");
$pend  = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='pending'");
$rej   = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status='rejected'");
$att   = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended=1');
$noshow = max(0, $appr - $att);
$male   = (int)q_val("SELECT COUNT(*) FROM registrants WHERE gender='male'");
$female = (int)q_val("SELECT COUNT(*) FROM registrants WHERE gender='female'");

/* قبل: تسجيلات آخر 30 يوماً */
$reg30 = [];
for ($i = 29; $i >= 0; $i--) $reg30[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (q_all("SELECT DATE(created_at) d, COUNT(*) c FROM registrants WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY DATE(created_at)") as $r) {
    if (isset($reg30[$r['d']])) $reg30[$r['d']] = (int)$r['c'];
}
$maxReg30 = max(1, max($reg30 ?: [1]));
$bySector = q_all("SELECT COALESCE(NULLIF(sector,''),'غير محدد') s, COUNT(*) c FROM registrants GROUP BY s ORDER BY c DESC LIMIT 8");
$byCity   = q_all("SELECT COALESCE(NULLIF(city,''),'غير محدد') s, COUNT(*) c FROM registrants GROUP BY s ORDER BY c DESC LIMIT 8");
$maxSector = max(1, (int)($bySector[0]['c'] ?? 1));
$maxCity   = max(1, (int)($byCity[0]['c'] ?? 1));

/* أثناء: الحضور لكل يوم مؤتمر */
$attByDay = [];
foreach ($days as $d) {
    $attByDay[$d['day_date']] = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended=1 AND DATE(attended_at)=?', [$d['day_date']]);
}
$maxAttDay = max(1, $attByDay ? max($attByDay) : 1);
/* توزيع الحضور بالساعة (اليوم) */
$byHour = array_fill(8, 12, 0);
foreach (q_all("SELECT HOUR(attended_at) h, COUNT(*) c FROM registrants WHERE attended=1 GROUP BY HOUR(attended_at)") as $r) {
    $h = (int)$r['h']; if ($h >= 8 && $h <= 19) $byHour[$h] = (int)$r['c'];
}
$maxHour = max(1, max($byHour));
$cap = (int)setting('hall_capacity', '150');

/* بعد: حضور حسب القطاع + معدل الحضور */
$attBySector = q_all("SELECT COALESCE(NULLIF(sector,''),'غير محدد') s, COUNT(*) c FROM registrants WHERE attended=1 GROUP BY s ORDER BY c DESC LIMIT 8");
$maxAttSector = max(1, (int)($attBySector[0]['c'] ?? 1));
$attRate = $appr > 0 ? round($att / $appr * 100) : 0;

admin_header('التقارير', 'reports');
?>
<div class="rep-tabs">
  <button class="rep-tab on" data-ph="before"><?= icon('calendar') ?> قبل المؤتمر</button>
  <button class="rep-tab" data-ph="during"><?= icon('ticket') ?> أثناء المؤتمر</button>
  <button class="rep-tab" data-ph="after"><?= icon('chart') ?> بعد المؤتمر</button>
</div>

<!-- ===== قبل ===== -->
<div class="rep-phase" data-ph="before">
  <div class="cards-grid">
    <div class="kcard k-blue"><b><?= $tot ?></b><span>إجمالي التسجيلات</span></div>
    <div class="kcard k-green"><b><?= $appr ?></b><span>المقبولون</span></div>
    <div class="kcard k-amber"><b><?= $pend ?></b><span>قيد المراجعة</span></div>
    <div class="kcard k-red"><b><?= $rej ?></b><span>المرفوضون</span></div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2><?= icon('chart') ?> التسجيلات خلال 30 يوماً</h2></div>
    <div class="bars-14">
      <?php foreach ($reg30 as $d => $c): ?>
        <div class="bar14" title="<?= e($d) ?>: <?= $c ?>"><div class="bar14-fill" style="height:<?= $c ? max(6, round($c/$maxReg30*100)) : 2 ?>%"><span><?= $c ?: '' ?></span></div><small><?= e(date('d', strtotime($d))) ?></small></div>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="reports-grid">
    <div class="panel"><div class="panel-head"><h2><?= icon('grid') ?> حسب القطاع</h2></div><div class="hbars">
      <?php foreach ($bySector as $s): ?><div class="hbar"><span class="hbar-label"><?= e($s['s']) ?></span><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($s['c']/$maxSector*100) ?>%"></div></div><b><?= (int)$s['c'] ?></b></div><?php endforeach; ?>
    </div></div>
    <div class="panel"><div class="panel-head"><h2><?= icon('pin') ?> حسب المدينة</h2></div><div class="hbars">
      <?php foreach ($byCity as $s): ?><div class="hbar"><span class="hbar-label"><?= e($s['s']) ?></span><div class="hbar-track"><div class="hbar-fill hbar-city" style="width:<?= round($s['c']/$maxCity*100) ?>%"></div></div><b><?= (int)$s['c'] ?></b></div><?php endforeach; ?>
    </div></div>
  </div>
</div>

<!-- ===== أثناء ===== -->
<div class="rep-phase" data-ph="during" hidden>
  <div class="cards-grid">
    <div class="kcard k-teal"><b><?= $att ?></b><span>إجمالي الحضور</span></div>
    <div class="kcard k-blue"><b><?= $cap ?></b><span>سعة القاعة</span></div>
    <div class="kcard k-violet"><b><?= $cap > 0 ? round($att/$cap*100) : 0 ?>%</b><span>إشغال القاعة</span></div>
    <div class="kcard k-amber"><b><?= max(0, $cap - $att) ?></b><span>مقاعد متبقية</span></div>
  </div>
  <div class="reports-grid">
    <div class="panel report-wide"><div class="panel-head"><h2><?= icon('ticket') ?> الحضور لكل يوم</h2></div>
      <div class="hbars">
        <?php if (!$days): ?><p class="hint">أضف أيام الأجندة بتواريخ لعرض الحضور اليومي.</p><?php endif; ?>
        <?php foreach ($days as $d): $c = $attByDay[$d['day_date']]; ?>
          <div class="hbar"><span class="hbar-label"><?= e($d['title_ar']) ?> <small class="sub" dir="ltr"><?= e($d['day_date']) ?></small></span><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($c/$maxAttDay*100) ?>%"></div></div><b><?= $c ?></b></div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="panel"><div class="panel-head"><h2><?= icon('clock') ?> توزيع الدخول بالساعة</h2></div>
      <div class="bars-14" style="height:150px">
        <?php foreach ($byHour as $h => $c): ?><div class="bar14" title="<?= $h ?>:00 — <?= $c ?>"><div class="bar14-fill" style="height:<?= $c ? max(6, round($c/$maxHour*100)) : 2 ?>%"><span><?= $c ?: '' ?></span></div><small><?= $h ?></small></div><?php endforeach; ?>
      </div>
    </div>
  </div>
  <div class="panel"><a class="btn-p" href="checkin.php"><?= icon('ticket') ?> فتح شاشة القاعة المباشرة</a></div>
</div>

<!-- ===== بعد ===== -->
<div class="rep-phase" data-ph="after" hidden>
  <div class="cards-grid">
    <div class="kcard k-green"><b><?= $att ?></b><span>حضروا فعلياً</span></div>
    <div class="kcard k-red"><b><?= $noshow ?></b><span>لم يحضروا (مقبول)</span></div>
    <div class="kcard k-blue"><b><?= $attRate ?>%</b><span>معدل الحضور</span></div>
    <div class="kcard k-violet"><b><?= $tot ?></b><span>إجمالي المسجّلين</span></div>
  </div>
  <div class="reports-grid">
    <div class="panel"><div class="panel-head"><h2><?= icon('chart') ?> ملخّص القمع</h2></div>
      <div class="funnel">
        <div class="fn-row"><span>مسجّل</span><div class="fn-bar" style="width:100%"><b><?= $tot ?></b></div></div>
        <div class="fn-row"><span>مقبول</span><div class="fn-bar fn-ok" style="width:<?= $tot?round($appr/max(1,$tot)*100):0 ?>%"><b><?= $appr ?></b></div></div>
        <div class="fn-row"><span>حاضر</span><div class="fn-bar fn-att" style="width:<?= $tot?round($att/max(1,$tot)*100):0 ?>%"><b><?= $att ?></b></div></div>
      </div>
      <div class="gender-split" style="margin-top:18px">
        <?php $gt = max(1, $male + $female); ?>
        <div class="gs-bar"><span class="gs-m" style="width:<?= round($male/$gt*100) ?>%"></span><span class="gs-f" style="width:<?= round($female/$gt*100) ?>%"></span></div>
        <div class="gs-legend"><span><i class="dot-m"></i> ذكور <b><?= $male ?></b></span><span><i class="dot-f"></i> إناث <b><?= $female ?></b></span></div>
      </div>
    </div>
    <div class="panel"><div class="panel-head"><h2><?= icon('grid') ?> الحضور حسب القطاع</h2></div><div class="hbars">
      <?php foreach ($attBySector as $s): ?><div class="hbar"><span class="hbar-label"><?= e($s['s']) ?></span><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($s['c']/$maxAttSector*100) ?>%"></div></div><b><?= (int)$s['c'] ?></b></div><?php endforeach; ?>
      <?php if (!$attBySector): ?><p class="empty">لا حضور بعد</p><?php endif; ?>
    </div></div>
  </div>
  <div class="panel"><a class="btn-s" href="export.php?type=csv"><?= icon('download') ?> تنزيل قاعدة المسجّلين الكاملة (CSV)</a></div>
</div>

<style>
.rep-tabs{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px}
.rep-tab{display:inline-flex;align-items:center;gap:8px;background:#fff;border:1.5px solid var(--line);border-radius:12px;padding:12px 22px;font-weight:800;font-size:14.5px;color:var(--muted);cursor:pointer;min-height:48px}
.rep-tab .ic{width:18px;height:18px}
.rep-tab.on{background:var(--navy);border-color:var(--navy);color:#fff}
</style>
<script>
document.querySelectorAll('.rep-tab').forEach(function(t){
  t.addEventListener('click',function(){
    document.querySelectorAll('.rep-tab').forEach(function(x){x.classList.toggle('on',x===t);});
    var ph=t.getAttribute('data-ph');
    document.querySelectorAll('.rep-phase').forEach(function(p){p.hidden=p.getAttribute('data-ph')!==ph;});
  });
});
</script>
<?php admin_footer(); ?>
