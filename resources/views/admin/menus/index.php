<?php use CMS\Core\Csrf; $menus=is_array($menus??null)?$menus:[]; ?>
<header class="page-header">
  <div><p class="eyebrow">Configuration</p><h1>Menus</h1><p class="muted">Build navigation once, then assign it to the Primary, Mobile or Footer location. Menu links keep stable references to Talvoro content.</p></div>
  <a class="button" href="<?= e(admin_url('/menus/new')) ?>">Create menu</a>
</header>
<?php if (!empty($created)): ?><div class="notice success">Menu created.</div><?php endif; ?>
<?php if (!empty($deleted)): ?><div class="notice success">Menu deleted.</div><?php endif; ?>
<section class="card">
  <div class="section-heading"><div><p class="eyebrow">Navigation</p><h2>Menu locations</h2></div><span class="soft-badge"><?= count($menus) ?> menu<?= count($menus)===1?'':'s' ?></span></div>
  <?php if (!$menus): ?>
    <div class="empty-state"><h3>No custom menus yet</h3><p>Talvoro will continue using the existing Page navigation settings until you assign a menu to a public location.</p><a class="button secondary compact" href="<?= e(admin_url('/menus/new')) ?>">Create your first menu</a></div>
  <?php else: ?>
    <div class="menu-admin-grid">
      <?php foreach ($menus as $menu): ?>
        <article class="menu-admin-card">
          <div><span class="soft-badge"><?= e(ucfirst((string)$menu['location'])) ?></span><h3><?= e($menu['name']) ?></h3><p class="muted"><?= e($menu['description'] ?: 'No description') ?></p></div>
          <div class="menu-card-meta"><span><?= (int)$menu['item_count'] ?> item<?= (int)$menu['item_count']===1?'':'s' ?></span><code><?= e($menu['menu_key']) ?></code></div>
          <a class="button secondary compact" href="<?= e(admin_url('/menus/'.(int)$menu['id'].'/edit')) ?>">Edit menu</a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
