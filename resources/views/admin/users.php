<header class="page-header">
    <div>
        <p class="eyebrow">Access</p>
        <h1>Users</h1>
        <p class="muted">Role-aware access, individual security and invitation delivery.</p>
    </div>
</header>

<?php if ($created): ?><div class="notice success">User created.</div><?php endif; ?>
<?php if ($mailStatus === 'sent'): ?><div class="notice success">Welcome email sent with the temporary login password.</div><?php endif; ?>
<?php if ($mailStatus === 'failed'): ?><div class="notice error">The user was created, but the welcome email could not be sent. Check Email settings and the delivery log.</div><?php endif; ?>
<?php if ($mailStatus === 'not_configured'): ?><div class="notice warning">The user was created. Email delivery is not configured, so no welcome email was sent.</div><?php endif; ?>
<?php if (isset($_GET['deleted'])): ?><div class="notice success">User deleted and owned content reassigned safely.</div><?php endif; ?>

<div class="two-col users-layout">
    <section class="card content-card">
        <div class="section-heading">
            <div><p class="eyebrow">People</p><h2>Current users</h2></div>
            <span class="soft-badge"><?= count($users) ?> users</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>2FA</th><th>Status</th><th>Security</th></tr></thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong><?= e($u['display_name']) ?></strong></td>
                        <td><?= e($u['email']) ?></td>
                        <td><span class="soft-badge role-<?= e($u['role_name']) ?>"><?= e($u['role_label']) ?></span></td>
                        <td><span class="health-chip <?= (int)$u['mfa_enabled'] === 1 ? 'ok' : 'warning' ?>"><?= (int)$u['mfa_enabled'] === 1 ? 'Enabled' : 'Off' ?></span></td>
                        <td><span class="status-badge <?= e($u['status'] === 'active' ? 'published' : 'draft') ?>"><?= e(ucfirst($u['status'])) ?></span></td>
                        <td>
                            <?php if (CMS\Core\Gate::allows('users.security') && CMS\Core\UserManager::canManage($actor, $u)): ?>
                                <a class="text-link" href="<?= e(admin_url()) ?>/users/<?= (int)$u['id'] ?>/security">Open security →</a>
                            <?php else: ?>
                                <span class="muted">Restricted</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card form-card">
        <div class="section-heading"><div><p class="eyebrow">Invite</p><h2>Add user</h2></div></div>
        <form class="stack" method="post" action="<?= e(admin_url()) ?>/users" data-password-generator>
            <?= CMS\Core\Csrf::field() ?>
            <label>Display name<input name="display_name" required minlength="2" maxlength="120" autocomplete="name"></label>
            <label>Email<input type="email" name="email" required autocomplete="email"></label>
            <label>Password
                <div class="password-generator-row">
                    <input type="text" name="password" required minlength="14" autocomplete="new-password" data-generated-password>
                    <button class="button secondary compact" type="button" data-generate-password>Generate</button>
                    <button class="button secondary compact" type="button" data-copy-password>Copy</button>
                </div>
                <small class="field-help">Generate a strong temporary password. The user must change it after first sign in.</small>
            </label>
            <label>Role
                <select name="role_id" required>
                    <option value="">Choose role</option>
                    <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['label']) ?></option><?php endforeach; ?>
                </select>
            </label>
            <button class="button full" type="submit">Create user<?= $mailReady ? ' & send invite' : '' ?></button>
        </form>

        <?php if ($mailReady): ?>
            <p class="privacy-note"><span>●</span> A welcome email is sent automatically with the temporary password and login URL.</p>
        <?php else: ?>
            <p class="privacy-note"><span>●</span> Configure <strong>Settings → Email</strong> to send login invitations automatically.</p>
        <?php endif; ?>
    </section>
</div>

<script src="/assets/js/password-generator.js?v=<?= e(app_version()) ?>" defer></script>
