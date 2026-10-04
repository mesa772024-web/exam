<?php
require_once __DIR__ . '/inc/layout.php';
$me = require_super();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_require();
    $act = (string)($_POST['act'] ?? '');

    if ($act === 'create') {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $name = clean_text($_POST['display_name'] ?? '', 120);
        $role = ($_POST['role'] ?? 'desk') === 'super' ? 'super' : 'desk';
        if (!preg_match('/^[A-Za-z0-9_.-]{4,40}$/', $user)) json_out(['ok' => false, 'msg' => 'اسم المستخدم: 4-40 حرفاً لاتينياً/أرقاماً بلا فراغات'], 422);
        if (strlen($pass) < 8) json_out(['ok' => false, 'msg' => 'كلمة المرور: 8 أحرف على الأقل'], 422);
        if (q_one('SELECT id FROM admins WHERE username = ?', [$user]) !== null) json_out(['ok' => false, 'msg' => 'اسم المستخدم مستخدم مسبقاً'], 409);
        q('INSERT INTO admins (username, pass_hash, display_name, role) VALUES (?,?,?,?)',
          [$user, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $name ?: $user, $role]);
        admin_audit('admin_create', $user . ' role=' . $role);
        json_out(['ok' => true]);
    }

    if ($act === 'role') {
        $id = (int)($_POST['id'] ?? 0);
        $role = ($_POST['role'] ?? 'desk') === 'super' ? 'super' : 'desk';
        if ($id === $me['id'] && $role !== 'super') json_out(['ok' => false, 'msg' => 'لا يمكنك تخفيض صلاحيتك'], 400);
        // منع إزالة آخر مدير كامل
        if ($role !== 'super') {
            $supers = (int)q_val("SELECT COUNT(*) FROM admins WHERE role = 'super'");
            $target = q_one('SELECT role FROM admins WHERE id = ?', [$id]);
            if ($target && $target['role'] === 'super' && $supers <= 1) json_out(['ok' => false, 'msg' => 'يجب بقاء مدير كامل واحد على الأقل'], 400);
        }
        q('UPDATE admins SET role = ? WHERE id = ?', [$role, $id]);
        admin_audit('admin_role', 'id=' . $id . ' -> ' . $role);
        json_out(['ok' => true]);
    }

    if ($act === 'reset_pass') {
        $id = (int)($_POST['id'] ?? 0);
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 8) json_out(['ok' => false, 'msg' => 'كلمة المرور: 8 أحرف على الأقل'], 422);
        q('UPDATE admins SET pass_hash = ?, failed_count = 0, locked_until = NULL WHERE id = ?',
          [password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
        admin_audit('admin_reset_pass', 'id=' . $id);
        json_out(['ok' => true]);
    }

    if ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id === $me['id']) json_out(['ok' => false, 'msg' => 'لا يمكنك حذف حسابك'], 400);
        $target = q_one('SELECT role FROM admins WHERE id = ?', [$id]);
        if ($target && $target['role'] === 'super' && (int)q_val("SELECT COUNT(*) FROM admins WHERE role='super'") <= 1) {
            json_out(['ok' => false, 'msg' => 'يجب بقاء مدير كامل واحد على الأقل'], 400);
        }
        q('DELETE FROM admins WHERE id = ?', [$id]);
        admin_audit('admin_delete', 'id=' . $id);
        json_out(['ok' => true]);
    }
    json_out(['ok' => false], 400);
}

