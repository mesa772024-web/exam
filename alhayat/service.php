<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$locale = current_locale();
$slug = preg_replace('/[^a-z-]/', '', (string) ($_GET['slug'] ?? ''));
$services = content_data('services');
// Services are now consolidated into a single page with in-page sections.
$target = 'services.php?lang=' . $locale . (isset($services[$slug]) ? '#' . $slug : '');
header('Location: ' . site_url($target), true, 301);
exit;
