<article class="article-page">
    <a class="back-link" href="/blog">← Blog</a>
    <header class="article-header">
        <?php if (!empty($post['primary_category'])): ?>
            <a class="article-category" href="/blog/category/<?= e($post['primary_category']['slug']) ?>"><?= e($post['primary_category']['name']) ?></a>
        <?php else: ?>
            <p class="eyebrow">Journal</p>
        <?php endif; ?>
        <h1><?= e($post['title']) ?></h1>
        <?php if (!empty($post['excerpt'])): ?><p class="article-deck"><?= e($post['excerpt']) ?></p><?php endif; ?>
        <div class="article-meta">
            <span><?= e(CMS\Core\Posts::displayDate($post['published_at'], 'j F Y')) ?></span>
            <span>·</span>
            <span><?= e($post['author_name']) ?></span>
        </div>
        <?php if (!empty($post['categories'])): ?>
            <div class="article-category-list">
                <?php foreach ($post['categories'] as $postCategory): ?>
                    <a href="/blog/category/<?= e($postCategory['slug']) ?>"><?= e($postCategory['name']) ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>
    <?php $featuredImage = CMS\Core\HomePage::safeStoredAssetPath((string)($post['featured_image_path'] ?? '')); ?>
    <?php if ($featuredImage !== ''): ?><figure class="article-featured-image"><img src="<?= e($featuredImage) ?>" alt="" loading="eager" decoding="async"></figure><?php endif; ?>
    <div class="article-body rich-public-content"><?= CMS\Core\Posts::publicHtml($post) ?></div>
</article>
