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
