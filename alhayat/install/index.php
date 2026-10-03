<?php
declare(strict_types=1);
require dirname(__DIR__) . '/includes/bootstrap.php';

$lockFile = STORAGE_PATH . DIRECTORY_SEPARATOR . 'installed.lock';
if (is_file($lockFile)) {
    header('Location: ' . admin_url('login.php'));
    exit;
}

$requirements = [
    'PHP 8.1+' => version_compare(PHP_VERSION, '8.1.0', '>='),
    'PDO' => extension_loaded('pdo'),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'Multibyte' => extension_loaded('mbstring'),
    'Fileinfo' => extension_loaded('fileinfo'),
    'JSON' => extension_loaded('json'),
    'Zip (للنسخ والتحديث)' => extension_loaded('zip'),
    'Storage writable' => is_writable(STORAGE_PATH),
];
$blockingReady = $requirements['PHP 8.1+'] && $requirements['PDO'] && $requirements['PDO MySQL'] && $requirements['Multibyte'] && $requirements['Fileinfo'] && $requirements['Storage writable'];
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf($_POST['csrf_token'] ?? null);
        if (!$blockingReady) throw new RuntimeException('متطلبات الخادم الأساسية غير مكتملة.');
        $_SESSION['installer_attempts'] = (int) ($_SESSION['installer_attempts'] ?? 0) + 1;
        if ($_SESSION['installer_attempts'] > 8) throw new RuntimeException('محاولات كثيرة. أغلق المتصفح وحاول لاحقاً.');

        $siteAr = clean_text($_POST['site_name_ar'] ?? '', 100);
        $siteEn = clean_text($_POST['site_name_en'] ?? '', 100);
        $appUrl = rtrim(clean_text($_POST['app_url'] ?? '', 300), '/');
        $dbHost = clean_text($_POST['database_host'] ?? 'localhost', 190);
        $dbPort = (int) ($_POST['database_port'] ?? 3306);
        $dbName = clean_text($_POST['database_name'] ?? '', 64);
        $dbUser = clean_text($_POST['database_username'] ?? '', 80);
        $dbPassword = (string) ($_POST['database_password'] ?? '');
        $displayName = clean_text($_POST['display_name'] ?? '', 100);
        $username = mb_strtolower(clean_text($_POST['username'] ?? '', 40), 'UTF-8');
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');

        if ($siteAr === '' || $siteEn === '' || $displayName === '' || !preg_match('/^[a-z0-9._-]{3,40}$/', $username)) throw new RuntimeException('يرجى إكمال أسماء الموقع والمدير واسم الدخول.');
        if ($appUrl !== '' && (!filter_var($appUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $appUrl))) throw new RuntimeException('رابط الموقع غير صحيح.');
        if (!preg_match('/^[A-Za-z0-9.-]+$/', $dbHost) || $dbPort < 1 || $dbPort > 65535) throw new RuntimeException('عنوان خادم قاعدة البيانات أو المنفذ غير صحيح.');
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $dbName) || !preg_match('/^[A-Za-z0-9_.@$-]{1,80}$/', $dbUser)) throw new RuntimeException('اسم قاعدة البيانات أو اسم مستخدمها غير صحيح.');
        if (strlen($dbPassword) > 500) throw new RuntimeException('كلمة مرور قاعدة البيانات طويلة جداً.');
        if (strlen($password) < 8 || $password !== $confirmation) throw new RuntimeException('كلمة مرور الإدارة يجب أن تكون 8 خانات على الأقل، ويمكن أن تكون 8 أرقام، مع تطابق التأكيد.');

        $config = [
            'app_url' => $appUrl,
            'database' => [
                'driver' => 'mysql',
                'host' => $dbHost,
                'port' => $dbPort,
                'name' => $dbName,
                'username' => $dbUser,
                'password' => $dbPassword,
                'charset' => 'utf8mb4',
            ],
        ];
        $GLOBALS['app_config'] = array_replace_recursive($GLOBALS['app_config'], $config);
        $pdo = db();
        if ((int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0) throw new RuntimeException('يوجد حساب إدارة مسبقاً في قاعدة البيانات هذه.');

        $configPhp = "<?php\ndeclare(strict_types=1);\nreturn " . var_export($config, true) . ";\n";
        if (file_put_contents(STORAGE_PATH . DIRECTORY_SEPARATOR . 'config.php', $configPhp, LOCK_EX) === false) throw new RuntimeException('تعذر حفظ إعدادات الموقع.');
        @chmod(STORAGE_PATH . DIRECTORY_SEPARATOR . 'config.php', 0600);
        $pdo->beginTransaction();
        $statement = $pdo->prepare("INSERT INTO admin_users(username, display_name, password_hash, role) VALUES(:username, :display, :password, 'super_admin')");
        $statement->execute(['username' => $username, 'display' => $displayName, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
        $adminId = (int) $pdo->lastInsertId();
        save_setting('siteNameAr', $siteAr, $adminId);
        save_setting('siteNameEn', $siteEn, $adminId);
        $pdo->commit();

        file_put_contents($lockFile, json_encode(['installed_at' => gmdate(DATE_ATOM), 'version' => '2.1.0'], JSON_UNESCAPED_SLASHES), LOCK_EX);
        @chmod($lockFile, 0600);
        unset($_SESSION['installer_attempts']);
        header('Location: ' . admin_url('login.php?installed=1'));
        exit;
    } catch (Throwable $exception) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
        $error = $exception instanceof PDOException ? 'تعذر الاتصال بقاعدة البيانات. تحقق من الخادم والاسم والمستخدم وكلمة المرور.' : $exception->getMessage();
    }
}
?>
<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>تثبيت مكتب الحياة V2</title><link rel="stylesheet" href="<?= h(site_url('assets/css/admin-v2.css?v=3.1.0')) ?>"></head><body>
<main class="login-page"><section class="login-art"><img src="<?= h(site_url('assets/logo.png')) ?>" alt="مكتب الحياة"><div><h1>تثبيت سريع،<br>وبداية آمنة.</h1><p>اتصال MySQL، هوية الموقع، وحساب المدير الأعلى في خطوة واحدة.</p></div></section><section class="login-box"><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><span style="color:#0f6b48;font-size:.7rem">AL HAYAT V2 · INSTALLER</span><h2>إعداد الموقع</h2><p>هذا المثبّت داخل مجلد مستقل. أكمل البيانات مرة واحدة، وبعد النجاح سيُقفل تلقائياً.</p><?php if ($error): ?><p class="login-error"><?= h($error) ?></p><?php endif; ?><div class="setup-checks"><?php foreach ($requirements as $label => $passed): ?><span class="<?= $passed ? '' : 'bad' ?>"><?= $passed ? '✓' : '×' ?> <?= h($label) ?></span><?php endforeach; ?></div><div class="admin-form"><label class="admin-field"><span>اسم الموقع بالعربية</span><input name="site_name_ar" value="مكتب الحياة العلمي" required></label><label class="admin-field"><span>Site name in English</span><input name="site_name_en" value="Al Hayat Scientific Office" dir="ltr" required></label><label class="admin-field full"><span>رابط الموقع الكامل (اختياري)</span><input name="app_url" type="url" placeholder="https://domain.com" dir="ltr"></label><label class="admin-field"><span>خادم قاعدة البيانات</span><input name="database_host" value="localhost" dir="ltr" required></label><label class="admin-field"><span>المنفذ</span><input name="database_port" type="number" value="3306" min="1" max="65535" dir="ltr" required></label><label class="admin-field"><span>اسم قاعدة البيانات</span><input name="database_name" pattern="[A-Za-z0-9_$-]{1,64}" dir="ltr" required></label><label class="admin-field"><span>مستخدم قاعدة البيانات</span><input name="database_username" pattern="[A-Za-z0-9_.@$-]{1,80}" dir="ltr" required></label><label class="admin-field full"><span>كلمة مرور قاعدة البيانات</span><input name="database_password" type="password" autocomplete="new-password" dir="ltr"></label><label class="admin-field"><span>اسم المدير</span><input name="display_name" required></label><label class="admin-field"><span>اسم الدخول</span><input name="username" pattern="[A-Za-z0-9._-]{3,40}" dir="ltr" required></label><label class="admin-field"><span>كلمة مرور الإدارة (8 خانات على الأقل)</span><input name="password" type="password" minlength="8" autocomplete="new-password" dir="ltr" required></label><label class="admin-field"><span>تأكيد كلمة المرور</span><input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" dir="ltr" required></label><div class="admin-form-actions"><button class="admin-primary" type="submit" <?= $blockingReady ? '' : 'disabled' ?>>تثبيت وفتح لوحة الإدارة</button></div></div></form></section></main></body></html>
