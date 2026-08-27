<?php use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'home-values-panel page-builder-values')) ?>" aria-label="Highlights">
    <?php foreach (($block['items'] ?? []) as $item): ?>
        <article class="home-value-item">
            <span class="home-value-mark" aria-hidden="true"><?= PageBlocks::iconSvg((string)($item['icon'] ?? 'heart')) ?></span>
            <h2><?= e($item['title'] ?? '') ?></h2>
            <p><?= e($item['body'] ?? '') ?></p>
        </article>
    <?php endforeach; ?>
</section>
