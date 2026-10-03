<?php
declare(strict_types=1);
require_once APP_ROOT . '/includes/v2-layout.php';

function render_header(string $locale, string $title = '', string $scope = ''): void
{
    if ($scope === '') {
        $scope = pathinfo(basename((string) ($_SERVER['PHP_SELF'] ?? 'page.php')), PATHINFO_FILENAME);
    }
    $scope = clean_slug($scope) ?: 'site';
    render_v2_head($locale, $title, '', $scope);
    render_v2_nav($locale);
}

function render_footer(string $locale): void
{
    render_v2_footer($locale);
}
