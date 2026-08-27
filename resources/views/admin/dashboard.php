<?php
use CMS\Core\Gate;
use CMS\Core\Posts;
use CMS\Core\Settings;

$siteMode = Settings::siteMode();
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Website operations</p>
        <h1>Control center</h1>
        <p class="muted">Manage publishing, growth and site health without leaving the product language behind.</p>
    </div>
    <?php if (Gate::allows('content.edit')): ?>
        <a class="button" href="<?= e(admin_url()) ?>/posts/new">New post</a>
    <?php endif; ?>
</header>

<section class="status-strip">
    <div>
        <span class="status-dot <?= e($siteMode) ?>"></span>
        <div>
            <small>Public site</small>
            <strong><?= $siteMode === 'live' ? 'Live' : 'In development' ?></strong>
        </div>
    </div>
    <?php if (Gate::allows('site.manage')): ?><a href="<?= e(admin_url()) ?>/site-mode">Change mode →</a><?php endif; ?>
</section>

<div class="metric-grid four">
    <article class="metric featured">
        <span>Total posts</span>
        <strong><?= (int)$stats['posts'] ?></strong>
        <small><?= (int)$postCounts['draft'] ?> draft · <?= (int)$postCounts['scheduled'] ?> scheduled</small>
    </article>
    <article class="metric">
        <span>Published</span>
        <strong><?= (int)$stats['published'] ?></strong>
        <small>Visible on your blog</small>
    </article>
    <article class="metric">
        <span>Visitors · 24h</span>
        <strong><?= (int)$stats['events'] ?></strong>
        <small>First-party page views</small>
    </article>
    <article class="metric">
        <span>Active users</span>
        <strong><?= (int)$stats['users'] ?></strong>
        <small>CMS access</small>
    </article>
</div>

<div class="operations-grid">
    <?php if (Gate::allows('analytics.view')): ?>
        <a class="operation-card" href="<?= e(admin_url()) ?>/analytics">
            <span class="operation-icon mint">↗</span>
            <div><p class="eyebrow">Growth</p><h3>Analytics</h3><p>Traffic, sessions, channels, devices and realtime activity.</p></div>
        </a>
    <?php endif; ?>
    <?php if (Gate::allows('seo.manage')): ?>
        <a class="operation-card" href="<?= e(admin_url()) ?>/seo">
            <span class="operation-icon coral">⌕</span>
            <div><p class="eyebrow">Discovery</p><h3>SEO</h3><p>Search titles, descriptions, social previews, sitemap and robots.</p></div>
        </a>
    <?php endif; ?>
    <?php if (Gate::allows('redirects.manage')): ?>
        <a class="operation-card" href="<?= e(admin_url()) ?>/redirects">
            <span class="operation-icon violet">→</span>
            <div><p class="eyebrow">URLs</p><h3>Redirects</h3><p>Permanent and temporary redirects with hit counts and loop checks.</p></div>
        </a>
    <?php endif; ?>
    <?php if (Gate::allows('sitehealth.view')): ?>
        <a class="operation-card" href="<?= e(admin_url()) ?>/site-health">
            <span class="operation-icon sand">✓</span>
            <div><p class="eyebrow">Foundation</p><h3>Site health</h3><p>Runtime, database, migrations, SEO coverage and publishing readiness.</p></div>
        </a>
    <?php endif; ?>
</div>

<section class="card content-card">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Content</p>
            <h2>Recent posts</h2>
        </div>
        <?php if (Gate::allows('content.view')): ?><a class="text-link" href="<?= e(admin_url()) ?>/posts">View all →</a><?php endif; ?>
    </div>

    <?php if (!$recentPosts): ?>
        <div class="empty-state compact">
            <div class="empty-mark">✦</div>
            <h3>Your publishing space is ready.</h3>
            <p>Create your first post and keep it private as a draft until it is ready.</p>
            <?php if (Gate::allows('content.edit')): ?><a class="button secondary" href="<?= e(admin_url()) ?>/posts/new">Create first post</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="content-list">
            <?php foreach ($recentPosts as $post): ?>
                <a class="content-row" href="<?= e(admin_url()) ?>/posts/<?= (int)$post['id'] ?>/edit">
                    <div>
                        <strong><?= e($post['title']) ?></strong>
                        <small>Updated <?= e(Posts::displayDate($post['updated_at'])) ?></small>
                    </div>
                    <span class="status-badge <?= e($post['status']) ?>"><?= e(ucfirst($post['status'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
