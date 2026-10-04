<?php
require_once __DIR__ . '/inc/layout.php';
require_super();
require_once dirname(__DIR__) . '/app/backup.php';

/* التنزيلات */
$dl = (string)($_GET['dl'] ?? '');
if ($dl !== '') {
    admin_audit('backup_download', $dl);
    if ($dl === 'content') {
        $data = backup_content_json();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="scf-content-' . date('Ymd-Hi') . '.json"');
        echo $data;
        exit;
    }
    if ($dl === 'full') {
        $data = backup_full_json();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="scf-full-' . date('Ymd-Hi') . '.json"');
        echo $data;
        exit;
    }
    if ($dl === 'sql') {
        $data = backup_sql_dump();
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="scf-db-' . date('Ymd-Hi') . '.sql"');
        echo $data;
        exit;
    }
    if ($dl === 'auto') {
        $f = basename((string)($_GET['f'] ?? ''));
        $path = SCF_STORAGE . '/backups/' . $f;
        if (preg_match('/^auto-[\d-]+\.json$/', $f) && is_file($path)) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $f . '"');
            readfile($path);
            exit;
        }
    }
    http_response_code(404);
    exit;
}

/* الاستيراد */
$importMsg = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    if (!empty($_FILES['backup_file']) && is_uploaded_file($_FILES['backup_file']['tmp_name'])) {
        if ($_FILES['backup_file']['size'] > 50 * 1024 * 1024) {
            json_out(['ok' => false, 'msg' => 'الملف كبير جداً']);
        }
        $raw = file_get_contents($_FILES['backup_file']['tmp_name']);
        $data = json_decode($raw, true);
        if (!is_array($data) || (($data['_meta']['site'] ?? '') !== 'scforum')) {
            json_out(['ok' => false, 'msg' => 'الملف ليس نسخة احتياطية صالحة لهذا الموقع']);
        }
        /* نسخة أمان تلقائية قبل الاستبدال */
        $dir = SCF_STORAGE . '/backups';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($dir . '/pre-restore-' . date('Ymd-His') . '.json', backup_full_json(), LOCK_EX);
        $r = backup_restore_content($data);
        admin_audit('backup_restore', implode(', ', $r['restored'] ?? []));
        json_out($r);
    }
    json_out(['ok' => false, 'msg' => 'اختر ملفاً'], 400);
}

$autoFiles = array_reverse(glob(SCF_STORAGE . '/backups/*.json') ?: []);
admin_header('النسخ الاحتياطي', 'backups');
?>
<div class="cards-grid cards-3">
  <a class="kcard k-blue" href="?dl=content"><b><?= icon('download') ?></b><span>نسخة المحتوى (JSON)<br><small class="sub">النصوص والمتحدثون والشركاء والأجندة والصفحات</small></span></a>
  <a class="kcard k-violet" href="?dl=full"><b><?= icon('download') ?></b><span>نسخة كاملة (JSON)<br><small class="sub">تشمل بيانات المسجّلين</small></span></a>
  <a class="kcard k-teal" href="?dl=sql"><b><?= icon('download') ?></b><span>قاعدة البيانات (SQL)<br><small class="sub">للاستعادة عبر phpMyAdmin</small></span></a>
</div>

<div class="panel">
  <div class="panel-head"><h2>استيراد نسخة محتوى</h2></div>
  <p class="hint">يستبدل الاستيراد محتوى الموقع الحالي (لا يمسّ بيانات المسجّلين). تُؤخذ نسخة أمان تلقائياً قبل التنفيذ.</p>
  <form id="importForm" class="import-row">
    <input type="file" name="backup_file" accept=".json" required>
    <button class="btn-p">استيراد</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h2>النسخ التلقائية <small class="sub">تُنشأ يومياً عند أول دخول، والاحتفاظ بآخر 30</small></h2></div>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>الملف</th><th>الحجم</th><th></th></tr></thead>
    <tbody>
      <?php foreach (array_slice($autoFiles, 0, 40) as $f): $bn = basename($f); ?>
      <tr>
        <td dir="ltr" class="mono"><?= e($bn) ?></td>
        <td class="mono"><?= e(number_format(filesize($f) / 1024, 1)) ?> KB</td>
        <td class="row-actions"><a class="act act-ok" href="?dl=auto&f=<?= e(urlencode($bn)) ?>"><?= icon('download') ?> تنزيل</a></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$autoFiles): ?><tr><td colspan="3" class="empty">ستظهر النسخ التلقائية هنا</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
document.getElementById('importForm').addEventListener('submit', function (ev) {
  ev.preventDefault();
  if (!confirm('سيتم استبدال محتوى الموقع الحالي بمحتوى النسخة. متابعة؟')) return;
  var fd = new FormData(this);
  fd.append('_csrf', window.CSRF);
  fetch('backups.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}})
    .then(function (r) { return r.json(); })
    .then(function (j) {
      window.toast(j.ok ? 'تم الاستيراد ✓' : (j.msg || 'خطأ'), j.ok ? 0 : 1);
      if (j.ok) setTimeout(function () { location.reload(); }, 1200);
    })
    .catch(function () { window.toast('تعذر الاتصال', 1); });
});
</script>
<?php admin_footer(); ?>
