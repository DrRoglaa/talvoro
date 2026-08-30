<?php
use CMS\Core\Settings;
$items = $listing['items'];
$isCategoryArchive = isset($category) && is_array($category);
$archiveBase = $isCategoryArchive ? '/blog/category/' . rawurlencode((string)$category['slug']) : '/blog';
?>
<section class="blog-hero">
    <p class="eyebrow"><?= $isCategoryArchive ? 'Blog category' : 'Journal' ?></p>
    <?php if ($isCategoryArchive): ?>
        <h1><?= e($category['name']) ?></h1>
        <p><?= e($category['description'] ?: 'Published stories in this category.') ?></p>
        <a class="back-link" href="/blog">&larr; All blog posts</a>
    <?php else: ?>
        <h1><?= e(Settings::blogArchiveTitle()) ?></h1>
        <?php if (Settings::blogArchiveIntro() !== ''): ?><p><?= e(Settings::blogArchiveIntro()) ?></p><?php endif; ?>
    <?php endif; ?>
</section>

<?php if (!empty($archiveCategories)): ?>
<nav class="blog-category-nav" aria-label="Blog categories">
    <a class="blog-category-chip<?= !$isCategoryArchive ? ' is-active' : '' ?>" href="/blog">All posts</a>
    <?php foreach ($archiveCategories as $itemCategory): ?>
        <a class="blog-category-chip<?= $isCategoryArchive && (int)$category['id'] === (int)$itemCategory['id'] ? ' is-active' : '' ?>" href="/blog/category/<?= e($itemCategory['slug']) ?>">
            <?= e($itemCategory['name']) ?><span><?= (int)$itemCategory['post_count'] ?></span>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!$items): ?>
    <section class="empty-state public-empty">
        <div class="empty-mark">✦</div>
        <h2><?= $isCategoryArchive ? 'Nothing published here yet.' : 'Nothing published yet.' ?></h2>
        <p><?= $isCategoryArchive ? 'Published posts assigned to this category will appear here.' : 'The first story will appear here when it is ready.' ?></p>
    </section>
<?php else: ?>
    <section class="blog-grid">
        <?php foreach ($items as $post): ?>
            <article class="blog-card<?= !empty($post['featured_image_path']) ? ' has-image' : '' ?>">
                <?php $featuredImage = CMS\Core\HomePage::safeStoredAssetPath((string)($post['featured_image_path'] ?? '')); ?>
                <?php if ($featuredImage !== ''): ?><a class="blog-card-image" href="/blog/<?= e($post['slug']) ?>"><img src="<?= e($featuredImage) ?>" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
                <?php if (!empty($post['primary_category'])): ?>
                    <a class="blog-card-category" href="/blog/category/<?= e($post['primary_category']['slug']) ?>"><?= e($post['primary_category']['name']) ?></a>
                <?php endif; ?>
                <h2><a href="/blog/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h2>
                <div class="blog-card-meta">
                    <span><?= e(CMS\Core\Posts::displayDate($post['published_at'], 'j M Y')) ?></span>
                    <span>·</span>
                    <span><?= e($post['author_name']) ?></span>
                </div>
                <p><?= e($post['excerpt'] ?: mb_substr($post['body'], 0, 180) . (mb_strlen($post['body']) > 180 ? '…' : '')) ?></p>
                <?php if (count($post['categories'] ?? []) > 1): ?>
                    <div class="blog-card-tags">
                        <?php foreach ($post['categories'] as $postCategory): ?>
                            <a href="/blog/category/<?= e($postCategory['slug']) ?>"><?= e($postCategory['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="read-more" href="/blog/<?= e($post['slug']) ?>">Read story →</a>
            </article>
        <?php endforeach; ?>
    </section>

    <?php if ((int)$listing['pages'] > 1): ?>
        <nav class="pagination public-pagination" aria-label="Blog pages">
            <?php if ((int)$listing['page'] > 1): ?><a href="<?= e($archiveBase) ?>?page=<?= (int)$listing['page'] - 1 ?>">← Newer</a><?php else: ?><span></span><?php endif; ?>
            <span>Page <?= (int)$listing['page'] ?> of <?= (int)$listing['pages'] ?></span>
            <?php if ((int)$listing['page'] < (int)$listing['pages']): ?><a href="<?= e($archiveBase) ?>?page=<?= (int)$listing['page'] + 1 ?>">Older →</a><?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
