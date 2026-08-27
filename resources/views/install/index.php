<?php
use CMS\Core\Csrf;
?>
<section class="install-progress" aria-label="Installation progress">
    <?php foreach (['requirements'=>'1. Server','database'=>'2. Database','site'=>'3. Website & Super Admin','complete'=>'4. Complete'] as $key=>$label): ?>
        <span class="<?= $step === $key ? 'active' : '' ?>"><?= e($label) ?></span>
    <?php endforeach; ?>
</section>

<?php if (!empty($error)): ?><div class="notice error"><strong>Could not continue.</strong><p><?= e($error) ?></p></div><?php endif; ?>

<?php if ($step === 'requirements'): ?>
<section class="install-card">
    <p class="eyebrow">Step 1</p>
    <h1>Server readiness</h1>
    <p class="muted">Talvoro checks the hosting environment before any database credentials are accepted.</p>
    <div class="install-security-note">
        <strong>Keep the application outside the public web root</strong>
        <p>Your domain must serve Talvoro's <code>public/</code> directory, not the project root. This keeps application code, migrations, logs, backups and database configuration private.</p>
    </div>
    <div class="install-checks">
        <?php foreach ($preflight as $item): ?>
            <article class="install-check <?= $item['ok'] ? 'ok' : 'error' ?>">
                <span><?= $item['ok'] ? '✓' : '×' ?></span>
                <div><strong><?= e($item['label']) ?></strong><small><?= e($item['detail']) ?></small></div>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="install-actions">
        <?php if ($canContinue): ?><a class="button" href="/install?step=database">Continue to database</a><?php else: ?><span class="health-chip error">Fix the failed requirements before continuing</span><?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ($step === 'database'): ?>
<section class="install-card">
    <p class="eyebrow">Step 2</p>
    <h1>Database connection</h1>
    <p class="muted">Use a dedicated MySQL/MariaDB database user. Credentials are kept server-side and are never placed in a URL.</p>
    <?php if (($database['db_host'] ?? '') === 'db'): ?><div class="install-security-note"><strong>Docker database detected</strong><p>The host, database and user are pre-filled from Docker. Enter the application database password from your local <code>.env</code> file.</p></div><?php endif; ?>
    <form method="post" action="/install/database" class="stack install-form" autocomplete="off">
        <?= Csrf::field() ?>
        <div class="two-fields">
            <label>Database host<input autocapitalize="none" spellcheck="false" name="db_host" value="<?= e($database['db_host'] ?? 'localhost') ?>" maxlength="255" required></label>
            <label>Port<input name="db_port" value="<?= e($database['db_port'] ?? '3306') ?>" inputmode="numeric" required></label>
        </div>
        <label>Database name<input autocapitalize="none" spellcheck="false" name="db_database" value="<?= e($database['db_database'] ?? '') ?>" maxlength="64" required autocomplete="off"></label>
        <label>Database user<input autocapitalize="none" spellcheck="false" name="db_username" value="<?= e($database['db_username'] ?? '') ?>" maxlength="128" required autocomplete="off"></label>
        <label>Database password<input type="password" name="db_password" required autocomplete="new-password"></label>
        <p class="privacy-note"><span>●</span> The installer refuses a database that already contains Talvoro tables, preventing accidental overwrite of an existing site.</p>
        <div class="install-actions"><a class="button secondary" href="/install">Back</a><button class="button" type="submit">Test connection</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($step === 'site'): ?>
<section class="install-card wide">
    <p class="eyebrow">Step 3</p>
    <h1>Website & first Super Administrator</h1>
    <p class="muted">The first account is always created as <strong>Super Administrator</strong>. No ordinary Administrator is created during setup.</p>
    <form method="post" action="/install/complete" class="stack install-form" autocomplete="off">
        <?= Csrf::field() ?>
        <div class="two-fields">
            <label>Website name<input name="site_name" maxlength="120" value="<?= e($defaults['site_name'] ?? 'My Website') ?>" required></label>
            <label>Website URL<input type="url" name="app_url" value="<?= e($defaults['app_url'] ?? '') ?>" required></label>
        </div>
        <label>Timezone<input name="timezone" value="<?= e($defaults['timezone'] ?? 'Europe/Ljubljana') ?>" required><small class="field-help">IANA timezone, for example Europe/Ljubljana.</small></label>

        <div class="install-divider"><span>Private administration URL</span></div>
        <div class="password-generate-row">
            <label>Admin path
                <div class="slug-field"><span>/</span><input name="admin_path" value="<?= e($defaults['admin_path'] ?? '') ?>" minlength="3" maxlength="64" pattern="[a-z0-9][a-z0-9_-]*" required autocomplete="off" data-install-admin-path></div>
                <small class="field-help">Use a private path that is difficult to guess. Talvoro does not publish it in robots.txt, sitemaps or the public navigation.</small>
            </label>
            <button class="button secondary" type="button" data-generate-admin-path>Generate secure path</button>
        </div>
        <div class="install-security-note">
            <strong>Your private CMS address</strong>
            <p>Dashboard: <code data-admin-dashboard-preview>/<?= e($defaults['admin_path'] ?? '') ?></code><br>Login: <code data-admin-login-preview>/<?= e($defaults['admin_path'] ?? '') ?>/login</code></p>
        </div>

        <div class="install-divider"><span>Super Administrator</span></div>
        <div class="two-fields">
            <label>Display name<input name="admin_name" maxlength="120" value="<?= e($defaults['admin_name'] ?? '') ?>" required autocomplete="name"></label>
            <label>Email<input type="email" name="admin_email" value="<?= e($defaults['admin_email'] ?? '') ?>" required autocomplete="username"></label>
        </div>
        <div class="password-generate-row">
            <label>Strong password<input type="password" name="admin_password" minlength="<?= (int)$passwordMinimum ?>" maxlength="128" required autocomplete="new-password" data-install-password></label>
            <button class="button secondary" type="button" data-generate-install-password>Generate strong password</button>
        </div>
        <label>Confirm password<input type="password" name="admin_password_confirm" minlength="<?= (int)$passwordMinimum ?>" maxlength="128" required autocomplete="new-password" data-install-password-confirm></label>
        <small class="field-help">Minimum <?= (int)$passwordMinimum ?> characters. The password must not contain the account email/name.</small>
        <div class="install-security-note">
            <strong>Security applied automatically</strong>
            <p>Random application key, prepared PDO statements, strict sessions, CSRF checks, secure headers, installer lock and protected bootstrap configuration.</p>
        </div>
        <div class="install-actions"><a class="button secondary" href="/install?step=database">Back</a><button class="button" type="submit">Install Talvoro</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($step === 'complete' && $installed): ?>
<section class="install-card install-complete">
    <span class="install-success-mark">✓</span>
    <p class="eyebrow">Installation complete</p>
    <h1>Talvoro is secured and ready.</h1>
    <p class="muted">The installer is now locked. Your first account was created as Super Administrator.</p>
    <div class="install-complete-grid">
        <span>✓ Database migrations applied</span>
        <span>✓ Super Administrator created</span>
        <span>✓ Application key generated</span>
        <span>✓ Secure bootstrap configuration written</span>
        <span>✓ Installer lock activated</span>
        <span>✓ Security headers and strict sessions enabled</span>
    </div>
    <div class="install-actions"><a class="button" href="<?= e($loginUrl ?? admin_login_url()) ?>">Sign in to Talvoro</a></div>
</section>
<?php endif; ?>
