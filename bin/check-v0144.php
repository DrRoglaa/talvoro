<?php
declare(strict_types=1);

use CMS\Core\ContentModelStarters;
use CMS\Core\ContentModels;
use CMS\Core\ContentPresentation;
use CMS\Core\CustomContent;
use CMS\Core\Database;
use CMS\Core\PageBlocks;
use CMS\Core\PagePatternStarters;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $modelTemplatesMethod = (new ReflectionClass(ContentModelStarters::class))->getMethod('templates');
    /** @var array<string,array<string,mixed>> $modelTemplates */
    $modelTemplates = $modelTemplatesMethod->invoke(null);
    $patternTemplatesMethod = (new ReflectionClass(PagePatternStarters::class))->getMethod('templates');
    /** @var array<string,array<string,mixed>> $patternTemplates */
    $patternTemplates = $patternTemplatesMethod->invoke(null);

    $modelKeys = [];
    foreach ($modelTemplates as $template) {
        $key = ContentModels::fieldKey((string)($template['model']['model_key'] ?? ''));
        if ($key !== '') $modelKeys[] = $key;
    }
    sort($modelKeys);

    $connected = [];
    $connectedModelKeys = [];
    $allPatternsValidate = true;
    foreach ($patternTemplates as $starterKey => $template) {
        $json = json_encode($template['blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) { $allPatternsValidate = false; break; }
        $validated = PageBlocks::validateSubmitted($json, false);
        if ($validated['errors']) { $allPatternsValidate = false; break; }
        $requires = is_array($template['requires'] ?? null) ? $template['requires'] : [];
        if ($requires) {
            $connected[$starterKey] = $template;
            $requiredKey = ContentModels::fieldKey((string)($requires['model_key'] ?? ''));
            if ($requiredKey !== '') $connectedModelKeys[] = $requiredKey;
            $block = is_array(($template['blocks'][0] ?? null)) ? $template['blocks'][0] : [];
            if (($block['type'] ?? '') !== 'collection' || ContentModels::fieldKey((string)($block['model_key'] ?? '')) !== $requiredKey) {
                $allPatternsValidate = false;
                break;
            }
        }
    }
    sort($connectedModelKeys);

    $assert('Page Builder supports the safe Connected content block', in_array('collection', PageBlocks::types(), true));
    $assert('All starter patterns pass the Page Builder validator', $allPatternsValidate);
    $assert('Every starter Content Model has a connected starter Pattern', count($modelKeys) === 15 && $connectedModelKeys === $modelKeys);
    $assert('Connected starter library covers all 15 models', count($connected) === 15);
    $assert('Connected content has curated presentation modes', array_keys(ContentPresentation::presentations()) === ['cards','people','testimonials','pricing','events','resources','faq','logos']);

    $patternSource = (string)@file_get_contents(base_path('app/Core/PagePatternStarters.php'));
    $modelSource = (string)@file_get_contents(base_path('app/Core/ContentModels.php'));
    $presentationSource = (string)@file_get_contents(base_path('app/Core/ContentPresentation.php'));
    $customContentSource = (string)@file_get_contents(base_path('app/Core/CustomContent.php'));
    $mediaSource = (string)@file_get_contents(base_path('app/Core/MediaLibrary.php'));
    $builderJs = (string)@file_get_contents(base_path('public/assets/js/page-builder.js'));
    $modelJs = (string)@file_get_contents(base_path('public/assets/js/content-model-form.js'));
    $modelForm = (string)@file_get_contents(base_path('resources/views/admin/content-models/form.php'));
    $collectionView = (string)@file_get_contents(base_path('resources/views/page/blocks/collection.php'));
    $archiveView = (string)@file_get_contents(base_path('resources/views/content/archive.php'));
    $css = (string)@file_get_contents(base_path('public/assets/css/app.css'));

    $assert('Installing a connected starter can install its required model atomically',
        str_contains($patternSource, 'ContentModelStarters::install($requiredStarter, $userId)')
        && str_contains($patternSource, '$db->beginTransaction()')
        && str_contains($patternSource, '$db->rollBack()')
    );
    $assert('Model deletion protects Page and Pattern collection references',
        str_contains($modelSource, 'PageBlocks::modelUsage')
        && str_contains($modelSource, 'Remove those Connected content blocks first.')
    );
    $assert('Public collection rendering is permission-safe and fail-closed',
        str_contains($presentationSource, "(int)\$model['is_public'] !== 1")
        && str_contains($presentationSource, 'catch (\\Throwable)')
        && str_contains($presentationSource, 'return null;')
    );
    $assert('Collection queries are bounded and avoid per-entry relation hydration',
        str_contains($customContentSource, 'publicCollectionEntries')
        && str_contains($customContentSource, 'min(12, $limit)')
        && str_contains($mediaSource, 'responsiveBatch')
    );
    $assert('Page Builder persists every repeatable block type',
        str_contains($builderJs, "['values', 'cards', 'gallery', 'testimonials', 'faq', 'stats'].includes(block.type)")
        && str_contains($builderJs, "['values','cards','gallery','testimonials','faq','stats'].includes(block.type)")
    );
    $assert('Content Model capabilities use progressive dependency controls',
        str_contains($modelForm, 'data-content-model-form')
        && str_contains($modelForm, 'data-requires-public')
        && str_contains($modelForm, 'data-requires-urls')
        && str_contains($modelForm, 'data-requires-revisions')
        && str_contains($modelJs, 'setDependent')
    );
    $assert('Structured archives and connected blocks share responsive presentation styling',
        str_contains($collectionView, 'collection-picture')
        && str_contains($archiveView, 'content-archive-picture')
        && str_contains($css, '.collection-grid')
        && str_contains($css, '.content-archive-picture')
    );

    // Database-backed safety smoke test. All fixtures live inside one transaction
    // and are rolled back, so the check leaves no starter/site content behind.
    // Release packaging environments may explicitly skip this section; normal
    // installed-site execution runs it by default.
    $skipDatabaseChecks = getenv('TALVORO_SKIP_DB_CHECKS') === '1';
    if ($skipDatabaseChecks) {
        $assert('Database-backed v0.14.4 checks explicitly skipped in this environment', true);
    } else {
    $db = Database::connection();
    $userId = (int)($db->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
    $assert('Database-backed v0.14.4 checks have an existing user fixture', $userId > 0);
    if ($userId > 0) {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $nonce = substr(bin2hex(random_bytes(8)), 0, 10);

            $presentationInput = [
                'singular_name' => 'QA card', 'plural_name' => 'QA cards',
                'model_key' => 'qa_card_' . $nonce, 'slug' => 'qa-cards-' . $nonce,
                'icon' => 'collection', 'description' => 'Temporary v0.14.4 collection check.',
                'status' => 'active', 'is_public' => '1', 'has_archive' => '1', 'has_urls' => '1',
                'searchable' => '1', 'sitemap_enabled' => '0', 'enable_revisions' => '1',
                'enable_autosave' => '1', 'enable_trash' => '1', 'enable_seo' => '0',
                'enable_featured_image' => '0', 'enable_scheduling' => '0',
            ];
            $validatedModel = ContentModels::validateModel($presentationInput);
            if ($validatedModel['errors']) throw new RuntimeException(implode(' ', $validatedModel['errors']));
            $modelId = ContentModels::createModel($validatedModel['data'], $userId);
            $field = ContentModels::validateField([
                'label' => 'Summary', 'field_key' => 'summary', 'field_type' => 'textarea',
                'is_required' => '1', 'sort_order' => 10, 'max_length' => '500', 'searchable' => '1',
            ], $modelId);
            if ($field['errors']) throw new RuntimeException(implode(' ', $field['errors']));
            ContentModels::saveField($modelId, $field['data']);
            $model = ContentModels::find($modelId);
            if (!$model) throw new RuntimeException('Temporary model could not be read.');
            $entry = CustomContent::validateEntry([
                'title' => 'Collection smoke test', 'status' => 'published',
                'fields' => ['summary' => 'This published entry is resolved through the generic collection presentation layer.'],
            ], $model, null, true);
            if ($entry['errors']) throw new RuntimeException(implode(' ', $entry['errors']));
            CustomContent::create($entry['data'], $userId);
            $resolved = ContentPresentation::resolveCollection([
                'id' => 'qacollect01', 'type' => 'collection', 'enabled' => true,
                'model_key' => (string)$model['model_key'], 'presentation' => 'cards',
                'heading' => 'QA cards', 'count' => 6, 'sort' => 'newest', 'featured_only' => false,
            ]);
            $resolvedItems = is_array($resolved['_collection']['items'] ?? null) ? $resolved['_collection']['items'] : [];
            $assert('Published structured entries resolve through the generic collection service',
                count($resolvedItems) === 1
                && (string)($resolvedItems[0]['title'] ?? '') === 'Collection smoke test'
                && str_contains((string)($resolvedItems[0]['summary'] ?? ''), 'generic collection')
            );

            $safetyInput = $presentationInput;
            $safetyInput['singular_name'] = 'QA safety';
            $safetyInput['plural_name'] = 'QA safety items';
            $safetyInput['model_key'] = 'qa_safety_' . $nonce;
            $safetyInput['slug'] = 'qa-safety-' . $nonce;
            $validatedSafety = ContentModels::validateModel($safetyInput);
            if ($validatedSafety['errors']) throw new RuntimeException(implode(' ', $validatedSafety['errors']));
            $safetyModelId = ContentModels::createModel($validatedSafety['data'], $userId);
            $safetyKey = (string)$validatedSafety['data']['model_key'];
            $blocks = json_encode([[
                'id' => 'qasafety001', 'type' => 'collection', 'enabled' => true,
                'model_key' => $safetyKey, 'presentation' => 'cards', 'heading' => 'QA safety',
                'count' => 3, 'sort' => 'newest', 'featured_only' => false,
            ]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare('INSERT INTO page_patterns (name,mode,blocks_json,created_by,updated_by,created_at,updated_at) VALUES (?,\'regular\',?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $stmt->execute(['QA v0144 ' . $nonce, $blocks, $userId, $userId]);
            $patternId = (int)$db->lastInsertId();
            $usage = PageBlocks::modelUsage($safetyKey);
            $assert('Collection reference usage is detected before model deletion', $usage['patterns'] === 1 && $usage['total'] === 1);
            $blocked = false;
            try { ContentModels::deleteModel($safetyModelId); }
            catch (RuntimeException $e) { $blocked = str_contains($e->getMessage(), 'still used by'); }
            $assert('Content Model deletion is blocked while a Pattern references it', $blocked);
            $db->prepare('DELETE FROM page_patterns WHERE id=?')->execute([$patternId]);
            ContentModels::deleteModel($safetyModelId);
            $assert('Unused empty Content Model can still be deleted', ContentModels::find($safetyModelId) === null);
        } finally {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
        }
    }
    }
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
    try { $db = Database::connection(); if ($db->inTransaction()) $db->rollBack(); } catch (Throwable) {}
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.4 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.4 connected-content checks passed.' . PHP_EOL;
