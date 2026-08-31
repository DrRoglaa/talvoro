<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$read = static fn(string $p): string => (string) @file_get_contents($root . '/' . $p);
$releaseManifestPath = $root . '/release.json';
$releaseManifestOk = true;
if (is_file($releaseManifestPath)) {
    $releaseManifest = json_decode($read('release.json'), true);
    $releaseManifestOk = is_array($releaseManifest) && ($releaseManifest['version'] ?? null) === '0.17.0';
}
$checks = [
    'VERSION is 0.17.0' => trim($read('VERSION')) === '0.17.0',
    'Theme Starter Sites guide exists' => is_file($root . '/docs/THEME-STARTER-SITES.md'),
    'README advertises Theme Starter Sites' => str_contains($read('README.md'), 'Theme Starter Sites') && str_contains($read('README.md'), 'docs/THEME-STARTER-SITES.md'),
    'Documentation index links starter-site guide' => str_contains($read('docs/README.md'), 'THEME-STARTER-SITES.md'),
    'Security policy documents declarative theme boundary' => str_contains($read('SECURITY.md'), 'starter/starter.json') && str_contains($read('SECURITY.md'), 'declarative'),
    'Master check exposes 0.17 starter checks' => str_contains($read('bin/check.php'), 'check-v0170-starter-manifest.php') && str_contains($read('bin/check.php'), 'starter_sites.manage') && str_contains($read('bin/check.php'), 'theme_starter_definitions'),
    'Release manifest is optional in source checkout and valid when present' => $releaseManifestOk,
    'Forced product-site repair can reapply an already-marked preset' => str_contains($read('app/Core/PublicSitePreset.php'), 'if (!$force && Settings::get(self::APPLIED_KEY, \'\') === self::APPLIED_VALUE)'),
    'Forced product-site repair clears uploaded branding remnants' => str_contains($read('app/Core/PublicSitePreset.php'), 'Settings::set(\'branding.logo_path\', \'\', $actorId);'),
    'Forced product-site repair restores Talvoro Editorial' => str_contains($read('app/Core/PublicSitePreset.php'), "slug='talvoro-editorial'") && str_contains($read('app/Core/PublicSitePreset.php'), 'is_active=1'),
    'Forced product-site repair removes active starter-owned CMS resources safely' => str_contains($read('app/Core/PublicSitePreset.php'), 'removeInstalledStarterSites($actorId)') && str_contains($read('app/Core/PublicSitePreset.php'), 'StarterSite::deleteDemoData'),
    'Forced product-site repair removes the legacy orphaned Spottina Dog model only when empty' => str_contains($read('app/Core/PublicSitePreset.php'), 'removeLegacyDefaultContamination($actorId)') && str_contains($read('app/Core/PublicSitePreset.php'), "model_key='dog'") && str_contains($read('app/Core/PublicSitePreset.php'), "slug='dalmatians'") && str_contains($read('app/Core/PublicSitePreset.php'), 'entryCount($modelId, false) !== 0'),
    'Default CMS views contain no Spottina or Dalmatian example copy' => !str_contains($read('resources/views/admin/pages/home-form.php'), 'Spottina') && !str_contains($read('resources/views/admin/media/index.php'), 'Dalmatian'),
];
$failed = [];
foreach ($checks as $name => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) {
        $failed[] = $name;
    }
}
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'Talvoro 0.17.0 release/documentation checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro 0.17.0 release/documentation checks passed.' . PHP_EOL;
