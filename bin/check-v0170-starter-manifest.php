<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); } }

use CMS\Core\StarterManifest;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };
$throws = static function (callable $fn, string $contains = ''): bool {
    try { $fn(); return false; }
    catch (Throwable $e) { return $contains === '' || str_contains($e->getMessage(), $contains); }
};

$valid = [
    'schema_version' => 1,
    'starter_version' => '1.0.0',
    'name' => 'Reference Starter',
    'description' => 'Safe declarative content.',
    'resources' => [
        ['key'=>'media.hero','type'=>'media','data'=>['source'=>'assets/hero.webp','title'=>'Hero','alt'=>'Editorial hero']],
        ['key'=>'model.projects','type'=>'content_model','data'=>['singular_name'=>'Project','plural_name'=>'Projects','model_key'=>'project','slug'=>'projects','is_public'=>true,'has_archive'=>true,'has_urls'=>true]],
        ['key'=>'entry.project.alpha','type'=>'content_entry','data'=>['model'=>['$ref'=>'model.projects'],'title'=>'Alpha','slug'=>'alpha','status'=>'published','featured_image'=>['$ref'=>'media.hero'],'values'=>['summary'=>'First project']]],
        ['key'=>'page.home','type'=>'page','data'=>['title'=>'Home','path'=>'/','status'=>'published','blocks'=>[['id'=>'hero000000000001','type'=>'hero','enabled'=>true,'heading'=>'Hello','image'=>['$ref'=>'media.hero']]]]],
        ['key'=>'menu.primary','type'=>'menu','data'=>['name'=>'Primary','menu_key'=>'primary','location'=>'primary']],
        ['key'=>'menu_item.primary.home','type'=>'menu_item','data'=>['menu'=>['$ref'=>'menu.primary'],'label'=>'Home','target'=>['$ref'=>'page.home'],'sort_order'=>10]],
    ],
];

try {
    $result = StarterManifest::decodeAndValidate(json_encode($valid, JSON_THROW_ON_ERROR), [
        'assets/hero.webp' => ['sha256'=>str_repeat('a', 64), 'extension'=>'webp'],
    ]);
    $assert('Valid starter manifest is accepted', ($result['schema_version'] ?? null) === 1 && count($result['resources'] ?? []) === 6);
    $assert('Canonical manifest hash is stable', preg_match('/^[a-f0-9]{64}$/', (string)($result['manifest_sha256'] ?? '')) === 1);
    $mediaRow = null; foreach (($result['resources'] ?? []) as $resource) { if (($resource['key'] ?? '') === 'media.hero') { $mediaRow = $resource; break; } }
    $assert('Validated media records pin packaged asset checksum', is_array($mediaRow) && ($mediaRow['data']['_asset_sha256'] ?? '') === str_repeat('a',64));
    $order = array_column($result['resources'], 'key');
    $assert('Dependencies are topologically ordered', array_search('media.hero',$order,true) < array_search('entry.project.alpha',$order,true) && array_search('menu.primary',$order,true) < array_search('menu_item.primary.home',$order,true));
} catch (Throwable $e) {
    $checks['Valid starter manifest is accepted'] = false;
    $checks['Unexpected valid-manifest exception: ' . $e->getMessage()] = false;
}

try {
    $storedOrder = array_column(StarterManifest::orderStoredResources(array_reverse($result['resources'] ?? [])), 'key');
    $assert('Stored canonical resources recover dependency order', $storedOrder === ($order ?? []));
} catch (Throwable $e) { $checks['Stored canonical resources recover dependency order'] = false; }

