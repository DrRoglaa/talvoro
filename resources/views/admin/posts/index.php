<?php
use CMS\Core\Csrf;
$items = $listing['items'];
$page = (int)$listing['page'];
$pages = (int)$listing['pages'];
$trashed = !empty($trashed);
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Content</p>
        <h1><?= $trashed ? 'Post Trash' : 'Posts' ?></h1>
        <p class="muted"><?= $trashed ? 'Restore deleted posts or permanently remove them after review. Items left here are automatically removed after ' . (int)($trashRetentionDays ?? 30) . ' days.' : 'Write quietly, schedule precisely, publish when it is ready.' ?></p>
    </div>
    <div class="header-actions">
        <?php if ($trashed): ?>
            <a class="button secondary" href="<?= e(admin_url('/posts')) ?>">← Back to Posts</a>
        <?php else: ?>
            <?php if ((int)($trashCount ?? 0) > 0): ?><a class="button secondary" href="<?= e(admin_url('/posts?view=trash')) ?>">Trash · <?= (int)$trashCount ?></a><?php endif; ?>
            <?php if (CMS\Core\Gate::allows('content.edit')): ?><a class="button" href="<?= e(admin_url('/posts/new')) ?>">New post</a><?php endif; ?>
        <?php endif; ?>
    </div>
</header>

<?php if (!empty($trashedNow)): ?><div class="notice success">Post moved to Trash. Its content and revision history are preserved.</div><?php endif; ?>
<?php if (!empty($restored)): ?><div class="notice success">Post restored from Trash.</div><?php endif; ?>
<?php if (!empty($purged)): ?><div class="notice success">Post permanently deleted.</div><?php endif; ?>
<?php if ((int)($expiredPurged ?? 0) > 0): ?><div class="notice neutral"><?= (int)$expiredPurged ?> expired post<?= (int)$expiredPurged === 1 ? '' : 's' ?> automatically removed from Trash.</div><?php endif; ?>

<section class="card content-card">
    <form class="filter-bar" method="get" action="<?= e(admin_url('/posts')) ?>">
        <?php if ($trashed): ?><input type="hidden" name="view" value="trash"><?php endif; ?>
        <label class="search-field"><span class="sr-only">Search posts</span><input type="search" name="q" value="<?= e($search) ?>" placeholder="Search title or slug…"></label>
        <label class="status-filter"><span class="sr-only">Filter by status</span><select name="status">
            <option value="">All statuses</option>
            <?php foreach (['draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $status === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></label>
        <button class="button secondary" type="submit">Filter</button>
        <?php if ($search !== '' || $status !== ''): ?><a class="text-link clear-filter" href="<?= e(admin_url('/posts' . ($trashed ? '?view=trash' : ''))) ?>">Clear</a><?php endif; ?>
    </form>

    <?php if (!$items): ?>
        <div class="empty-state">
            <div class="empty-mark"><?= $trashed ? '✓' : '✦' ?></div>
            <h2><?= $trashed ? 'Trash is empty.' : ($search !== '' || $status !== '' ? 'Nothing matched.' : 'No posts yet.') ?></h2>
            <p><?= $trashed ? 'Deleted posts will remain recoverable here until you permanently remove them.' : ($search !== '' || $status !== '' ? 'Try a different title, slug or status.' : 'Start with a draft. Nothing becomes public until you choose to publish it.') ?></p>
            <?php if (!$trashed && CMS\Core\Gate::allows('content.edit')): ?><a class="button secondary" href="<?= e(admin_url('/posts/new')) ?>">Create post</a><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="posts-table">
            <?php foreach ($items as $post): ?>
                <article class="post-row<?= $trashed ? ' trash-row' : '' ?>">
                    <div class="post-row-main">
                        <div class="post-title-line">
                            <?php if ($trashed): ?><strong><?= e($post['title']) ?></strong><?php else: ?><a href="<?= e(admin_url('/posts/' . (int)$post['id'] . '/edit')) ?>"><?= e($post['title']) ?></a><?php endif; ?>
                            <span class="status-badge <?= e($post['status']) ?>"><?= e(ucfirst($post['status'])) ?></span>
                        </div>
                        <div class="post-meta"><span>/blog/<?= e($post['slug']) ?></span><span>·</span><span><?= e($post['author_name']) ?></span><?php if (!empty($post['primary_category'])): ?><span>·</span><span><?= e($post['primary_category']['name']) ?></span><?php endif; ?><?php if (!$trashed): ?><span>·</span><span>Updated <?= e(CMS\Core\Posts::displayDate($post['updated_at'])) ?></span><?php endif; ?></div>
                        <?php if (!empty($post['excerpt'])): ?><p><?= e($post['excerpt']) ?></p><?php endif; ?>
                    </div>
                    <?php if ($trashed): ?>
                        <div class="trash-actions">
                            <small>Deleted <?= e(CMS\Core\Posts::displayDate($post['deleted_at'], 'j M Y · H:i')) ?></small>
                            <form method="post" action="<?= e(admin_url('/posts/' . (int)$post['id'] . '/restore')) ?>"><?= Csrf::field() ?><button class="button secondary small" type="submit">Restore</button></form>
                            <form method="post" action="<?= e(admin_url('/posts/' . (int)$post['id'] . '/permanent-delete')) ?>" class="inline-danger-form"><?= Csrf::field() ?><input type="hidden" name="confirm_delete" value="1"><button class="text-link danger-link" type="submit" data-confirm="Permanently delete this post and its revision history? This cannot be undone.">Delete permanently</button></form>
                        </div>
                    <?php else: ?>
                        <div class="post-row-side">
                            <?php if ($post['status'] === 'published'): ?><small>Published</small><strong><?= e(CMS\Core\Posts::displayDate($post['published_at'], 'j M Y')) ?></strong>
                            <?php elseif ($post['status'] === 'scheduled'): ?><small>Goes live</small><strong><?= e(CMS\Core\Posts::displayDate($post['published_at'], 'j M · H:i')) ?></strong>
                            <?php else: ?><small>Visibility</small><strong>Private</strong><?php endif; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pages > 1): ?>
            <nav class="pagination" aria-label="Posts pages">
                <?php $baseParams=['q'=>$search,'status'=>$status]; if ($trashed) $baseParams['view']='trash'; ?>
                <?php if ($page > 1): ?><a href="<?= e(admin_url('/posts?' . http_build_query(array_merge($baseParams, ['page'=>$page-1])))) ?>">← Previous</a><?php else: ?><span></span><?php endif; ?>
                <span>Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?><a href="<?= e(admin_url('/posts?' . http_build_query(array_merge($baseParams, ['page'=>$page+1])))) ?>">Next →</a><?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
