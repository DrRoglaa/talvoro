<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $path = $root . '/' . $relative;
    return is_file($path) ? (string) file_get_contents($path) : '';
};

$themesView = $read('resources/views/admin/themes.php');
$appCss = $read('public/assets/css/app.css');
$migration = $read('database/migrations/026_remove_legacy_trenlume_theme.sql');
$fullCheck = $read('bin/check.php');
$redesign02 = $read('bin/check-redesign-02.php');
$themeManager = $read('app/Core/ThemeManager.php');

$zipNames = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $name = strtolower($file->getFilename());
    if (str_ends_with($name, '.zip') && str_contains($name, 'trenlume')) {
        $zipNames[] = $file->getPathname();
    }
}

$checks = [
    'Theme admin no longer exposes Trenlume Light' => !str_contains($themesView, 'Trenlume Light') && !str_contains($themesView, 'legacy Trenlume'),
    'Theme admin keeps custom ZIP import guidance' => str_contains($themesView, 'custom theme') && str_contains($themesView, 'Theme ZIP'),
    'Legacy Trenlume preview selector removed' => !str_contains($appCss, '.theme-preview-trenlume'),
    'Theme manager hides and rejects removed legacy theme' => substr_count($themeManager, "slug<>'trenlume-light'") >= 2 && str_contains($themeManager, "'trenlume-light'") && str_contains($themeManager, 'Theme not found.'),
    'Removed legacy slug cannot be recreated by custom theme import' => str_contains($themeManager, "\$slug === 'trenlume-light'") && str_contains($themeManager, 'reserved'),
    'Migration 026 removes the legacy built-in theme' => $migration !== '' && str_contains($migration, "DELETE FROM themes") && str_contains($migration, "slug='trenlume-light'"),
    'Migration 026 keeps Talvoro Editorial active' => str_contains($migration, "slug='talvoro-editorial'") && str_contains($migration, 'is_active=1'),
    'Migration 026 normalizes obsolete frontend theme setting' => str_contains($migration, "frontend.theme") && str_contains($migration, 'talvoro-editorial') && str_contains($migration, 'trenlume-light'),
    'Full integration check expects legacy theme to be absent' => str_contains($fullCheck, 'Trenlume Light theme removed') && str_contains($fullCheck, "slug='trenlume-light'") && str_contains($fullCheck, '=== 0'),
    'Redesign 02 regression no longer requires legacy compatibility theme' => !str_contains($redesign02, 'Legacy Trenlume Light compatibility remains in migration') && str_contains($redesign02, 'Legacy Trenlume Light is removed by forward migration'),
    'No bundled Trenlume theme ZIP remains' => $zipNames === [],
];

$failed = 0;
foreach ($checks as $label => $passed) {
    printf('[%s] %s\n', $passed ? 'PASS' : 'FAIL', $label);
    if (!$passed) $failed++;
}

printf("\n%d/%d redesign-02a checks passed.\n", count($checks) - $failed, count($checks));
exit($failed === 0 ? 0 : 1);
