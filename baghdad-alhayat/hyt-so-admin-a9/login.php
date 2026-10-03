<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';
if (!is_file(STORAGE_PATH . DIRECTORY_SEPARATOR . 'installed.lock')) { header('Location: ' . site_url('install/')); exit; }
if (admin_count() === 0) { header('Location: ' . site_url('install/')); exit; }
if (current_admin()) { header('Location: ' . admin_url('index.php')); exit; }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        if (!attempt_admin_login((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) throw new RuntimeException('بيانات الدخول غير صحيحة أو أن الحساب مقفل مؤقتاً.');
        $return = clean_text($_POST['return'] ?? '', 300);
        $allowedPrefix = BASE_URL . '/' . ADMIN_SLUG . '/';
        header('Location: ' . (str_starts_with($return, $allowedPrefix) ? $return : admin_url('index.php')));
        exit;
    } catch (Throwable $exception) { $error = $exception->getMessage(); }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>دخول الإدارة · مكتب بغداد الحياة</title><link rel="stylesheet" href="<?= h(site_url('assets/css/admin-v2.css?v=3.1.0')) ?>"></head><body><main class="login-page"><section class="login-art"><img src="<?= h(site_url(setting('logoPath', 'assets/images/logo.png'))) ?>" alt="مكتب بغداد الحياة"><div><h1>مركز إدارة<br>مكتب بغداد الحياة.</h1><p>محتوى، مراسلات، حجوزات، تقارير، وأمان من مكان واحد.</p></div></section><section class="login-box"><form method="post" autocomplete="on"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><input type="hidden" name="return" value="<?= h($_GET['return'] ?? '') ?>"><span style="color:#6b0f34;font-size:.7rem">SECURE ADMIN · V2</span><h2>مرحباً بعودتك</h2><p>أدخل بيانات الإدارة. تُقفل المحاولة مؤقتاً بعد تكرار الخطأ.</p><?php if (!empty($_GET['installed'])): ?><p class="admin-notice">تم تثبيت الموقع. يمكنك تسجيل الدخول الآن.</p><?php endif; ?><?php if ($error): ?><p class="login-error"><?= h($error) ?></p><?php endif; ?><label class="admin-field"><span>اسم المستخدم</span><input name="username" required autofocus autocomplete="username" dir="ltr"></label><label class="admin-field"><span>كلمة المرور</span><input name="password" type="password" required autocomplete="current-password" dir="ltr"></label><button class="admin-primary" type="submit">دخول آمن</button></form></section></main></body></html>
