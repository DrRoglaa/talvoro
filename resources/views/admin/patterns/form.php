<?php
$blocks = is_array($pattern['blocks'] ?? null) ? $pattern['blocks'] : CMS\Core\PageBlocks::decode((string)($pattern['blocks_json'] ?? '[]'));
$blocksJson = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$mediaJson = json_encode(array_values($mediaAssets ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$patternsJson = json_encode(array_values($patterns ?? []), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$contentModelsJson = json_encode(CMS\Core\ContentPresentation::builderModels(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
?>
<header class="page-header editor-header">
    <div><a class="back-link" href="<?= e(admin_url('/patterns')) ?>">← Patterns</a><p class="eyebrow">Reusable pattern</p><h1><?= $isEdit ? e($pattern['name']) : 'New pattern' ?></h1><p class="muted">Create a reusable set of structured blocks. Regular patterns are copied; synced patterns keep one shared source.</p></div>
</header>
<?php if (!empty($created)): ?><div class="notice success">Pattern created.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="notice success">Pattern saved. Synced pages now use the updated source.</div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="pattern-editor-form" data-page-editor-form data-content-safety-form data-builder-mode="pattern">
    <?= CMS\Core\Csrf::field() ?>
    <div class="pattern-editor-meta card">
        <label>Pattern name<input name="name" maxlength="160" required value="<?= e($pattern['name'] ?? '') ?>" placeholder="Five kennel values"></label>
        <label>Pattern behavior<select name="mode"><option value="regular" <?= ($pattern['mode'] ?? 'regular') === 'regular' ? 'selected' : '' ?>>Regular · insert an editable copy</option><option value="synced" <?= ($pattern['mode'] ?? '') === 'synced' ? 'selected' : '' ?>>Synced · one source updates every instance</option></select></label>
        <div class="pattern-meta-help"><strong>Recommendation</strong><span>Use Regular for starter layouts. Use Synced for shared sections such as guarantees, contact bands or brand statements.</span></div>
    </div>

    <section class="page-builder-shell page-builder-v2" data-page-builder data-builder-mode="pattern">
        <div class="page-builder-v2-topbar">
            <div><p class="eyebrow">Visual builder</p><h2>Pattern blocks</h2></div>
            <div class="builder-top-actions"><button class="button secondary small" type="button" data-add-block>+ Add block</button></div>
        </div>
        <div class="page-builder-workspace">
            <aside class="builder-outline-pane"><div class="builder-pane-head"><strong>Structure</strong><span data-block-count>0 blocks</span></div><div class="builder-outline" data-block-outline></div><button class="builder-outline-add" type="button" data-add-block>+ Add block</button></aside>
            <section class="builder-inspector-pane"><div class="page-builder-empty" data-blocks-empty>No blocks yet. Add your first reusable section.</div><div class="page-builder-list" data-block-list></div></section>
            <section class="builder-preview-pane"><div class="builder-preview-toolbar"><strong>Preview</strong><div class="builder-preview-actions"><button type="button" class="preview-open-button" data-preview-focus aria-pressed="false">Focus preview</button><button type="button" class="preview-open-button" data-open-builder-preview>Open preview ↗</button><div class="preview-device-switcher" role="group" aria-label="Preview size"><button type="button" data-preview-size="desktop" class="is-active">Desktop</button><button type="button" data-preview-size="tablet">Tablet</button><button type="button" data-preview-size="mobile">Mobile</button></div></div></div><div class="builder-preview-stage" data-preview-stage><iframe title="Pattern preview" data-builder-preview sandbox="allow-same-origin"></iframe></div></section>
        </div>
        <textarea name="page_blocks_json" data-page-blocks-json hidden><?= e((string)($pattern['blocks_json'] ?? '[]')) ?></textarea>
        <script type="application/json" data-page-blocks-initial><?= $blocksJson ?></script>
        <script type="application/json" data-media-library-initial><?= $mediaJson ?></script>
        <script type="application/json" data-patterns-initial><?= $patternsJson ?></script>
        <script type="application/json" data-content-models-initial><?= $contentModelsJson ?></script>
        <script type="application/json" data-builder-config><?= json_encode(['patternCreateUrl' => admin_url('/page-builder/patterns/create'),'patternsUrl' => admin_url('/patterns'),'internalLinksUrl' => admin_url('/internal-links'),'csrf' => CMS\Core\Csrf::token(),'mode' => 'pattern','version' => app_version()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    </section>

    <div class="editor-save-bar"><a class="button secondary" href="<?= e(admin_url('/patterns')) ?>">Cancel</a><button class="button" type="submit"><?= $isEdit ? 'Save pattern' : 'Create pattern' ?></button></div>
</form>

<?php if ($isEdit): ?>
<section class="danger-zone">
    <div><strong>Delete pattern</strong><p><?php if ((int)($pattern['usage_count'] ?? 0) > 0): ?>This pattern is used by <?= (int)$pattern['usage_count'] ?> page<?= (int)$pattern['usage_count'] === 1 ? '' : 's' ?>. Detach those synced instances before deleting it.<?php else: ?>Deleting the source cannot be undone. Regular copies already inserted into pages remain independent.<?php endif; ?></p></div>
    <form method="post" action="<?= e(admin_url('/patterns/' . (int)$pattern['id'] . '/delete')) ?>"><?= CMS\Core\Csrf::field() ?><label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> Permanently delete this pattern.</label><button class="button danger" type="submit" <?= (int)($pattern['usage_count'] ?? 0) > 0 ? 'disabled' : '' ?>>Delete pattern</button></form>
</section>
<?php endif; ?>
<script src="/assets/js/page-builder.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/content-safety.js?v=<?= e(app_version()) ?>" defer></script>
