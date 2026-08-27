<?php
declare(strict_types=1);

use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\ContentModels;
use CMS\Core\CustomContent;
use CMS\Core\Database;
use CMS\Core\MediaLibrary;
use CMS\Core\Redirects;
use CMS\Core\View;

require __DIR__ . '/../bootstrap/app.php';

/**
 * Transactional integration checks for Talvoro v0.13 structured content.
 *
 * The script deliberately creates realistic model/component/entry fixtures and
 * rolls the entire fixture back at the end. It is safe to run against an
 * upgraded local database and is intended to complement bin/check.php.
 */
$checks = [];
$fail = false;
$record = static function (string $label, bool $ok) use (&$checks): void { $checks[$label] = $ok; };
$containsError = static function (array $errors, string $needle): bool {
    foreach ($errors as $error) if (str_contains(mb_strtolower((string)$error), mb_strtolower($needle))) return true;
    return false;
};

$db = null;
$ownsTransaction = false;
try {
    $db = Database::connection();
    $migration = $db->prepare("SELECT COUNT(*) FROM schema_migrations WHERE migration IN ('016_content_models.sql','017_content_models_hardening.sql')");
    $migration->execute();
    $record('Migrations 016 and 017 applied', (int)$migration->fetchColumn() === 2);

    $userId = (int)$db->query(
        "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' ORDER BY CASE r.name WHEN 'super_administrator' THEN 0 WHEN 'administrator' THEN 1 ELSE 2 END,u.id ASC LIMIT 1"
    )->fetchColumn();
    if ($userId < 1) throw new RuntimeException('No active user is available for structured-content integration checks.');

    $ownsTransaction = !$db->inTransaction();
    if ($ownsTransaction) $db->beginTransaction();

    $token = 'qa' . strtolower(bin2hex(random_bytes(4)));
    $modelSlug = 'dogs-' . $token;
    $modelInput = [
        'singular_name'=>'QA Dog ' . $token,
        'plural_name'=>'QA Dogs ' . $token,
        'slug'=>$modelSlug,
        'icon'=>'paw',
        'description'=>'Transactional v0.13 integration fixture',
        'status'=>'active',
        'is_public'=>'1','has_archive'=>'1','has_urls'=>'1','searchable'=>'1','sitemap_enabled'=>'1',
        'enable_revisions'=>'1','enable_autosave'=>'1','enable_trash'=>'1','enable_seo'=>'1','enable_featured_image'=>'1','enable_scheduling'=>'1',
    ];
    $modelValidation = ContentModels::validateModel($modelInput);
    $record('Model creation validation', $modelValidation['errors'] === []);
    $record('Automatic model key generation', (string)$modelValidation['data']['model_key'] === ContentModels::fieldKey((string)$modelInput['singular_name']));
    if ($modelValidation['errors']) throw new RuntimeException(implode(' ', $modelValidation['errors']));
    $modelId = ContentModels::createModel($modelValidation['data'], $userId);
    $model = ContentModels::find($modelId);
    $record('Model created', is_array($model) && (string)$model['slug'] === $modelSlug);

    $duplicateModel = ContentModels::validateModel(array_merge($modelInput, [
        'singular_name'=>'Duplicate fixture','plural_name'=>'Duplicate fixtures','slug'=>'duplicate-'.$token,
        'model_key'=>(string)$modelValidation['data']['model_key'],
    ]));
    $record('Duplicate model keys rejected case-insensitively', $containsError($duplicateModel['errors'], 'internal key is already used'));

    $reservedModel = ContentModels::validateModel(array_merge($modelInput, [
        'singular_name'=>'Reserved fixture','plural_name'=>'Reserved fixtures','slug'=>'reserved-'.$token,'model_key'=>'title',
    ]));
    $record('Reserved identifiers rejected', $containsError($reservedModel['errors'], 'reserved'));

    $permissionRows = $db->prepare(
        "SELECT COUNT(*) FROM content_model_role_permissions mp JOIN roles r ON r.id=mp.role_id WHERE mp.model_id=? AND r.name IN ('administrator','editor')"
    );
    $permissionRows->execute([$modelId]);
    $record('Per-model permission rows seeded', (int)$permissionRows->fetchColumn() === 2);

    $permissionMatrix = ContentModels::modelRolePermissions($modelId);
    $permissionSubmit = [];
    $editorRoleId = 0;
    foreach ($permissionMatrix as $permissionRole) {
        $roleId=(int)$permissionRole['id'];
        $values=['view'=>'1','create'=>'1','edit'=>'1','publish'=>'1','delete'=>'1'];
        if ((string)$permissionRole['name']==='editor') {
            $editorRoleId=$roleId;
            $values=['view'=>'1','edit'=>'1'];
        }
        $permissionSubmit[(string)$roleId]=$values;
    }
    ContentModels::saveModelRolePermissions($modelId,$permissionSubmit);
    $permissionCheck=$db->prepare('SELECT can_view,can_create,can_edit,can_publish,can_delete FROM content_model_role_permissions WHERE model_id=? AND role_id=?');
    $permissionCheck->execute([$modelId,$editorRoleId]);
    $editorModelPermissions=$permissionCheck->fetch(PDO::FETCH_ASSOC)?:[];
    $record('Per-model permissions save independently', $editorRoleId>0
        && (int)($editorModelPermissions['can_view']??0)===1
        && (int)($editorModelPermissions['can_create']??1)===0
        && (int)($editorModelPermissions['can_edit']??0)===1
        && (int)($editorModelPermissions['can_publish']??1)===0
        && (int)($editorModelPermissions['can_delete']??1)===0);

    // Reusable component: Health Test.
    $componentValidation = ContentModels::validateComponent(['name'=>'Health Test ' . $token,'slug'=>'health-test-'.$token,'description'=>'Reusable test group','status'=>'active']);
    $record('Reusable component validation', $componentValidation['errors'] === []);
    $componentId = ContentModels::saveComponent($componentValidation['data'], $userId);

    $componentField = static function (int $componentId, array $input) use ($record): int {
        $v = ContentModels::validateComponentField($input, $componentId);
        $record('Component field ' . (string)$input['label'] . ' valid', $v['errors'] === []);
        if ($v['errors']) throw new RuntimeException(implode(' ', $v['errors']));
        return ContentModels::saveComponentField($componentId, $v['data']);
    };
    $componentField($componentId, ['label'=>'Test name','field_type'=>'text','is_required'=>'1','sort_order'=>'10','max_length'=>'160']);
    $componentField($componentId, ['label'=>'Result','field_type'=>'select','options'=>"Clear\nCarrier\nAffected",'sort_order'=>'20']);
    $componentField($componentId, ['label'=>'Test date','field_type'=>'date','sort_order'=>'30']);
    $legacySubfieldId = $componentField($componentId, ['label'=>'Legacy note','field_type'=>'text','sort_order'=>'40']);
    $record('Reusable component created', count(ContentModels::componentFields($componentId)) === 4);

    $addField = static function (int $modelId, array $input) use ($record): int {
        $v = ContentModels::validateField($input, $modelId);
        $record('Model field ' . (string)$input['label'] . ' valid', $v['errors'] === []);
        if ($v['errors']) throw new RuntimeException(implode(' ', $v['errors']));
        return ContentModels::saveField($modelId, $v['data']);
    };

    $dobFieldId = $addField($modelId, ['label'=>'Date of birth','field_type'=>'date','is_required'=>'1','sort_order'=>'10','searchable'=>'1']);
    $record('Automatic field key generation', (string)ContentModels::field($modelId,$dobFieldId)['field_key'] === 'date_of_birth');
    $addField($modelId, ['label'=>'Gender','field_type'=>'select','options'=>"Female\nMale",'sort_order'=>'20','searchable'=>'1']);
    $addField($modelId, ['label'=>'Kennel reference','field_type'=>'text','sort_order'=>'30','unique'=>'1','searchable'=>'1']);
    $addField($modelId, ['label'=>'Biography','field_type'=>'rich_text','sort_order'=>'40','searchable'=>'1']);
    $addField($modelId, ['label'=>'Main image','field_type'=>'media','sort_order'=>'50']);
    $addField($modelId, ['label'=>'Gallery','field_type'=>'gallery','sort_order'=>'60']);
    $repeaterFieldId = $addField($modelId, ['label'=>'Health tests','field_type'=>'repeater','component_id'=>(string)$componentId,'sort_order'=>'70']);
    $relationFieldId = $addField($modelId, ['label'=>'Mother','field_type'=>'relation','target_model_id'=>(string)$modelId,'relation_type'=>'many_to_one','sort_order'=>'80']);

    $duplicateField = ContentModels::validateField(['label'=>'Date of birth','field_key'=>'date_of_birth','field_type'=>'text'], $modelId);
    $record('Duplicate field keys rejected', $containsError($duplicateField['errors'], 'already used'));
    $badSelect = ContentModels::validateField(['label'=>'Empty choices','field_type'=>'select','options'=>''], $modelId);
    $record('Select fields require options', $containsError($badSelect['errors'], 'at least one option'));

    // Create a temporary Media Library record inside the rollback transaction so
    // media/gallery/featured/social references can be exercised without writing files.
    $mediaPath = '/uploads/site/' . $token . '.jpg';
    $mediaInsert = $db->prepare(
        'INSERT INTO media_assets (storage_path,original_name,alt_text,mime_type,size_bytes,width,height,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
    );
    $mediaInsert->execute([$mediaPath,$token.'.jpg','QA media','image/jpeg',128,64,64,$userId]);
    $mediaId = (int)$db->lastInsertId();
    $record('Media fixture available through Media Library', (int)(MediaLibrary::find($mediaId)['id'] ?? 0) === $mediaId);

    $missingRequired = CustomContent::validateEntry([
        'title'=>'Missing required ' . $token,'status'=>'draft','fields'=>['kennel_reference'=>'MISSING-'.$token],
    ], $model, null, true);
    $record('Required field validation', $containsError($missingRequired['errors'], 'date of birth'));

    $motherInput = [
        'title'=>'Luna ' . $token,'status'=>'published','featured_media_id'=>$mediaId,
        'seo_title'=>'Luna profile','seo_description'=>'QA mother entry','robots'=>'index,follow','social_media_id'=>$mediaId,
        'fields'=>[
            'date_of_birth'=>'2020-05-12','gender'=>'Female','kennel_reference'=>'REF-A-'.$token,
            'biography'=>'<p><strong>Healthy</strong><script>alert(1)</script></p>',
            'main_image'=>$mediaId,'gallery'=>[$mediaId],
            'health_tests'=>[['test_name'=>'Hips','result'=>'Clear','test_date'=>'2024-05-01','legacy_note'=>'preserve-me']],
        ],
    ];
    $motherValidation = CustomContent::validateEntry($motherInput,$model,null,true);
    $record('Structured entry validation', $motherValidation['errors'] === []);
    $record('Rich text sanitized', !str_contains(strtolower((string)$motherValidation['data']['field_values']['biography']), '<script'));
    if ($motherValidation['errors']) throw new RuntimeException(implode(' ', $motherValidation['errors']));
    $motherId = CustomContent::create($motherValidation['data'],$userId);
    $mother = CustomContent::find($motherId);
    $record('Record create', is_array($mother) && (string)$mother['slug'] === CustomContent::slug((string)$motherInput['title']));
    $record('Public record lookup', is_array(CustomContent::findPublishedBySlug($modelId,(string)$mother['slug'])));
    $record('Media usage normalized', MediaLibrary::structuredUsage($mediaId)['current'] >= 1);

    $secondInput = $motherInput;
    $secondInput['fields']['kennel_reference'] = 'REF-B-'.$token;
    $secondValidation = CustomContent::validateEntry($secondInput,$model,null,true);
    $record('Slug collisions resolved automatically', $secondValidation['errors'] === [] && (string)$secondValidation['data']['slug'] !== (string)$mother['slug'] && str_ends_with((string)$secondValidation['data']['slug'],'-2'));
    $secondId = CustomContent::create($secondValidation['data'],$userId);

    $uniqueConflict = $motherInput;
    $uniqueConflict['title'] = 'Unique conflict '.$token;
    $uniqueConflict['fields']['kennel_reference'] = 'REF-A-'.$token;
    $uniqueValidation = CustomContent::validateEntry($uniqueConflict,$model,null,true);
    $record('Unique fields enforced', $containsError($uniqueValidation['errors'], 'must be unique'));

    $childInput = [
        'title'=>'Nova ' . $token,'status'=>'published','fields'=>[
            'date_of_birth'=>'2025-05-12','gender'=>'Female','kennel_reference'=>'REF-C-'.$token,
            'biography'=>'Child entry','mother'=>$motherId,
            'health_tests'=>[['test_name'=>'Eyes','result'=>'Clear','test_date'=>'2026-01-15','legacy_note'=>'nested-preserved']],
        ],
    ];
    $childValidation = CustomContent::validateEntry($childInput,$model,null,true);
    $record('Relation validation', $childValidation['errors'] === [] && (int)($childValidation['data']['field_values']['mother'] ?? 0) === $motherId);
    $childId = CustomContent::create($childValidation['data'],$userId);
    $record('Relations use stable entry IDs', CustomContent::relationReferencesCount($motherId) === 1);

    // Existing slugs stay stable when the title changes unless a URL edit is explicit.
    $child = CustomContent::find($childId);
    $stableUpdate = $childInput;
    $stableUpdate['title'] = 'Nova renamed '.$token;
    $stableUpdate['slug'] = '';
    $stableValidation = CustomContent::validateEntry($stableUpdate,$model,$childId,true);
    $record('Published rename keeps URL stable', $stableValidation['errors'] === [] && (string)$stableValidation['data']['slug'] === (string)$child['slug']);
    CustomContent::update($childId,$stableValidation['data'],$child['published_at'] ?? null);

    $collisionUpdate = $stableUpdate;
    $collisionUpdate['slug'] = (string)$mother['slug'];
    $collisionValidation = CustomContent::validateEntry($collisionUpdate,$model,$childId,true);
    $record('Explicit URL collision rejected', $containsError($collisionValidation['errors'], 'already used'));

    $redirectSource = '/old-'.$modelSlug;
    $redirectDestination = '/'.$modelSlug.'/'.(string)$stableValidation['data']['slug'];
    Redirects::upsertPermanentLocal($redirectSource,$redirectDestination,$userId);
    $redirectCheck = $db->prepare('SELECT destination,status_code FROM redirects WHERE source_path=? LIMIT 1');
    $redirectCheck->execute([$redirectSource]);
    $redirectRow = $redirectCheck->fetch(PDO::FETCH_ASSOC) ?: [];
    $record('Permanent redirect integration', ($redirectRow['destination'] ?? '') === $redirectDestination && (int)($redirectRow['status_code'] ?? 0) === 301);

    // Revision + autosave + restoration.
    $revisionId = (int)(ContentHistory::capture('entry',$childId,$userId,'qa_baseline') ?? 0);
    $record('Revision captured', $revisionId > 0 && ContentHistory::count('entry',$childId) >= 1);
    $record('Revision media references normalized', is_array(ContentHistory::revision('entry',$childId,$revisionId)));
    $autosave = ContentHistory::saveAutosave('entry',$childId,$userId,array_merge($stableUpdate,['model_id'=>$modelId]));
    $record('Autosave stored', !empty($autosave['hash']) && is_array(ContentHistory::latestAutosave('entry',$childId,$userId)));

    $changed = $stableUpdate;
    $changed['title'] = 'Changed after revision '.$token;
    $changedValidation = CustomContent::validateEntry($changed,$model,$childId,true);
    CustomContent::update($childId,$changedValidation['data'],(string)($child['published_at'] ?? ''));
    ContentHistory::restore('entry',$childId,$revisionId,$userId);
    $record('Revision restoration', (string)(CustomContent::find($childId)['title'] ?? '') === (string)$stableValidation['data']['title']);

    // Schema archive safety, including nested reusable-component data.
    $legacyFieldId = $addField($modelId, ['label'=>'Legacy field','field_type'=>'text','sort_order'=>'90']);
    $withLegacy = $stableUpdate;
    $withLegacy['fields']['legacy_field'] = 'keep-after-archive';
    $withLegacy['fields']['health_tests'][0]['legacy_note'] = 'nested-preserved';
    $legacyValidation = CustomContent::validateEntry($withLegacy,$model,$childId,true);
    if ($legacyValidation['errors']) throw new RuntimeException(implode(' ', $legacyValidation['errors']));
    CustomContent::update($childId,$legacyValidation['data'],(string)($child['published_at'] ?? ''));
    $record('Field data count detects meaningful values', ContentModels::fieldDataCount($modelId,$legacyFieldId) >= 1);
    ContentModels::deleteField($modelId,$legacyFieldId);
    $archivedField = ContentModels::field($modelId,$legacyFieldId);
    $record('Field with data archives instead of deleting', is_array($archivedField) && !empty($archivedField['archived_at']));

    ContentModels::deleteComponentField($componentId,$legacySubfieldId);
    $archivedSubfield = ContentModels::componentField($componentId,$legacySubfieldId);
    $record('In-use component field archives safely', is_array($archivedSubfield) && !empty($archivedSubfield['archived_at']));

    $postArchiveInput = $stableUpdate;
    unset($postArchiveInput['fields']['legacy_field']);
    if (isset($postArchiveInput['fields']['health_tests'][0]['legacy_note'])) unset($postArchiveInput['fields']['health_tests'][0]['legacy_note']);
    $postArchiveValidation = CustomContent::validateEntry($postArchiveInput,$model,$childId,true);
    if ($postArchiveValidation['errors']) throw new RuntimeException(implode(' ', $postArchiveValidation['errors']));
    CustomContent::update($childId,$postArchiveValidation['data'],(string)($child['published_at'] ?? ''));
    $storedAfterArchive = CustomContent::decodedValues((string)(CustomContent::rawFind($childId,true)['field_values_json'] ?? '{}'));
    $record('Archived top-level values survive later edits', ($storedAfterArchive['legacy_field'] ?? null) === 'keep-after-archive');
    $record('Archived nested component values survive later edits', ($storedAfterArchive['health_tests'][0]['legacy_note'] ?? null) === 'nested-preserved');

    $typeChange = ContentModels::validateField(['label'=>'Date of birth','field_type'=>'text'], $modelId, $dobFieldId);
    $record('Unsafe field type change blocked after data exists', $containsError($typeChange['errors'], 'cannot be changed after entries exist'));

    $deleteModelBlocked = false;
    try { ContentModels::deleteModel($modelId); } catch (Throwable) { $deleteModelBlocked = true; }
    $record('Model deletion blocked while entries exist', $deleteModelBlocked);

    // Relation targets may be trashed without destroying the relationship.
    ContentLifecycle::moveToTrash('entry',$motherId,$userId);
    $trashedTarget = CustomContent::relationTargets($childId,'mother');
    $record('Trashing relation target preserves relation', count($trashedTarget) === 1 && !empty($trashedTarget[0]['deleted_at']));
    $editWithTrashedRelation = $postArchiveInput;
    $editWithTrashedRelation['fields']['mother'] = $motherId;
    $editWithTrashedValidation = CustomContent::validateEntry($editWithTrashedRelation,$model,$childId,true);
    $record('Existing relation to Trash remains saveable', !$containsError($editWithTrashedValidation['errors'], 'currently in Trash'));
    if ($editWithTrashedValidation['errors']) throw new RuntimeException(implode(' ', $editWithTrashedValidation['errors']));
    CustomContent::update($childId,$editWithTrashedValidation['data'],(string)($child['published_at'] ?? ''));
    $record('Relation preserved after source edit', CustomContent::relationReferencesCount($motherId) === 1);

    $permanentBlocked = false;
    try { ContentLifecycle::permanentlyDelete('entry',$motherId); } catch (Throwable $e) { $permanentBlocked = str_contains(mb_strtolower($e->getMessage()), 'references'); }
    $record('Referenced target permanent deletion blocked', $permanentBlocked);
    ContentLifecycle::restore('entry',$motherId,$userId);

    // Trash/restore/permanent delete on an unreferenced fixture.
    $temporaryInput = $motherInput;
    $temporaryInput['title'] = 'Temporary '.$token;
    $temporaryInput['fields']['kennel_reference'] = 'REF-TEMP-'.$token;
    $tempValidation = CustomContent::validateEntry($temporaryInput,$model,null,true);
    $tempId = CustomContent::create($tempValidation['data'],$userId);
    ContentLifecycle::moveToTrash('entry',$tempId,$userId);
    $record('Trash', is_array(ContentLifecycle::trashedItem('entry',$tempId)));
    ContentLifecycle::restore('entry',$tempId,$userId);
    $record('Restore', is_array(CustomContent::find($tempId)));
    ContentLifecycle::moveToTrash('entry',$tempId,$userId);
    ContentLifecycle::permanentlyDelete('entry',$tempId);
    $record('Permanent delete', CustomContent::rawFind($tempId,true) === null);

    // Sitemap helper excludes noindex records while public rendering works.
    $noindexInput = $motherInput;
    $noindexInput['title'] = 'Hidden from sitemap '.$token;
    $noindexInput['robots'] = 'noindex,follow';
    $noindexInput['fields']['kennel_reference'] = 'REF-NOINDEX-'.$token;
    $noindexValidation = CustomContent::validateEntry($noindexInput,$model,null,true);
    $noindexId = CustomContent::create($noindexValidation['data'],$userId);
    $noindexEntry = CustomContent::find($noindexId);
    $slugs = CustomContent::publicSlugs($modelId);
    $record('Sitemap inclusion respects noindex', in_array((string)$mother['slug'],$slugs,true) && !in_array((string)$noindexEntry['slug'],$slugs,true));

    $rendered = View::render('content/show',[
        'title'=>(string)$mother['title'],'model'=>$model,'entry'=>CustomContent::find($motherId),'fields'=>ContentModels::fields($modelId),'relatedTo'=>[],
    ]);
    $record('Public structured-content rendering', str_contains($rendered,(string)$mother['title']) && str_contains($rendered,'Date of birth'));

    // Reordering must include the exact active schema set.
    $activeFieldIds = array_map(static fn(array $row): int => (int)$row['id'], ContentModels::fields($modelId));
    $invalidOrderBlocked = false;
    try { ContentModels::reorderFields($modelId,array_slice($activeFieldIds,0,max(0,count($activeFieldIds)-1))); } catch (Throwable) { $invalidOrderBlocked = true; }
    $record('Malformed schema reorder rejected', $invalidOrderBlocked);
    ContentModels::reorderFields($modelId,array_reverse($activeFieldIds));
    $record('Accessible reorder service accepts exact field set', count(ContentModels::fields($modelId)) === count($activeFieldIds));

    // Core request-hardening hooks are deliberately asserted here because the
    // HTTP controllers are what turn malformed IDs/CSRF attempts into failures.
    $controllerSource = (string)file_get_contents(base_path('app/Http/CustomContentController.php'));
    $modelControllerSource = (string)file_get_contents(base_path('app/Http/ContentModelController.php'));
    $record('Structured write routes enforce CSRF', substr_count($controllerSource,'Csrf::valid') >= 6 && substr_count($modelControllerSource,'Csrf::valid') >= 8);
    $record('Malformed custom-content IDs use strict numeric parsing', str_contains($controllerSource,"ctype_digit(\$v)"));

    if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
    $ownsTransaction = false;
} catch (Throwable $e) {
    if ($ownsTransaction && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    $ownsTransaction = false;
    $checks['Integration test execution'] = false;
    echo 'Structured-content integration error: ' . $e->getMessage() . "\n";
}

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . "\n";
    $fail = $fail || !$ok;
}

exit($fail ? 1 : 0);
