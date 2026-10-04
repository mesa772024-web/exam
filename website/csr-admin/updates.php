<?php
require_once __DIR__ . '/inc/layout.php';
$admin = require_super();
require_once dirname(__DIR__) . '/app/update_manager.php';
require_once dirname(__DIR__) . '/app/backup.php';

/* ---------- التنزيلات ---------- */
if (isset($_GET['download'])) {
    $what = (string)$_GET['download'];
    if (!hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) { http_response_code(419); exit('انتهت صلاحية الرابط، حدّث الصفحة.'); }
    @set_time_limit(600);
    $stamp = date('Ymd-His');
    if ($what === 'skill') {
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="Skill.md"');
        readfile(dirname(__DIR__) . '/app/docs/Skill.md');
        exit;
    }
    if ($what === 'db') {
        admin_audit('download_db');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="scforum-database-' . $stamp . '.sql"');
        echo backup_sql_dump();
        exit;
    }
    try {
        if ($what === 'template') { $file = scf_update_template_zip(); $name = 'scforum-update-template.zip'; }
        elseif ($what === 'site') { $file = scf_site_zip(!empty($_GET['uploads'])); $name = 'scforum-site-files-' . $stamp . '.zip'; admin_audit('download_site'); }
        elseif ($what === 'full') { $file = scf_site_zip(true, backup_sql_dump()); $name = 'scforum-full-backup-' . $stamp . '.zip'; admin_audit('download_full'); }
        else { http_response_code(404); exit; }
    } catch (Throwable $e) {
        http_response_code(500); exit('تعذر التجهيز: ' . e($e->getMessage()));
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($file));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    readfile($file);
    @unlink($file);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $action = (string)($_POST['action'] ?? '');

    /* 1) رفع وتحليل الحزمة (بدون تثبيت) */
    if ($action === 'analyze') {
        if (!rate_limit('admin_system_update', 30, 3600)) json_out(['ok' => false, 'msg' => 'محاولات كثيرة، حاول لاحقاً'], 429);
        if (empty($_FILES['update_file']) || !is_uploaded_file($_FILES['update_file']['tmp_name'])) json_out(['ok' => false, 'msg' => 'لم يصل الملف (قد يكون أكبر من حد الاستضافة ' . ini_get('upload_max_filesize') . ')'], 400);
        $f = $_FILES['update_file'];
        if ($f['error'] !== UPLOAD_ERR_OK) json_out(['ok' => false, 'msg' => 'فشل الرفع (' . (int)$f['error'] . ')'], 400);
        if (strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)) !== 'zip') json_out(['ok' => false, 'msg' => 'يجب اختيار ملف ZIP'], 422);
        $token = bin2hex(random_bytes(12));
        $dest = scf_update_staging_dir() . '/' . $token . '.zip';
        if (!move_uploaded_file($f['tmp_name'], $dest)) json_out(['ok' => false, 'msg' => 'تعذر حفظ الحزمة مؤقتاً'], 500);
        try {
            $an = scf_update_analyze($dest);
        } catch (Throwable $e) {
            @unlink($dest);
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        $_SESSION['upd_staged'] = ['token' => $token, 'name' => basename((string)$f['name'])];
        $list = [];
        foreach ($an['files'] as $p => $x) $list[] = ['path' => $p, 'status' => $x['status'], 'size' => $x['size']];
        json_out(['ok' => true, 'token' => $token, 'files' => $list, 'deletes' => $an['deletes'], 'warnings' => $an['warnings'],
            'version' => (string)($an['manifest']['version'] ?? ''), 'title' => (string)($an['manifest']['title'] ?? ''), 'notes' => (string)($an['manifest']['notes'] ?? ''),
            'migrate' => !empty($an['manifest']['migrate']), 'current' => scf_update_current_version()]);
    }

    /* 2) تثبيت الحزمة التي حُلّلت */
    if ($action === 'install') {
        $staged = $_SESSION['upd_staged'] ?? null;
        $token = (string)($_POST['token'] ?? '');
        if (!$staged || !hash_equals((string)$staged['token'], $token) || !preg_match('/^[a-f0-9]{24}$/', $token)) json_out(['ok' => false, 'msg' => 'ارفع الحزمة مرة أخرى'], 422);
        $zip = scf_update_staging_dir() . '/' . $token . '.zip';
        if (!is_file($zip)) json_out(['ok' => false, 'msg' => 'انتهت صلاحية الحزمة، ارفعها مرة أخرى'], 422);
        $lock = (int)q_val("SELECT GET_LOCK('scforum_code_update', 10)");
        if ($lock !== 1) json_out(['ok' => false, 'msg' => 'هناك تحديث آخر قيد التثبيت'], 409);
        try {
            $res = scf_update_install($zip, (string)$staged['name'], (int)$admin['id'], !empty($_POST['db_backup']));
            admin_audit('system_update', $res['previous'] . ' -> ' . $res['version'] . ' files=' . $res['files']);
        } catch (Throwable $e) {
            sec_log('admin_update_failed', clean_text($e->getMessage(), 500), 'high');
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        } finally {
            q_val("SELECT RELEASE_LOCK('scforum_code_update')");
            @unlink($zip);
            unset($_SESSION['upd_staged']);
        }
        json_out($res);
    }

    if ($action === 'cancel') {
        $staged = $_SESSION['upd_staged'] ?? null;
        if ($staged && preg_match('/^[a-f0-9]{24}$/', (string)$staged['token'])) @unlink(scf_update_staging_dir() . '/' . $staged['token'] . '.zip');
        unset($_SESSION['upd_staged']);
        json_out(['ok' => true]);
    }

    if ($action === 'rollback') {
        try {
            $res = scf_update_rollback((int)($_POST['id'] ?? 0), (int)$admin['id']);
            admin_audit('system_rollback', 'id=' . (int)$_POST['id']);
        } catch (Throwable $e) {
            json_out(['ok' => false, 'msg' => $e->getMessage()], 422);
        }
        json_out($res);
    }

    if ($action === 'migrate_db') {
        $backupDir = SCF_STORAGE . '/backups';
        if (!is_dir($backupDir)) @mkdir($backupDir, 0750, true);
        @file_put_contents($backupDir . '/pre-db-update-' . date('Ymd-His') . '.sql', backup_sql_dump(), LOCK_EX);
        try {
            require_once dirname(__DIR__) . '/app/migrate.php';
            $done = scf_migrate();
        } catch (Throwable $e) {
            json_out(['ok' => false, 'msg' => 'فشل تحديث قاعدة البيانات؛ النسخة الاحتياطية محفوظة'], 500);
        }
        admin_audit('database_update', 'steps=' . count($done));
        json_out(['ok' => true, 'msg' => 'تم فحص وتحديث قاعدة البيانات (' . count($done) . ' خطوة)']);
    }
    json_out(['ok' => false], 400);
}

