<header class="page-header">
    <div>
        <p class="eyebrow">Content availability</p>
        <h1>Blog</h1>
        <p class="muted">Control public availability and the editorial copy shown on the Journal archive.</p>
    </div>
    <span class="health-chip <?= $enabled ? 'ok' : 'warning' ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span>
</header>

<?php if ($saved): ?><div class="notice success">Blog availability updated.</div><?php endif; ?>

<form method="post" action="<?= e(admin_url()) ?>/blog-settings">
    <?= CMS\Core\Csrf::field() ?>
    <section class="card blog-toggle-card">
        <label class="switch-setting-row">
            <span>
                <strong>Public blog</strong>
                <small>When disabled, /blog and individual post URLs return 404, the Blog navigation item disappears, and blog URLs are removed from the sitemap.</small>
            </span>
            <span class="switch-control"><input type="checkbox" name="blog_enabled" value="1" <?= $enabled ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></span>
        </label>
        <div class="form-grid two-column blog-archive-copy-settings">
            <label>Archive title<input name="blog_archive_title" maxlength="255" value="<?= e((string)($archiveTitle ?? '')) ?>" placeholder="Thoughts, updates and useful things."></label>
            <label>Archive introduction<textarea name="blog_archive_intro" maxlength="500" rows="3" placeholder="Introduce your Journal."><?= e((string)($archiveIntro ?? '')) ?></textarea></label>
        </div>
        <div class="form-actions"><button class="button" type="submit">Save blog settings</button></div>
    </section>
</form>

<section class="card content-card blog-category-settings-card">
    <div class="section-heading">
        <div><p class="eyebrow">Organization</p><h2>Blog categories</h2></div>
        <a class="button secondary" href="<?= e(admin_url()) ?>/blog-categories">Manage categories</a>
    </div>
    <p class="muted">Define reusable categories first, then assign one or more to every blog post with a primary category for public display.</p>
</section>
