<?php
use CMS\Core\Csrf;
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Security</p>
        <h1>Admin access</h1>
        <p class="muted">Move the CMS away from common scanner targets while keeping authentication protections active underneath.</p>
    </div>
</header>

<?php if (!empty($saved)): ?><div class="alert success">Admin path changed successfully. You are already using the new address.</div><?php endif; ?>
<?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

<div class="two-col analytics-layout">
    <section class="card form-card stack">
        <div>
            <p class="eyebrow">Private CMS address</p>
            <h2>Admin path</h2>
            <p class="muted">Changing this makes the previous admin and login URLs return 404. Talvoro never redirects old admin paths to the new one.</p>
        </div>

        <form method="post" action="<?= e(admin_url('/security')) ?>" class="stack">
            <?= Csrf::field() ?>
            <input class="password-manager-username" type="email" name="username" value="<?= e($currentUserEmail ?? '') ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
            <div class="password-generate-row">
                <label>Admin path
                    <div class="slug-field"><span>/</span><input name="admin_path" value="<?= e($adminPath) ?>" maxlength="64" minlength="3" pattern="[a-z0-9][a-z0-9_-]*" required autocomplete="off" data-security-admin-path></div>
                    <small class="field-help">3–64 characters. Lowercase letters, numbers, hyphens and underscores only.</small>
                </label>
                <button class="button secondary" type="button" data-security-generate-path>Generate secure path</button>
            </div>

            <div class="security-url-preview">
                <div><small>Dashboard</small><code data-security-dashboard-preview><?= e($dashboardUrl) ?></code></div>
                <div><small>Login</small><code data-security-login-preview><?= e($loginUrl) ?></code></div>
            </div>

            <div class="install-security-note">
                <strong>Confirm this security-sensitive change</strong>
                <p>Your current password is required. If two-factor authentication is enabled, enter a current authenticator code too.</p>
            </div>
            <label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>
            <?php if ($mfaEnabled): ?>
                <label>Authenticator code<input name="mfa_code" inputmode="numeric" autocomplete="one-time-code" maxlength="12" required></label>
            <?php endif; ?>
            <button class="button" type="submit">Save admin path</button>
        </form>
    </section>

    <section class="card stack">
        <div>
            <p class="eyebrow">Protection status</p>
            <h2>Login security</h2>
        </div>
        <div class="split-row"><span>Hidden admin path</span><strong>Enabled</strong></div>
        <div class="split-row"><span>Per-account throttling</span><strong>Enabled</strong></div>
        <div class="split-row"><span>Per-IP throttling</span><strong>Enabled</strong></div>
        <div class="split-row"><span>CSRF protection</span><strong>Enabled</strong></div>
        <div class="split-row"><span>Strict session cookies</span><strong>Enabled</strong></div>
        <div class="split-row"><span>Session timeout</span><strong><?= (int)$sessionMinutes ?> min</strong></div>
        <p class="privacy-note"><span>●</span> A custom URL reduces automated scanning; passwords, throttling, MFA and session controls remain the real authentication boundary.</p>
    </section>
</div>

<section class="card" style="margin-top:16px">
    <div class="section-heading"><div><p class="eyebrow">Audit</p><h2>Recent login activity</h2></div></div>
    <?php if (!$loginActivity): ?>
        <div class="empty-state compact"><p>No recent authentication activity has been recorded yet.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>Event</th><th>User</th><th>Time (UTC)</th></tr></thead>
            <tbody>
            <?php foreach ($loginActivity as $row): ?>
                <tr>
                    <td><strong><?= e($row['label']) ?></strong></td>
                    <td><?= e($row['display_name'] ?: 'Unknown / anonymous') ?></td>
                    <td><?= e($row['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card" style="margin-top:16px">
    <p class="eyebrow">Emergency recovery</p>
    <h2>Locked out?</h2>
    <p class="muted">From the server terminal you can inspect or reset the admin path without modifying the database manually.</p>
    <pre class="code-block">php bin/admin-path.php show
php bin/admin-path.php reset</pre>
</section>
