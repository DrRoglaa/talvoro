<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$publicCss = $read('public/assets/css/talvoro-public.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');

$checks = [
    'Public footer uses a light editorial surface' =>
        str_contains($publicCss, '.talvoro-public-footer')
        && str_contains($publicCss, '.talvoro-public-footer') && str_contains($publicCss, 'background: rgba(255,253,250,.84)')
        && !str_contains($publicCss, "background: var(--tv-ink);\n  color: #f9f4ee"),
    'Public footer uses dark readable text' =>
        str_contains($publicCss, 'color: var(--tv-text);'),
    'Public footer has a restrained border and shadow' =>
        str_contains($publicCss, 'border: 1px solid rgba(82,65,52,.10);')
        && str_contains($publicCss, 'box-shadow: 0 16px 48px rgba(86,61,43,.07);'),
    'CMS workspace cancels the legacy double sidebar offset' =>
        preg_match('/\.cms-workspace\s*\{[^}]*margin-left:\s*0\s*;/s', $adminCss) === 1,
    'CMS main workspace is full width inside the sidebar grid' =>
        preg_match('/\.admin-main\s*\{[^}]*width:\s*100%\s*;[^}]*max-width:\s*none\s*;/s', $adminCss) === 1,
    'CMS dashboard stacks do not stretch cards vertically' =>
        str_contains($adminCss, '.overview-primary.stack, .overview-secondary.stack')
        && str_contains($adminCss, 'align-content: start;'),
    'CMS surfaces use the softer editorial treatment' =>
        preg_match('/\.admin-surface\s*\{[^}]*box-shadow:/s', $adminCss) === 1
        && preg_match('/\.admin-surface\s*\{[^}]*background:/s', $adminCss) === 1,
    'CMS overview headline is restrained for a working interface' =>
        str_contains($adminCss, '.overview-header h1')
        && str_contains($adminCss, '.overview-header h1') && str_contains($adminCss, 'font-size: clamp(2.15rem,3.2vw,3rem);'),
    'Milestone remains dependency-free PHP/CSS/JS' =>
        !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-01a checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
