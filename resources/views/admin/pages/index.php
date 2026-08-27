<?php
use CMS\Core\Csrf;
$trashed = !empty($trashed);
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Content</p>
        <h1><?= $trashed ? 'Page Trash' : 'Pages' ?></h1>
        <p class="muted"><?= $trashed ? 'Restore pages safely or permanently remove them when you are certain they are no longer needed. Items left here are automatically removed after ' . (int)($trashRetentionDays ?? 30) . ' days.' : 'Manage the Home page and permanent website pages separately from blog posts.' ?></p>
    </div>
    <div class="header-actions">
        <?php if ($trashed): ?>
            <a class="button secondary" href="<?= e(admin_url('/pages')) ?>">← Back to Pages</a>
        <?php else: ?>
            <?php if ((int)($trashCount ?? 0) > 0): ?><a class="button secondary" href="<?= e(admin_url('/pages?view=trash')) ?>">Trash · <?= (int)$trashCount ?></a><?php endif; ?>
            <?php if (CMS\Core\Gate::allows('pages.edit')): ?><a class="button" href="<?= e(admin_url('/pages/new')) ?>">New page</a><?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<?php if (!empty($trashedNow)): ?><div class="notice success">Page moved to Trash. It can be restored until permanently deleted.</div><?php endif; ?>
<?php if (!empty($restored)): ?><div class="notice success">Page restored from Trash.</div><?php endif; ?>
<?php if (!empty($purged)): ?><div class="notice success">Page permanently deleted.</div><?php endif; ?>
<?php if ((int)($expiredPurged ?? 0) > 0): ?><div class="notice neutral"><?= (int)$expiredPurged ?> expired page<?= (int)$expiredPurged === 1 ? '' : 's' ?> automatically removed from Trash.</div><?php endif; ?>

<section class="card content-card">
    <form class="filter-bar" method="get" action="<?= e(admin_url('/pages')) ?>">
        <?php if ($trashed): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
        <label class="search-field"><span class="sr-only">Search pages</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title or path…"></label>
        <label class="status-filter"><span class="sr-only">Filter by status</span><select name="status">
            <option value="">All statuses</option>
            <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option>
            <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
        </select></label>
        <button class="button secondary" type="submit">Filter</button>
        <?php if ($search !== '' || $status !== ''): ?><a class="text-link clear-filter" href="<?= e(admin_url('/pages' . ($trashed ? '?view=trash' : ''))) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if (!$pages): ?>
        <div class="empty-state">
            <div class="empty-mark"><?= $trashed ? '✓' : '✦' ?></div>
            <h2><?= $trashed ? 'Trash is empty.' : 'No pages yet.' ?></h2>
            <p><?= $trashed ? 'Deleted pages will appear here instead of disappearing immediately.' : 'Create permanent pages such as About, Contact, Privacy or Features.' ?></p>
            <?php if (!$trashed && CMS\Core\Gate::allows('pages.edit')): ?><a class="button secondary" href="<?= e(admin_url('/pages/new')) ?>">Create page</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="posts-table">
            <?php foreach ($pages as $page): ?>
                <article class="post-row<?= $trashed ? ' trash-row' : '' ?>">
                    <div class="post-row-main">
                        <div class="post-title-line">
                            <?php if ($trashed): ?><strong><?= e($page['title']) ?></strong><?php else: ?><a href="<?= e(admin_url('/pages/' . (int)$page['id'] . '/edit')) ?>"><?= e($page['title']) ?></a><?php endif; ?>
                            <span class="status-badge <?= e($page['status']) ?>"><?= e(ucfirst($page['status'])) ?></span>
                        </div>
                        <div class="post-meta">
                            <span><?= e($page['path']) ?></span><span>·</span><span><?= e($page['author_name']) ?></span>
                            <?php if (($page['path'] ?? '') === '/'): ?><span>·</span><span>Front page</span><?php endif; ?>
                            <?php if (!$trashed && (int)($page['show_in_navigation'] ?? 0) === 1): ?><span>·</span><span>Main menu</span><?php endif; ?>
                            <?php if (!$trashed && (int)($page['show_in_footer'] ?? 0) === 1): ?><span>·</span><span>Footer</span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($trashed): ?>
                        <div class="trash-actions">
                            <small>Deleted <?= e(CMS\Core\Posts::displayDate($page['deleted_at'], 'j M Y · H:i')) ?></small>
                            <form method="post" action="<?= e(admin_url('/pages/' . (int)$page['id'] . '/restore')) ?>"><?= Csrf::field() ?><button class="button secondary small" type="submit">Restore</button></form>
                            <form method="post" action="<?= e(admin_url('/pages/' . (int)$page['id'] . '/permanent-delete')) ?>" class="inline-danger-form"><?= Csrf::field() ?><input type="hidden" name="confirm_delete" value="1"><button class="text-link danger-link" type="submit" data-confirm="Permanently delete this page and its revision history? This cannot be undone.">Delete permanently</button></form>
                        </div>
                    <?php else: ?>
                        <div class="post-row-side"><small>Updated</small><strong><?= e(CMS\Core\Posts::displayDate($page['updated_at'], 'j M Y')) ?></strong></div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
