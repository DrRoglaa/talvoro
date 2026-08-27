<?php
use CMS\Core\HomePage;

$home = is_array($home ?? null) ? $home : HomePage::current();
$logo = HomePage::safeStoredAssetPath((string)($home['branding.logo_path'] ?? ''));
$hero = HomePage::safeStoredAssetPath((string)($home['homepage.hero_image_path'] ?? ''));
$maxUploadMb = (int)($maxUploadMb ?? 12);
?>
<header class="page-header editor-header">
    <div>
        <a class="back-link" href="<?= e(admin_url()) ?>/pages">← Pages</a>
        <p class="eyebrow">Front page</p>
        <h1>Home</h1>
        <p class="muted">Build the public front page from structured, editable sections. The layout follows the warm editorial composition shown in the Spottina reference.</p>
    </div>
    <a class="button secondary" href="/" target="_blank" rel="noopener">View homepage ↗</a>
</header>

<?php if (!empty($created)): ?><div class="notice success">Home page created.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="notice success">Homepage changes saved.</div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form method="post" action="<?= e($action) ?>" enctype="multipart/form-data" class="editor-layout page-editor-layout home-page-editor">
    <?= CMS\Core\Csrf::field() ?>
    <input type="hidden" name="page_template" value="home">

    <div class="stack home-editor-main">
        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Section 1</p><h2>Hero</h2></div><span class="section-hint">Large editorial introduction + banner image</span></div>
            <label>Eyebrow<input name="homepage_eyebrow" maxlength="120" value="<?= e($home['homepage.eyebrow'] ?? '') ?>" placeholder="CARE. LOVE. PURPOSE."></label>
            <label>Headline<textarea name="homepage_heading" rows="3" maxlength="300" required placeholder="Build a *beautiful* home for your story."><?= e($home['homepage.heading'] ?? '') ?></textarea><small class="field-help">Wrap one phrase in *asterisks* to give it the italic accent treatment.</small></label>
            <label>Introduction<textarea name="homepage_intro" rows="4" maxlength="1400" required><?= e($home['homepage.intro'] ?? '') ?></textarea></label>

            <div class="homepage-button-editor">
                <div class="card inset-card">
                    <label class="check-row"><input type="checkbox" name="homepage_primary_enabled" value="1" <?= ($home['homepage.primary_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Show primary button</span></label>
                    <label>Label<input name="homepage_primary_label" maxlength="80" value="<?= e($home['homepage.primary_label'] ?? '') ?>"></label>
                    <label>URL<input name="homepage_primary_url" maxlength="1000" value="<?= e($home['homepage.primary_url'] ?? '') ?>" placeholder="/about"></label>
                </div>
                <div class="card inset-card">
                    <label class="check-row"><input type="checkbox" name="homepage_secondary_enabled" value="1" <?= ($home['homepage.secondary_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Show secondary button</span></label>
                    <label>Label<input name="homepage_secondary_label" maxlength="80" value="<?= e($home['homepage.secondary_label'] ?? '') ?>"></label>
                    <label>URL<input name="homepage_secondary_url" maxlength="1000" value="<?= e($home['homepage.secondary_url'] ?? '') ?>" placeholder="/blog"></label>
                </div>
            </div>

            <div class="asset-editor-grid">
                <div>
                    <label>Hero / banner image<input type="file" name="homepage_hero_image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
                    <small class="field-help">JPEG, PNG or WebP · up to <?= $maxUploadMb ?> MB. A wide portrait/landscape crop works best.</small>
                    <?php if ($hero !== ''): ?><label class="check-row"><input type="checkbox" name="remove_homepage_hero_image" value="1"><span>Remove current hero image</span></label><?php endif; ?>
                </div>
                <?php if ($hero !== ''): ?><figure class="asset-preview hero-admin-preview"><img src="<?= e($hero) ?>" alt="Current homepage hero"></figure><?php endif; ?>
            </div>
        </section>

        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Section 2</p><h2>Trust / value strip</h2></div><label class="compact-toggle"><input type="checkbox" name="homepage_values_enabled" value="1" <?= ($home['homepage.values_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Enabled</span></label></div>
            <p class="muted">Five compact value points displayed in the floating panel beneath the hero.</p>
            <div class="home-values-editor-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="home-value-editor">
                        <span class="home-editor-index">0<?= $i ?></span>
                        <label>Title<input name="homepage_value<?= $i ?>_title" maxlength="100" value="<?= e($home['homepage.value'.$i.'_title'] ?? '') ?>"></label>
                        <label>Description<textarea name="homepage_value<?= $i ?>_body" rows="4" maxlength="420"><?= e($home['homepage.value'.$i.'_body'] ?? '') ?></textarea></label>
                    </div>
                <?php endfor; ?>
            </div>
        </section>

        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Section 3</p><h2>Featured cards</h2></div><label class="compact-toggle"><input type="checkbox" name="homepage_featured_enabled" value="1" <?= ($home['homepage.featured_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Enabled</span></label></div>
            <div class="two-fields">
                <label>Eyebrow<input name="homepage_featured_eyebrow" maxlength="120" value="<?= e($home['homepage.featured_eyebrow'] ?? '') ?>" placeholder="OUR FAMILY"></label>
                <label>Heading<input name="homepage_featured_heading" maxlength="220" value="<?= e($home['homepage.featured_heading'] ?? '') ?>" placeholder="Our dogs"></label>
            </div>
            <div class="two-fields">
                <label>View-all label<input name="homepage_featured_view_label" maxlength="80" value="<?= e($home['homepage.featured_view_label'] ?? '') ?>"></label>
                <label>View-all URL<input name="homepage_featured_view_url" maxlength="1000" value="<?= e($home['homepage.featured_view_url'] ?? '') ?>" placeholder="/dogs"></label>
            </div>

            <div class="home-featured-editor-grid">
                <?php for ($i = 1; $i <= 4; $i++):
                    $prefix = 'homepage.featured_card'.$i;
                    $image = HomePage::safeStoredAssetPath((string)($home[$prefix.'_image_path'] ?? ''));
                ?>
                    <article class="home-featured-editor-card">
                        <div class="home-featured-editor-head"><strong>Card <?= $i ?></strong><label class="compact-toggle"><input type="checkbox" name="homepage_featured_card<?= $i ?>_enabled" value="1" <?= ($home[$prefix.'_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Show</span></label></div>
                        <?php if ($image !== ''): ?><figure class="asset-preview featured-admin-preview"><img src="<?= e($image) ?>" alt=""></figure><?php endif; ?>
                        <label>Image<input type="file" name="homepage_featured_card<?= $i ?>_image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
                        <?php if ($image !== ''): ?><label class="check-row compact"><input type="checkbox" name="remove_homepage_featured_card<?= $i ?>_image" value="1"><span>Remove image</span></label><?php endif; ?>
                        <label>Title<input name="homepage_featured_card<?= $i ?>_title" maxlength="120" value="<?= e($home[$prefix.'_title'] ?? '') ?>"></label>
                        <label>Small label<input name="homepage_featured_card<?= $i ?>_meta" maxlength="80" value="<?= e($home[$prefix.'_meta'] ?? '') ?>" placeholder="Male / Female / Service"></label>
                        <label>Link<input name="homepage_featured_card<?= $i ?>_url" maxlength="1000" value="<?= e($home[$prefix.'_url'] ?? '') ?>" placeholder="/about"></label>
                        <label>Image alt text<input name="homepage_featured_card<?= $i ?>_image_alt" maxlength="180" value="<?= e($home[$prefix.'_image_alt'] ?? '') ?>"></label>
                    </article>
                <?php endfor; ?>
            </div>
        </section>

        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Section 4</p><h2>Latest blog posts</h2></div><label class="compact-toggle"><input type="checkbox" name="homepage_latest_posts_enabled" value="1" <?= ($home['homepage.latest_posts_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Enabled</span></label></div>
            <div class="two-fields">
                <label>Eyebrow<input name="homepage_latest_posts_eyebrow" maxlength="120" value="<?= e($home['homepage.latest_posts_eyebrow'] ?? '') ?>"></label>
                <label>Heading<input name="homepage_latest_posts_heading" maxlength="180" value="<?= e($home['homepage.latest_posts_heading'] ?? '') ?>"></label>
            </div>
            <div class="two-fields">
                <label>View-all label<input name="homepage_latest_posts_view_label" maxlength="80" value="<?= e($home['homepage.latest_posts_view_label'] ?? '') ?>"></label>
                <label>Posts to show<input type="number" name="homepage_latest_posts_count" min="1" max="6" value="<?= (int)($home['homepage.latest_posts_count'] ?? 3) ?>"></label>
            </div>
            <p class="muted">Cards use each blog post's featured image when available.</p>
        </section>

        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Section 5</p><h2>Closing call to action</h2></div><label class="compact-toggle"><input type="checkbox" name="homepage_cta_enabled" value="1" <?= ($home['homepage.cta_enabled'] ?? '0') === '1' ? 'checked' : '' ?>><span>Enabled</span></label></div>
            <label>Eyebrow<input name="homepage_cta_eyebrow" maxlength="120" value="<?= e($home['homepage.cta_eyebrow'] ?? '') ?>"></label>
            <label>Heading<textarea name="homepage_cta_heading" rows="3" maxlength="260"><?= e($home['homepage.cta_heading'] ?? '') ?></textarea></label>
            <div class="two-fields">
                <label>Button label<input name="homepage_cta_button_label" maxlength="80" value="<?= e($home['homepage.cta_button_label'] ?? '') ?>"></label>
                <label>Button URL<input name="homepage_cta_button_url" maxlength="1000" value="<?= e($home['homepage.cta_button_url'] ?? '') ?>"></label>
            </div>
        </section>

        <section class="card editor-card home-section-card">
            <div class="section-heading"><div><p class="eyebrow">Search</p><h2>SEO</h2></div></div>
            <label>SEO title<input name="seo_title" maxlength="255" value="<?= e($seo['meta_title'] ?? '') ?>"></label>
            <label>SEO description<textarea name="seo_description" rows="3" maxlength="500"><?= e($seo['meta_description'] ?? '') ?></textarea></label>
        </section>
    </div>

    <aside class="editor-sidebar stack">
        <section class="card publish-card sticky-editor-card">
            <div class="section-heading"><div><p class="eyebrow">Front page</p><h2>Home</h2></div></div>
            <p class="muted">This protected page is served at <strong>/</strong> and cannot be deleted or moved into a menu.</p>
            <button class="button full" type="submit">Save homepage</button>
            <a class="button secondary full" href="/" target="_blank" rel="noopener">Preview site ↗</a>
        </section>

        <section class="card">
            <div class="section-heading"><div><p class="eyebrow">Brand</p><h2>Header & footer</h2></div></div>
            <label>Website name<input name="branding_site_name" maxlength="120" value="<?= e($home['branding.site_name'] ?? '') ?>" placeholder="Uses APP_NAME when blank"></label>
            <label>Tagline<input name="branding_tagline" maxlength="160" value="<?= e($home['branding.tagline'] ?? '') ?>"></label>
            <?php if ($logo !== ''): ?><figure class="asset-preview logo-admin-preview"><img src="<?= e($logo) ?>" alt="Current website logo"></figure><?php endif; ?>
            <label>Logo<input type="file" name="branding_logo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
            <small class="field-help">Used in the public header and rich footer.</small>
            <?php if ($logo !== ''): ?><label class="check-row"><input type="checkbox" name="remove_branding_logo" value="1"><span>Remove current logo</span></label><?php endif; ?>
        </section>
    </aside>
</form>
