<?php
// $view variable contains target view filename from render method
$viewFile = __DIR__ . '/' . $view;
$config = $config ?? [];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($config['app_name'] ?? '118 Team Banks'); ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="bg-light">
<header class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container-fluid">
        <div class="d-flex align-items-center gap-2">
            <img src="<?php echo htmlspecialchars($config['logo_url']); ?>" alt="Logo" class="brand-logo">
            <span class="navbar-brand mb-0 h1">118 Team Banks</span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <a class="text-white" href="<?php echo htmlspecialchars($config['telegram']); ?>" target="_blank">تيليغرام</a>
            <a class="text-white" href="<?php echo htmlspecialchars($config['instagram']); ?>" target="_blank">إنستغرام</a>
        </div>
    </div>
</header>
<main class="container-fluid py-4">
    <?php if (file_exists($viewFile)) { include $viewFile; } else { echo '<div class="alert alert-danger">الملف غير موجود: '.htmlspecialchars($view).'</div>'; } ?>
</main>
<footer class="text-center py-4 text-muted small">
    &copy; <?php echo date('Y'); ?> 118 Team Banks
</footer>
</body>
</html>
