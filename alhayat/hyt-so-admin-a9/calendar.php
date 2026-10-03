<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('reviewer');
$pdo = db();

$monthInput = clean_text($_GET['month'] ?? date('Y-m'), 7);
if (!preg_match('/^\d{4}-\d{2}$/', $monthInput)) $monthInput = date('Y-m');
$month = DateTimeImmutable::createFromFormat('!Y-m', $monthInput, new DateTimeZone('Asia/Baghdad')) ?: new DateTimeImmutable('first day of this month');
$gridStart = $month->modify('-' . ((int) $month->format('N') - 1) . ' days');
$gridEnd = $gridStart->modify('+42 days');

$statement = $pdo->prepare('SELECT * FROM calendar_events WHERE event_date >= :start AND event_date < :end ORDER BY event_date, created_at');
$statement->execute(['start' => $gridStart->format('Y-m-d'), 'end' => $gridEnd->format('Y-m-d')]);
$events = $statement->fetchAll();
$byDay = [];
foreach ($events as $event) $byDay[substr((string) $event['event_date'], 0, 10)][] = $event;

$upcoming = $pdo->query("SELECT * FROM calendar_events WHERE event_date >= CURDATE() ORDER BY event_date LIMIT 8")->fetchAll();
$previous = $month->modify('-1 month')->format('Y-m');
$next = $month->modify('+1 month')->format('Y-m');
$colors = ['green' => 'أخضر', 'maroon' => 'خمري', 'blue' => 'أزرق', 'amber' => 'كهرماني'];

render_admin_start($admin, 'التقويم والفعاليات', 'calendar');
?>
<style>
  .cal-event.green{background:#e7f5ec;color:#12693f;border-inline-start:3px solid #1e9e5a}
  .cal-event.maroon{background:#fbeef0;color:#9c2b3e;border-inline-start:3px solid #b23a4d}
  .cal-event.blue{background:#e8f0fb;color:#1e4a9c;border-inline-start:3px solid #3a6ad4}
  .cal-event.amber{background:#fdf3e2;color:#8a5a12;border-inline-start:3px solid #d99a2b}
  .cal-event{display:block;text-decoration:none;font-size:.62rem;font-weight:700;padding:3px 6px;border-radius:6px;margin-top:3px;line-height:1.3;overflow:hidden}
</style>
<div class="admin-heading"><div><span>EVENTS CALENDAR</span><h1>تقويم <?= h($month->format('Y / m')) ?></h1><p>أضف فعاليات ومناسبات ومواعيد داخلية، وتظهر ملوّنة على التقويم.</p></div><div style="display:flex;gap:8px"><a class="admin-secondary" href="<?= h(admin_url('calendar.php?month=' . $previous)) ?>">← السابق</a><a class="admin-secondary" href="<?= h(admin_url('calendar.php?month=' . $next)) ?>">التالي →</a></div></div>

<?php if (isset($_GET['ok'])): ?><div style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#e7f5ec;color:#12693f;font-size:.82rem">تم الحفظ.</div><?php endif; ?>

<div class="admin-grid">
  <section class="admin-card wide"><div class="card-title"><div><span>ASIA / BAGHDAD</span><h2>الفعاليات</h2></div><em><?= count($events) ?> في هذا الشهر</em></div>
    <div class="calendar-grid">
      <?php foreach (['الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت','الأحد'] as $day): ?><div style="min-height:auto;background:#f3f7f4"><strong style="font-size:.65rem"><?= $day ?></strong></div><?php endforeach; ?>
      <?php for ($i = 0; $i < 42; $i++): $date = $gridStart->modify('+' . $i . ' days'); $key = $date->format('Y-m-d'); ?>
        <div class="<?= $date->format('m') !== $month->format('m') ? 'outside' : '' ?><?= $key === date('Y-m-d') ? ' is-today' : '' ?>">
          <span><?= h($date->format('d')) ?></span>
          <?php foreach ($byDay[$key] ?? [] as $event): ?>
            <a class="cal-event <?= h(array_key_exists($event['color'], $colors) ? $event['color'] : 'green') ?>" href="<?= h(admin_url('calendar.php?month=' . $monthInput . '&event=' . $event['id'])) ?>"><?= h($event['title']) ?></a>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
    </div>
  </section>

  <aside class="admin-card narrow"><div class="card-title"><div><span>NEW EVENT</span><h2>إضافة فعالية</h2></div></div>
    <form class="admin-form" method="post" action="<?= h(admin_url('api.php')) ?>">
      <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
      <input type="hidden" name="action" value="save_event">
      <input type="hidden" name="fallback" value="calendar.php?month=<?= h($monthInput) ?>">
      <label class="admin-field full"><span>العنوان</span><input name="title" maxlength="255" required></label>
      <label class="admin-field"><span>التاريخ</span><input name="event_date" type="date" value="<?= h($month->format('Y-m-d')) ?>" required></label>
      <label class="admin-field"><span>اللون</span><select name="color"><?php foreach ($colors as $val => $lbl): ?><option value="<?= h($val) ?>"><?= h($lbl) ?></option><?php endforeach; ?></select></label>
      <label class="admin-field full"><span>وصف (اختياري)</span><textarea name="description" rows="3" maxlength="2000"></textarea></label>
      <div class="admin-form-actions"><button class="admin-primary" type="submit">إضافة الفعالية</button></div>
    </form>
    <div class="card-title" style="margin-top:24px"><div><span>UPCOMING</span><h2>القادم</h2></div></div>
    <div class="page-list">
      <?php if (!$upcoming): ?><p style="color:#687a70;font-size:.74rem">لا توجد فعاليات قادمة.</p><?php endif; ?>
      <?php foreach ($upcoming as $event): ?>
        <a href="<?= h(admin_url('calendar.php?month=' . substr((string) $event['event_date'], 0, 7) . '&event=' . $event['id'])) ?>"><strong><?= h($event['title']) ?><span dir="ltr"><?= h(substr((string) $event['event_date'], 0, 10)) ?></span></strong></a>
      <?php endforeach; ?>
    </div>
  </aside>
</div>

<?php
$eventId = clean_text($_GET['event'] ?? '', 50);
if ($eventId !== '') {
    $s = $pdo->prepare('SELECT * FROM calendar_events WHERE id = :id');
    $s->execute(['id' => $eventId]);
    $ev = $s->fetch() ?: null;
    if ($ev):
?>
<section class="admin-card full" style="margin-top:18px"><div class="card-title"><div><span>EVENT DETAIL</span><h2><?= h($ev['title']) ?></h2></div><em dir="ltr"><?= h(substr((string) $ev['event_date'], 0, 10)) ?></em></div>
  <?php if (trim((string) $ev['description']) !== ''): ?><p style="color:#3a4f45;font-size:.9rem;line-height:1.8;white-space:pre-wrap"><?= h($ev['description']) ?></p><?php endif; ?>
  <form class="admin-form" method="post" action="<?= h(admin_url('api.php')) ?>" style="margin-top:10px">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <input type="hidden" name="action" value="delete_event">
    <input type="hidden" name="fallback" value="calendar.php?month=<?= h($monthInput) ?>">
    <input type="hidden" name="event_id" value="<?= h($ev['id']) ?>">
    <div class="admin-form-actions"><button class="admin-danger" type="submit" data-confirm="حذف هذه الفعالية؟">حذف الفعالية</button></div>
  </form>
</section>
<?php endif; } ?>
<?php render_admin_end(); ?>
