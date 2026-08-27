<?php use CMS\Core\Csrf; ?>
<header class="page-header editor-header">
    <div>
        <a class="back-link" href="<?= e(admin_url('/content-models')) ?>">← Content models</a>
        <p class="eyebrow">Structured content</p>
        <h1><?= $modelId ? e($model['plural_name']) : 'New content model' ?></h1>
        <p class="muted">Define the content structure once. Editors then get a clean, purpose-built form for every entry.</p>
    </div>
    <?php if ($modelId): ?><a class="button secondary" href="<?= e(admin_url('/content/' . $model['slug'])) ?>">Open content →</a><?php endif; ?>
</header>
<?php if (!empty($created)): ?><div class="notice success">Content model created. Add the fields editors should fill in.</div><?php endif; ?>
<?php if (!empty($starterInstalled)): ?><div class="notice success">Starter model installed. It is now a normal Talvoro content model — review the fields and adjust anything that should fit your site better.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="notice success">Content model saved.</div><?php endif; ?>
<?php if (!empty($fieldCreated)): ?><div class="notice success">Field added.</div><?php endif; ?>
<?php if (!empty($fieldSaved)): ?><div class="notice success">Field updated.</div><?php endif; ?>
<?php if (!empty($fieldDeleted)): ?><div class="notice success">Unused field deleted.</div><?php endif; ?>
<?php if (!empty($fieldArchived)): ?><div class="notice success">Field archived. Existing entry data is preserved and the field is no longer shown to editors.</div><?php endif; ?>
<?php if (!empty($fieldRestored)): ?><div class="notice success">Archived field restored.</div><?php endif; ?>
<?php if ($modelId && !empty($dynamicUsage['total'])): ?><div class="notice info"><strong>Used dynamically on the site.</strong> <?= (int)$dynamicUsage['pages'] ?> page<?= (int)$dynamicUsage['pages']===1?'':'s' ?> and <?= (int)$dynamicUsage['patterns'] ?> pattern<?= (int)$dynamicUsage['patterns']===1?'':'s' ?> currently read published <?= e(mb_strtolower((string)$model['plural_name'])) ?> from this model. Those sections update automatically when entries change.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($modelId ? admin_url('/content-models/'.$modelId.'/edit') : admin_url('/content-models/new')) ?>" class="model-editor-form stack" data-content-model-form>
<?= Csrf::field() ?>
<section class="card editor-card stack">
    <div class="section-heading"><div><p class="eyebrow">Identity</p><h2>Model details</h2></div><?php if ($modelId): ?><span class="health-chip <?= $model['status']==='active'?'ok':'' ?>"><?= e(ucfirst($model['status'])) ?></span><?php endif; ?></div>
    <div class="form-grid two">
        <label>Singular name<input name="singular_name" value="<?= e($model['singular_name']) ?>" maxlength="120" required placeholder="Dog" data-model-singular></label>
        <label>Plural name<input name="plural_name" value="<?= e($model['plural_name']) ?>" maxlength="120" required placeholder="Dogs" data-model-plural></label>
    </div>
    <label>URL base<div class="slug-field"><span>/</span><input name="slug" value="<?= e($model['slug']) ?>" maxlength="100" placeholder="dogs" <?= $entryCount>0?'readonly':'' ?> data-model-slug></div><small class="field-help"><?= $entryCount>0 ? 'Locked after content exists so public URLs cannot break.' : 'Generated from the plural name. You can adjust it before content is created.' ?></small></label>
    <label>Description<textarea name="description" rows="3" maxlength="500" placeholder="What editors will manage with this model."><?= e($model['description']) ?></textarea></label>
    <details class="advanced-panel">
        <summary>Advanced identity</summary>
        <div class="form-grid two advanced-panel-body">
            <label>Internal key<input name="model_key" value="<?= e($model['model_key']) ?>" maxlength="100" placeholder="dog" <?= $modelId?'readonly':'' ?> data-model-key><small class="field-help"><?= $modelId ? 'Stable after creation so permissions, relations and future APIs stay compatible.' : 'Generated automatically from the singular name. Usually you do not need to change this.' ?></small></label>
            <label>Icon<select name="icon"><?php foreach ($icons as $key=>$label): ?><option value="<?= e($key) ?>" <?= ($model['icon']??'collection')===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select><small class="field-help">Used in the CMS navigation to make content types easier to scan.</small></label>
        </div>
    </details>
