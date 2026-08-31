<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); } }

use CMS\Core\StarterReferences;
use CMS\Core\StarterResourceRegistry;
use CMS\Core\StarterSite;

require __DIR__ . '/../bootstrap/app.php';

$checks=[];
$assert=static function(string $name,bool $ok) use (&$checks): void {$checks[$name]=$ok;};

try {
    $resolved=[
        'media.hero'=>['resource_type'=>'media','record_id'=>41,'record_locator'=>'/uploads/site/media-hero.webp'],
        'model.dogs'=>['resource_type'=>'content_model','record_id'=>7,'record_locator'=>'dog'],
        'page.about'=>['resource_type'=>'page','record_id'=>12,'record_locator'=>'/our-story'],
        'entry.dog.luna'=>['resource_type'=>'content_entry','record_id'=>55,'record_locator'=>'/dalmatians/luna'],
    ];
    $assert('Reference helper extracts strict logical references', StarterReferences::key(['$ref'=>'media.hero'])==='media.hero' && StarterReferences::key(['$ref'=>'media.hero','x'=>1])===null);
    $assert('Reference helper resolves record IDs', StarterReferences::recordId(['$ref'=>'media.hero'],$resolved,'media')===41);
    $assert('Reference helper resolves public locators', StarterReferences::locator(['$ref'=>'page.about'],$resolved,'page')==='/our-story');
    $value=StarterReferences::resolveValueTree(['portrait'=>['$ref'=>'media.hero'],'parents'=>[['$ref'=>'entry.dog.luna']]],$resolved,['media','content_entry']);
    $assert('Structured values resolve media and entry refs to IDs', $value===['portrait'=>41,'parents'=>[55]]);
} catch (Throwable $e) { $checks['Reference helper available: '.$e->getMessage()]=false; }

try {
    $assert('Registry exposes concrete adapters for all resource types', count(StarterResourceRegistry::adapterGroups())>=5 && StarterResourceRegistry::adapterClass('media')!=='' && StarterResourceRegistry::adapterClass('page')!=='');
    $assert('Arbitrary executable adapter types remain impossible', StarterResourceRegistry::adapterClass('php')==='');
} catch (Throwable $e) { $checks['Adapter registry available: '.$e->getMessage()]=false; }

try {
    $assert('Only safe starter setting keys are allowlisted', StarterSite::starterSettingAllowed('branding.site_name') && StarterSite::starterSettingAllowed('branding.tagline') && StarterSite::starterSettingAllowed('branding.footer_text') && StarterSite::starterSettingAllowed('branding.footer_note') && StarterSite::starterSettingAllowed('blog.enabled'));
    $assert('Themes without starter media do not require an installed asset directory', StarterSite::assetRootIfPresent(['slug'=>'starter-no-assets'])===null);
    $assert('Mail and security settings are forbidden to themes', !StarterSite::starterSettingAllowed('mail.smtp_password_enc') && !StarterSite::starterSettingAllowed('security.admin_path') && !StarterSite::starterSettingAllowed('contact.default_recipient'));
    $assert('Untouched created starter resources are deleted', StarterSite::removalDecision(['ownership_mode'=>'created','state'=>'owned','baseline_sha256'=>'a'], 'a', true)==='remove');
    $assert('Modified demo content is preserved and detached', StarterSite::removalDecision(['ownership_mode'=>'created','state'=>'owned','baseline_sha256'=>'a'], 'b', true)==='detach');
    $assert('Missing demo content is marked removed without touching unrelated content', StarterSite::removalDecision(['ownership_mode'=>'created','state'=>'owned','baseline_sha256'=>'a'], null, false)==='mark_removed');
    $assert('Untouched controlled mutation restores prior state', StarterSite::removalDecision(['ownership_mode'=>'mutated','state'=>'owned','baseline_sha256'=>'a'], 'a', true)==='restore');
} catch (Throwable $e) { $checks['Starter lifecycle decisions available: '.$e->getMessage()]=false; }

$adapterFiles=[
    'app/Core/StarterResourceAdapter.php',
    'app/Core/StarterResources/MediaStarterResource.php',
    'app/Core/StarterResources/StructuredContentStarterResource.php',
    'app/Core/StarterResources/PublishingStarterResource.php',
    'app/Core/StarterResources/NavigationStarterResource.php',
    'app/Core/StarterResources/ConfigurationStarterResource.php',
    'app/Core/StarterFilesystemJournal.php',
    'app/Core/StarterSite.php',
];
foreach($adapterFiles as $file) $assert('Starter lifecycle file exists: '.$file,is_file(base_path($file)));

