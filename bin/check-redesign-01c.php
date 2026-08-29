<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$foundation = $read('public/assets/css/talvoro-foundation.css');
$publicCss = $read('public/assets/css/talvoro-public.css');
$adminCss = $read('public/assets/css/talvoro-admin.css');
$design = $read('app/Core/DesignSystem.php');
$contact = $read('resources/views/page/blocks/contact.php');
$layout = $read('resources/views/layouts/app.php');
$migration = $read('database/migrations/023_trenlume_visual_defaults.sql');

$checks = [
    'Foundation uses Trenlume light canvas and surface' =>
        str_contains($foundation, '--tv-bg: #faf7f3;')
        && str_contains($foundation, '--tv-panel: #fffdfa;')
        && str_contains($foundation, '--tv-panel-soft: #f7f0eb;'),
    'Foundation uses Trenlume coral aqua violet and green accents' =>
        str_contains($foundation, '--tv-coral: #ff6b52;')
        && str_contains($foundation, '--tv-aqua: #3bcfc8;')
        && str_contains($foundation, '--tv-indigo: #8d7af0;')
        && str_contains($foundation, '--tv-success: #27aa83;'),
    'Foundation uses Trenlume text hierarchy' =>
        str_contains($foundation, '--tv-ink: #26221f;')
        && str_contains($foundation, '--tv-text-secondary: #625b56;')
        && str_contains($foundation, '--tv-muted: #8d837c;'),
    'Public body uses coral and aqua ambient gradients' =>
        str_contains($publicCss, 'rgba(255, 111, 82, .12)')
        && str_contains($publicCss, 'rgba(59, 207, 200, .10)'),
    'Public panels use warm light Trenlume surfaces' =>
        str_contains($publicCss, '#fffdfb')
        && str_contains($publicCss, 'rgba(82,65,52,.11)'),
    'CMS sidebar is warm light rather than dark' =>
        str_contains($adminCss, '.cms-sidebar')
        && str_contains($adminCss, 'background: rgba(255,253,251,.94);')
        && !str_contains($adminCss, 'background: linear-gradient(180deg, #241f1d'),
    'CMS active navigation uses refined coral active state' =>
        str_contains($adminCss, 'background: color-mix(in srgb, var(--tv-coral) 11%, white);'),
    'CMS workspace uses Trenlume light background treatment' =>
        str_contains($adminCss, 'linear-gradient(180deg,#faf7f4 0%,#f4efea 100%)'),
    'Design System defaults match Trenlume palette' =>
        str_contains($design, "'brand' => ['label' => 'Primary accent', 'type' => 'color', 'default' => '#ff6b52']")
        && str_contains($design, "'accent' => ['label' => 'Sea glass accent', 'type' => 'color', 'default' => '#3bcfc8']")
        && str_contains($design, "'background' => ['label' => 'Page background', 'type' => 'color', 'default' => '#faf7f3']"),
    'Contact labels use inline star/optional text' =>
        str_contains($contact, 'contact-required-star')
        && str_contains($contact, '>optional</span>')
        && !str_contains($contact, '>Required</span>'),
    'Design assets use content-aware cache busting' =>
        str_contains($layout, "filemtime(base_path('public/assets/css/talvoro-foundation.css'))")
        && str_contains($layout, "filemtime(base_path('public/assets/css/talvoro-admin.css'))")
        && str_contains($layout, "filemtime(base_path('public/assets/css/talvoro-public.css'))"),
    'Migration updates only known legacy default palette values' =>
        str_contains($migration, 'design.theme.%')
        && str_contains($migration, '#b75544')
        && str_contains($migration, '#ff6b52')
        && str_contains($migration, '#f7f2ea')
        && str_contains($migration, '#faf7f3'),
    'No Node dependency introduced' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-01c checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
