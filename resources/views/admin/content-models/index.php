<?php use CMS\Core\Csrf; ?>
<header class="page-header">
    <div>
        <p class="eyebrow">Configuration</p>
        <h1>Content models</h1>
        <p class="muted">Create structured content such as Dogs, Events, Products or Testimonials without writing PHP.</p>
    </div>
    <div class="header-actions">
        <a class="button secondary" href="<?= e(admin_url('/components/new')) ?>">New component</a>
        <a class="button" href="<?= e(admin_url('/content-models/new')) ?>">New content model</a>
    </div>
</header>

<?php if (!empty($deleted)): ?><div class="notice success">Content model deleted.</div><?php endif; ?>
<?php if (!empty($_GET['component_deleted'])): ?><div class="notice success">Component deleted.</div><?php endif; ?>

<div class="model-dashboard-grid">
<section class="card content-card">
    <div class="section-heading"><div><p class="eyebrow">Schemas</p><h2>Content models</h2></div><span class="health-chip ok"><?= count($models) ?> models</span></div>
    <?php if (!$models): ?>
        <div class="empty-state"><div class="empty-mark">◇</div><h3>No custom content models yet.</h3><p>Start with something meaningful to your site, such as Dogs, Events or Testimonials.</p><a class="button secondary" href="<?= e(admin_url('/content-models/new')) ?>">Create content model</a></div>
    <?php else: ?>
        <div class="posts-table">
        <?php foreach ($models as $model): ?>
            <article class="post-row">
                <div class="post-row-main">
                    <div class="post-title-line"><span class="model-icon-label" aria-hidden="true"><?= e((string)($model['icon']??'collection')) ?></span><a href="<?= e(admin_url('/content-models/' . (int)$model['id'] . '/edit')) ?>"><?= e($model['plural_name']) ?></a><span class="status-badge <?= $model['status']==='active'?'published':'draft' ?>"><?= e(ucfirst($model['status'])) ?></span></div>
                    <div class="post-meta"><span>/<?= e($model['slug']) ?></span><span>·</span><span><?= (int)$model['field_count'] ?> fields</span><span>·</span><span><?= (int)$model['entry_count'] ?> entries</span><?php if ((int)$model['is_public']===1): ?><span>·</span><span>Public</span><?php endif; ?></div>
                </div>
                <div class="post-row-side"><small>Content</small><a class="text-link" href="<?= e(admin_url('/content/' . $model['slug'])) ?>">Open <?= e($model['plural_name']) ?> →</a></div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card content-card">
    <div class="section-heading"><div><p class="eyebrow">Reusable fields</p><h2>Components</h2></div><span class="health-chip"><?= count($components) ?></span></div>
    <p class="muted">Components are reusable groups of fields. A Health test or Address can be embedded in multiple content models.</p>
    <?php if (!$components): ?>
        <div class="empty-state compact"><h3>No components yet.</h3><p>Create one when several fields belong together.</p></div>
    <?php else: ?>
        <div class="model-compact-list">
        <?php foreach ($components as $component): ?>
            <a href="<?= e(admin_url('/components/' . (int)$component['id'] . '/edit')) ?>"><span><strong><?= e($component['name']) ?></strong><small><?= (int)$component['field_count'] ?> fields · <?= e($component['slug']) ?></small></span><span>→</span></a>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
</div>

<section class="card content-card starter-library">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Quick start</p>
            <h2>Starter models</h2>
            <p class="muted">Optional, editable starting points for common real-world content. Installing one creates a normal Talvoro model and fields that you can change or remove later.</p>
        </div>
    </div>
    <label class="starter-library-search">
        <span>Find a starter model</span>
        <input type="search" placeholder="Search services, jobs, FAQs, resources…" autocomplete="off" data-starter-library-search>
    </label>
    <div class="starter-card-grid">
        <?php foreach ($starterModels as $starter): ?>
        <article class="starter-card" data-starter-library-card data-starter-search-text="<?= e(mb_strtolower((string)$starter['name'] . ' ' . (string)$starter['summary'] . ' ' . (string)$starter['category'])) ?>">
            <div class="starter-card-top">
                <span class="starter-icon" aria-hidden="true"><?= e((string)$starter['icon']) ?></span>
                <span class="starter-card-badges"><span class="soft-badge"><?= e((string)$starter['category']) ?></span><span class="soft-badge"><?= (int)$starter['field_count'] ?> fields</span></span>
            </div>
            <h3><?= e((string)$starter['name']) ?></h3>
            <p><?= e((string)$starter['summary']) ?></p>
            <div class="starter-card-action">
                <?php if ((int)$starter['installed_id'] > 0): ?>
                    <span class="health-chip ok">Installed</span>
                    <a class="text-link" href="<?= e(admin_url('/content-models/' . (int)$starter['installed_id'] . '/edit')) ?>">Edit model →</a>
                <?php else: ?>
                    <form method="post" action="<?= e(admin_url('/content-models/starters/install')) ?>">
                        <?= Csrf::field() ?>
                        <input type="hidden" name="starter_key" value="<?= e((string)$starter['key']) ?>">
                        <button class="button secondary small" type="submit">Add <?= e((string)$starter['name']) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <p class="muted starter-library-empty" data-starter-library-empty hidden>No starter models match that search.</p>
</section>

<script src="/assets/js/starter-library.js?v=<?= e(app_version()) ?>" defer></script>