</section>

<section class="card editor-card stack">
    <div class="section-heading"><div><p class="eyebrow">Publishing</p><h2>Visibility & URLs</h2></div></div>
    <p class="model-capability-note">Public URLs, archive pages, sitemap inclusion, scheduling and SEO are only available when Public content is enabled.</p>
    <div class="toggle-grid">
        <label class="toggle-card"><input type="checkbox" name="is_public" value="1" data-model-public <?= (int)$model['is_public']===1?'checked':'' ?>><span><strong>Public content</strong><small>Show published entries on the public website.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="has_urls" value="1" data-requires-public <?= (int)$model['has_urls']===1?'checked':'' ?>><span><strong>Individual URLs</strong><small>Give each entry its own page, for example /dogs/luna.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="has_archive" value="1" data-requires-public <?= (int)$model['has_archive']===1?'checked':'' ?>><span><strong>Archive page</strong><small>Create a listing page at /<?= e($model['slug'] ?: 'dogs') ?>.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="searchable" value="1" <?= (int)$model['searchable']===1?'checked':'' ?>><span><strong>Searchable</strong><small>Include entries in Talvoro's internal search and content link picker.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="sitemap_enabled" value="1" data-requires-public <?= (int)$model['sitemap_enabled']===1?'checked':'' ?>><span><strong>Include in sitemap</strong><small>Add public archive and entry URLs to sitemap.xml.</small></span></label>
    </div>
    <div class="section-heading subheading"><div><h3>Editing & safety</h3><p class="muted">Keep Talvoro's built-in editing protections enabled unless this content type genuinely does not need them.</p></div></div>
    <div class="toggle-grid">
        <label class="toggle-card"><input type="checkbox" name="enable_revisions" value="1" data-model-revisions <?= (int)$model['enable_revisions']===1?'checked':'' ?>><span><strong>Revision history</strong><small>Keep human-readable versions that editors can restore.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="enable_autosave" value="1" data-requires-revisions <?= (int)$model['enable_autosave']===1?'checked':'' ?>><span><strong>Autosave</strong><small>Recover newer unsaved work. Requires revision history.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="enable_trash" value="1" <?= (int)$model['enable_trash']===1?'checked':'' ?>><span><strong>Trash</strong><small>Use restore-before-delete instead of immediate permanent deletion.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="enable_scheduling" value="1" data-requires-public <?= (int)$model['enable_scheduling']===1?'checked':'' ?>><span><strong>Scheduled publishing</strong><small>Allow editors to publish an entry at a future date and time.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="enable_seo" value="1" data-requires-public data-requires-urls <?= (int)$model['enable_seo']===1?'checked':'' ?>><span><strong>SEO controls</strong><small>Use intelligent defaults with optional search and social overrides.</small></span></label>
        <label class="toggle-card"><input type="checkbox" name="enable_featured_image" value="1" <?= (int)$model['enable_featured_image']===1?'checked':'' ?>><span><strong>Featured image</strong><small>Add a primary image from the existing Media Library.</small></span></label>
    </div>
    <label>Status<select name="status"><option value="active" <?= $model['status']==='active'?'selected':'' ?>>Active</option><option value="disabled" <?= $model['status']==='disabled'?'selected':'' ?>>Disabled</option></select></label>
    <?php if ($modelId && $modelPermissions): ?>
    <details class="advanced-panel">
        <summary>Role access</summary>
        <div class="advanced-panel-body stack">
            <div class="permission-intro">
                <strong>Set access for this model</strong>
                <p>Choose what each role can do here. These settings can only reduce access: the role must also have the matching global Structured Content permission. Super Administrator always keeps full access.</p>
            </div>
            <div class="permission-matrix-scroll">
                <table class="permission-matrix">
                    <caption class="sr-only">Content model role permissions</caption>
                    <thead><tr><th scope="col">Role</th><th scope="col">View</th><th scope="col">Create</th><th scope="col">Edit</th><th scope="col">Publish</th><th scope="col">Delete</th></tr></thead>
                    <tbody>
                    <?php foreach ($modelPermissions as $role): ?>
                    <tr>
                        <th scope="row"><?= e($role['label']) ?></th>
                        <?php foreach (['view'=>'can_view','create'=>'can_create','edit'=>'can_edit','publish'=>'can_publish','delete'=>'can_delete'] as $action=>$column): ?>
                        <td><label class="permission-check"><span class="sr-only"><?= e($role['label'].' '.ucfirst($action)) ?></span><input type="checkbox" name="model_permissions[<?= (int)$role['id'] ?>][<?= e($action) ?>]" value="1" <?= (int)$role[$column]===1?'checked':'' ?>></label></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </details>
    <?php endif; ?>
    <div class="form-actions"><button class="button" type="submit"><?= $modelId?'Save model':'Create model' ?></button></div>
