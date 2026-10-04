<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

$tot   = (int)q_val('SELECT COUNT(*) FROM registrants');
$appr  = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status = 'approved'");
$pend  = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status = 'pending'");
$rej   = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status = 'rejected'");
$today = (int)q_val('SELECT COUNT(*) FROM registrants WHERE DATE(created_at) = CURDATE()');
$waNot = (int)q_val("SELECT COUNT(*) FROM registrants WHERE status = 'approved' AND wa_sent = 0");
$att   = (int)q_val('SELECT COUNT(*) FROM registrants WHERE attended = 1');
$secH  = (int)q_val("SELECT COUNT(*) FROM security_log WHERE severity = 'high' AND ts > DATE_SUB(NOW(), INTERVAL 7 DAY)");
$latest = q_all('SELECT id, code, full_name, phone, status, created_at FROM registrants ORDER BY id DESC LIMIT 8');
$regOpen = setting('reg_open', '1') === '1';

/* ---------- تقارير ---------- */
// آخر 14 يوماً
$daily = [];
for ($i = 13; $i >= 0; $i--) $daily[date('Y-m-d', strtotime("-$i day"))] = 0;
foreach (q_all("SELECT DATE(created_at) d, COUNT(*) c FROM registrants WHERE created_at > DATE_SUB(NOW(), INTERVAL 14 DAY) GROUP BY DATE(created_at)") as $row) {
    if (isset($daily[$row['d']])) $daily[$row['d']] = (int)$row['c'];
}
$maxDaily = max(1, max($daily ?: [1]));
$bySector = q_all("SELECT COALESCE(NULLIF(sector,''),'غير محدد') s, COUNT(*) c FROM registrants GROUP BY s ORDER BY c DESC LIMIT 6");
$byCity   = q_all("SELECT COALESCE(NULLIF(city,''),'غير محدد') s, COUNT(*) c FROM registrants GROUP BY s ORDER BY c DESC LIMIT 6");
$male   = (int)q_val("SELECT COUNT(*) FROM registrants WHERE gender='male'");
$female = (int)q_val("SELECT COUNT(*) FROM registrants WHERE gender='female'");
$maxSector = max(1, (int)($bySector[0]['c'] ?? 1));
$maxCity   = max(1, (int)($byCity[0]['c'] ?? 1));

$spNew = speakup_unread_count();
$postsCount = 0;
try { require_once dirname(__DIR__) . '/app/posts.php'; $postsCount = count(scf_posts_list(false)); } catch (Throwable $e) {}
admin_header('الرئيسية', 'dashboard');
?>
<div class="quick-live">
  <a class="ql-main" href="../?edit=1" target="_blank"><span class="ql-ico"><?= icon('edit') ?></span><b>تعديل الموقع مباشرة</b><small>اضغط أي نص (عربي / English) أو صورة في الموقع وعدّلها مكانها، ورتّب الأقسام.</small></a>
  <a href="posts.php"><span class="ql-ico"><?= icon('image') ?></span><b>المنشورات والتغطية</b><small><?= $postsCount ?> منشور — إضافة، تعديل، صور، تقديم وتأخير.</small></a>
  <a href="texts.php"><span class="ql-ico"><?= icon('doc') ?></span><b>نصوص الواجهة</b><small>كل نصوص الموقع باللغتين في مكان واحد مع بحث.</small></a>
  <a href="media.php"><span class="ql-ico"><?= icon('camera') ?></span><b>الصور والفيديو</b><small>الشعار، فيديو العرض، الغلاف، وصور الأقسام.</small></a>
  <a href="speakups.php"><?php if ($spNew): ?><em><?= $spNew ?> جديدة</em><?php endif; ?><span class="ql-ico"><?= icon('mail') ?></span><b>آراء الزوار</b><small>رسائل «شاركنا تجربتك · Speak Up».</small></a>
  <a href="updates.php"><span class="ql-ico"><?= icon('refresh') ?></span><b>التحديثات والتنزيلات</b><small>رفع تحديث مباشر، رجوع، تحميل الملفات وقاعدة البيانات.</small></a>
