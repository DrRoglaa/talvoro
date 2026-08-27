<header class="page-header editor-header">
    <div>
        <a class="back-link" href="<?= e(admin_url()) ?>/blog-categories">&larr; Categories</a>
        <p class="eyebrow">Blog category</p>
        <h1><?= e($heading) ?></h1>
        <p class="muted">Categories organize posts and create public archive pages automatically.</p>
    </div>
    <?php if ($isEdit && ($category['status'] ?? '') === 'active'): ?>
        <a class="button secondary" href="/blog/category/<?= e($category['slug']) ?>" target="_blank" rel="noopener">View archive &nearr;</a>
    <?php endif; ?>
</header>

<?php if (!empty($saved)): ?><div class="alert success">Category saved.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" class="editor-layout category-editor-layout">
    <?= CMS\Core\Csrf::field() ?>

    <section class="card editor-card stack">
        <div class="post-editor-top-grid">
            <label>Name
                <input class="post-title-input" name="name" value="<?= e($category['name'] ?? '') ?>" maxlength="120" required autofocus placeholder="News">
            </label>
            <label>URL slug
                <div class="slug-field"><span>/blog/category/</span><input name="slug" value="<?= e($category['slug'] ?? '') ?>" maxlength="191" placeholder="generated-from-name"></div>
                <small class="field-help">Leave blank on a new category to generate it automatically.</small>
            </label>
        </div>

        <label>Description
            <textarea name="description" rows="5" maxlength="4000" placeholder="What belongs in this category?"><?= e($category['description'] ?? '') ?></textarea>
            <small class="field-help">Shown on the public category archive when provided.</small>
        </label>

        <div class="section-heading"><div><p class="eyebrow">Search</p><h2>Category SEO</h2></div></div>
        <label>SEO title
            <input name="seo_title" value="<?= e($category['seo_title'] ?? '') ?>" maxlength="255" placeholder="Optional custom search title">
        </label>
        <label>Meta description
            <textarea name="meta_description" rows="3" maxlength="500" placeholder="Optional search description"><?= e($category['meta_description'] ?? '') ?></textarea>
        </label>
    </section>

    <aside class="editor-sidebar stack">
        <section class="card publish-card">
            <div class="section-heading"><div><p class="eyebrow">Availability</p><h2>Category status</h2></div></div>
            <label>Status
                <select name="status" <?= !empty($category['is_default']) ? 'disabled' : '' ?>>
                    <option value="active" <?= ($category['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= ($category['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
                <?php if (!empty($category['is_default'])): ?>
                    <input type="hidden" name="status" value="active">
                    <small class="field-help">The default category must remain active.</small>
                <?php else: ?>
                    <small class="field-help">Inactive categories stay in the CMS but their public archive is unavailable.</small>
                <?php endif; ?>
            </label>
            <label>Sort order
                <input type="number" min="0" max="10000" step="1" name="sort_order" value="<?= (int)($category['sort_order'] ?? 100) ?>">
                <small class="field-help">Lower numbers appear first.</small>
            </label>
            <button class="button full" type="submit"><?= $isEdit ? 'Save changes' : 'Create category' ?></button>
            <?php if (!empty($category['is_default'])): ?><p class="privacy-note"><span>&bull;</span> This is the protected default category.</p><?php endif; ?>
        </section>
    </aside>
</form>

<?php if ($isEdit && empty($category['is_default'])): ?>
<section class="danger-zone">
    <div>
        <strong>Delete category</strong>
        <p>Posts that would otherwise have no category are automatically moved to the default category.</p>
    </div>
    <form method="post" action="<?= e(admin_url()) ?>/blog-categories/<?= (int)$category['id'] ?>/delete" class="delete-form">
        <?= CMS\Core\Csrf::field() ?>
        <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> I understand this permanently deletes the category.</label>
        <button class="button danger" type="submit">Delete category</button>
    </form>
</section>
<?php endif; ?>
