<?php use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-stats')) ?>">
    <?php if (!empty($block['eyebrow']) || !empty($block['heading'])): ?>
        <div class="home-section-heading"><div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
        </div></div>
    <?php endif; ?>
    <div class="page-stats-grid">
        <?php foreach (($block['items'] ?? []) as $item): ?>
            <article><strong><?= e($item['value'] ?? '') ?></strong><h3><?= e($item['label'] ?? '') ?></h3><?php if (!empty($item['body'])): ?><p><?= e($item['body']) ?></p><?php endif; ?></article>
        <?php endforeach; ?>
    </div>
</section>
