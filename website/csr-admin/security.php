<?php
require_once __DIR__ . '/inc/layout.php';
require_super();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');
    if ($act === 'unban') {
        q('DELETE FROM banned_ips WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        admin_audit('unban', 'id=' . (int)$_POST['id']);
        json_out(['ok' => true]);
    }
    if ($act === 'ban') {
        $ip = preg_replace('/[^0-9a-fA-F:.]/', '', (string)($_POST['ip'] ?? ''));
        if ($ip === '' || $ip === client_ip()) json_out(['ok' => false, 'msg' => 'عنوان غير صالح']);
        ip_ban($ip, 24 * 7, 'حظر يدوي من اللوحة');
        json_out(['ok' => true]);
    }
    if ($act === 'whitelist') {
        $wl = clean_text($_POST['admin_ip_whitelist'] ?? '', 500);
        setting_set('admin_ip_whitelist', $wl);
        admin_audit('whitelist_save', $wl);
        json_out(['ok' => true]);
    }
    if ($act === 'passwd') {
        $u = admin_require();
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $row = q_one('SELECT * FROM admins WHERE id = ?', [$u['id']]);
        if ($row === null || !password_verify($cur, $row['pass_hash'])) {
            json_out(['ok' => false, 'msg' => 'كلمة المرور الحالية غير صحيحة']);
        }
        if (strlen($new) < 10) json_out(['ok' => false, 'msg' => 'الجديدة: 10 أحرف على الأقل']);
        q('UPDATE admins SET pass_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]), $u['id']]);
        admin_audit('password_change');
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$sev = in_array($_GET['sev'] ?? '', ['high', 'medium', 'info'], true) ? $_GET['sev'] : '';
$w = $sev !== '' ? 'WHERE severity = ?' : '';
$params = $sev !== '' ? [$sev] : [];
$logs = q_all("SELECT * FROM security_log $w ORDER BY id DESC LIMIT 200", $params);
$bans = q_all('SELECT * FROM banned_ips WHERE until_at > NOW() ORDER BY id DESC');
$myIp = client_ip();

admin_header('الأمان والمراقبة', 'security');
?>
<div class="content-grid">

<div class="panel">
  <div class="panel-head"><h2>العناوين المحظورة (<?= count($bans) ?>)</h2></div>
  <form id="banForm" class="import-row">
    <input name="ip" dir="ltr" placeholder="حظر عنوان IP يدوياً" required>
    <button class="btn-danger btn-s">حظر</button>
  </form>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>العنوان</th><th>السبب</th><th>حتى</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($bans as $b): ?>
      <tr data-id="<?= (int)$b['id'] ?>">
        <td dir="ltr" class="mono"><?= e($b['ip']) ?></td>
        <td class="sub"><?= e($b['reason']) ?></td>
        <td class="mono"><?= e($b['until_at']) ?></td>
        <td class="row-actions"><button class="act" data-unban>فك الحظر</button></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$bans): ?><tr><td colspan="4" class="empty">لا عناوين محظورة حالياً</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>القائمة البيضاء للوحة التحكم</h2></div>
  <p class="hint">عند تعبئتها لا يمكن فتح اللوحة إلا من هذه العناوين (مفصولة بفواصل). عنوانك الحالي: <code dir="ltr"><?= e($myIp) ?></code><br>⚠ تأكد من إضافة عنوانك قبل الحفظ وإلا فُقد الوصول.</p>
  <form id="wlForm" class="import-row">
    <input name="admin_ip_whitelist" dir="ltr" value="<?= e(setting('admin_ip_whitelist')) ?>" placeholder="مثال: 85.203.4.7, 192.168.1.">
    <button class="btn-p btn-s">حفظ</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h2>تغيير كلمة المرور</h2></div>
  <form id="pwForm" class="grid2">
    <label>الحالية<input type="password" name="current" required></label>
    <label>الجديدة (10+ أحرف)<input type="password" name="new" required minlength="10"></label>
    <button class="btn-p">تغيير</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head">
    <h2>سجل المراقبة</h2>
    <div class="ftabs">
      <a class="ftab <?= $sev === '' ? 'on' : '' ?>" href="security.php">الكل</a>
      <a class="ftab <?= $sev === 'high' ? 'on' : '' ?>" href="?sev=high">تحذيرات عالية</a>
      <a class="ftab <?= $sev === 'medium' ? 'on' : '' ?>" href="?sev=medium">متوسطة</a>
      <a class="ftab <?= $sev === 'info' ? 'on' : '' ?>" href="?sev=info">معلومات</a>
    </div>
  </div>
  <div class="tbl-scroll">
  <table class="tbl tbl-log">
    <thead><tr><th>الوقت</th><th>العنوان</th><th>الحدث</th><th>التفاصيل</th><th>المستوى</th></tr></thead>
    <tbody>
      <?php foreach ($logs as $lg): ?>
      <tr>
        <td class="mono"><?= e(date('m/d H:i:s', strtotime($lg['ts']))) ?></td>
        <td dir="ltr" class="mono"><?= e($lg['ip']) ?></td>
        <td class="mono"><?= e($lg['action']) ?></td>
        <td class="sub" dir="ltr"><?= e(mb_substr((string)$lg['detail'], 0, 90)) ?></td>
        <td><span class="badge b-sev-<?= e($lg['severity']) ?>"><?= e($lg['severity']) ?></span></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="5" class="empty">السجل فارغ</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

</div>
<script>
function postSec(fd, cb) {
  fd.append('_csrf', window.CSRF);
  fetch('security.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
    .then(function (r) { return r.json(); }).then(cb)
    .catch(function () { window.toast('تعذر الاتصال', 1); });
}
document.getElementById('banForm').addEventListener('submit', function (ev) {
  ev.preventDefault();
  var fd = new FormData(this); fd.append('act', 'ban');
  postSec(fd, function (j) { window.toast(j.ok ? 'تم الحظر' : (j.msg || 'خطأ'), j.ok ? 0 : 1); if (j.ok) location.reload(); });
});
document.querySelectorAll('[data-unban]').forEach(function (b) {
  b.addEventListener('click', function () {
    var fd = new FormData(); fd.append('act', 'unban'); fd.append('id', b.closest('tr').getAttribute('data-id'));
    postSec(fd, function (j) { if (j.ok) b.closest('tr').remove(); });
  });
});
document.getElementById('wlForm').addEventListener('submit', function (ev) {
  ev.preventDefault();
  if (!confirm('تأكد أن عنوانك الحالي ضمن القائمة. حفظ؟')) return;
  var fd = new FormData(this); fd.append('act', 'whitelist');
  postSec(fd, function (j) { window.toast(j.ok ? 'تم الحفظ ✓' : 'خطأ', j.ok ? 0 : 1); });
});
document.getElementById('pwForm').addEventListener('submit', function (ev) {
  ev.preventDefault();
  var fd = new FormData(this); fd.append('act', 'passwd');
  postSec(fd, function (j) { window.toast(j.ok ? 'تم التغيير ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1); if (j.ok) ev.target.reset(); });
});
</script>
<?php admin_footer(); ?>
