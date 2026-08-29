<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string)file_get_contents($path) : '';
};

$layout = $read('resources/views/layouts/app.php');
$foundation = $read('public/assets/css/talvoro-foundation.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$design = $read('app/Core/DesignSystem.php');
$dashboard = $read('resources/views/admin/dashboard.php');
$adminNav = $read('public/assets/js/admin-nav.js');

$checks = [
    'Foundation stylesheet exists' => $foundation !== '',
    'Public stylesheet exists' => $publicCss !== '',
    'Admin stylesheet exists' => $adminCss !== '',
    'Layout loads foundation before legacy CSS' =>
        strpos($layout, '/assets/css/talvoro-foundation.css') !== false
        && strpos($layout, '/assets/css/talvoro-foundation.css') < strpos($layout, '/assets/css/app.css'),
    'Layout loads public redesign stylesheet' => str_contains($layout, '/assets/css/talvoro-public.css'),
    'Layout loads admin redesign stylesheet' => str_contains($layout, '/assets/css/talvoro-admin.css'),
    'Concept B product tokens exist' =>
        str_contains($foundation, '--tv-ink:')
        && str_contains($foundation, '--tv-parchment:')
        && str_contains($foundation, '--tv-coral:')
        && str_contains($foundation, '--tv-sea-glass:')
        && str_contains($foundation, '--tv-indigo:'),
    'Accessible primary action token exists' => str_contains($foundation, '--tv-action-primary: #d85f4a'),
    'Semantic danger is separate from coral brand' =>
        str_contains($foundation, '--tv-danger:')
        && !str_contains($foundation, '--tv-danger:var(--tv-coral)'),
    'Reduced-motion contract present' => str_contains($foundation, '@media (prefers-reduced-motion: reduce)'),
    'Visible focus contract present' => str_contains($foundation, ':focus-visible'),
    'Admin shell uses final navigation groups' =>
        str_contains($layout, '>Overview<')
        && str_contains($layout, '>Content<')
        && str_contains($layout, '>Design<')
        && str_contains($layout, '>Insights<')
        && str_contains($layout, '>System<'),
    'Duplicate System navigation removed' => substr_count($layout, '<span>System</span>') === 1,
    'Admin navigation restores opener focus' => str_contains($adminNav, 'lastFocusedBeforeOpen'),
    'Public design system exposes depth token' => str_contains($design, "'depth' =>"),
    'Dashboard asks attention/change/performance/next-action questions' =>
        str_contains($dashboard, 'Needs attention')
        && str_contains($dashboard, 'Recently edited')
        && str_contains($dashboard, 'Site snapshot')
        && str_contains($dashboard, 'Quick actions'),
    'Node package metadata not introduced' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-foundation checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
