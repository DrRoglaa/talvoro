<?php
$selectedCategoryIds = array_map('intval', is_array($post['category_ids'] ?? null) ? $post['category_ids'] : []);
if ($selectedCategoryIds === []) {
    foreach ($categories as $candidate) {
        if ((int)($candidate['is_default'] ?? 0) === 1) {
            $selectedCategoryIds[] = (int)$candidate['id'];
            break;
        }
    }
}
$primaryCategoryId = (int)($post['primary_category_id'] ?? 0);
if ($primaryCategoryId < 1 && $selectedCategoryIds !== []) {
    $primaryCategoryId = $selectedCategoryIds[0];
}

$autosave = is_array($autosave ?? null) ? $autosave : null;
$autosaveJson = json_encode($autosave['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
?>
<header class="page-header editor-header post-editor-header">
    <div>
        <a class="back-link" href="<?= e(admin_url()) ?>/posts">&larr; Posts</a>
        <p class="eyebrow"><?= $isEdit ? 'Post' : 'New post' ?></p>
        <h1><?= e($isEdit ? 'Edit post' : 'Create post') ?></h1>
        <p class="muted"><?= $isEdit ? 'Edit the story and control exactly when it becomes public.' : 'Write first, keep it private, and publish only when it is ready.' ?></p>
    </div>
    <?php if ($isEdit && ($post['status'] ?? '') === 'published'): ?>
        <a class="button secondary" href="/blog/<?= e($post['slug']) ?>" target="_blank" rel="noopener">View post &nearr;</a>
    <?php endif; ?>
</header>

<?php if (!empty($created)): ?><div class="alert success">Post created successfully.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="alert success">Changes saved.</div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="editor-layout post-editor-layout" data-content-safety-form data-internal-link-url="<?= e(admin_url('/internal-links')) ?>"<?= $isEdit ? ' data-autosave-url="' . e((string)($autosaveUrl ?? '')) . '"' : '' ?>>
    <?= CMS\Core\Csrf::field() ?>

    <section class="card editor-card post-main-editor">
        <div class="post-editor-top-grid">
            <label>Title
                <input class="post-title-input" name="title" value="<?= e($post['title'] ?? '') ?>" maxlength="255" required autofocus placeholder="A clear, useful title">
            </label>
            <label>URL slug
                <div class="slug-field"><span>/blog/</span><input name="slug" value="<?= e($post['slug'] ?? '') ?>" maxlength="191" placeholder="generated-from-title"></div>
                <small class="field-help">Leave blank on a new post to generate it automatically.</small>
            </label>
        </div>

        <label>Excerpt
            <textarea name="excerpt" rows="3" maxlength="1000" placeholder="A short introduction for the blog listing."><?= e($post['excerpt'] ?? '') ?></textarea>
        </label>

        <div class="rich-field-heading">
            <label>Article</label>
            <span>Rich text - bold, italic, headings, lists, links, quotes and code</span>
        </div>
        <div class="rich-editor post-rich-editor" data-rich-editor>
            <div class="rich-toolbar" data-rich-toolbar role="toolbar" aria-label="Post formatting">
                <button type="button" data-command="bold" title="Bold"><strong>B</strong></button>
                <button type="button" data-command="italic" title="Italic"><em>I</em></button>
                <button type="button" data-command="underline" title="Underline"><u>U</u></button>
                <button type="button" data-command="strikeThrough" title="Strikethrough"><s>ABC</s></button>
                <span class="toolbar-separator"></span>
                <button type="button" data-block="P">P</button>
                <button type="button" data-block="H2">H2</button>
                <button type="button" data-block="H3">H3</button>
                <button type="button" data-block="H4">H4</button>
                <span class="toolbar-separator"></span>
                <button type="button" data-command="insertUnorderedList">&bull; List</button>
                <button type="button" data-command="insertOrderedList">1. List</button>
                <button type="button" data-block="BLOCKQUOTE">Quote</button>
                <button type="button" data-action="code">Code</button>
                <span class="toolbar-separator"></span>
                <button type="button" data-action="align" data-align="left" title="Align left">Left</button>
                <button type="button" data-action="align" data-align="center" title="Align center">Center</button>
                <button type="button" data-action="align" data-align="right" title="Align right">Right</button>
                <span class="toolbar-separator"></span>
                <button type="button" data-action="link">Link</button>
                <button type="button" data-action="clear">Clear</button>
                <?php if ($canHtml): ?><button type="button" data-action="html">HTML</button><?php endif; ?>
            </div>
            <div class="rich-editable" contenteditable="true" data-rich-editable data-placeholder="Write your post..."><?= $post['body_html'] ?? '' ?></div>
            <textarea class="rich-source" data-rich-source hidden><?= e($post['body_html'] ?? '') ?></textarea>
            <textarea name="body_html" data-rich-hidden hidden><?= e($post['body_html'] ?? '') ?></textarea>
        </div>
        <small class="field-help">Formatting is sanitized on save. Scripts, event handlers and unsafe links are removed.</small>
    </section>

    <aside class="editor-sidebar stack">
        <section class="card publish-card">
            <div class="section-heading"><div><p class="eyebrow">Publishing</p><h2>Visibility</h2></div></div>
            <label>Status
                <select name="status">
                    <option value="draft" <?= ($post['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft - private</option>
                    <?php if ($canPublish): ?>
                        <option value="scheduled" <?= ($post['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="published" <?= ($post['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option>
                    <?php endif; ?>
                </select>
            </label>
            <?php if ($canPublish): ?>
                <label>Publish date & time
                    <input type="datetime-local" name="published_at_local" value="<?= e($post['published_at_local'] ?? '') ?>">
                    <small class="field-help">Times use <?= e(CMS\Core\Env::get('APP_TIMEZONE', 'Europe/Ljubljana')) ?> and are stored in UTC.</small>
                </label>
            <?php endif; ?>
            <button class="button full" type="submit"><?= $isEdit ? 'Save changes' : 'Create post' ?></button>
            <?php if ($isEdit): ?><div class="autosave-status" data-autosave-status aria-live="polite">Autosave ready</div><?php endif; ?>
            <p class="privacy-note"><span>&bull;</span> Drafts never appear on the public blog.</p>
        </section>

        <section class="card post-image-card">
            <div class="section-heading"><div><p class="eyebrow">Presentation</p><h2>Featured image</h2></div></div>
            <?php $featuredImage = CMS\Core\HomePage::safeStoredAssetPath((string)($post['featured_image_path'] ?? '')); ?>
            <?php if ($featuredImage !== ''): ?><figure class="asset-preview post-featured-admin-preview"><img src="<?= e($featuredImage) ?>" alt="Current featured image"></figure><?php endif; ?>
            <?php if (!empty($mediaAssets)): ?>
                <label>Choose from Media Library
                    <select name="featured_media_id">
                        <option value="0">— Keep current / upload below —</option>
                        <?php foreach ($mediaAssets as $asset): ?>
                            <option value="<?= (int)$asset['id'] ?>"><?= e($asset['label']) ?> · <?= (int)$asset['width'] ?>×<?= (int)$asset['height'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label><?= !empty($mediaAssets) ? 'Or upload a new image' : 'Image' ?><input type="file" name="featured_image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
            <small class="field-help">Used by the homepage news cards and public blog. JPEG, PNG or WebP · up to <?= (int)($maxUploadMb ?? 12) ?> MB. <a href="<?= e(admin_url('/media')) ?>">Open Media Library</a></small>
            <?php if ($featuredImage !== ''): ?><label class="check-row"><input type="checkbox" name="remove_featured_image" value="1"><span>Remove current image</span></label><?php endif; ?>
        </section>

        <section class="card post-category-card">
            <div class="section-heading"><div><p class="eyebrow">Organization</p><h2>Categories</h2></div><?php if (CMS\Core\Gate::allows('blog.manage')): ?><a class="text-link" href="<?= e(admin_url()) ?>/blog-categories">Manage</a><?php endif; ?></div>
            <?php if (!$categories): ?>
                <p class="muted">No blog categories are available.</p>
            <?php else: ?>
                <div class="post-category-options">
                    <?php foreach ($categories as $category): ?>
                        <?php $categoryId = (int)$category['id']; ?>
                        <label class="post-category-option">
                            <input type="checkbox" name="category_ids[]" value="<?= $categoryId ?>" <?= in_array($categoryId, $selectedCategoryIds, true) ? 'checked' : '' ?>>
                            <span><strong><?= e($category['name']) ?></strong><small><?= e($category['status'] === 'active' ? 'Active' : 'Inactive') ?><?= !empty($category['is_default']) ? ' · Default' : '' ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label>Primary category
                    <select name="primary_category_id">
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= (int)$category['id'] ?>" <?= $primaryCategoryId === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="field-help">The primary category is highlighted on listings and the article page. It must also be selected above.</small>
                </label>
            <?php endif; ?>
        </section>

        <?php if ($isEdit): ?>
            <section class="card content-safety-card">
                <div class="section-heading"><div><p class="eyebrow">Content safety</p><h2>History</h2></div></div>
                <div class="split-row"><span>Saved revisions</span><strong><?= (int)($revisionCount ?? 0) ?></strong></div>
                <a class="button secondary full" href="<?= e(admin_url('/posts/' . (int)$post['id'] . '/edit/revisions')) ?>">View revision history</a>
                <small class="field-help">Autosave protects unsaved work; revisions protect deliberate saves.</small>
            </section>
            <section class="card details-card">
                <div class="section-heading"><div><p class="eyebrow">Details</p><h2>Post info</h2></div></div>
                <div class="split-row"><span>Author</span><strong><?= e($post['author_name'] ?? '') ?></strong></div>
                <div class="split-row"><span>Created</span><strong><?= e(CMS\Core\Posts::displayDate($post['created_at'] ?? null, 'j M Y')) ?></strong></div>
                <div class="split-row"><span>Updated</span><strong><?= e(CMS\Core\Posts::displayDate($post['updated_at'] ?? null, 'j M Y - H:i')) ?></strong></div>
            </section>
        <?php endif; ?>
    </aside>
</form>

<?php if ($isEdit): ?>
<section class="danger-zone">
    <div><strong>Move post to Trash</strong><p>The post is removed from the public site immediately but remains recoverable until you permanently delete it from Trash.</p></div>
    <form method="post" action="<?= e(admin_url()) ?>/posts/<?= (int)$post['id'] ?>/delete" class="delete-form">
        <?= CMS\Core\Csrf::field() ?>
        <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> Move this post to Trash.</label>
        <button class="button danger" type="submit">Move to Trash</button>
    </form>
</section>
<?php endif; ?>

<script src="/assets/js/rich-text-editor.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/content-safety.js?v=<?= e(app_version()) ?>" defer></script>
