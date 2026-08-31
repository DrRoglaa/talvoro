<header class="page-header" id="theme-library">
    <div>
        <p class="eyebrow">Appearance</p>
        <h1>Frontend themes</h1>
        <p class="muted">Talvoro Editorial is the protected default. Create or import a custom theme with CSS and local image assets when you want a different frontend look.</p>
    </div>
</header>

<?php if ($created): ?><div class="notice success">Theme created.</div><?php endif; ?>
<?php if ($imported): ?><div class="notice success">Theme imported.</div><?php endif; ?>
<?php if ($activated): ?><div class="notice success">Frontend theme activated.</div><?php endif; ?>
<?php if ($deactivated): ?><div class="notice success">Custom theme deactivated. Talvoro Editorial is active again.</div><?php endif; ?>
<?php if ($deleted): ?><div class="notice success">Theme deleted.</div><?php endif; ?>

<div class="theme-library-grid">
    <?php foreach ($themes as $theme): ?>
        <article class="theme-library-card <?= (int)$theme['is_active'] === 1 ? 'selected' : '' ?>">
            <div class="theme-preview <?= (int)$theme['is_builtin'] === 1 ? 'theme-preview-editorial' : 'theme-preview-custom' ?>">
                <span></span><span></span><span></span>
            </div>
            <div class="theme-card-head">
                <div>
                    <strong><?= e($theme['name']) ?></strong>
                    <small><?= e($theme['version']) ?><?= $theme['author'] ? ' · ' . e($theme['author']) : '' ?></small>
                </div>
                <span class="health-chip <?= (int)$theme['is_active'] === 1 ? 'ok' : 'warning' ?>"><?= (int)$theme['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
            </div>
            <p><?= e($theme['description'] ?: 'Custom frontend theme.') ?></p>
            <?php if (!empty($theme['starterDefinition'])): ?>
                <?php
                    $starterCode = (string)($theme['starterState']['code'] ?? 'not_installed');
                    $starterLabel = match ($starterCode) {
                        'installed' => 'Starter Site installed',
                        'modified' => 'Starter Site installed · modified',
                        'repair_available' => 'Starter Site · repair available',
                        'needs_attention' => 'Starter Site · needs attention',
                        'starter_update_available' => 'Starter Site update available',
                        default => 'Starter Site Available',
                    };
                ?>
                <div class="starter-theme-summary">
                    <strong><?= e($starterLabel) ?></strong>
                    <small><?= e((string)$theme['starterDefinition']['name']) ?> · v<?= e((string)$theme['starterDefinition']['starter_version']) ?></small>
                    <?php if (CMS\Core\Gate::allows('starter_sites.manage')): ?>
                        <a class="text-link" href="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/starter">Review Starter Site</a>
                    <?php else: ?>
                        <span class="muted">Starter Site actions require <code>starter_sites.manage</code> (Super Administrator by default).</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="theme-actions">
                <?php if ((int)$theme['is_active'] === 1): ?>
                    <?php if ((int)$theme['is_builtin'] !== 1): ?>
                        <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/deactivate"><?= CMS\Core\Csrf::field() ?><button class="button secondary" type="submit">Deactivate</button></form>
                    <?php else: ?>
                        <span class="soft-badge">Protected default</span>
                    <?php endif; ?>
                <?php else: ?>
                    <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/activate"><?= CMS\Core\Csrf::field() ?><button class="button" type="submit">Activate</button></form>
                <?php endif; ?>
                <?php if ((int)$theme['is_builtin'] !== 1 && (int)$theme['is_active'] !== 1): ?>
                    <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/delete"><?= CMS\Core\Csrf::field() ?><button class="link-button danger-text" type="submit">Delete</button></form>
                <?php endif; ?>
            </div>

        </article>
    <?php endforeach; ?>
</div>

<div class="theme-builder-grid">
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Create</p><h2>New theme</h2></div></div>
        <form method="post" action="<?= e(admin_url()) ?>/themes/create" class="stack">
            <?= CMS\Core\Csrf::field() ?>
            <div class="two-fields">
                <label>Name<input name="name" maxlength="120" required placeholder="My website theme"></label>
                <label>Slug<input name="slug" maxlength="120" placeholder="my-website-theme"></label>
            </div>
            <div class="two-fields">
                <label>Version<input name="version" maxlength="40" value="1.0.0"></label>
                <label>Author<input name="author" maxlength="120"></label>
            </div>
            <label>Description<textarea name="description" rows="2" maxlength="500"></textarea></label>
            <label>CSS<textarea class="theme-css-editor" name="css_text" rows="15" required placeholder=".public-body { ... }"></textarea></label>
            <button class="button" type="submit">Create inactive theme</button>
        </form>
    </section>

    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Import</p><h2>Theme package</h2></div></div>
        <?php if (CMS\Core\Gate::allows('themes.import')): ?>
            <p class="muted">Import a ZIP with <code>theme.json</code>, <code>style.css</code> and optional images inside <code>assets/</code>. A professional theme may also include declarative Starter Site data at <code>starter/starter.json</code>. Importing or activating a theme never installs that content automatically.</p>
            <p class="muted">Maximum <?= (int)$importLimits['package_mb'] ?> MB compressed, <?= (int)$importLimits['expanded_mb'] ?> MB expanded and <?= (int)$importLimits['files'] ?> files. Starter manifests are limited to <?= (int)$importLimits['starter_kib'] ?> KiB and <?= (int)$importLimits['starter_resources'] ?> resources. Allowed images: <?= e(implode(', ', array_map(static fn(string $ext): string => '.' . $ext, $importLimits['extensions']))) ?>. PHP, JavaScript, SVG and executable files are rejected.</p>
            <form method="post" action="<?= e(admin_url()) ?>/themes/import" enctype="multipart/form-data" class="stack" data-no-scroll-restore>
                <?= CMS\Core\Csrf::field() ?>
                <input type="hidden" name="MAX_FILE_SIZE" value="<?= (int)$importLimits['package_mb'] * 1024 * 1024 ?>">
                <label>Theme ZIP<input type="file" name="theme_zip" accept=".zip,application/zip" required></label>
                <button class="button secondary" type="submit">Import theme</button>
            </form>

            <div class="theme-package-example">
                <p class="eyebrow">theme.json example</p>
                <pre>{
  "name": "My Theme",
  "slug": "my-theme",
  "version": "1.0.0",
  "author": "Your name",
  "description": "Optional description"
}</pre>
                <p class="eyebrow">Package structure</p>
                <pre>my-theme/
  theme.json
  style.css
  assets/
    hero.webp
    logo.png
  starter/
    starter.json</pre>
                <p class="muted">Reference packaged images from CSS with relative URLs such as <code>url("assets/hero.webp")</code>. Talvoro rewrites them to the theme's isolated public asset path during import.</p>
            </div>
        <?php else: ?>
            <p class="muted">Theme ZIP import is restricted to Super Administrator accounts.</p>
        <?php endif; ?>
    </section>
</div>

<div class="form-actions"><a class="button secondary" href="/" target="_blank" rel="noopener">Preview website ↗</a></div>
