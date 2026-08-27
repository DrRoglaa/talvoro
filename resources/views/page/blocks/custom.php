<?php
use CMS\Core\HomePage;
use CMS\Core\PageBlocks;
$image = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
$layout = in_array((string)($block['layout'] ?? ''), ['stacked','centered','split-left','split-right'], true) ? (string)$block['layout'] : 'stacked';
$tone = in_array((string)($block['tone'] ?? ''), ['plain','soft','accent'], true) ? (string)$block['tone'] : 'plain';
?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-custom layout-' . $layout . ' tone-' . $tone . ($image !== '' ? ' has-media' : ''))) ?>">
    <div class="page-custom-copy">
        <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
        <?php if (!empty($block['heading'])): ?><h2><?= HomePage::accentHeadingHtml((string)$block['heading']) ?></h2><?php endif; ?>
        <?php if (!empty($block['body'])): ?><p><?= nl2br(e($block['body'])) ?></p><?php endif; ?>
        <?php if (!empty($block['primary_enabled']) || !empty($block['secondary_enabled'])): ?>
            <div class="home-hero-actions">
                <?php if (!empty($block['primary_enabled']) && !empty($block['primary_url'])): ?><a class="home-pill primary" href="<?= e($block['primary_url']) ?>"><?= e($block['primary_label'] ?? '') ?> <span>→</span></a><?php endif; ?>
                <?php if (!empty($block['secondary_enabled']) && !empty($block['secondary_url'])): ?><a class="home-pill secondary" href="<?= e($block['secondary_url']) ?>"><?= e($block['secondary_label'] ?? '') ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($image !== '' || in_array($layout, ['split-left','split-right'], true)): ?>
        <figure class="page-custom-media<?= $image === '' ? ' is-placeholder' : '' ?>">
            <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($block['image_alt'] ?? '') ?>" loading="lazy" decoding="async"><?php else: ?><span>Add an image in the page editor</span><?php endif; ?>
        </figure>
    <?php endif; ?>
</section>
