<?php
use CMS\Core\Csrf;
use CMS\Core\HomePage;

$logo = HomePage::safeStoredAssetPath((string)($settings['branding.logo_path'] ?? ''));
$hero = HomePage::safeStoredAssetPath((string)($settings['homepage.hero_image_path'] ?? ''));
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Website</p>
        <h1>Homepage & branding</h1>
        <p class="muted">Change the public logo, hero/banner, homepage copy, calls to action and visible sections without editing theme files.</p>
    </div>
    <a class="button secondary" href="/" target="_blank" rel="noopener">Preview website ↗</a>
</header>

<?php if (!empty($saved)): ?><div class="notice success">Homepage settings saved.</div><?php endif; ?>
<?php if (!empty($error)): ?><div class="notice error"><strong>Could not save homepage.</strong><p><?= e($error) ?></p></div><?php endif; ?>

<form method="post" action="<?= e(admin_url('/homepage')) ?>" enctype="multipart/form-data" class="stack homepage-settings-form">
    <?= Csrf::field() ?>

    <section class="card form-card stack">
        <div class="section-heading"><div><p class="eyebrow">Branding</p><h2>Logo & website identity</h2></div></div>
        <div class="two-fields">
            <label>Website display name<input name="branding_site_name" maxlength="120" value="<?= e($settings['branding.site_name'] ?? '') ?>"><small class="field-help">Leave blank to use the configured website name.</small></label>
            <label>Short tagline<input name="branding_tagline" maxlength="160" value="<?= e($settings['branding.tagline'] ?? '') ?>"></label>
        </div>
        <div class="asset-editor-row">
            <div>
                <label>Logo image<input type="file" name="branding_logo" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
                <small class="field-help">JPEG, PNG or WebP, up to <?= (int)$maxUploadMb ?> MB. Transparent PNG/WebP works well for logos.</small>
                <?php if ($logo !== ''): ?><label class="checkbox-row"><input type="checkbox" name="remove_branding_logo" value="1"> Remove current logo</label><?php endif; ?>
            </div>
            <?php if ($logo !== ''): ?><div class="asset-preview logo-preview"><img src="<?= e($logo) ?>" alt="Current website logo"></div><?php endif; ?>
        </div>
    </section>

    <section class="card form-card stack">
        <div class="section-heading"><div><p class="eyebrow">Hero</p><h2>Front-page banner</h2></div></div>
        <label>Eyebrow<input name="homepage_eyebrow" maxlength="120" value="<?= e($settings['homepage.eyebrow'] ?? '') ?>"></label>
        <label>Headline<textarea name="homepage_heading" rows="4" maxlength="240" required><?= e($settings['homepage.heading'] ?? '') ?></textarea><small class="field-help">Line breaks are preserved on the public homepage.</small></label>
        <label>Introduction<textarea name="homepage_intro" rows="4" maxlength="1200" required><?= e($settings['homepage.intro'] ?? '') ?></textarea></label>

        <div class="asset-editor-row">
            <div>
                <label>Hero / banner image<input type="file" name="homepage_hero_image" accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"></label>
                <small class="field-help">JPEG, PNG or WebP, up to <?= (int)$maxUploadMb ?> MB. Wide landscape images work best.</small>
                <?php if ($hero !== ''): ?><label class="checkbox-row"><input type="checkbox" name="remove_homepage_hero_image" value="1"> Remove current banner</label><?php endif; ?>
            </div>
            <?php if ($hero !== ''): ?><div class="asset-preview hero-preview"><img src="<?= e($hero) ?>" alt="Current homepage hero"></div><?php endif; ?>
        </div>

        <div class="homepage-button-editor">
            <div class="homepage-button-card">
                <label class="checkbox-row"><input type="checkbox" name="homepage_primary_enabled" value="1" <?= ($settings['homepage.primary_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Show primary button</label>
                <label>Label<input name="homepage_primary_label" maxlength="80" value="<?= e($settings['homepage.primary_label'] ?? '') ?>"></label>
                <label>URL<input name="homepage_primary_url" maxlength="1000" value="<?= e($settings['homepage.primary_url'] ?? '') ?>" placeholder="/blog"></label>
            </div>
            <div class="homepage-button-card">
                <label class="checkbox-row"><input type="checkbox" name="homepage_secondary_enabled" value="1" <?= ($settings['homepage.secondary_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Show secondary button</label>
                <label>Label<input name="homepage_secondary_label" maxlength="80" value="<?= e($settings['homepage.secondary_label'] ?? '') ?>"></label>
                <label>URL<input name="homepage_secondary_url" maxlength="1000" value="<?= e($settings['homepage.secondary_url'] ?? '') ?>" placeholder="/about"></label>
            </div>
        </div>
    </section>

    <section class="card form-card stack">
        <div class="section-heading"><div><p class="eyebrow">Sections</p><h2>Homepage highlights</h2></div></div>
        <label class="checkbox-row"><input type="checkbox" name="homepage_features_enabled" value="1" <?= ($settings['homepage.features_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Show the three-highlight section</label>
        <label>Eyebrow<input name="homepage_features_eyebrow" maxlength="120" value="<?= e($settings['homepage.features_eyebrow'] ?? '') ?>"></label>
        <label>Heading<input name="homepage_features_heading" maxlength="240" value="<?= e($settings['homepage.features_heading'] ?? '') ?>"></label>
        <label>Introduction<textarea name="homepage_features_intro" rows="3" maxlength="800"><?= e($settings['homepage.features_intro'] ?? '') ?></textarea></label>
        <div class="homepage-feature-editor">
            <?php for ($i=1; $i<=3; $i++): ?>
                <div class="homepage-button-card">
                    <strong>Highlight <?= $i ?></strong>
                    <label>Title<input name="homepage_feature<?= $i ?>_title" maxlength="120" value="<?= e($settings['homepage.feature'.$i.'_title'] ?? '') ?>"></label>
                    <label>Description<textarea name="homepage_feature<?= $i ?>_body" rows="4" maxlength="700"><?= e($settings['homepage.feature'.$i.'_body'] ?? '') ?></textarea></label>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="card form-card stack">
        <div class="section-heading"><div><p class="eyebrow">Blog</p><h2>Latest posts</h2></div></div>
        <label class="checkbox-row"><input type="checkbox" name="homepage_latest_posts_enabled" value="1" <?= ($settings['homepage.latest_posts_enabled'] ?? '0') === '1' ? 'checked' : '' ?>> Show latest published blog posts on the homepage</label>
        <div class="two-fields">
            <label>Section heading<input name="homepage_latest_posts_heading" maxlength="180" value="<?= e($settings['homepage.latest_posts_heading'] ?? '') ?>"></label>
            <label>Number of posts<select name="homepage_latest_posts_count"><?php for ($n=1;$n<=6;$n++): ?><option value="<?= $n ?>" <?= (int)($settings['homepage.latest_posts_count'] ?? 3) === $n ? 'selected' : '' ?>><?= $n ?></option><?php endfor; ?></select></label>
        </div>
    </section>

    <div class="form-actions sticky-actions"><button class="button" type="submit">Save homepage</button><a class="button secondary" href="/" target="_blank" rel="noopener">Preview website ↗</a></div>
</form>