</section>
</form>

<script src="/assets/js/content-model-form.js?v=<?= e(app_version()) ?>" defer></script>

<?php if ($modelId): ?>
<section class="card content-card model-fields-card">
    <div class="section-heading"><div><p class="eyebrow">Editor form</p><h2>Fields</h2><p class="muted">These fields appear in order when an editor creates a <?= e(mb_strtolower($model['singular_name'])) ?>.</p></div><a class="button" href="<?= e(admin_url('/content-models/'.$modelId.'/fields/new')) ?>">Add field</a></div>
    <?php if (!$fields): ?><div class="empty-state compact"><h3>No custom fields yet.</h3><p>Every entry already has a Title and publishing metadata, with URL/SEO controls when enabled for the model. Add fields for the structured data that makes this content unique.</p></div><?php else: ?>
        <div class="schema-field-list" data-schema-sortable data-reorder-url="<?= e(admin_url('/content-models/'.$modelId.'/fields/reorder')) ?>" data-reorder-token="<?= e(Csrf::token()) ?>">
        <?php foreach ($fields as $field): ?><div class="schema-field-row" draggable="true" data-field-id="<?= (int)$field['id'] ?>"><button type="button" class="schema-drag" data-drag-handle aria-label="Drag <?= e($field['label']) ?> to reorder">::</button><a class="schema-field-main" href="<?= e(admin_url('/content-models/'.$modelId.'/fields/'.(int)$field['id'].'/edit')) ?>"><strong><?= e($field['label']) ?></strong><small><?= e($field['field_key']) ?> - <?= e(CMS\Core\ContentModels::fieldTypes()[$field['field_type']] ?? $field['field_type']) ?><?= (int)$field['is_required']===1?' - Required':'' ?><?= (int)(($field['settings']['unique']??0))===1?' - Unique':'' ?></small></a><div class="schema-field-actions"><button type="button" class="text-link" data-move-field="up" aria-label="Move <?= e($field['label']) ?> up">Up</button><button type="button" class="text-link" data-move-field="down" aria-label="Move <?= e($field['label']) ?> down">Down</button><span class="schema-order">#<?= (int)$field['sort_order'] ?></span></div></div><?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php if (!empty($archivedFields)): ?>
<section class="card content-card">
    <div class="section-heading"><div><p class="eyebrow">Safety archive</p><h2>Archived fields</h2><p class="muted">These fields are hidden from editors, but their existing data is preserved. Restore a field if you need it again.</p></div></div>
    <div class="schema-field-list">
    <?php foreach ($archivedFields as $field): ?><div class="schema-field-row"><span class="schema-drag" aria-hidden="true">--</span><div class="schema-field-main"><strong><?= e($field['label']) ?></strong><small><?= e($field['field_key']) ?> - archived</small></div><form method="post" action="<?= e(admin_url('/content-models/'.$modelId.'/fields/'.(int)$field['id'].'/restore')) ?>"><?= Csrf::field() ?><button class="button secondary small" type="submit">Restore field</button></form></div><?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
<script src="/assets/js/schema-sortable.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/schema-fields.js?v=<?= e(app_version()) ?>" defer></script>

<section class="danger-zone"><div><strong>Delete content model</strong><p>Talvoro only allows this when the model has no entries, including Trash, and is not referenced by relation fields, Pages or Patterns.</p></div><form method="post" action="<?= e(admin_url('/content-models/'.$modelId.'/delete')) ?>"><?= Csrf::field() ?><label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> I understand this permanently deletes this unused model and its field definitions.</label><button class="button danger" type="submit">Delete model</button></form></section>
<?php endif; ?>
