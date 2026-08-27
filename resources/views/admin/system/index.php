<?php
use CMS\Core\Csrf;
?>
<header class="page-header">
    <div>
        <p class="eyebrow">System</p>
        <h1>Updates & recovery</h1>
        <p class="muted">Controlled application updates with package checksums, backups, migrations and Super Administrator re-authentication.</p>
    </div>
    <span class="health-chip ok">v<?= e(app_version()) ?></span>
</header>

<?php if ($message === 'staged'): ?><div class="notice success">Update package validated and staged. Review it below before applying.</div><?php endif; ?>
<?php if ($message === 'updated'): ?><div class="notice success">Talvoro update completed successfully.</div><?php endif; ?>
<?php if ($message === 'restored'): ?><div class="notice success">Application files and the database were restored together from the pre-update backup.</div><?php endif; ?>

<div class="metric-grid four system-metrics">
    <article class="metric"><span>Installed version</span><strong><?= e(app_version()) ?></strong><small>Application code</small></article>
    <article class="metric"><span>Installer</span><strong><?= $installerLocked ? 'Locked' : 'Open' ?></strong><small>Must remain locked after setup</small></article>
    <article class="metric"><span>Bootstrap config</span><strong><?= $configFile ? 'Protected file' : 'Env/dev' ?></strong><small><?= $configFile ? 'storage/config.php' : 'Legacy Docker/environment mode' ?></small></article>
    <article class="metric"><span>Migrations</span><strong><?= count($pendingMigrations) ?></strong><small>Pending database migrations</small></article>
</div>

<?php if ($recovery): ?>
<section class="card recovery-card">
    <div class="section-heading"><div><p class="eyebrow">Recovery required</p><h2>Interrupted update detected</h2></div><span class="health-chip error"><?= e($recovery['status'] ?? 'failed') ?></span></div>
    <p class="muted">From <?= e($recovery['from'] ?? '?') ?> to <?= e($recovery['to'] ?? '?') ?>. <?= e($recovery['error'] ?? 'The update did not finish cleanly.') ?></p>
    <form method="post" action="<?= e(admin_url()) ?>/system/update/restore" class="critical-reauth-form" data-password-manager-context>
        <?= Csrf::field() ?>
        <input class="password-manager-username" type="email" name="username" value="<?= e($currentUserEmail ?? '') ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>Authenticator code<input name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
        <button class="button danger" type="submit">Restore database + application files</button>
    </form>
</section>
<?php endif; ?>

<?php if ($staged): $m=$staged['manifest']; ?>
<section class="card staged-update-card">
    <div class="section-heading"><div><p class="eyebrow">Validated update</p><h2><?= e(app_version()) ?> → <?= e($m['version']) ?></h2></div><span class="health-chip ok">Checksums verified</span></div>
    <p class="muted"><?= count($m['files']) ?> application files are listed in the release manifest. Protected configuration, uploads and backups cannot be overwritten by the package.</p>
    <form method="post" action="<?= e(admin_url()) ?>/system/update/apply" class="critical-reauth-form" data-password-manager-context>
        <?= Csrf::field() ?>
        <input class="password-manager-username" type="email" name="username" value="<?= e($currentUserEmail ?? '') ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
        <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
        <label>Authenticator code<input name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
        <button class="button" type="submit">Create backup and apply update</button>
    </form>
</section>
<?php else: ?>
<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Update package</p><h2>Upload a newer Talvoro release</h2></div></div>
    <p class="muted">Only a Super Administrator with a valid password and enabled 2FA can stage an update. Packages are checked for path traversal, symlinks, size limits, protected files and SHA-256 manifest integrity.</p>
    <form method="post" action="<?= e(admin_url()) ?>/system/update/stage" enctype="multipart/form-data" class="stack update-upload-form" data-password-manager-context>
        <?= Csrf::field() ?>
        <input class="password-manager-username" type="email" name="username" value="<?= e($currentUserEmail ?? '') ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
        <label>Talvoro ZIP<input type="file" name="update_zip" accept=".zip,application/zip" required></label>
        <div class="two-fields">
            <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            <label>Authenticator code<input name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code"></label>
        </div>
        <button class="button" type="submit">Validate and stage update</button>
    </form>
</section>
<?php endif; ?>

<section class="card security-foundation-card">
    <div class="section-heading"><div><p class="eyebrow">Security model</p><h2>Update protections</h2></div></div>
    <div class="security-foundation-grid">
        <span>✓ Super Administrator only</span>
        <span>✓ Current-password re-authentication</span>
        <span>✓ TOTP 2FA required</span>
        <span>✓ CSRF + same-origin validation</span>
        <span>✓ ZIP traversal/symlink rejection</span>
        <span>✓ Per-file SHA-256 manifest validation</span>
        <span>✓ Database + replaced-file backup</span>
        <span>✓ Automatic database-aware rollback on failed updates</span>
        <span>✓ Protected config/uploads never overwritten</span>
    </div>
</section>
