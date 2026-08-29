<?php
use CMS\Core\HomePage;
use CMS\Core\PageBlocks;
$image = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
$layout = in_array((string)($block['layout'] ?? ''), ['stacked','centered','split-left','split-right'], true) ? (string)$block['layout'] : 'stacked';
$tone = in_array((string)($block['tone'] ?? ''), ['plain','soft','accent'], true) ? (string)$block['tone'] : 'plain';
$variant = PageBlocks::sectionStyle($block)['style_variant'];
?>
<section<?= $variant === 'install' ? ' id="install"' : '' ?> class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-custom layout-' . $layout . ' tone-' . $tone . ($image !== '' ? ' has-media' : ''))) ?>">
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

    <?php if ($variant === 'product-ui'): ?>
        <div class="product-ui-preview" aria-label="Talvoro publishing workspace preview">
            <div class="product-ui-bar"><span></span><span></span><span></span><strong>Talvoro</strong><em>Saved</em></div>
            <div class="product-ui-body">
                <aside><b>Overview</b><span>Content</span><span>Media</span><span>Design</span><span>Insights</span></aside>
                <div class="product-ui-editor"><small>PAGE</small><strong>Write with focus.</strong><p>Clean content editing, publishing status and preview in one calm workspace.</p><div class="product-ui-lines"><i></i><i></i><i></i></div></div>
                <div class="product-ui-inspector"><small>STATUS</small><b>Published</b><small>SEO</small><b>Ready</b><small>VISIBILITY</small><b>Public</b></div>
            </div>
        </div>
    <?php elseif ($variant === 'ownership'): ?>
        <div class="ownership-preview" aria-label="Self-hosted ownership diagram">
            <div class="ownership-node primary"><small>YOUR DOMAIN</small><strong>talvoro.example</strong></div>
            <span class="ownership-line" aria-hidden="true"></span>
            <div class="ownership-cluster">
                <div class="ownership-node"><small>APPLICATION</small><strong>PHP 8.5</strong><span>Your server</span></div>
                <div class="ownership-node"><small>DATABASE</small><strong>MySQL</strong><span>Your data</span></div>
                <div class="ownership-node"><small>MEDIA</small><strong>Local files</strong><span>Your assets</span></div>
            </div>
            <p>No mandatory account. No remote kill switch. No third-party tracker required.</p>
        </div>
    <?php elseif ($variant === 'capabilities'): ?>
        <div class="capabilities-preview" aria-label="Talvoro advanced capabilities">
            <?php foreach (['SEO controls','Revisions','Redirects','First-party analytics','Contact forms','Backups','MFA & security','Structured content','Page patterns','Media library','Site health','Update recovery'] as $feature): ?>
                <span><?= e($feature) ?><i aria-hidden="true">✓</i></span>
            <?php endforeach; ?>
        </div>
    <?php elseif ($variant === 'install'): ?>
        <div class="install-preview" aria-label="Supported Talvoro installation paths">
            <article class="install-option recommended"><span>Recommended</span><small>DOCKER</small><strong>Predictable, portable deployment.</strong><p>Run the app, database and web edge as a reproducible stack while keeping persistent data outside the container image.</p></article>
            <article class="install-option"><small>TRADITIONAL HOSTING</small><strong>Standard PHP hosting.</strong><p>Use PHP 8.5 and MySQL on a conventional web host when Docker is not part of your environment.</p></article>
        </div>
    <?php elseif ($variant === 'theme-showcase'): ?>
        <div class="theme-showcase-preview" aria-label="Talvoro Editorial theme preview">
            <div class="theme-preview-browser">
                <div class="theme-preview-nav"><b>Talvoro</b><span>Product&nbsp;&nbsp; Themes&nbsp;&nbsp; Resources</span><i>Get Talvoro</i></div>
                <div class="theme-preview-hero"><small>INDEPENDENT PUBLISHING</small><strong>Warm. Clear. Yours.</strong><p>A calm editorial system for pages that need to feel intentional without feeling over-designed.</p></div>
            </div>
            <div class="theme-palette" aria-label="Theme palette"><span class="swatch coral"></span><span class="swatch aqua"></span><span class="swatch violet"></span><span class="swatch ink"></span><span class="swatch ivory"></span></div>
        </div>
    <?php endif; ?>

    <?php if ($image !== '' || in_array($layout, ['split-left','split-right'], true)): ?>
        <figure class="page-custom-media<?= $image === '' ? ' is-placeholder' : '' ?>">
            <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($block['image_alt'] ?? '') ?>" loading="lazy" decoding="async"><?php else: ?><span>Add an image in the page editor</span><?php endif; ?>
        </figure>
    <?php endif; ?>
</section>