</div>
<div class="cards-grid">
  <a class="kcard k-blue" href="registrants.php"><b><?= $tot ?></b><span>إجمالي المسجّلين</span></a>
  <a class="kcard k-green" href="registrants.php?status=approved"><b><?= $appr ?></b><span>المقبولون</span></a>
  <a class="kcard k-amber" href="registrants.php?status=pending"><b><?= $pend ?></b><span>قيد المراجعة</span></a>
  <a class="kcard k-red" href="registrants.php?status=rejected"><b><?= $rej ?></b><span>المرفوضون</span></a>
  <a class="kcard k-cyan" href="registrants.php?wa=0&status=approved"><b><?= $waNot ?></b><span>بانتظار إرسال واتساب</span></a>
  <a class="kcard k-violet" href="registrants.php"><b><?= $today ?></b><span>مسجّلو اليوم</span></a>
  <a class="kcard k-teal" href="checkin.php"><b><?= $att ?></b><span>الحضور الفعلي</span></a>
  <a class="kcard <?= $secH > 0 ? 'k-red' : 'k-gray' ?>" href="security.php"><b><?= $secH ?></b><span>تنبيهات أمنية (7 أيام)</span></a>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>إجراءات سريعة</h2>
  </div>
  <div class="quick-row">
    <a class="btn-p" href="registrants.php?status=pending">مراجعة الطلبات الجديدة</a>
    <a class="btn-s" href="speakers.php?new=1">+ متحدث جديد</a>
    <a class="btn-s" href="orgs.php?kind=sponsor&new=1">+ راعٍ جديد</a>
    <a class="btn-s" href="orgs.php?kind=partner&new=1">+ شريك جديد</a>
    <a class="btn-s" href="pages.php">+ صفحة جديدة</a>
    <a class="btn-s" href="backups.php">تنزيل نسخة احتياطية</a>
    <button class="btn-s" data-post="registrant-api.php" data-action="toggle_reg" id="regToggle">
      <?= $regOpen ? icon('pause') . ' إيقاف التسجيل' : icon('play') . ' فتح التسجيل' ?>
    </button>
  </div>
</div>

<!-- ============ التقارير ============ -->
<div class="reports-grid">
  <div class="panel report-wide">
    <div class="panel-head"><h2><?= icon('chart') ?> التسجيلات خلال 14 يوماً</h2><span class="rep-total"><?= array_sum($daily) ?> تسجيل</span></div>
    <div class="bars-14">
      <?php foreach ($daily as $d => $c): ?>
        <div class="bar14" title="<?= e($d) ?>: <?= $c ?>">
          <div class="bar14-fill" style="height:<?= $c ? max(6, round($c / $maxDaily * 100)) : 2 ?>%"><span><?= $c ?: '' ?></span></div>
          <small><?= e(date('m/d', strtotime($d))) ?></small>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= icon('users') ?> حسب الجنس</h2></div>
    <?php $gt = max(1, $male + $female); ?>
    <div class="gender-split">
      <div class="gs-bar"><span class="gs-m" style="width:<?= round($male / $gt * 100) ?>%"></span><span class="gs-f" style="width:<?= round($female / $gt * 100) ?>%"></span></div>
      <div class="gs-legend">
        <span><i class="dot-m"></i> ذكور <b><?= $male ?></b></span>
        <span><i class="dot-f"></i> إناث <b><?= $female ?></b></span>
      </div>
    </div>
    <div class="funnel">
      <div class="fn-row"><span>الإجمالي</span><div class="fn-bar" style="width:100%"><b><?= $tot ?></b></div></div>
      <div class="fn-row"><span>مقبول</span><div class="fn-bar fn-ok" style="width:<?= $tot ? round($appr / max(1, $tot) * 100) : 0 ?>%"><b><?= $appr ?></b></div></div>
      <div class="fn-row"><span>حاضر</span><div class="fn-bar fn-att" style="width:<?= $tot ? round($att / max(1, $tot) * 100) : 0 ?>%"><b><?= $att ?></b></div></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= icon('grid') ?> أعلى القطاعات</h2></div>
    <div class="hbars">
      <?php foreach ($bySector as $s): ?>
        <div class="hbar"><span class="hbar-label"><?= e($s['s']) ?></span><div class="hbar-track"><div class="hbar-fill" style="width:<?= round($s['c'] / $maxSector * 100) ?>%"></div></div><b><?= (int)$s['c'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$bySector): ?><p class="empty">لا بيانات</p><?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2><?= icon('pin') ?> أعلى المدن</h2></div>
    <div class="hbars">
      <?php foreach ($byCity as $s): ?>
        <div class="hbar"><span class="hbar-label"><?= e($s['s']) ?></span><div class="hbar-track"><div class="hbar-fill hbar-city" style="width:<?= round($s['c'] / $maxCity * 100) ?>%"></div></div><b><?= (int)$s['c'] ?></b></div>
      <?php endforeach; ?>
      <?php if (!$byCity): ?><p class="empty">لا بيانات</p><?php endif; ?>
    </div>
  </div>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>أحدث المسجّلين</h2>
    <a class="btn-s" href="registrants.php">عرض الكل</a>
  </div>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>الرمز</th><th>الاسم</th><th>الهاتف</th><th>الحالة</th><th>التاريخ</th></tr></thead>
    <tbody>
      <?php foreach ($latest as $r): ?>
      <tr>
        <td dir="ltr" class="mono"><?= e($r['code']) ?></td>
        <td><b><?= e($r['full_name']) ?></b></td>
        <td dir="ltr" class="mono">+<?= e($r['phone']) ?></td>
        <td><span class="badge b-<?= e($r['status']) ?>"><?= $r['status'] === 'approved' ? 'مقبول' : ($r['status'] === 'rejected' ? 'مرفوض' : 'قيد المراجعة') ?></span></td>
        <td class="mono"><?= e(date('m/d H:i', strtotime($r['created_at']))) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$latest): ?><tr><td colspan="5" class="empty">لا يوجد مسجّلون بعد</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>
<?php admin_footer(); ?>
