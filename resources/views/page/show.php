<article class="article-page cms-page">
    <header class="article-header">
        <?php if (!empty($page['eyebrow'])): ?><p class="eyebrow"><?= e($page['eyebrow']) ?></p><?php endif; ?>
        <h1><?= e($page['title']) ?></h1>
        <?php if (!empty($page['excerpt'])): ?><p class="article-deck"><?= e($page['excerpt']) ?></p><?php endif; ?>
    </header>
    <?php if (trim((string)($page['body_html'] ?? '')) !== ''): ?><div class="article-body rich-public-content"><?= $page['body_html'] ?></div><?php endif; ?>
</article>
<?php require __DIR__ . '/blocks.php'; ?>
