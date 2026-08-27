<?php use CMS\Core\PageBlocks; ?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-faq')) ?>">
    <?php if (!empty($block['eyebrow']) || !empty($block['heading'])): ?>
        <div class="home-section-heading"><div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
        </div></div>
    <?php endif; ?>
    <div class="page-faq-list">
        <?php foreach (($block['items'] ?? []) as $item): ?>
            <details>
                <summary><?= e($item['question'] ?? '') ?></summary>
                <p><?= nl2br(e($item['answer'] ?? '')) ?></p>
            </details>
        <?php endforeach; ?>
    </div>
</section>
