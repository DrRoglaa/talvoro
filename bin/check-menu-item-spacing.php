<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$view = (string) file_get_contents($root . '/resources/views/admin/menus/form.php');
$css = (string) file_get_contents($root . '/public/assets/css/talvoro-admin.css');

$checks = [
    'Menu item summary uses an explicit layout wrapper' => str_contains($view, 'class="menu-item-summary"'),
    'Menu item type has its own metadata class' => str_contains($view, 'class="menu-item-type"'),
    'Menu item summary layout provides spacing between title and type' => str_contains($css, '.menu-item-summary') && str_contains($css, 'gap: .5rem;'),
    'Menu item type is visually secondary' => str_contains($css, '.menu-item-type') && str_contains($css, 'color: var(--tv-muted);'),
    'Version is unchanged during development fix' => trim((string) file_get_contents($root . '/VERSION')) === '0.16.0',
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d menu-item-spacing checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