$admins = q_all('SELECT id, username, display_name, role, last_login, created_at FROM admins ORDER BY id');
admin_header('حسابات المديرين', 'admins');
?>
<div class="panel">
  <div class="panel-head">
    <h2><?= count($admins) ?> حساب</h2>
    <button class="btn-p" id="newAdmin"><?= icon('plus') ?> حساب جديد</button>
  </div>
  <p class="hint"><b>مدير كامل:</b> صلاحية كاملة على كل شيء. &nbsp; <b>موظف تسجيل:</b> يرى فقط شاشة قاعة المؤتمر (مسح الباركود وإدخال الحضور وطباعة الباج) — بلا وصول لباقي اللوحة.</p>
  <div class="tbl-scroll">
  <table class="tbl">
    <thead><tr><th>المستخدم</th><th>الاسم</th><th>الصلاحية</th><th>آخر دخول</th><th style="width:280px">إجراءات</th></tr></thead>
    <tbody>
      <?php foreach ($admins as $a): ?>
      <tr data-id="<?= (int)$a['id'] ?>">
        <td dir="ltr" class="mono"><b><?= e($a['username']) ?></b><?= $a['id'] === $me['id'] ? ' <span class="badge b-approved">أنت</span>' : '' ?></td>
        <td><?= e($a['display_name']) ?></td>
        <td>
          <select class="role-sel" <?= $a['id'] === $me['id'] ? 'disabled' : '' ?>>
            <option value="super" <?= $a['role'] === 'super' ? 'selected' : '' ?>>مدير كامل</option>
            <option value="desk" <?= $a['role'] === 'desk' ? 'selected' : '' ?>>موظف تسجيل</option>
          </select>
        </td>
        <td class="mono sub"><?= $a['last_login'] ? e(date('Y/m/d H:i', strtotime($a['last_login']))) : '—' ?></td>
        <td class="row-actions">
          <button class="act" data-reset><?= icon('lock') ?> كلمة مرور</button>
          <?php if ($a['id'] !== $me['id']): ?><button class="act act-del" data-del><?= icon('trash') ?></button><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="modal" id="newModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>حساب مدير جديد</h3>
    <form id="newForm" class="modal-form">
      <label>اسم المستخدم (لاتيني)<input name="username" dir="ltr" required pattern="[A-Za-z0-9_.-]{4,40}"></label>
      <label>الاسم المعروض<input name="display_name" maxlength="120"></label>
      <label>كلمة المرور (8+ أحرف)<input name="password" type="password" required minlength="8"></label>
      <label>الصلاحية
        <select name="role"><option value="desk">موظف تسجيل (قاعة فقط)</option><option value="super">مدير كامل</option></select>
      </label>
      <button class="btn-p btn-block"><?= icon('check') ?> إنشاء</button>
    </form>
  </div>
</div>

<div class="modal" id="resetModal" hidden>
  <div class="modal-card">
    <button class="modal-x" data-close><?= icon('close') ?></button>
    <h3>تغيير كلمة المرور</h3>
    <form id="resetForm" class="modal-form">
      <input type="hidden" name="id" value="0">
      <label>كلمة المرور الجديدة (8+ أحرف)<input name="password" type="password" required minlength="8"></label>
      <button class="btn-p btn-block"><?= icon('save') ?> حفظ</button>
    </form>
  </div>
</div>

<script>
(function(){
  var newModal=document.getElementById('newModal'), resetModal=document.getElementById('resetModal');
  document.getElementById('newAdmin').addEventListener('click',function(){newModal.hidden=false;});
  document.getElementById('newForm').addEventListener('submit',function(ev){
    ev.preventDefault(); var fd=new FormData(this); fd.append('act','create');
    window.apost('admins.php',fd,function(j){ if(j.ok){window.toast('تم الإنشاء ✓');setTimeout(function(){location.reload();},800);} else window.toast(j.msg||'خطأ',1); });
  });
  document.querySelectorAll('#resetForm').forEach(function(){});
  document.querySelectorAll('.role-sel').forEach(function(sel){
    sel.addEventListener('change',function(){
      var id=sel.closest('tr').getAttribute('data-id');
      window.apost('admins.php',{act:'role',id:id,role:sel.value},function(j){ window.toast(j.ok?'تم تغيير الصلاحية ✓':(j.msg||'خطأ'),j.ok?0:1); if(!j.ok)location.reload(); });
    });
  });
  document.querySelectorAll('[data-reset]').forEach(function(b){
    b.addEventListener('click',function(){ resetModal.hidden=false; resetModal.querySelector('[name=id]').value=b.closest('tr').getAttribute('data-id'); resetModal.querySelector('[name=password]').value=''; });
  });
  document.getElementById('resetForm').addEventListener('submit',function(ev){
    ev.preventDefault(); var fd=new FormData(this); fd.append('act','reset_pass');
    window.apost('admins.php',fd,function(j){ if(j.ok){resetModal.hidden=true;window.toast('تم التغيير ✓');} else window.toast(j.msg||'خطأ',1); });
  });
  document.querySelectorAll('[data-del]').forEach(function(b){
    b.addEventListener('click',function(){ if(!confirm('حذف هذا الحساب نهائياً؟'))return;
      window.apost('admins.php',{act:'delete',id:b.closest('tr').getAttribute('data-id')},function(j){ if(j.ok)b.closest('tr').remove(); else window.toast(j.msg||'خطأ',1); });
    });
  });
})();
</script>
<?php admin_footer(); ?>
