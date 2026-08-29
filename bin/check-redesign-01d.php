<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$design = $read('app/Core/DesignSystem.php');
$foundation = $read('public/assets/css/talvoro-foundation.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$migration = $read('database/migrations/024_trenlume_typography_defaults.sql');

$checks = [
    'Design System defaults heading font to system' =>
        str_contains($design, "'heading_font' => ['label' => 'Heading font', 'type' => 'choice', 'default' => 'system'"),
    'Design System defaults body font to system' =>
        str_contains($design, "'body_font' => ['label' => 'Body font', 'type' => 'choice', 'default' => 'system'"),
    'System font stack follows Trenlume-style Apple-first order' =>
        str_contains($design, '"SF Pro Display"')
        && str_contains($design, '"SF Pro Text"')
        && str_contains($design, 'BlinkMacSystemFont'),
    'Foundation display and UI fonts use the same clean system stack' =>
        str_contains($foundation, '--tv-font-display: -apple-system')
        && str_contains($foundation, '--tv-font-ui: -apple-system'),
    'Public redesign forces the default site onto the system stack' =>
        str_contains($publicCss, 'body.public-body')
        && str_contains($publicCss, 'font-family: var(--talvoro-font-body, var(--tv-font-ui))'),
    'Public redesign removes decorative serif from headings' =>
        str_contains($publicCss, '.public-body :where(h1, h2, h3, h4, h5, h6)')
        && str_contains($publicCss, 'font-family: var(--talvoro-font-heading, var(--tv-font-ui))'),
    'CMS headings remain on the shared UI font' =>
        str_contains($adminCss, 'body.admin-body :where(h1,h2,h3,h4)')
        && str_contains($adminCss, 'font-family: var(--tv-font-ui)'),
    'Migration updates only old shipped typography defaults' =>
        str_contains($migration, 'design.theme.%.heading_font')
        && str_contains($migration, "IN ('editorial')")
        && str_contains($migration, 'design.theme.%.body_font')
        && str_contains($migration, "IN ('humanist')")
        && str_contains($migration, "SET setting_value = 'system'"),
    'Milestone remains dependency-free PHP/CSS/JS' =>
        !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-01d checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
