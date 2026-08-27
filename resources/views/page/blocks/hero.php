<?php
use CMS\Core\HomePage;
use CMS\Core\PageBlocks;
$image = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'spottina-home-hero page-builder-hero' . ($image !== '' ? ' has-media' : ''))) ?>">
    <div class="spottina-home-hero-copy">
        <?php if (trim((string)($block['eyebrow'] ?? '')) !== ''): ?><p class="home-kicker">♡ <?= e($block['eyebrow']) ?></p><?php endif; ?>
        <?php if (($page['path'] ?? '') === '/'): ?><h1 class="page-builder-hero-title"><?= HomePage::accentHeadingHtml((string)($block['heading'] ?? '')) ?></h1><?php else: ?><h2 class="page-builder-hero-title"><?= HomePage::accentHeadingHtml((string)($block['heading'] ?? '')) ?></h2><?php endif; ?>
        <?php if (trim((string)($block['intro'] ?? '')) !== ''): ?><p class="home-hero-intro"><?= e($block['intro']) ?></p><?php endif; ?>
        <?php if (!empty($block['primary_enabled']) || !empty($block['secondary_enabled'])): ?>
            <div class="home-hero-actions">
                <?php if (!empty($block['primary_enabled']) && !empty($block['primary_url'])): ?><a class="home-pill primary" href="<?= e($block['primary_url']) ?>"><?= e($block['primary_label'] ?? '') ?> <span>→</span></a><?php endif; ?>
                <?php if (!empty($block['secondary_enabled']) && !empty($block['secondary_url'])): ?><a class="home-pill secondary" href="<?= e($block['secondary_url']) ?>"><?= e($block['secondary_label'] ?? '') ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    <figure class="spottina-home-hero-media<?= $image === '' ? ' is-placeholder' : '' ?>">
        <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($block['image_alt'] ?? '') ?>" loading="<?= (($page['path'] ?? '') === '/') ? 'eager' : 'lazy' ?>" decoding="async"><?php else: ?><span>Add a hero image in the page editor</span><?php endif; ?>
    </figure>
</section>
