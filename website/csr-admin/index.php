<?php
/** تسجيل الدخول للوحة التحكم */
require_once __DIR__ . '/inc/auth.php';

if (admin_user() !== null) {
    header('Location: dashboard.php');
    exit;
}

$err = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    /* تقييد صارم للمحاولات لكل عنوان */
    if (!rate_limit('admin_login', LOGIN_MAX_FAILS + 2, LOGIN_LOCK_MIN * 60, LOGIN_LOCK_MIN)) {
        $err = 'محاولات كثيرة — المحاولة متاحة بعد ' . LOGIN_LOCK_MIN . ' دقيقة';
    } elseif (!csrf_verify()) {
        $err = 'انتهت صلاحية النموذج، أعد المحاولة';
    } else {
        $user = trim((string)($_POST['username'] ?? ''));
        $pass = (string)($_POST['password'] ?? '');
        $row = q_one('SELECT * FROM admins WHERE username = ? LIMIT 1', [$user]);
        $locked = $row && $row['locked_until'] !== null && strtotime($row['locked_until']) > time();
        if ($locked) {
            $err = 'الحساب موقوف مؤقتاً، أعد المحاولة لاحقاً';
            sec_log('login_locked', 'user=' . $user, 'medium');
        } elseif ($row === null || !password_verify($pass, $row['pass_hash'])) {
            $err = 'بيانات الدخول غير صحيحة';
            sec_log('login_fail', 'user=' . $user, 'medium');
            if ($row !== null) {
                $fails = (int)$row['failed_count'] + 1;
                $lock = $fails >= LOGIN_MAX_FAILS ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_MIN * 60) : null;
                q('UPDATE admins SET failed_count = ?, locked_until = ? WHERE id = ?', [$fails, $lock, $row['id']]);
                if ($lock !== null) sec_log('login_account_locked', 'user=' . $user, 'high');
            }
        } else {
            /* نجاح */
            session_regenerate_id(true);
            $_SESSION['admin_id']    = (int)$row['id'];
            $_SESSION['admin_user']  = $row['username'];
            $_SESSION['admin_name']  = $row['display_name'] ?: $row['username'];
            $_SESSION['admin_role']  = $row['role'] ?? 'super';
            $_SESSION['admin_start'] = time();
            $_SESSION['admin_last']  = time();
            q('UPDATE admins SET failed_count = 0, locked_until = NULL, last_login = NOW() WHERE id = ?', [$row['id']]);
            sec_log('login_ok', 'user=' . $row['username'], 'info');

            /* نسخة احتياطية تلقائية يومية عند الدخول */
            try {
                require_once dirname(__DIR__) . '/app/backup.php';
                auto_backup_if_due();
            } catch (Throwable $e) {
            }
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>الدخول</title>
<link rel="stylesheet" href="../assets/css/fonts.css">
<link rel="stylesheet" href="assets/admin.css?v=1">
</head>
<body class="login-body">
<div class="login-card">
  <img src="../assets/img/csr-mark.png" alt="" class="login-logo" style="width:96px;height:auto">
  <h1>لوحة التحكم</h1>
  <p class="login-sub">منتدى المسؤولية الاجتماعية واستدامة الأعمال العراقي</p>
  <?php if ($err): ?><div class="alert alert-err"><?= e($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>اسم المستخدم</label>
    <input name="username" required autofocus autocapitalize="none">
    <label>كلمة المرور</label>
    <input name="password" type="password" required>
    <button class="btn-p btn-block">دخول</button>
  </form>
</div>
</body>
</html>
