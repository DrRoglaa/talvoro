<?php use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-testimonials')) ?>">
    <?php if (!empty($block['eyebrow']) || !empty($block['heading'])): ?>
        <div class="home-section-heading"><div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
        </div></div>
    <?php endif; ?>
    <div class="page-testimonial-grid">
        <?php foreach (($block['items'] ?? []) as $item): ?>
            <blockquote class="page-testimonial">
                <p>“<?= e($item['quote'] ?? '') ?>”</p>
                <footer><strong><?= e($item['name'] ?? '') ?></strong><?php if (!empty($item['role'])): ?><span><?= e($item['role']) ?></span><?php endif; ?></footer>
            </blockquote>
        <?php endforeach; ?>
    </div>
</section>
