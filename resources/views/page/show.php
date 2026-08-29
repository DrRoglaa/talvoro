<?php
use CMS\Core\PageBlocks;

$sourceBlocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : PageBlocks::decode((string)($page['blocks_json'] ?? ''));
$renderedBlocks = PageBlocks::renderBlocks($sourceBlocks);
$hasLeadingHero = (($renderedBlocks[0]['type'] ?? '') === 'hero');
$page['_leading_hero'] = $hasLeadingHero;
?>
<?php if (!$hasLeadingHero || trim((string)($page['body_html'] ?? '')) !== ''): ?>
<article class="article-page cms-page<?= $hasLeadingHero ? ' has-leading-hero' : '' ?>">
    <?php if (!$hasLeadingHero): ?>
        <header class="article-header">
            <?php if (!empty($page['eyebrow'])): ?><p class="eyebrow"><?= e($page['eyebrow']) ?></p><?php endif; ?>
            <h1><?= e($page['title']) ?></h1>
            <?php if (!empty($page['excerpt'])): ?><p class="article-deck"><?= e($page['excerpt']) ?></p><?php endif; ?>
        </header>
    <?php endif; ?>
    <?php if (trim((string)($page['body_html'] ?? '')) !== ''): ?><div class="article-body rich-public-content"><?= $page['body_html'] ?></div><?php endif; ?>
</article>
<?php endif; ?>
<?php require __DIR__ . '/blocks.php'; ?>