$starterSource=(string)@file_get_contents(base_path('app/Core/StarterSite.php'));
$assert('Starter lifecycle exposes review install repair and Delete Demo Data operations', str_contains($starterSource,'function review') && str_contains($starterSource,'function install') && str_contains($starterSource,'function repair') && str_contains($starterSource,'function deleteDemoData'));
$assert('Starter install and repair require the active theme', substr_count($starterSource,'requireActiveTheme')>=2);
$assert('Starter writes are transaction protected', str_contains($starterSource,'beginTransaction') && str_contains($starterSource,'rollBack') && str_contains($starterSource,'commit'));

$siteAssetsSource=(string)@file_get_contents(base_path('app/Core/SiteAssets.php'));
$journalSource=(string)@file_get_contents(base_path('app/Core/StarterFilesystemJournal.php'));
$assert('Generated AVIF Media Library variants are valid managed upload paths', method_exists(\CMS\Core\SiteAssets::class,'managedUploadPath') && \CMS\Core\SiteAssets::managedUploadPath('/uploads/site/media-42-480.avif')==='/uploads/site/media-42-480.avif');
$assert('Starter filesystem journal uses the managed upload path boundary', str_contains($journalSource,'SiteAssets::managedUploadPath'));
$assert('Managed upload removal supports generated AVIF variants safely', str_contains($siteAssetsSource, "(?:jpe?g|png|webp|avif)"));

$mediaSource=(string)@file_get_contents(base_path('app/Core/MediaLibrary.php'));
$assert('Media Library exposes only a verified-theme starter import boundary', str_contains($mediaSource,'importVerifiedThemeAsset') && str_contains($mediaSource,'expectedSha256') && str_contains($mediaSource,'themeAssetRoot'));
$categoriesSource=(string)@file_get_contents(base_path('app/Core/Categories.php'));
$assert('Starter removal dependencies are outer-transaction safe', substr_count($mediaSource,'$ownsTransaction')>=1 && str_contains($categoriesSource,'$ownsTransaction'));

$settingsSource=(string)@file_get_contents(base_path('app/Core/Settings.php'));
$designSource=(string)@file_get_contents(base_path('app/Core/DesignSystem.php'));
$assert('Starter-controlled settings can restore true absence', str_contains($settingsSource,'function forget'));
$assert('Theme design snapshots can target an inactive starter theme safely', str_contains($designSource,'valuesForTheme') && str_contains($designSource,'saveForTheme'));

$publishingSource=(string)@file_get_contents(base_path('app/Core/StarterResources/PublishingStarterResource.php'));
$navigationSource=(string)@file_get_contents(base_path('app/Core/StarterResources/NavigationStarterResource.php'));
$assert('Explicit page replacement is a reversible controlled mutation', str_contains($publishingSource, 'replace_existing') && str_contains($publishingSource, 'ownership-safe removal') && str_contains($publishingSource, "if(\$type==='page')"));
$assert('Restoring a replaced page cleans starter-only Page Builder assets after commit', str_contains($publishingSource, 'array_diff($starterFiles,$previousFiles)') && str_contains($starterSource, "foreach((\$result['files']??[])"));
$assert('Existing SEO is preserved as a reversible controlled mutation', str_contains($publishingSource, 'Existing SEO settings will be replaced') && str_contains($publishingSource, "if(\$type==='seo')") && str_contains($publishingSource, 'SEO::save($restore'));
$assert('SEO restoration preserves a disabled sitemap flag', str_contains($publishingSource, "unset(\$restore['sitemap_enabled'])"));
$assert('Occupied menu locations are displaced without deleting the previous menu', str_contains($navigationSource, 'displaced_menu') && str_contains($navigationSource, "location'=>'unassigned'") && str_contains($navigationSource, 'restoreDisplacedMenu'));

$starterGuide=(string)@file_get_contents(base_path('docs/THEME-STARTER-SITES.md'));
$assert('Delete Demo Data is documented as ownership-safe starter removal', str_contains($starterGuide,'Delete Demo Data') && str_contains($starterGuide,'preserve'));

$failed=array_keys(array_filter($checks,static fn(bool $ok):bool=>!$ok));
foreach($checks as $name=>$ok) echo ($ok?'[OK]   ':'[FAIL] ').$name.PHP_EOL;
if($failed){fwrite(STDERR,PHP_EOL.'Talvoro 0.17.0 starter lifecycle checks failed: '.implode(', ',$failed).PHP_EOL);exit(1);}
echo PHP_EOL.'Talvoro 0.17.0 starter lifecycle checks passed.'.PHP_EOL;
