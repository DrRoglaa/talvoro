<?php
use CMS\Core\Gate;
use CMS\Core\Posts;
use CMS\Core\Settings;

$siteMode = Settings::siteMode();
?>
<header class="page-header overview-header">
    <div>
        <p class="eyebrow">Overview</p>
        <h1>Your site at a glance</h1>
        <p class="muted">Publish, review what changed, and see what needs attention without digging through the CMS.</p>
    </div>
    <div class="overview-quick-actions" aria-label="Quick actions">
        <?php if (Gate::allows('content.edit')): ?><a class="button" href="<?= e(admin_url('/posts/new')) ?>">New post</a><?php endif; ?>
        <?php if (Gate::allows('pages.edit')): ?><a class="button secondary" href="<?= e(admin_url('/pages/new')) ?>">New page</a><?php endif; ?>
        <a class="button ghost" href="/" target="_blank" rel="noopener">View site ↗</a>
    </div>
</header>

<div class="overview-layout">
    <div class="overview-primary stack">
        <section class="admin-surface overview-attention">
            <div class="section-heading"><div><p class="eyebrow">What needs attention?</p><h2>Needs attention</h2></div></div>
            <ul class="attention-list">
                <?php if ($siteMode !== 'live'): ?>
                    <li><span class="attention-dot warning" aria-hidden="true"></span><div><strong>Public site is in development mode.</strong><?php if (Gate::allows('site.manage')): ?> <a href="<?= e(admin_url('/site-mode')) ?>">Review site mode</a><?php endif; ?></div></li>
                <?php endif; ?>
                <?php if ((int)$postCounts['draft'] > 0): ?>
                    <li><span class="attention-dot neutral" aria-hidden="true"></span><div><strong><?= (int)$postCounts['draft'] ?> draft post<?= (int)$postCounts['draft'] === 1 ? '' : 's' ?></strong> waiting for editorial review.</div></li>
                <?php endif; ?>
                <?php if ((int)$postCounts['scheduled'] > 0): ?>
                    <li><span class="attention-dot info" aria-hidden="true"></span><div><strong><?= (int)$postCounts['scheduled'] ?> scheduled post<?= (int)$postCounts['scheduled'] === 1 ? '' : 's' ?></strong> queued for publishing.</div></li>
                <?php endif; ?>
                <?php if ($siteMode === 'live' && (int)$postCounts['draft'] === 0 && (int)$postCounts['scheduled'] === 0): ?>
                    <li class="attention-clear"><span class="attention-dot success" aria-hidden="true"></span><div><strong>Nothing urgent.</strong> Your public site is live and there are no draft or scheduled posts needing attention.</div></li>
                <?php endif; ?>
            </ul>
        </section>

        <section class="admin-surface">
            <div class="section-heading">
                <div><p class="eyebrow">What changed?</p><h2>Recently edited</h2></div>
                <?php if (Gate::allows('content.view')): ?><a class="text-link" href="<?= e(admin_url('/posts')) ?>">View all →</a><?php endif; ?>
            </div>

            <?php if (!$recentPosts): ?>
                <div class="empty-state compact">
                    <div class="empty-mark">✦</div>
                    <h3>Your publishing space is ready.</h3>
                    <p>Create your first post and keep it private as a draft until it is ready.</p>
                    <?php if (Gate::allows('content.edit')): ?><a class="button secondary" href="<?= e(admin_url('/posts/new')) ?>">Create first post</a><?php endif; ?>
                </div>
            <?php else: ?>
                <div class="content-list">
                    <?php foreach ($recentPosts as $post): ?>
                        <a class="content-row" href="<?= e(admin_url('/posts/' . (int)$post['id'] . '/edit')) ?>">
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
    </div>

    <aside class="overview-secondary stack">
        <section class="admin-surface">
            <div class="section-heading"><div><p class="eyebrow">How is the site doing?</p><h2>Site snapshot</h2></div></div>
            <dl class="snapshot-list">
                <div><dt>Posts</dt><dd><?= (int)$stats['posts'] ?></dd></div>
                <div><dt>Published</dt><dd><?= (int)$stats['published'] ?></dd></div>
                <div><dt>Page views · 24h</dt><dd><?= (int)$stats['events'] ?></dd></div>
                <div><dt>CMS users</dt><dd><?= (int)$stats['users'] ?></dd></div>
            </dl>
        </section>

        <section class="admin-surface">
            <div class="section-heading"><div><p class="eyebrow">What can I do next?</p><h2>Quick actions</h2></div></div>
            <nav class="overview-action-list" aria-label="Overview shortcuts">
                <?php if (Gate::allows('pages.view')): ?><a href="<?= e(admin_url('/pages')) ?>"><span>Manage pages</span><span aria-hidden="true">→</span></a><?php endif; ?>
                <?php if (Gate::allows('media.view')): ?><a href="<?= e(admin_url('/media')) ?>"><span>Open media library</span><span aria-hidden="true">→</span></a><?php endif; ?>
                <?php if (Gate::allows('analytics.view')): ?><a href="<?= e(admin_url('/analytics')) ?>"><span>Review analytics</span><span aria-hidden="true">→</span></a><?php endif; ?>
                <?php if (Gate::allows('design.manage')): ?><a href="<?= e(admin_url('/design/styles')) ?>"><span>Customize design</span><span aria-hidden="true">→</span></a><?php endif; ?>
                <?php if (Gate::allows('site.manage')): ?><a href="<?= e(admin_url('/site-mode')) ?>"><span>Review site mode</span><span aria-hidden="true">→</span></a><?php endif; ?>
            </nav>
        </section>
    </aside>
</div>
