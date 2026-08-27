<?php
use CMS\Core\HomePage;
use CMS\Core\Posts;
use CMS\Core\Settings;
use CMS\Core\PageBlocks;
$count = max(1, min(6, (int)($block['count'] ?? 3)));
$items = Settings::blogEnabled() ? Posts::publicList(1, $count)['items'] : [];
?>
<?php if ($items): ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'home-editorial-section home-news-section page-builder-latest-posts')) ?>">
    <div class="home-section-heading">
        <div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <h2><?= e($block['heading'] ?? 'Latest news') ?></h2>
        </div>
        <a class="home-section-link" href="/blog"><?= e($block['view_label'] ?? 'View all news') ?> →</a>
    </div>
    <div class="home-news-grid">
        <?php foreach ($items as $post):
            $postImage = HomePage::safeStoredAssetPath((string)($post['featured_image_path'] ?? ''));
        ?>
            <article class="home-news-card">
                <a class="home-news-media<?= $postImage === '' ? ' is-placeholder' : '' ?>" href="/blog/<?= e($post['slug']) ?>">
                    <?php if ($postImage !== ''): ?><img src="<?= e($postImage) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span><?= e(mb_substr((string)$post['title'], 0, 1)) ?></span><?php endif; ?>
                </a>
                <div class="home-news-content">
                    <?php if (!empty($post['primary_category'])): ?><a class="home-news-category" href="/blog/category/<?= e($post['primary_category']['slug']) ?>"><?= e($post['primary_category']['name']) ?></a><?php endif; ?>
                    <h3><a href="/blog/<?= e($post['slug']) ?>"><?= e($post['title']) ?></a></h3>
                    <time><?= e(Posts::displayDate($post['published_at'], 'F j, Y')) ?></time>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>
