<header class="page-header">
    <div>
        <p class="eyebrow">Blog</p>
        <h1>Categories</h1>
        <p class="muted">Define the categories available to blog posts. Every post always keeps at least one category.</p>
    </div>
    <a class="button" href="<?= e(admin_url()) ?>/blog-categories/new">New category</a>
</header>

<?php if (!empty($created)): ?><div class="alert success">Category created.</div><?php endif; ?>
<?php if (!empty($updated)): ?><div class="alert success">Category updated.</div><?php endif; ?>
<?php if (!empty($deleted)): ?><div class="alert success">Category deleted. Any orphaned posts were reassigned safely.</div><?php endif; ?>

<section class="card content-card">
    <div class="section-heading category-heading">
        <div>
            <p class="eyebrow">Structure</p>
            <h2>Blog categories</h2>
        </div>
        <span class="category-count"><?= count($categories) ?> total</span>
    </div>

    <?php if (!$categories): ?>
        <div class="empty-state">
            <div class="empty-mark">✦</div>
            <h2>No categories found.</h2>
            <p>The installer normally creates General automatically. Create a category to restore blog organization.</p>
            <a class="button secondary" href="<?= e(admin_url()) ?>/blog-categories/new">Create category</a>
        </div>
    <?php else: ?>
        <div class="category-list">
            <?php foreach ($categories as $category): ?>
                <article class="category-row">
                    <div class="category-row-main">
                        <div class="category-title-line">
                            <a href="<?= e(admin_url()) ?>/blog-categories/<?= (int)$category['id'] ?>/edit"><?= e($category['name']) ?></a>
                            <?php if ((int)$category['is_default'] === 1): ?><span class="status-badge published">Default</span><?php endif; ?>
                            <span class="status-badge <?= $category['status'] === 'active' ? 'published' : 'draft' ?>"><?= e(ucfirst($category['status'])) ?></span>
                        </div>
                        <div class="post-meta">
                            <span>/blog/category/<?= e($category['slug']) ?></span>
                            <span>·</span>
                            <span>Order <?= (int)$category['sort_order'] ?></span>
                            <span>·</span>
                            <span><?= (int)$category['post_count'] ?> <?= (int)$category['post_count'] === 1 ? 'post' : 'posts' ?></span>
                        </div>
                        <?php if (!empty($category['description'])): ?><p><?= e($category['description']) ?></p><?php endif; ?>
                    </div>
                    <div class="category-row-side">
                        <a class="button secondary small-button" href="<?= e(admin_url()) ?>/blog-categories/<?= (int)$category['id'] ?>/edit">Edit</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
