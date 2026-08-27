<?php use CMS\Core\HomePage; use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'home-editorial-section home-featured-section page-builder-cards')) ?>">
    <?php if (!empty($block['heading']) || !empty($block['eyebrow']) || (!empty($block['view_label']) && !empty($block['view_url']))): ?>
        <div class="home-section-heading">
            <div>
                <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
                <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
            </div>
            <?php if (!empty($block['view_label']) && !empty($block['view_url'])): ?><a class="home-section-link" href="<?= e($block['view_url']) ?>"><?= e($block['view_label']) ?> →</a><?php endif; ?>
        </div>
    <?php endif; ?>
    <div class="home-featured-grid">
        <?php foreach (($block['items'] ?? []) as $index => $item):
            $image = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? ''));
            $url = trim((string)($item['url'] ?? '')) ?: '#';
        ?>
            <a class="home-featured-card tone-<?= (($index % 4) + 1) ?>" href="<?= e($url) ?>">
                <figure class="home-featured-media<?= $image === '' ? ' is-placeholder' : '' ?>">
                    <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($item['image_alt'] ?? '') ?>" loading="lazy" decoding="async"><?php else: ?><span class="home-card-placeholder-copy"><strong><?= e(str_pad((string)((int)$index + 1), 2, '0', STR_PAD_LEFT)) ?></strong><small>Add image</small></span><?php endif; ?>
                </figure>
                <div class="home-featured-caption"><strong><?= e($item['title'] ?? '') ?></strong><span><?= e($item['meta'] ?? '') ?></span></div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
