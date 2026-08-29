<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$foundation = $read('public/assets/css/talvoro-foundation.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$design = $read('app/Core/DesignSystem.php');

$checks = [
    'Bright Trenlume coral remains the brand accent' => str_contains($foundation, '--tv-coral: #ff6b52;'),
    'Primary action uses restrained deep coral' => str_contains($foundation, '--tv-action-primary: #d85f4a;'),
    'Primary action hover uses darker terracotta' => str_contains($foundation, '--tv-action-primary-hover: #c94f3d;'),
    'Dynamic public design exports a dedicated action token' => str_contains($design, '--talvoro-action:#d85f4a;') && str_contains($design, '--talvoro-action-hover:#c94f3d;'),
    'Ordinary public links use action color rather than aqua accent' => str_contains($design, 'color:var(--talvoro-action)') && !str_contains($design, 'a:not(.home-pill){color:var(--talvoro-accent)'),
    'Public cards and article links use the restrained action color' => str_contains($publicCss, '--talvoro-action, var(--tv-action-primary)'),
    'CMS active navigation uses a pale coral surface rather than saturated fill' => str_contains($adminCss, 'background: color-mix(in srgb, var(--tv-coral) 11%, white);') && str_contains($adminCss, 'color: var(--tv-text);'),
    'Aqua remains available as a supporting accent' => str_contains($foundation, '--tv-aqua: #3bcfc8;'),
    'Milestone remains dependency-free PHP/CSS/JS' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-01e checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
