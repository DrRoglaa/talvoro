<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$themeManager = $read('app/Core/ThemeManager.php');
$settings = $read('app/Core/Settings.php');
$installer = $read('app/Core/Installer.php');
$preset = $read('app/Core/PublicSitePreset.php');
$migration = $read('database/migrations/025_talvoro_editorial_public_site.sql');
$cleanupMigration = $read('database/migrations/026_remove_legacy_trenlume_theme.sql');
$pageBlocks = $read('app/Core/PageBlocks.php');
$builder = $read('public/assets/js/page-builder.js');
$customView = $read('resources/views/page/blocks/custom.php');
$cardsView = $read('resources/views/page/blocks/cards.php');
$layout = $read('resources/views/layouts/app.php');
$publicCss = $read('public/assets/css/talvoro-public.css');
$themesView = $read('resources/views/admin/themes.php');
$presetCli = $read('bin/apply-talvoro-product-site.php');

$checks = [
    'Talvoro Editorial migration exists' => $migration !== '' && str_contains($migration, "'Talvoro Editorial'") && str_contains($migration, "'talvoro-editorial'"),
    'Legacy Trenlume Light is removed by forward migration' => $cleanupMigration !== '' && str_contains($cleanupMigration, "DELETE FROM themes") && str_contains($cleanupMigration, "slug='trenlume-light'"),
    'Theme fallback is Talvoro Editorial' => str_contains($themeManager, "'name' => 'Talvoro Editorial'") && str_contains($themeManager, "'slug' => 'talvoro-editorial'"),
    'Settings frontend fallback is Talvoro Editorial' => str_contains($settings, "'talvoro-editorial'"),
    'Installer selects Talvoro Editorial' => str_contains($installer, "Settings::set('frontend.theme', 'talvoro-editorial'"),
    'Public site preset service exists' => $preset !== '' && str_contains($preset, 'final class PublicSitePreset') && str_contains($preset, 'public static function apply'),
    'Preset defines complete product architecture' => $preset !== '' && !array_filter(['/product','/themes','/resources','/self-hosting','/open-source','/support','/demo'], static fn(string $path): bool => !str_contains($preset, "'{$path}'")),
    'Preset dogfoods pages, menus and SEO' => str_contains($preset, 'PageBlocks::validateSubmitted') && str_contains($preset, 'SEO::save') && str_contains($preset, "location='primary'") && str_contains($preset, "location='footer'"),
    'Talvoro product preset is explicit rather than automatic on customer installs' => str_contains($presetCli, 'PublicSitePreset::apply') && !str_contains($installer, 'PublicSitePreset::apply'),
    'Custom product variants are registered server-side' => str_contains($pageBlocks, "'product-ui'") && str_contains($pageBlocks, "'ownership'") && str_contains($pageBlocks, "'capabilities'") && str_contains($pageBlocks, "'install'"),
    'Audience cards variant is registered server-side' => str_contains($pageBlocks, "'audiences'"),
    'Builder exposes Redesign 02 variants' => str_contains($builder, "['product-ui','Product UI']") && str_contains($builder, "['audiences','Audience stories']"),
    'Public block views render product visual hooks' => str_contains($customView, 'product-ui-preview') && str_contains($cardsView, 'audience-card'),
    'Public layout identifies Talvoro Editorial product site' => str_contains($layout, 'talvoro-product-site'),
    'Public stylesheet contains Redesign 02 product-site system' => str_contains($publicCss, 'REDESIGN 02') && str_contains($publicCss, '.talvoro-product-site') && str_contains($publicCss, '.product-ui-preview'),
    'Theme admin names Talvoro Editorial as protected default' => str_contains($themesView, 'Talvoro Editorial') && !str_contains($themesView, 'Trenlume Light'),
    'Milestone remains dependency-free PHP/CSS/JS' => !is_file($root . '/package.json') && !is_dir($root . '/node_modules'),
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}
printf("\n%d/%d redesign-02 checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