$currentVersion = scf_update_current_version();
$history = [];
try { $history = q_all('SELECT su.*, a.username FROM system_updates su LEFT JOIN admins a ON a.id=su.admin_id ORDER BY su.id DESC LIMIT 40'); } catch (Throwable $e) {}
$lastInstall = '';
foreach ($history as $h) if ($h['status'] === 'installed') { $lastInstall = $h['installed_at']; break; }
$t = urlencode(csrf_token());
$statusNames = ['installed' => ['تم', 'b-approved'], 'failed' => ['فشل', 'b-rejected'], 'rolled_back' => ['تم الرجوع عنه', 'b-pending'], 'rollback' => ['رجوع', 'b-pending']];

admin_header('التحديثات والتنزيلات', 'updates');
?>
<style>
.up-kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:16px}
.up-kpi{background:#fff;border:1px solid #e3eaf2;border-radius:14px;padding:14px 16px}
.up-kpi b{display:block;font-size:18px;color:#24275F}.up-kpi span{font-size:12px;color:#7b8a9c}
.up-drop{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;border:2px dashed #b9cfdd;border-radius:16px;padding:34px 16px;text-align:center;background:#f7fbfe;cursor:pointer;transition:background .2s,border-color .2s}
.up-drop.drag{background:#e6f3fb;border-color:#B08A3A}
.up-drop .ic{width:38px;height:38px;color:#196598}
.up-drop b{font-size:16px;color:#24275F}.up-drop small{color:#7b8a9c}
.up-review{margin-top:16px;border:1px solid #e3eaf2;border-radius:14px;overflow:hidden}
.up-review header{background:#f4f9fd;padding:12px 16px;display:flex;flex-wrap:wrap;gap:10px 18px;align-items:center}
.up-review header b{color:#24275F}
.up-files{max-height:340px;overflow:auto}
.up-files div{display:flex;gap:10px;align-items:center;padding:7px 16px;border-top:1px solid #f0f4f8;font-size:13px}
.up-files code{direction:ltr;unicode-bidi:isolate;flex:1;text-align:left;color:#33485a}
.st{border-radius:20px;padding:1px 9px;font-size:11px;white-space:nowrap}
.st-new{background:#e3f6ec;color:#1b8a5b}.st-changed{background:#fff3dc;color:#8a5a00}.st-same{background:#eef2f6;color:#8a97a6}.st-del{background:#fde8e6;color:#c0392b}
.up-warn{background:#fff8e6;color:#8a5a00;padding:10px 16px;font-size:13px;border-top:1px solid #f5e3b8}
.up-foot{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;border-top:1px solid #e3eaf2}
.up-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:12px}
.up-card{border:1px solid #e3eaf2;border-radius:14px;padding:16px;background:#fff;display:flex;flex-direction:column;gap:8px}
.up-card b{color:#24275F}.up-card p{margin:0;font-size:13px;color:#5b6b80;flex:1;line-height:1.8}
.up-card a{align-self:flex-start}
.up-result{margin-top:12px;padding:12px 16px;border-radius:12px;font-weight:600}
.up-result.ok{background:#e3f6ec;color:#1b8a5b}.up-result.bad{background:#fde8e6;color:#c0392b}
</style>

<div class="up-kpis">
  <div class="up-kpi"><b dir="ltr"><?= e($currentVersion) ?></b><span>الإصدار الحالي</span></div>
  <div class="up-kpi"><b dir="ltr"><?= e($lastInstall ?: '—') ?></b><span>آخر تحديث</span></div>
  <div class="up-kpi"><b><?= count($history) ?></b><span>عمليات في السجل (مع نسخ رجوع)</span></div>
  <div class="up-kpi"><b dir="ltr"><?= e((string)ini_get('upload_max_filesize')) ?></b><span>حد الرفع في الاستضافة</span></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>تثبيت تحديث مباشر</h2><a class="ftab" href="?download=skill&amp;t=<?= $t ?>">📘 Skill.md</a></div>
  <p class="hint">ارفع ملف ZIP يحتوي الملفات المعدّلة بمساراتها من جذر الموقع. ستظهر لك قائمة الملفات (جديد / معدّل / بدون تغيير) قبل التثبيت. يُفحص كل ملف PHP صياغياً، وتُحفظ نسخة رجوع تلقائياً. لا حاجة لأي توقيع.</p>
  <label class="up-drop" id="upDrop">
    <?= icon('upload') ?><b id="upName">اسحب ملف التحديث هنا أو اضغط للاختيار</b><small>ZIP فقط — راجع «Skill.md» لطريقة كتابة التحديث</small>
    <input type="file" id="upFile" accept=".zip,application/zip" hidden>
  </label>
  <div id="upReview"></div>
  <div id="upResult"></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>التنزيلات والنسخ الاحتياطية</h2></div>
  <div class="up-cards">
    <div class="up-card"><b>🗄️ قاعدة البيانات (SQL)</b><p>كل الجداول: المسجلون، المحتوى، المنشورات، النصوص، الرسائل… يمكن استيرادها من phpMyAdmin.</p><a class="btn-p btn-s" href="?download=db&amp;t=<?= $t ?>">تحميل SQL</a></div>
    <div class="up-card"><b>📦 ملفات الموقع (ZIP)</b><p>كل ملفات الموقع مع الصور المرفوعة، بدون بيانات الاتصال والنسخ الاحتياطية — جاهزة لإعادة التثبيت.</p><a class="btn-p btn-s" href="?download=site&amp;uploads=1&amp;t=<?= $t ?>">تحميل الملفات</a></div>
    <div class="up-card"><b>🧰 نسخة كاملة</b><p>الملفات + ملف قاعدة البيانات داخل <code dir="ltr">_backup/database.sql</code> في ZIP واحد.</p><a class="btn-p btn-s" href="?download=full&amp;t=<?= $t ?>">تحميل النسخة الكاملة</a></div>
    <div class="up-card"><b>📘 دليل التحديثات (Skill.md)</b><p>القواعد التي يجب اتباعها عند كتابة أي تحديث — أعطه للمطوّر أو للذكاء الاصطناعي.</p><div style="display:flex;gap:6px;flex-wrap:wrap"><a class="btn-p btn-s" href="?download=skill&amp;t=<?= $t ?>">Skill.md</a><a class="act" href="?download=template&amp;t=<?= $t ?>">قالب تحديث ZIP</a></div></div>
  </div>
</div>

<div class="panel">
  <div class="panel-head"><h2>قاعدة البيانات</h2></div>
  <p class="hint">فحص الجداول وإضافة ما ينقص (آمن للتكرار) مع نسخة احتياطية تلقائية قبل البدء.</p>
  <button class="btn-p btn-s" id="migrateBtn"><?= icon('database') ?> فحص وتحديث قاعدة البيانات</button>
</div>

<div class="panel">
  <div class="panel-head"><h2>سجل التحديثات</h2></div>
  <div class="tbl-scroll">
    <table class="tbl">
      <thead><tr><th>الإصدار</th><th>السابق</th><th>الملفات</th><th>الحالة</th><th>الوصف</th><th>الأدمن</th><th>التاريخ</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($history as $row): $st = $statusNames[$row['status']] ?? [$row['status'], 'b-pending']; ?>
        <tr>
          <td class="mono" dir="ltr"><?= e($row['version']) ?></td>
          <td class="mono" dir="ltr"><?= e($row['previous_version']) ?></td>
          <td><?= (int)$row['file_count'] ?></td>
          <td><span class="badge <?= e($st[1]) ?>"><?= e($st[0]) ?></span></td>
          <td class="sub"><?= e(mb_strimwidth((string)($row['detail'] ?? ''), 0, 80, '…')) ?></td>
          <td><?= e($row['username'] ?: '—') ?></td>
          <td class="mono" dir="ltr"><?= e($row['installed_at']) ?></td>
          <td><?php if ($row['status'] === 'installed' && strpos((string)$row['backup_path'], 'update-backups/') === 0): ?><button class="act" data-rollback="<?= (int)$row['id'] ?>">رجوع</button><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$history): ?><tr><td colspan="8" class="empty">لا توجد تحديثات سابقة</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
(function () {
  var drop = document.getElementById('upDrop'), file = document.getElementById('upFile'), review = document.getElementById('upReview'), result = document.getElementById('upResult'), nameEl = document.getElementById('upName');
  var stNames = {'new': 'جديد', 'changed': 'معدّل', 'same': 'بدون تغيير'};
  function send(fd) { fd.append('_csrf', window.CSRF); return fetch('updates.php', {method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With': 'fetch'}}).then(function (r) { return r.json().catch(function () { return {ok: false, msg: 'استجابة غير متوقعة (' + r.status + ')'}; }); }); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
  function show(msg, ok) { result.innerHTML = '<div class="up-result ' + (ok ? 'ok' : 'bad') + '">' + esc(msg) + '</div>'; }
  function analyze(f) {
    if (!f) return;
    nameEl.textContent = f.name + ' — جارٍ الفحص…'; review.innerHTML = ''; result.innerHTML = '';
    var fd = new FormData(); fd.append('action', 'analyze'); fd.append('update_file', f);
    send(fd).then(function (j) {
      nameEl.textContent = f.name;
      if (!j.ok) { show(j.msg || 'تعذر فحص الحزمة', false); return; }
      var counts = {new: 0, changed: 0, same: 0};
      j.files.forEach(function (x) { counts[x.status]++; });
      var h = '<div class="up-review"><header><b>' + esc(j.title || f.name) + '</b><span>الإصدار: <code dir="ltr">' + esc(j.version || 'تلقائي') + '</code> (الحالي ' + esc(j.current) + ')</span>' +
        '<span class="st st-new">جديد ' + counts.new + '</span><span class="st st-changed">معدّل ' + counts.changed + '</span><span class="st st-same">بدون تغيير ' + counts.same + '</span>' +
        (j.deletes.length ? '<span class="st st-del">حذف ' + j.deletes.length + '</span>' : '') + (j.migrate ? '<span class="st st-changed">+ تحديث قاعدة البيانات</span>' : '') + '</header>';
      if (j.notes) h += '<div style="padding:10px 16px;font-size:13px;color:#33485a">' + esc(j.notes) + '</div>';
      if (j.warnings.length) h += '<div class="up-warn">⚠ ' + j.warnings.map(esc).join('<br>⚠ ') + '</div>';
      h += '<div class="up-files">';
      j.files.forEach(function (x) { h += '<div><span class="st st-' + x.status + '">' + stNames[x.status] + '</span><code>' + esc(x.path) + '</code><small>' + Math.max(1, Math.round(x.size / 1024)) + ' KB</small></div>'; });
      j.deletes.forEach(function (p) { h += '<div><span class="st st-del">حذف</span><code>' + esc(p) + '</code></div>'; });
      h += '</div><div class="up-foot"><label class="chkline"><input type="checkbox" id="dbBk" checked> نسخة من قاعدة البيانات قبل التثبيت</label><span style="flex:1"></span>' +
        '<button class="act" id="upCancel">إلغاء</button><button class="btn-p" id="upInstall"' + (counts.new + counts.changed + j.deletes.length ? '' : ' disabled') + '>تثبيت التحديث الآن</button></div></div>';
      review.innerHTML = h;
      document.getElementById('upCancel').onclick = function () { var c = new FormData(); c.append('action', 'cancel'); send(c); review.innerHTML = ''; nameEl.textContent = 'اسحب ملف التحديث هنا أو اضغط للاختيار'; file.value = ''; };
      document.getElementById('upInstall').onclick = function () {
        var b = this; b.disabled = true; b.textContent = 'جارٍ التثبيت…';
        var i = new FormData(); i.append('action', 'install'); i.append('token', j.token); if (document.getElementById('dbBk').checked) i.append('db_backup', '1');
        send(i).then(function (r) {
          if (r.ok) { review.innerHTML = ''; show('تم التحديث إلى ' + r.version + ' ✓ — ' + r.files + ' ملف' + (r.deleted ? '، حُذف ' + r.deleted : '') + (r.migrated ? '، وتم تحديث قاعدة البيانات' : ''), true); setTimeout(function () { location.reload(); }, 2200); }
          else { show(r.msg || 'فشل التثبيت — لم يتغيّر الموقع', false); b.disabled = false; b.textContent = 'تثبيت التحديث الآن'; }
        }).catch(function () { show('تعذر الاتصال', false); b.disabled = false; });
      };
    }).catch(function () { nameEl.textContent = f.name; show('تعذر الاتصال (قد يكون الملف أكبر من حد الاستضافة)', false); });
  }
  file.addEventListener('change', function () { analyze(file.files[0]); });
  drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('drag'); });
  drop.addEventListener('dragleave', function () { drop.classList.remove('drag'); });
  drop.addEventListener('drop', function (e) { e.preventDefault(); drop.classList.remove('drag'); analyze(e.dataTransfer.files[0]); });
  document.querySelectorAll('[data-rollback]').forEach(function (b) {
    b.addEventListener('click', function () {
      if (!confirm('إرجاع ملفات الموقع إلى ما قبل هذا التحديث؟')) return;
      var fd = new FormData(); fd.append('action', 'rollback'); fd.append('id', b.dataset.rollback);
      send(fd).then(function (j) { window.toast(j.ok ? 'تم الرجوع ✓ (' + j.files + ' ملف)' : (j.msg || 'تعذر الرجوع'), j.ok ? 0 : 1); if (j.ok) setTimeout(function () { location.reload(); }, 1200); });
    });
  });
  document.getElementById('migrateBtn').addEventListener('click', function () {
    if (!confirm('أخذ نسخة احتياطية وفحص قاعدة البيانات الآن؟')) return;
    var fd = new FormData(); fd.append('action', 'migrate_db');
    send(fd).then(function (j) { window.toast(j.msg || (j.ok ? 'تم' : 'خطأ'), j.ok ? 0 : 1); });
  });
})();
</script>
<?php admin_footer(); ?>
