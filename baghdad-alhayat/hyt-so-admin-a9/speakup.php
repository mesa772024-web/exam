<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
require APP_ROOT . '/includes/admin-layout.php';
$admin = require_admin('reviewer');
$pdo = db();

$filter = in_array($_GET['status'] ?? '', ['new', 'read', 'archived'], true) ? $_GET['status'] : 'all';
$sql = 'SELECT * FROM contact_messages';
$params = [];
if ($filter !== 'all') { $sql .= ' WHERE status = :s'; $params['s'] = $filter; }
$sql .= ' ORDER BY (status = "new") DESC, created_at DESC LIMIT 200';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$messages = $statement->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM contact_messages GROUP BY status")->fetchAll() as $row) {
    $counts[$row['status']] = (int) $row['c'];
}
$newCount = $counts['new'] ?? 0;

render_admin_start($admin, 'الرسائل الواردة', 'messages');
?>
<div class="admin-heading">
  <div><span>SPEAK UP · INBOX</span><h1>الرسائل الواردة</h1><p>رسائل الزوّار من نموذج «أسمِعنا صوتك». تصل مباشرة إلى المكتب وتُحفظ هنا.</p></div>
  <div><span class="status-pill"><?= $newCount ?> جديدة</span></div>
</div>

<?php if (isset($_GET['ok'])): ?><div style="margin-bottom:16px;padding:12px 16px;border-radius:12px;background:#f5e7ee;color:#691237;font-size:.82rem">تم التحديث.</div><?php endif; ?>

<div class="admin-card full" style="margin-bottom:16px">
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <?php
    $tabs = ['all' => 'الكل', 'new' => 'جديدة', 'read' => 'مقروءة', 'archived' => 'مؤرشفة'];
    foreach ($tabs as $key => $label):
        $active = $filter === $key;
        $badge = $key === 'all' ? array_sum($counts) : ($counts[$key] ?? 0);
    ?>
      <a href="<?= h(admin_url('speakup.php' . ($key === 'all' ? '' : '?status=' . $key))) ?>" class="<?= $active ? 'admin-primary' : 'admin-secondary' ?>" style="text-decoration:none"><?= h($label) ?> · <?= (int) $badge ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (!$messages): ?>
  <div class="admin-card full" style="text-align:center;padding:60px;color:#7a6870">لا توجد رسائل في هذا التصنيف بعد.</div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:14px">
    <?php foreach ($messages as $m):
        $status = (string) $m['status'];
        $hasEmail = trim((string) $m['email']) !== '';
    ?>
    <article class="admin-card full" style="border-inline-start:4px solid <?= $status === 'new' ? '#9e1e56' : ($status === 'archived' ? '#d3c3ca' : '#e5d5dc') ?>">
      <div style="display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap;align-items:flex-start">
        <div style="min-width:0">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <strong style="font-size:1rem;color:#240e17"><?= h($m['full_name']) ?></strong>
            <?php if ($status === 'new'): ?><span class="status-pill" style="background:#f5e7ee;color:#691237">جديدة</span><?php endif; ?>
            <?php if ($status === 'archived'): ?><span class="status-pill" style="background:#f2eef0;color:#7a6870">مؤرشفة</span><?php endif; ?>
          </div>
          <div style="font-size:.75rem;color:#7a6870;margin-top:6px" dir="ltr">
            <?php if ($hasEmail): ?><a href="mailto:<?= h($m['email']) ?>" style="color:#7a1440"><?= h($m['email']) ?></a><?php else: ?><span style="color:#b8a7af">بلا بريد</span><?php endif; ?>
            <?php if (trim((string) $m['phone']) !== ''): ?> · <a href="tel:<?= h($m['phone']) ?>" style="color:#7a1440"><?= h($m['phone']) ?></a><?php endif; ?>
            · <?= h(substr((string) $m['created_at'], 0, 16)) ?>
          </div>
        </div>
        <form method="post" action="<?= h(admin_url('api.php')) ?>" style="display:flex;gap:6px;align-items:center;flex-shrink:0">
          <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
          <input type="hidden" name="action" value="speakup_status">
          <input type="hidden" name="fallback" value="speakup.php<?= $filter === 'all' ? '' : '?status=' . h($filter) ?>">
          <input type="hidden" name="message_id" value="<?= h($m['id']) ?>">
          <select name="status" style="border:1px solid #e6dce1;border-radius:999px;padding:6px 12px;font-size:.75rem">
            <option value="new" <?= $status === 'new' ? 'selected' : '' ?>>جديدة</option>
            <option value="read" <?= $status === 'read' ? 'selected' : '' ?>>مقروءة</option>
            <option value="archived" <?= $status === 'archived' ? 'selected' : '' ?>>مؤرشفة</option>
          </select>
          <button class="admin-secondary" type="submit">حفظ</button>
        </form>
      </div>
      <?php if (trim((string) $m['subject']) !== ''): ?><div style="margin-top:12px;font-weight:700;color:#362029;font-size:.9rem"><?= h($m['subject']) ?></div><?php endif; ?>
      <p style="margin:8px 0 0;color:#4f3a43;font-size:.9rem;line-height:1.8;white-space:pre-wrap"><?= h($m['message']) ?></p>
    </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php render_admin_end(); ?>
