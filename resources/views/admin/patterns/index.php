<?php use CMS\Core\Csrf; ?>
<header class="page-header">
    <div><p class="eyebrow">Page builder</p><h1>Patterns</h1><p class="muted">Save page sections once, then reuse them as independent copies or synced sections that update everywhere.</p></div>
    <a class="button" href="<?= e(admin_url('/patterns/new')) ?>">+ New pattern</a>
</header>
<?php if (!empty($created)): ?><div class="notice success">Pattern created.</div><?php endif; ?>
<?php if (!empty($saved)): ?><div class="notice success">Pattern saved.</div><?php endif; ?>
<?php if (!empty($deleted)): ?><div class="notice success">Pattern deleted.</div><?php endif; ?>
<?php if (!empty($starterInstalled)): ?><div class="notice success">Starter pattern added to your library. It is a normal regular pattern now, so you can edit it freely.</div><?php endif; ?>

<section class="pattern-library-grid">
<?php if (!$patterns): ?>
    <div class="card empty-state pattern-empty-state"><p class="eyebrow">Reusable design</p><h2>No patterns yet</h2><p>Build a useful section in a Page, choose <strong>Save as pattern</strong>, or create one here.</p><a class="button" href="<?= e(admin_url('/patterns/new')) ?>">Create first pattern</a></div>
<?php else: ?>
    <?php foreach ($patterns as $pattern): ?>
    <article class="card pattern-library-card">
        <?php $previewType = (string)(($pattern['blocks'][0]['type'] ?? 'custom')); ?>
        <div class="pattern-visual-preview preview-type-<?= e($previewType) ?>" aria-hidden="true"><span class="pv-eyebrow"></span><span class="pv-title"></span><span class="pv-copy"></span><span class="pv-action"></span><span class="pv-media"></span><span class="pattern-preview-flow"><?php foreach (array_slice($pattern['preview_types'] ?? [$previewType], 0, 4) as $type): ?><i class="flow-<?= e((string)$type) ?>"></i><?php endforeach; ?></span></div>
        <div class="pattern-card-head"><span class="status-pill <?= e((string)$pattern['mode']) ?>"><?= $pattern['mode'] === 'synced' ? 'Synced' : 'Regular' ?></span><span class="muted"><?= count($pattern['blocks']) ?> block<?= count($pattern['blocks']) === 1 ? '' : 's' ?></span></div>
        <h2><?= e($pattern['name']) ?></h2>
        <p><?= $pattern['mode'] === 'synced' ? 'Pages reference one shared source. Editing this pattern updates every synced instance.' : 'Insertion creates an independent copy that can be edited without changing this pattern.' ?></p>
        <div class="split-row"><span>Used by pages</span><strong><?= (int)$pattern['usage_count'] ?></strong></div>
        <div class="pattern-card-actions"><a class="button secondary" href="<?= e(admin_url('/patterns/' . (int)$pattern['id'] . '/edit')) ?>">Edit pattern</a></div>
    </article>
    <?php endforeach; ?>
<?php endif; ?>
</section>

<section class="card content-card starter-library pattern-starter-library">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Quick start</p>
            <h2>Starter patterns</h2>
            <p class="muted">Useful page sections you can add to the library, then edit like any other regular pattern. Connected starters read from Content Models, so updating one structured entry automatically updates every page that displays it.</p>
        </div>
    </div>
    <label class="starter-library-search">
        <span>Find a starter pattern</span>
        <input type="search" placeholder="Search hero, pricing, team, gallery…" autocomplete="off" data-starter-library-search>
    </label>
    <div class="starter-card-grid">
        <?php foreach ($starterPatterns as $starter): ?>
        <article class="starter-card" data-starter-library-card data-starter-search-text="<?= e(mb_strtolower((string)$starter['name'] . ' ' . (string)$starter['summary'] . ' ' . (string)$starter['category'] . ' ' . (string)$starter['block_type'])) ?>">
            <div class="pattern-visual-preview starter-preview preview-type-<?= e((string)($starter['preview_type'] ?? 'custom')) ?>" aria-hidden="true"><span class="pv-eyebrow"></span><span class="pv-title"></span><span class="pv-copy"></span><span class="pv-action"></span><span class="pv-media"></span><span class="pattern-preview-flow"><?php foreach (array_slice($starter['preview_types'] ?? [(string)($starter['preview_type'] ?? 'custom')], 0, 4) as $type): ?><i class="flow-<?= e((string)$type) ?>"></i><?php endforeach; ?></span></div>
            <div class="starter-card-top">
                <span class="starter-card-badges"><span class="soft-badge"><?= e((string)$starter['category']) ?></span><span class="soft-badge"><?= e((string)$starter['block_type']) ?></span><?php if (!empty($starter['required_model_name'])): ?><span class="soft-badge connected">Uses <?= e((string)$starter['required_model_name']) ?></span><?php endif; ?></span>
                <?php if ((int)$starter['installed_id'] > 0): ?><span class="health-chip ok">Installed</span><?php endif; ?>
            </div>
            <h3><?= e((string)$starter['name']) ?></h3>
            <p><?= e((string)$starter['summary']) ?></p>
            <?php if (!empty($starter['required_model_name']) && empty($starter['required_model_installed'])): ?><p class="starter-dependency-note"><strong>Includes <?= e((string)$starter['required_model_name']) ?>.</strong> Talvoro will install the matching starter Content Model at the same time.</p><?php elseif (!empty($starter['required_model_name']) && empty($starter['required_model_public'])): ?><p class="starter-dependency-note warning"><strong><?= e((string)$starter['required_model_name']) ?> is installed but private.</strong> The Pattern can be added, but the live collection stays hidden until Public content is enabled for that model.</p><?php endif; ?>
            <div class="starter-card-action">
                <?php if ((int)$starter['installed_id'] > 0): ?>
                    <a class="text-link" href="<?= e(admin_url('/patterns/' . (int)$starter['installed_id'] . '/edit')) ?>">Edit pattern →</a>
                <?php else: ?>
                    <form method="post" action="<?= e(admin_url('/patterns/starters/install')) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="starter_key" value="<?= e((string)$starter['key']) ?>">
                        <button class="button secondary small" type="submit"><?= !empty($starter['required_model_name']) && empty($starter['required_model_installed']) ? 'Add pattern + model' : 'Add to library' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="muted starter-library-empty" data-starter-library-empty hidden>No starter patterns match that search.</p>
</section>

<script src="/assets/js/starter-library.js?v=<?= e(app_version()) ?>" defer></script>

