<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('viewer');
$pdo = db();
$today = gmdate('Y-m-d');
$since30 = gmdate('Y-m-d H:i:s', time() - 30 * 86400);
$countValue = static function (PDO $pdo, string $sql, array $values = []): int {
    $statement = $pdo->prepare($sql);
    $statement->execute($values);
    return (int) $statement->fetchColumn();
};
$stats = [
    'visits_today' => $countValue($pdo, 'SELECT COUNT(*) FROM visit_events WHERE DATE(created_at) = :today', ['today' => $today]),
    'unique_30' => $countValue($pdo, 'SELECT COUNT(DISTINCT session_hash) FROM visit_events WHERE created_at >= :since', ['since' => $since30]),
    'messages_new' => (int) $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn(),
    'messages_total' => (int) $pdo->query('SELECT COUNT(*) FROM contact_messages')->fetchColumn(),
    'pages' => (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE status = 'published'")->fetchColumn(),
];
// message status breakdown
$statusCounts = ['new' => 0, 'read' => 0, 'archived' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM contact_messages GROUP BY status")->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['c'];
}
// 14-day charts (visits + messages)
$visitStmt = $pdo->prepare('SELECT COUNT(*) FROM visit_events WHERE date(created_at) = :date');
$msgStmt = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE date(created_at) = :date');
$chart = [];
for ($offset = 13; $offset >= 0; $offset--) {
    $date = gmdate('Y-m-d', strtotime('-' . $offset . ' days'));
    $visitStmt->execute(['date' => $date]);
    $msgStmt->execute(['date' => $date]);
    $chart[] = ['date' => $date, 'visits' => (int) $visitStmt->fetchColumn(), 'messages' => (int) $msgStmt->fetchColumn()];
}
$visitMax = max(1, ...array_column($chart, 'visits'));
$msgMax = max(1, ...array_column($chart, 'messages'));
$recent = $pdo->query("SELECT full_name, email, phone, subject, message, status, created_at FROM contact_messages ORDER BY (status='new') DESC, created_at DESC LIMIT 8")->fetchAll();
$upcoming = $pdo->query("SELECT * FROM calendar_events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 6")->fetchAll();
render_admin_start($admin, 'نظرة عامة', 'dashboard');
?>
<style>
  .chart.msg i{background:linear-gradient(180deg,#b5344d,#7f2233)}
  .status-tiles{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
  .status-tile{border-radius:16px;padding:18px;border:1px solid var(--line,#e3ede7)}
  .status-tile b{display:block;font:800 1.9rem/1 var(--latin,Inter,sans-serif)}
  .status-tile span{font-size:.72rem;color:#687a70}
  .status-tile.new{background:#e7f5ec}.status-tile.new b{color:#12693f}
  .status-tile.read{background:#eef4f8}.status-tile.read b{color:#1e4a9c}
  .status-tile.archived{background:#f4f2ee}.status-tile.archived b{color:#7a6a4a}
  .msg-row{display:block;text-decoration:none;padding:12px 0;border-top:1px solid var(--line,#e3ede7)}
  .msg-row:first-child{border-top:0}
  .msg-row .top{display:flex;justify-content:space-between;gap:10px;align-items:center}
  .msg-row strong{color:#0e241b;font-size:.9rem}
  .msg-row .snip{color:#5c7268;font-size:.78rem;margin-top:4px;line-height:1.6}
  .msg-row time{color:#8aa196;font-size:.68rem}
</style>
<div class="admin-heading"><div><span>OPERATIONS OVERVIEW</span><h1>مرحباً، <?= h(explode(' ', $admin['display_name'])[0]) ?></h1><p>قراءة مباشرة لحركة الموقع والرسائل الواردة.</p></div><a class="admin-primary" href="<?= h(admin_url('content.php')) ?>">إنشاء محتوى جديد ＋</a></div>
<section class="metric-grid">
  <?php admin_metric('زيارات اليوم', $stats['visits_today'], 'مشاهدة صفحة مسجلة', 'green'); ?>
  <?php admin_metric('زوار 30 يوماً', $stats['unique_30'], 'زائر مميز'); ?>
  <?php admin_metric('رسائل جديدة', $stats['messages_new'], 'من نموذج «Speak Up»'); ?>
  <?php admin_metric('صفحات منشورة', $stats['pages'], 'على الموقع'); ?>
</section>
<div class="admin-grid">
  <section class="admin-card wide"><div class="card-title"><div><span>TRAFFIC</span><h2>الزيارات خلال 14 يوماً</h2></div><em><?= $stats['unique_30'] ?> زائراً مميزاً</em></div><div class="chart"><?php foreach ($chart as $day): ?><div><i style="--height:<?= (int) round($day['visits'] / $visitMax * 100) ?>%"></i><span><?= h(substr($day['date'], 5)) ?></span></div><?php endforeach; ?></div></section>
  <section class="admin-card narrow"><div class="card-title"><div><span>MESSAGES</span><h2>حالة الرسائل</h2></div><em><?= $stats['messages_total'] ?> إجمالاً</em></div>
    <div class="status-tiles">
      <div class="status-tile new"><b><?= $statusCounts['new'] ?></b><span>جديدة</span></div>
      <div class="status-tile read"><b><?= $statusCounts['read'] ?></b><span>مقروءة</span></div>
      <div class="status-tile archived"><b><?= $statusCounts['archived'] ?></b><span>مؤرشفة</span></div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:16px"><a class="admin-secondary" href="<?= h(admin_url('speakup.php')) ?>">فتح الرسائل</a></div>
  </section>
  <section class="admin-card wide"><div class="card-title"><div><span>SPEAK UP · 14 DAYS</span><h2>الرسائل الواردة يومياً</h2></div><em><?= array_sum(array_column($chart, 'messages')) ?> خلال أسبوعين</em></div><div class="chart msg"><?php foreach ($chart as $day): ?><div><i style="--height:<?= (int) round($day['messages'] / $msgMax * 100) ?>%"></i><span><?= h(substr($day['date'], 5)) ?></span></div><?php endforeach; ?></div></section>
  <section class="admin-card narrow"><div class="card-title"><div><span>CALENDAR</span><h2>فعاليات قادمة</h2></div><a class="admin-secondary" href="<?= h(admin_url('calendar.php')) ?>">التقويم</a></div><div class="page-list"><?php if (!$upcoming): ?><p style="color:#687a70;font-size:.74rem">لا توجد فعاليات قادمة.</p><?php endif; ?><?php foreach ($upcoming as $event): ?><a href="<?= h(admin_url('calendar.php?month=' . substr((string) $event['event_date'], 0, 7) . '&event=' . $event['id'])) ?>"><strong><?= h($event['title']) ?></strong><span dir="ltr"><?= h(substr((string) $event['event_date'], 0, 10)) ?></span></a><?php endforeach; ?></div></section>
  <section class="admin-card wide"><div class="card-title"><div><span>INBOX</span><h2>آخر الرسائل الواردة</h2></div><a class="admin-secondary" href="<?= h(admin_url('speakup.php')) ?>">عرض الكل</a></div>
    <div><?php if (!$recent): ?><p style="color:#687a70;font-size:.78rem">لا توجد رسائل بعد.</p><?php endif; ?>
      <?php foreach ($recent as $m): $hasEmail = trim((string) $m['email']) !== ''; ?>
        <div class="msg-row">
          <div class="top"><strong><?= h($m['full_name']) ?><?= $m['status'] === 'new' ? ' <span class="status-pill" style="background:#e7f5ec;color:#12693f">جديدة</span>' : '' ?></strong><time dir="ltr"><?= h(substr((string) $m['created_at'], 0, 16)) ?></time></div>
          <div class="snip"><?= h(mb_substr(trim((string) ($m['subject'] ? $m['subject'] . ' — ' : '') . $m['message']), 0, 110)) ?></div>
          <div class="snip" dir="ltr" style="color:#8aa196"><?= $hasEmail ? h($m['email']) : 'بلا بريد' ?><?= trim((string) $m['phone']) !== '' ? ' · ' . h($m['phone']) : '' ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <section class="admin-card narrow"><div class="card-title"><div><span>QUICK REPORTS</span><h2>تصدير التقارير</h2></div><em>CSV / PDF</em></div><p style="color:#687a70;font-size:.75rem">تقرير تنفيذي بالزيارات والرسائل الواردة خلال الفترة المختارة.</p><div style="display:flex;gap:8px;flex-wrap:wrap"><a class="admin-secondary" href="<?= h(admin_url('reports.php?format=csv&period=30')) ?>">CSV · 30 يوم</a><a class="admin-secondary" href="<?= h(admin_url('reports.php?format=pdf&period=30')) ?>">PDF · 30 يوم</a></div></section>
</div>
<?php render_admin_end(); ?>
