<?php
declare(strict_types=1);

use CMS\Core\Database;
use CMS\Core\Menus;
use CMS\Core\MediaLibrary;
use CMS\Core\SEO;
use CMS\Core\Settings;

require __DIR__ . '/../bootstrap/app.php';

$checks=[]; $db=Database::connection(); $started=false;
$assert=static function(string $name,bool $ok) use (&$checks): void {$checks[$name]=$ok;};
try {
    $db->beginTransaction(); $started=true;
    $userId=(int)($db->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn()?:0);

    $menuInput=['name'=>'v0.14 Check Menu','menu_key'=>'v014_check_menu','location'=>'unassigned','description'=>'Transactional release check'];
    $validated=Menus::validateMenu($menuInput); $assert('Menu validation', $validated['errors']===[]);
    $menuId=Menus::create($validated['data'],$userId); $assert('Menu creation', $menuId>0 && Menus::find($menuId)!==null);
    $itemValidation=Menus::validateItem($menuId,['label'=>'Talvoro','target_type'=>'custom','custom_url'=>'/','sort_order'=>10,'is_enabled'=>'1']);
    $assert('Menu item validation',$itemValidation['errors']===[]); $itemId=Menus::createItem($menuId,$itemValidation['data']);
    $assert('Stable menu item stored',$itemId>0 && (int)(Menus::item($itemId)['menu_id']??0)===$menuId);
    $childValidation=Menus::validateItem($menuId,['label'=>'Child','target_type'=>'custom','custom_url'=>'/child','parent_id'=>(string)$itemId,'sort_order'=>20,'is_enabled'=>'1']); $childId=Menus::createItem($menuId,$childValidation['data']);
    $cycle=Menus::validateItem($menuId,['label'=>'Talvoro','target_type'=>'custom','custom_url'=>'/','parent_id'=>(string)$childId,'sort_order'=>10,'is_enabled'=>'1'],$itemId);
    $assert('Nested menu cycle protection',$childId>0 && $cycle['errors']!==[]);
    $unsafe=Menus::validateItem($menuId,['label'=>'Unsafe','target_type'=>'custom','custom_url'=>'javascript:alert(1)','is_enabled'=>'1']);
    $assert('Executable menu URLs rejected',$unsafe['errors']!==[]);

    $seoPath='/__talvoro-v014-check'; $seo=['path'=>$seoPath,'search_phrase'=>'release check','meta_title'=>'Talvoro v0.14 check','meta_description'=>'Temporary transactional SEO validation record.','social_title'=>'Social check','social_description'=>'Social description','canonical_url'=>'https://example.test/__talvoro-v014-check','robots'=>'noindex,follow','schema_type'=>'WebPage','sitemap_enabled'=>'1'];
    $assert('SEO 2.0 validation',SEO::validate($seo)===[]); SEO::save($seo,$userId); $stored=SEO::get($seoPath);
    $assert('SEO 2.0 storage',is_array($stored) && ($stored['schema_type']??'')==='WebPage' && ($stored['robots']??'')==='noindex,follow');
    $meta=SEO::metaForPath($seoPath,'Check'); $schema=SEO::structuredData($seoPath,$meta);
    $assert('Schema.org generation',($schema['@context']??'')==='https://schema.org' && ($schema['@type']??'')==='WebPage');
    $inventory=SEO::inventory();
    $inventoryPostCount=count(array_filter($inventory,static fn(array $item): bool=>($item['kind']??'')==='Post' && str_starts_with((string)($item['path']??''),'/blog/')));
    $publishedPostCount=Settings::blogEnabled()?(int)$db->query("SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status='published' AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()")->fetchColumn():0;
    $assert('SEO inventory includes published blog posts',$inventoryPostCount===min(2000,$publishedPostCount));

    $assert('Media 2.0 folders readable',is_array(MediaLibrary::folders()));
    $folderId=MediaLibrary::createFolder('v0.14 Check Folder',null,$userId); $assert('Media folder creation',$folderId>0 && MediaLibrary::folder($folderId)!==null);
    $assert('Media 2.0 stable usage batching',is_array(MediaLibrary::usageReferencesForAssets([])) && MediaLibrary::usageReferencesForAssets([])===[]);
    $assert('Responsive pipeline methods',method_exists(MediaLibrary::class,'regenerateVariants') && method_exists(MediaLibrary::class,'transform'));
} catch (Throwable $e) {
    $checks['Unexpected exception: '.$e->getMessage()]=false;
} finally {
    if($started && $db->inTransaction()) $db->rollBack();
}

$failed=array_keys(array_filter($checks,static fn(bool $ok): bool=>!$ok));
foreach($checks as $name=>$ok) echo ($ok?'[OK]   ':'[FAIL] ').$name.PHP_EOL;
if($failed){fwrite(STDERR,PHP_EOL.'v0.14 checks failed: '.implode(', ',$failed).PHP_EOL);exit(1);} echo PHP_EOL.'Talvoro v0.14 focused checks passed. Transactional fixtures rolled back.'.PHP_EOL;
