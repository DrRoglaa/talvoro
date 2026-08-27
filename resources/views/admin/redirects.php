<?php
use CMS\Core\Csrf;
$old = $old ?? ['source_path' => '', 'destination' => '', 'status_code' => 301];
$errors = $errors ?? [];
?>
<header class="page-header">
    <div>
        <p class="eyebrow">URL management</p>
        <h1>Redirects</h1>
        <p class="muted">Move public URLs safely without losing visitors or creating hidden redirect loops.</p>
    </div>
</header>

<?php if ($created ?? false): ?><div class="notice success">Redirect created.</div><?php endif; ?>
<?php if ($deleted ?? false): ?><div class="notice success">Redirect deleted.</div><?php endif; ?>
<?php if ($errors): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">New rule</p><h2>Add redirect</h2></div></div>
    <form method="post" action="<?= e(admin_url()) ?>/redirects" class="redirect-form">
        <?= Csrf::field() ?>
        <label>From<input name="source_path" value="<?= e($old['source_path']) ?>" placeholder="/old-page" required></label>
        <label>To<input name="destination" value="<?= e($old['destination']) ?>" placeholder="/new-page or https://example.com" required></label>
        <label>Status
            <select name="status_code">
                <?php foreach ([301 => '301 · Permanent',302 => '302 · Temporary',307 => '307 · Temporary',308 => '308 · Permanent'] as $code => $label): ?>
                    <option value="<?= $code ?>" <?= (int)$old['status_code'] === $code ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button" type="submit">Add redirect</button>
    </form>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Rules</p><h2>Active redirects</h2></div><span class="soft-badge"><?= count($redirects) ?> total</span></div>
    <?php if (!$redirects): ?>
        <div class="empty-state compact"><h3>No redirects yet.</h3><p>Add one when a public URL moves.</p></div>
    <?php else: ?>
        <div class="redirect-table">
            <div class="redirect-row header"><span>Source</span><span>Destination</span><span>Status</span><span>Hits</span><span></span></div>
            <?php foreach ($redirects as $redirect): ?>
                <div class="redirect-row">
                    <strong><?= e($redirect['source_path']) ?></strong>
                    <span class="truncate"><?= e($redirect['destination']) ?></span>
                    <span class="soft-badge"><?= (int)$redirect['status_code'] ?></span>
                    <span><?= number_format((int)$redirect['hit_count']) ?></span>
                    <form method="post" action="<?= e(admin_url()) ?>/redirects/<?= (int)$redirect['id'] ?>/delete">
                        <?= Csrf::field() ?>
                        <button class="link-button danger-text" type="submit">Delete</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
