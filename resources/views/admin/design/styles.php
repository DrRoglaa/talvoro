<?php use CMS\Core\Csrf; ?>
<header class="page-header">
  <div><p class="eyebrow">Design · <?= e((string)($theme['name'] ?? 'Active theme')) ?></p><h1>Styles</h1><p class="muted">Set the visual language for this theme. Pages, Patterns and structured content inherit these safe design tokens automatically.</p></div>
  <a class="button secondary compact" href="/theme.css" target="_blank" rel="noopener">View generated CSS</a>
</header>
<?php if ($saved): ?><div class="notice success">Design styles saved. Open any Page Builder preview to see the updated system.</div><?php endif; ?>
<?php if ($reset): ?><div class="notice success">Design styles reset to Talvoro defaults.</div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><strong>Styles were not saved.</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($warnings): ?><div class="notice warning" data-design-server-warnings><strong>Accessibility review</strong><ul><?php foreach ($warnings as $warning): ?><li><?= e($warning) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="notice warning design-live-warnings" data-design-live-warnings hidden><strong>Accessibility review</strong><ul></ul></div>

<form method="post" class="design-styles-form" data-design-styles>
  <?= Csrf::field() ?>
  <div class="design-styles-layout">
    <div class="stack">
      <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Foundation</p><h2>Colors</h2><p class="muted">Set the theme's editorial accents and neutral foundation. Brand accents are visual identity; success, warning and destructive states remain separate Talvoro system colors.</p></div></div>
        <div class="design-color-grid">
          <?php foreach (['brand','accent','depth','background','surface','text','muted','border'] as $key): $def=$definitions[$key]; ?>
            <label class="design-color-field"><span><?= e($def['label']) ?></span><span class="design-color-control"><input type="color" name="<?= e($key) ?>" value="<?= e($values[$key]) ?>" data-design-token="<?= e($key) ?>"><code data-color-value><?= e($values[$key]) ?></code></span></label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Typography</p><h2>Type system</h2><p class="muted">System font stacks keep Talvoro fast and private—no remote font service is required.</p></div></div>
        <div class="two-fields">
          <?php foreach (['heading_font','body_font','type_scale'] as $key): $def=$definitions[$key]; ?>
          <label><?= e($def['label']) ?><select name="<?= e($key) ?>" data-design-token="<?= e($key) ?>"><?php foreach ($def['options'] as $option=>$label): ?><option value="<?= e($option) ?>" <?= $values[$key]===$option?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Layout</p><h2>Rhythm & surfaces</h2></div></div>
        <div class="two-fields">
          <?php foreach (['content_width','wide_width'] as $key): $def=$definitions[$key]; ?><label><?= e($def['label']) ?><div class="input-suffix"><input type="number" name="<?= e($key) ?>" min="<?= (int)$def['min'] ?>" max="<?= (int)$def['max'] ?>" value="<?= e($values[$key]) ?>" data-design-token="<?= e($key) ?>"><span>px</span></div></label><?php endforeach; ?>
          <?php foreach (['section_spacing','radius','button_radius','shadow','link_style'] as $key): $def=$definitions[$key]; ?><label><?= e($def['label']) ?><select name="<?= e($key) ?>" data-design-token="<?= e($key) ?>"><?php foreach ($def['options'] as $option=>$label): ?><option value="<?= e($option) ?>" <?= $values[$key]===$option?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><?php endforeach; ?>
        </div>
      </section>
      <button class="button" type="submit">Save design styles</button>
    </div>

    <aside class="stack design-preview-sidebar">
      <section class="card design-live-preview" data-design-preview>
        <p class="eyebrow">Live sample</p>
        <div class="design-preview-canvas">
          <span class="design-preview-kicker">YOUR BRAND</span>
          <h2>One visual system.<br>Every page.</h2>
          <p>Design tokens keep typography, spacing, colors and surfaces consistent while editors work with simple semantic choices.</p>
          <div class="design-preview-actions"><span class="design-preview-button">Primary action</span><a href="#">Text link</a></div>
          <div class="design-preview-card"><strong>Surface example</strong><span>Cards and reusable Patterns inherit the same system.</span></div>
        </div>
      </section>
      <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Portable foundation</p><h2>Token map</h2></div></div>
        <p class="muted">These semantic tokens belong to <strong><?= e((string)($theme['name'] ?? 'the active theme')) ?></strong>. Switching themes keeps each theme’s visual settings independent.</p>
        <dl class="design-token-list"><?php foreach ($tokens as $token=>$value): ?><div><dt><?= e($token) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?></dl>
      </section>
    </aside>
  </div>
</form>

<section class="danger-zone"><div><strong>Reset design styles</strong><p>Restore this theme’s default tokens. Page content and per-section Design choices are not removed.</p></div><form method="post" action="<?= e(admin_url('/design/styles/reset')) ?>"><?= Csrf::field() ?><label class="confirm-check"><input type="checkbox" name="confirm_reset" value="1" required> I understand this resets the active theme’s design tokens.</label><button class="button danger" type="submit">Reset styles</button></form></section>
<script src="/assets/js/design-styles.js?v=<?= e(app_version()) ?>" defer></script>
