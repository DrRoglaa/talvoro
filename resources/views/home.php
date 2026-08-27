<?php if (!empty($page)): ?>
<div class="home-editorial-page page-builder-public-home">
    <?php if (trim((string)($page['body_html'] ?? '')) !== ''): ?>
        <div class="home-intro-rich rich-public-content"><?= $page['body_html'] ?></div>
    <?php endif; ?>
    <?php require __DIR__ . '/page/blocks.php'; ?>
</div>
<?php endif; ?>
