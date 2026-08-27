<?php use CMS\Core\HomePage; use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-gallery layout-' . (string)($block['layout'] ?? 'grid'))) ?>">
    <?php if (!empty($block['eyebrow']) || !empty($block['heading'])): ?>
        <div class="home-section-heading"><div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
        </div></div>
    <?php endif; ?>
    <div class="page-gallery-grid">
        <?php foreach (($block['items'] ?? []) as $item): $image = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? '')); if ($image === '') continue; ?>
            <figure class="page-gallery-item">
                <img src="<?= e($image) ?>" alt="<?= e($item['image_alt'] ?? '') ?>" loading="lazy" decoding="async">
                <?php if (!empty($item['caption'])): ?><figcaption><?= e($item['caption']) ?></figcaption><?php endif; ?>
            </figure>
        <?php endforeach; ?>
    </div>
</section>
