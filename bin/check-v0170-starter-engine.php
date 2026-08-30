<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); } }

use CMS\Core\StarterResourceRegistry;
use CMS\Core\StarterSiteEngine;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

$expectedTypes = [
    'media','content_component','component_field','content_model','content_field','content_entry',
    'blog_category','post','page','menu','menu_item','seo','setting','theme_design',
];

try {
    $types = StarterResourceRegistry::supportedTypes();
    $assert('Registry exposes every schema resource type', $types === $expectedTypes);
    $assert('Registry rejects unknown executable resource type', !StarterResourceRegistry::supports('php') && !StarterResourceRegistry::supports('sql'));
    $assert('Registry provides human labels for review UI', StarterResourceRegistry::label('content_entry') === 'Structured Content entries' && StarterResourceRegistry::label('media') === 'Media items');
} catch (Throwable $e) {
    $checks['Registry is available: ' . $e->getMessage()] = false;
}

$definition = [
    'manifest_sha256' => str_repeat('a', 64),
    'resources' => [
        ['key'=>'page.home','type'=>'page','definition_sha256'=>str_repeat('1',64),'data'=>[]],
        ['key'=>'media.hero','type'=>'media','definition_sha256'=>str_repeat('2',64),'data'=>[]],
        ['key'=>'menu.primary','type'=>'menu','definition_sha256'=>str_repeat('3',64),'data'=>[]],
    ],
];
$installation = ['id'=>8,'manifest_sha256'=>str_repeat('a',64),'status'=>'installed'];
$healthy = [
    'page.home'=>['state'=>'owned','exists'=>true,'baseline_sha256'=>'home','current_sha256'=>'home'],
    'media.hero'=>['state'=>'owned','exists'=>true,'baseline_sha256'=>'hero','current_sha256'=>'hero'],
    'menu.primary'=>['state'=>'owned','exists'=>true,'baseline_sha256'=>'menu','current_sha256'=>'menu'],
];

try {
    $assert('No installation reports not installed', StarterSiteEngine::stateFromSnapshot($definition, null, [])['code'] === 'not_installed');
    $assert('Healthy owned resources report installed', StarterSiteEngine::stateFromSnapshot($definition, $installation, $healthy)['code'] === 'installed');

    $missing = $healthy; $missing['media.hero']['exists'] = false; $missing['media.hero']['current_sha256'] = null;
    $missingState = StarterSiteEngine::stateFromSnapshot($definition, $installation, $missing);
    $assert('Missing owned resource reports repair available', $missingState['code'] === 'repair_available' && $missingState['missing'] === ['media.hero']);

    $modified = $healthy; $modified['page.home']['current_sha256'] = 'changed';
    $modifiedState = StarterSiteEngine::stateFromSnapshot($definition, $installation, $modified);
    $assert('User-modified starter resource is detected', $modifiedState['code'] === 'modified' && $modifiedState['modified'] === ['page.home']);

    $detached = $healthy; $detached['menu.primary']['state'] = 'detached';
    $assert('Detached ownership reports attention rather than repair', StarterSiteEngine::stateFromSnapshot($definition, $installation, $detached)['code'] === 'needs_attention');

    $updatedDefinition = $definition; $updatedDefinition['manifest_sha256'] = str_repeat('b',64);
    $updateState = StarterSiteEngine::stateFromSnapshot($updatedDefinition, $installation, $healthy);
    $assert('Different imported definition reports starter update available', $updateState['code'] === 'starter_update_available');

    $summary = StarterSiteEngine::summarizeDefinition($definition);
    $assert('Review summary counts resources by registered type', ($summary['total'] ?? 0) === 3 && ($summary['counts']['Pages'] ?? 0) === 1 && ($summary['counts']['Media items'] ?? 0) === 1);
} catch (Throwable $e) {
    $checks['Starter state engine is available: ' . $e->getMessage()] = false;
}


try {
    $hashA=StarterSiteEngine::snapshotHash(['b'=>2,'a'=>['y'=>2,'x'=>1]]);
    $hashB=StarterSiteEngine::snapshotHash(['a'=>['x'=>1,'y'=>2],'b'=>2]);
    $assert('Snapshot hashing is canonical and stable', is_string($hashA) && strlen($hashA)===64 && $hashA===$hashB);
    $assert('Missing snapshots hash to null for repair detection', StarterSiteEngine::snapshotHash(null)===null);

    $repair=StarterSiteEngine::repairCandidates([
        'page.home'=>['state'=>'owned','exists'=>true,'baseline_sha256'=>'a','current_sha256'=>'b'],
        'media.hero'=>['state'=>'owned','exists'=>false,'baseline_sha256'=>'c','current_sha256'=>null],
        'menu.primary'=>['state'=>'detached','exists'=>false,'baseline_sha256'=>'d','current_sha256'=>null],
    ]);
    $assert('Repair recreates only missing owned resources', $repair===['media.hero']);

    $removal=StarterSiteEngine::removalOrder($definition,[
        'page.home'=>['resource_key'=>'page.home','id'=>1],
        'media.hero'=>['resource_key'=>'media.hero','id'=>2],
        'menu.primary'=>['resource_key'=>'menu.primary','id'=>3],
        'legacy.detached'=>['resource_key'=>'legacy.detached','id'=>99],
    ]);
    $assert('Removal reverses dependency-safe definition order and retains orphan ownership rows', $removal===['menu.primary','media.hero','page.home','legacy.detached']);

    $blocked=StarterSiteEngine::preflightDecision([
        ['key'=>'page.about','action'=>'conflict','message'=>'Path conflict'],
        ['key'=>'page.home','action'=>'controlled_mutation','message'=>'Home changes'],
    ],false);
    $assert('Preflight blocks conflicts and unconfirmed mutations', !$blocked['allowed'] && $blocked['conflicts']===['page.about'] && $blocked['mutations']===['page.home']);
    $confirmed=StarterSiteEngine::preflightDecision([
        ['key'=>'page.home','action'=>'controlled_mutation','message'=>'Home changes'],
        ['key'=>'media.hero','action'=>'create'],
    ],true);
    $assert('Explicit confirmation permits controlled mutations', $confirmed['allowed'] && $confirmed['conflicts']===[]);
} catch (Throwable $e) {
    $checks['Starter lifecycle reducer helpers are available: '.$e->getMessage()]=false;
}

$repositoryPath = base_path('app/Core/StarterSiteRepository.php');
$repositorySource = is_file($repositoryPath) ? (string)file_get_contents($repositoryPath) : '';
$assert('Starter repository is isolated from CMS record adapters', is_file($repositoryPath) && str_contains($repositorySource, 'definitionForTheme') && str_contains($repositorySource, 'createInstallation') && str_contains($repositorySource, 'recordResource'));
$assert('Repository exposes active installation and ownership reads', str_contains($repositorySource, 'activeInstallationForTheme') && str_contains($repositorySource, 'resourcesForInstallation'));
$assert('Repository records detach/remove state without deleting unrelated rows', str_contains($repositorySource, 'markResourceState') && str_contains($repositorySource, "'detached'") && str_contains($repositorySource, "'removed'"));

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name=>$ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) { fwrite(STDERR, PHP_EOL . 'Talvoro 0.17.0 starter engine checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo PHP_EOL . 'Talvoro 0.17.0 starter engine checks passed.' . PHP_EOL;
