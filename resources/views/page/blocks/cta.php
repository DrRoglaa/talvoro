<?php use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'home-closing-cta page-builder-cta')) ?>">
    <div>
        <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
        <h2><?= e($block['heading'] ?? '') ?></h2>
    </div>
    <?php if (!empty($block['button_url'])): ?><a class="home-pill primary" href="<?= e($block['button_url']) ?>"><?= e($block['button_label'] ?? '') ?> <span>→</span></a><?php endif; ?>
</section>