$assert('Malformed JSON is rejected', $throws(fn() => StarterManifest::decodeAndValidate('{bad', []), 'invalid JSON'));
$invalidSchema = $valid; $invalidSchema['schema_version'] = 2;
$assert('Unsupported schema version is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($invalidSchema, JSON_THROW_ON_ERROR), []), 'schema_version'));
$duplicate = $valid; $duplicate['resources'][] = $duplicate['resources'][0];
$assert('Duplicate resource key is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($duplicate, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'Duplicate'));
$badKey = $valid; $badKey['resources'][0]['key'] = '../media.hero';
$assert('Unsafe resource key is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($badKey, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'key'));
$badType = $valid; $badType['resources'][0]['type'] = 'sql';
$assert('Unsupported resource type is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($badType, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'type'));
$unknownRoot = $valid; $unknownRoot['execute'] = 'phpinfo()';
$assert('Unknown root fields are rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($unknownRoot, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'Unsupported starter field'));
$unknownResource = $valid; $unknownResource['resources'][0]['php'] = '<?php';
$assert('Unknown resource envelope fields are rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($unknownResource, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'Unsupported resource field'));
$unknownData = $valid; $unknownData['resources'][0]['data']['remote_url'] = 'https://example.com/a.webp';
$assert('Unknown resource data fields are rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($unknownData, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'Unsupported media field'));
$missingRef = $valid; $missingRef['resources'][2]['data']['model'] = ['$ref'=>'model.missing'];
$assert('Missing logical reference is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($missingRef, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'missing resource'));
$invalidRef = $valid; $invalidRef['resources'][2]['data']['model'] = ['$ref'=>'page.home'];
$assert('Invalid reference target type is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($invalidRef, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'reference'));
$cycle = $valid;
$cycle['resources'][] = ['key'=>'entry.project.beta','type'=>'content_entry','data'=>['model'=>['$ref'=>'model.projects'],'title'=>'Beta','slug'=>'beta','values'=>['related'=>['$ref'=>'entry.project.gamma']]]];
$cycle['resources'][] = ['key'=>'entry.project.gamma','type'=>'content_entry','data'=>['model'=>['$ref'=>'model.projects'],'title'=>'Gamma','slug'=>'gamma','values'=>['related'=>['$ref'=>'entry.project.beta']]]];
$assert('Cyclic references are rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($cycle, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'cycle'));
$traversal = $valid; $traversal['resources'][0]['data']['source'] = 'assets/../secret.webp';
$assert('Path traversal asset is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($traversal, JSON_THROW_ON_ERROR), []), 'asset'));
$remote = $valid; $remote['resources'][0]['data']['source'] = 'https://example.com/hero.webp';
$assert('Remote starter media is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($remote, JSON_THROW_ON_ERROR), []), 'asset'));
$missingAsset = $valid;
$assert('Missing packaged asset is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($missingAsset, JSON_THROW_ON_ERROR), []), 'missing'));
$gif = $valid; $gif['resources'][0]['data']['source'] = 'assets/hero.gif';
$assert('Unsupported starter media format is rejected', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($gif, JSON_THROW_ON_ERROR), ['assets/hero.gif'=>['sha256'=>str_repeat('b',64),'extension'=>'gif']]), 'JPEG, PNG or WebP'));

$tooMany = $valid; $tooMany['resources'] = [];
for ($i=0; $i<501; $i++) $tooMany['resources'][] = ['key'=>'page.p'.$i,'type'=>'page','data'=>['title'=>'Page '.$i,'path'=>'/p'.$i,'status'=>'draft','body_html'=>'Body']];
$assert('Resource count limit is enforced', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($tooMany, JSON_THROW_ON_ERROR), []), '500'));
$longKey = $valid; $longKey['resources'][0]['key'] = 'media.' . str_repeat('a', 170);
$assert('Resource key length limit is enforced', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($longKey, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'key'));

$replacePage = $valid; $replacePage['resources'][3]['data']['replace_existing'] = true;
$assert('Page starter may explicitly request ownership-safe replacement', !$throws(fn() => StarterManifest::decodeAndValidate(json_encode($replacePage, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']])));
$badReplacePage = $valid; $badReplacePage['resources'][3]['data']['replace_existing'] = 'yes';
$assert('Page replacement flag must be boolean', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($badReplacePage, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'replace_existing'));

$reservedField = $valid;
$reservedField['resources'][] = ['key'=>'field.projects.status','type'=>'content_field','data'=>['model'=>['$ref'=>'model.projects'],'field_key'=>'status','label'=>'Status','field_type'=>'select','settings'=>['options'=>['Planned','Live']]]];
$assert('Reserved Structured Content field keys are rejected at theme import', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($reservedField, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'reserved by Talvoro'));

$reservedComponentField = $valid;
$reservedComponentField['resources'][] = ['key'=>'component.address','type'=>'content_component','data'=>['name'=>'Address','slug'=>'address']];
$reservedComponentField['resources'][] = ['key'=>'component_field.address.title','type'=>'component_field','data'=>['component'=>['$ref'=>'component.address'],'field_key'=>'title','label'=>'Title','field_type'=>'text']];
$assert('Reserved component field keys are rejected at theme import', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($reservedComponentField, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'reserved by Talvoro'));

$reservedModelKey = $valid;
$reservedModelKey['resources'][1]['data']['model_key'] = 'content';
$assert('Reserved Structured Content model keys are rejected at theme import', $throws(fn() => StarterManifest::decodeAndValidate(json_encode($reservedModelKey, JSON_THROW_ON_ERROR), ['assets/hero.webp'=>['sha256'=>str_repeat('a',64),'extension'=>'webp']]), 'reserved by Talvoro'));


$themeManagerSource = (string)file_get_contents(base_path('app/Core/ThemeManager.php'));
$migrationPath = base_path('database/migrations/027_theme_starter_sites.sql');
$migrationSource = is_file($migrationPath) ? (string)file_get_contents($migrationPath) : '';
$assert('Theme importer recognizes fixed starter manifest path', str_contains($themeManagerSource, 'starter/starter.json') && str_contains($themeManagerSource, 'StarterManifest::decodeAndValidate'));
$assert('Theme import limits expose starter contract', str_contains($themeManagerSource, "'starter_kib'") && str_contains($themeManagerSource, "'starter_resources'"));
$assert('Theme starter migration exists', is_file($migrationPath));
$assert('Migration persists definitions installations and resources', str_contains($migrationSource, 'CREATE TABLE theme_starter_definitions') && str_contains($migrationSource, 'CREATE TABLE starter_site_installations') && str_contains($migrationSource, 'CREATE TABLE starter_site_resources'));
$assert('Starter management permission defaults to super administrator only', str_contains($migrationSource, "('starter_sites.manage','Manage theme starter sites')") && str_contains($migrationSource, "r.name='super_administrator'") && !str_contains($migrationSource, "r.name='administrator'"));
$assert('Theme deletion guards active starter ownership', str_contains($themeManagerSource, 'starter_site_installations') && str_contains($themeManagerSource, 'Remove the Starter Site'));

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name=>$ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) { fwrite(STDERR, PHP_EOL . 'Talvoro 0.17.0 starter manifest checks failed: ' . implode(', ', $failed) . PHP_EOL); exit(1); }
echo PHP_EOL . 'Talvoro 0.17.0 starter manifest checks passed.' . PHP_EOL;
