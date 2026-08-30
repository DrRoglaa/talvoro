<?php
use CMS\Core\HomePage;
use CMS\Core\PageBlocks;

$isHome = !empty($isHome) || (($page['path'] ?? '') === '/') || (($page['page_template'] ?? '') === 'home');
$placement = (string)($page['navigation_placement'] ?? '');
if (!in_array($placement, ['hidden','main','footer','both'], true)) {
    $main = !empty($page['show_in_navigation']);
    $footer = !empty($page['show_in_footer']);
    $placement = $main && $footer ? 'both' : ($main ? 'main' : ($footer ? 'footer' : 'hidden'));
}
$blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : PageBlocks::decode((string)($page['blocks_json'] ?? ''));
$blocksJson = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$home = is_array($home ?? null) ? $home : [];
$logo = $isHome ? HomePage::safeStoredAssetPath((string)($home['branding.logo_path'] ?? '')) : '';
$maxUploadMb = (int)($maxUploadMb ?? 12);
$mediaAssets = is_array($mediaAssets ?? null) ? array_values($mediaAssets) : [];
$mediaJson = json_encode($mediaAssets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$patterns = is_array($patterns ?? null) ? array_values($patterns) : [];
$patternsJson = json_encode($patterns, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$contentModelsJson = json_encode(CMS\Core\ContentPresentation::builderModels(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '[]';
$autosave = is_array($autosave ?? null) ? $autosave : null;
$autosaveJson = json_encode($autosave['payload'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}';
$pathMode = $isHome ? 'manual' : (string)($page['path_mode'] ?? ($isEdit ? 'manual' : 'auto'));
if (!in_array($pathMode, ['auto','manual'], true)) $pathMode = $isEdit ? 'manual' : 'auto';
$pagePathValue = $isHome ? '/' : ltrim((string)($page['path'] ?? ''), '/');
$canManageRedirects = !empty($canManageRedirects);
$redirectChecked = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? isset($_POST['create_path_redirect']) : true;
?>
<header class="page-header editor-header">
    <div>
        <a class="back-link" href="<?= e(admin_url()) ?>/pages">← Pages</a>
        <p class="eyebrow"><?= $isHome ? 'Front page' : ($isEdit ? 'Page' : 'New page') ?></p>
        <h1><?= $isHome ? 'Home' : e(($page['title'] ?? '') !== '' ? $page['title'] : 'Untitled page') ?></h1>
        <p class="muted"><?= $isHome ? 'Build your front page with the same editor as every other page. Add structured sections from the Blocks button.' : 'Rich content, reusable blocks, public path, navigation and search presentation in one editor.' ?></p>
    </div>
    <?php if ($isEdit && (($page['status'] ?? '') === 'published' || $isHome)): ?>
        <a class="button secondary" href="<?= e($isHome ? '/' : (string)$page['path']) ?>" target="_blank" rel="noopener">View <?= $isHome ? 'homepage' : 'page' ?> ↗</a>
    <?php endif; ?>
</header>

<?php if (!empty($created)): ?><div class="notice success">Page created.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="notice success">Changes saved. A revision snapshot was created.</div><?php endif; ?>
<?php if ($autosave): ?><div class="notice autosave-recovery" data-autosave-recovery><strong>Newer autosave available.</strong> Talvoro saved unsaved changes at <?= e(CMS\Core\Posts::displayDate($autosave['saved_at'], 'j M Y · H:i')) ?>. <button type="button" class="text-link" data-restore-autosave>Restore autosave</button><script type="application/json" data-autosave-payload><?= $autosaveJson ?></script></div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form id="page-editor-form" method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="editor-layout page-editor-layout" data-page-editor-form data-content-safety-form data-internal-link-url="<?= e(admin_url('/internal-links')) ?>"<?= $isEdit ? ' data-autosave-url="' . e((string)($autosaveUrl ?? '')) . '"' : '' ?>>
    <?= CMS\Core\Csrf::field() ?>
    <input type="hidden" name="page_template" value="<?= $isHome ? 'home' : 'standard' ?>">

    <section class="card editor-card page-main-editor">
        <div class="page-core-fields">
            <?php if ($isHome): ?>
                <label>Title<input class="title-input" value="Home" readonly aria-readonly="true"><input type="hidden" name="title" value="Home"></label>
                <label>Public URL<input value="/" readonly aria-readonly="true"><input type="hidden" name="path" value="/"><input type="hidden" name="path_mode" value="manual"><small class="field-help">The Home page is protected and always served at /.</small></label>
            <?php else: ?>
                <label>Title<input class="title-input" name="title" value="<?= e($page['title'] ?? '') ?>" maxlength="255" required placeholder="About us" data-page-title></label>
                <div class="page-url-field" data-page-url-field data-is-new="<?= $isEdit ? '0' : '1' ?>">
                    <div class="page-url-label-row"><label for="page-path-input">Public URL</label><button class="text-button" type="button" data-path-edit><?= $pathMode === 'manual' && $pagePathValue !== '' ? 'Edit URL' : 'Customize' ?></button></div>
                    <div class="page-url-input-wrap"><span aria-hidden="true">/</span><input id="page-path-input" name="path" value="<?= e($pagePathValue) ?>" maxlength="191" placeholder="Automatically generated from the title" data-page-path <?= $pathMode === 'auto' ? 'readonly aria-readonly="true"' : 'readonly aria-readonly="true"' ?>></div>
                    <input type="hidden" name="path_mode" value="<?= e($pathMode) ?>" data-path-mode>
                    <div class="page-url-meta"><small class="field-help" data-path-help><?= $isEdit ? 'The URL stays stable when you change the title. Choose Edit URL only when you intentionally want to change it.' : 'Talvoro creates this automatically from the title. You can customize it if needed.' ?></small><button class="text-button" type="button" data-path-auto hidden>Use title automatically</button></div>
                    <?php if ($isEdit && $canManageRedirects && (($page['status'] ?? '') === 'published')): ?>
                        <label class="page-url-redirect-option"><input type="checkbox" name="create_path_redirect" value="1" <?= $redirectChecked ? 'checked' : '' ?>><span><strong>Keep the old URL working</strong><small>If you change this page URL, Talvoro creates or updates a permanent 301 redirect from the old address.</small></span></label>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <label>Eyebrow<input name="eyebrow" maxlength="120" value="<?= e($page['eyebrow'] ?? '') ?>" placeholder="ABOUT"></label>
        <label>Excerpt<textarea name="excerpt" rows="3" maxlength="1000" placeholder="Short search/social fallback description."><?= e($page['excerpt'] ?? '') ?></textarea></label>

        <div class="rich-field-heading">
            <label>Page content</label>
            <span>Rich text + reusable visual sections</span>
        </div>
        <div class="rich-editor" data-rich-editor>
            <div class="rich-toolbar" data-rich-toolbar>
                <button type="button" data-command="bold"><strong>B</strong></button>
                <button type="button" data-command="italic"><em>I</em></button>
                <button type="button" data-command="underline"><u>U</u></button>
                <span class="toolbar-separator"></span>
                <button type="button" data-block="H2">H2</button>
                <button type="button" data-block="H3">H3</button>
                <button type="button" data-block="H4">H4</button>
                <button type="button" data-command="insertUnorderedList">• List</button>
                <button type="button" data-command="insertOrderedList">1. List</button>
                <button type="button" data-block="BLOCKQUOTE">Quote</button>
                <button type="button" data-action="code">Code</button>
                <span class="toolbar-separator"></span>
                <button type="button" data-action="link">Link</button>
                <button type="button" data-action="clear">Clear</button>
                <?php if ($canHtml): ?><button type="button" data-action="html">HTML</button><?php endif; ?>
                <button type="button" class="toolbar-block-button" data-action="blocks">+ Blocks</button>
            </div>
            <div class="rich-editable" contenteditable="true" data-rich-editable data-placeholder="Write page content, or build the page with Blocks…"><?= $page['body_html'] ?? '' ?></div>
            <textarea class="rich-source" data-rich-source hidden><?= e($page['body_html'] ?? '') ?></textarea>
            <textarea name="body_html" data-rich-hidden hidden><?= e($page['body_html'] ?? '') ?></textarea>
        </div>
        <small class="field-help">Blocks are structured and validated separately from HTML. Talvoro includes layouts for heroes, image/text sections, galleries, testimonials, FAQs, statistics, cards, latest posts, dynamic Content Model collections, CTAs and a flexible Custom section you can build manually and save as a Pattern.</small>

        <section class="page-builder-shell page-builder-v2" data-page-builder data-builder-mode="page">
            <div class="page-builder-v2-topbar">
                <div><p class="eyebrow">Visual builder</p><h2>Page structure</h2><p class="muted">Select a section to edit it. Drag sections in Structure to reorder them.</p></div>
                <div class="builder-top-actions"><a class="button secondary small" href="<?= e(admin_url('/patterns')) ?>">Patterns</a><button class="button secondary small" type="button" data-add-block>+ Add block</button></div>
            </div>
            <div class="page-builder-workspace">
                <aside class="builder-outline-pane">
                    <div class="builder-pane-head"><strong>Structure</strong><span data-block-count>0 blocks</span></div>
                    <div class="builder-outline" data-block-outline></div>
                    <button class="builder-outline-add" type="button" data-add-block>+ Add block</button>
                    <p class="builder-shortcut-help">Tip: type <kbd>/hero</kbd>, <kbd>/image</kbd>, <kbd>/gallery</kbd>, <kbd>/faq</kbd>, <kbd>/section</kbd> and more in an otherwise empty rich-text area.</p>
                </aside>
                <section class="builder-inspector-pane">
                    <div class="page-builder-empty" data-blocks-empty>No blocks yet. Add a section or insert a reusable Pattern.</div>
                    <div class="page-builder-list" data-block-list></div>
                </section>
                <section class="builder-preview-pane">
                    <div class="builder-preview-toolbar"><strong>Live preview</strong><div class="builder-preview-actions"><button type="button" class="preview-open-button" data-preview-focus aria-pressed="false">Focus preview</button><button type="button" class="preview-open-button" data-open-builder-preview>Open preview ↗</button><div class="preview-device-switcher" role="group" aria-label="Preview size"><button type="button" data-preview-size="desktop" class="is-active">Desktop</button><button type="button" data-preview-size="tablet">Tablet</button><button type="button" data-preview-size="mobile">Mobile</button></div></div></div>
                    <div class="builder-preview-stage" data-preview-stage><iframe title="Live page preview" data-builder-preview sandbox="allow-same-origin"></iframe></div>
                </section>
            </div>
            <textarea name="page_blocks_json" data-page-blocks-json hidden><?= e((string)($page['blocks_json'] ?? '[]')) ?></textarea>
            <script type="application/json" data-page-blocks-initial><?= $blocksJson ?></script>
            <script type="application/json" data-media-library-initial><?= $mediaJson ?></script>
            <script type="application/json" data-patterns-initial><?= $patternsJson ?></script>
            <script type="application/json" data-content-models-initial><?= $contentModelsJson ?></script>
            <script type="application/json" data-builder-config><?= json_encode(['patternCreateUrl' => admin_url('/page-builder/patterns/create'),'patternsUrl' => admin_url('/patterns'),'internalLinksUrl' => admin_url('/internal-links'),'csrf' => CMS\Core\Csrf::token(),'mode' => 'page','version' => app_version()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
        </section>

        <label>SEO title<input name="seo_title" maxlength="255" value="<?= e($seo['meta_title'] ?? '') ?>"></label>
        <label>SEO description<textarea name="seo_description" rows="3" maxlength="500"><?= e($seo['meta_description'] ?? '') ?></textarea></label>
    </section>

    <aside class="editor-sidebar stack">
        <section class="card publish-card">
            <div class="section-heading"><div><p class="eyebrow">Publishing</p><h2>Visibility</h2></div></div>
            <?php if ($isHome): ?>
                <div class="status-summary"><strong>Published front page</strong><p class="muted">Home is always published and protected from deletion.</p></div>
                <input type="hidden" name="status" value="published">
            <?php else: ?>
                <label>Status
                    <select name="status">
                        <option value="draft" <?= ($page['status'] ?? 'draft') === 'draft' ? 'selected' : '' ?>>Draft · private</option>
                        <?php if ($canPublish): ?><option value="published" <?= ($page['status'] ?? '') === 'published' ? 'selected' : '' ?>>Published</option><?php endif; ?>
                    </select>
                </label>
            <?php endif; ?>
            <?php if ($isEdit): ?><div class="autosave-status" data-autosave-status aria-live="polite">Autosave ready</div><?php endif; ?>
        </section>

        <?php if (!$isHome): ?>
        <section class="card">
            <div class="section-heading"><div><p class="eyebrow">Navigation</p><h2>Placement</h2></div></div>
            <label>Show page in
                <select name="navigation_placement">
                    <option value="hidden" <?= $placement === 'hidden' ? 'selected' : '' ?>>Hidden from menus</option>
                    <option value="main" <?= $placement === 'main' ? 'selected' : '' ?>>Main menu only</option>
                    <option value="footer" <?= $placement === 'footer' ? 'selected' : '' ?>>Footer only</option>
                    <option value="both" <?= $placement === 'both' ? 'selected' : '' ?>>Main menu + footer</option>
                </select>
                <small class="field-help">Footer only is ideal for Privacy Policy, Terms, legal pages and secondary information.</small>
            </label>
            <label>Main menu label<input name="navigation_label" maxlength="120" value="<?= e($page['navigation_label'] ?? '') ?>" placeholder="Defaults to page title"></label>
            <label>Main menu order<input type="number" min="0" max="10000" name="navigation_order" value="<?= (int)($page['navigation_order'] ?? 100) ?>"></label>
            <label>Footer label<input name="footer_label" maxlength="120" value="<?= e($page['footer_label'] ?? '') ?>" placeholder="Defaults to page title"></label>
            <label>Footer order<input type="number" min="0" max="10000" name="footer_order" value="<?= (int)($page['footer_order'] ?? 100) ?>"></label>
        </section>
        <?php else: ?>
        <section class="card site-identity-card">
            <div class="section-heading"><div><p class="eyebrow">Brand</p><h2>Header & footer</h2></div></div>
            <label>Website name<input name="branding_site_name" maxlength="120" value="<?= e($home['branding.site_name'] ?? '') ?>" placeholder="Uses APP_NAME when blank"></label>
            <label>Tagline<input name="branding_tagline" maxlength="160" value="<?= e($home['branding.tagline'] ?? '') ?>" placeholder="Independent publishing"></label>
            <label>Footer copyright / legal line<input name="branding_footer_note" maxlength="240" value="<?= e($home['branding.footer_note'] ?? '') ?>" placeholder="© 2026 - David - All rights reserved."><small class="field-help">Plain text only. Leave blank to use Talvoro's automatic footer text.</small></label>
            <?php if ($mediaAssets): ?>
                <label>Choose logo from Media Library
                    <select name="branding_logo_media_id">
                        <option value="0">— Keep current / upload below —</option>
                        <?php foreach ($mediaAssets as $asset): ?><option value="<?= (int)$asset['id'] ?>"><?= e($asset['label']) ?> · <?= (int)$asset['width'] ?>×<?= (int)$asset['height'] ?></option><?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
            <label><?= $mediaAssets ? 'Or upload a new logo' : 'Logo' ?><input type="file" name="branding_logo" accept="image/jpeg,image/png,image/webp"><small class="field-help">JPEG, PNG or WebP, up to <?= $maxUploadMb ?> MB. <a href="<?= e(admin_url('/media')) ?>">Open Media Library</a></small></label>
            <?php if ($logo !== ''): ?><div class="asset-preview compact"><img src="<?= e($logo) ?>" alt="Current website logo"><label class="check-row"><input type="checkbox" name="remove_branding_logo" value="1"> Remove current logo</label></div><?php endif; ?>
        </section>
        <?php endif; ?>
        <?php if ($isEdit): ?>
        <section class="card content-safety-card">
            <div class="section-heading"><div><p class="eyebrow">Content safety</p><h2>History</h2></div></div>
            <div class="split-row"><span>Saved revisions</span><strong><?= (int)($revisionCount ?? 0) ?></strong></div>
            <a class="button secondary full" href="<?= e(admin_url('/pages/' . (int)$page['id'] . '/edit/revisions')) ?>">View revision history</a>
            <small class="field-help">Talvoro autosaves unsaved edits and creates a revision whenever you save deliberately.</small>
        </section>
        <?php endif; ?>
    </aside>
    <div class="editor-save-bar page-editor-save-bar">
        <span class="muted"><?= $isHome ? 'Front page' : (($page['status'] ?? 'draft') === 'published' ? 'Published page' : 'Draft page') ?><?= $isEdit ? ' · revisions and autosave enabled' : ' · URL will be created automatically' ?></span>
        <button class="button" type="submit"><?= $isEdit ? ($isHome ? 'Save homepage' : 'Save changes') : 'Create page' ?></button>
    </div>
</form>

<?php if ($isEdit && !$isHome): ?>
<section class="danger-zone">
    <div><strong>Move page to Trash</strong><p>The page becomes private immediately and can be restored later. Permanent deletion is only available from Trash.</p></div>
    <form method="post" action="<?= e(admin_url()) ?>/pages/<?= (int)$page['id'] ?>/delete">
        <?= CMS\Core\Csrf::field() ?>
        <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> Move this page to Trash.</label>
        <button class="button danger" type="submit">Move to Trash</button>
    </form>
</section>
<?php endif; ?>

<script src="/assets/js/page-slug.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/rich-text-editor.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/page-builder.js?v=<?= e(app_version()) ?>" defer></script>
<script src="/assets/js/content-safety.js?v=<?= e(app_version()) ?>" defer></script>
