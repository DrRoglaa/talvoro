<?php
use CMS\Core\Csrf;
use CMS\Core\Posts;

$isSelf = (int)$actor['id'] === (int)$target['id'];
$recoveryHashes = json_decode((string)($target['mfa_recovery_hashes'] ?? '[]'), true);
$recoveryRemaining = is_array($recoveryHashes) ? count($recoveryHashes) : 0;
$auditRows = $auditPage['rows'] ?? [];
?>
<header class="page-header">
    <div>
        <a class="back-link" href="<?= e(admin_url()) ?>/users">← Users</a>
        <p class="eyebrow">Per-user security</p>
        <h1><?= e($target['display_name']) ?></h1>
        <p class="muted"><?= e($target['email']) ?> · <?= e($target['role_label']) ?></p>
    </div>
    <div class="security-header-badges">
        <span class="health-chip <?= (int)$target['mfa_enabled'] === 1 ? 'ok' : 'warning' ?>">2FA <?= (int)$target['mfa_enabled'] === 1 ? 'enabled' : 'off' ?></span>
        <span class="health-chip <?= $target['status'] === 'active' ? 'ok' : 'warning' ?>"><?= e(ucfirst($target['status'])) ?></span>
    </div>
</header>

<?php if ($saved): ?><div class="notice success">User security updated.</div><?php endif; ?>
<?php if ($passwordReset): ?><div class="notice success">Temporary password set and existing sessions revoked.</div><?php endif; ?>
<?php if ($sessionsRevoked): ?><div class="notice success">All recorded sessions for this user were revoked.</div><?php endif; ?>
<?php if ($auditPurged): ?><div class="notice success">User audit history was cleared. The purge action itself remains recorded.</div><?php endif; ?>
<?php if (!empty($mfaEnabledNow)): ?><div class="notice success">Two-factor authentication is enabled. Save the recovery codes below before leaving this page.</div><?php endif; ?>
<?php if (!empty($mfaDisabled)): ?><div class="notice success">Two-factor authentication was disabled. Your current session stayed active; other recorded sessions were revoked.</div><?php endif; ?>
<?php if ($mfaReset): ?><div class="notice success">Two-factor authentication was reset.</div><?php endif; ?>
<?php if (!empty($recoveryRegenerated)): ?><div class="notice success">New recovery codes were generated. Previous recovery codes no longer work.</div><?php endif; ?>
<?php if (!empty($mfaError)): ?><div class="notice error"><?= e($mfaError) ?></div><?php endif; ?>

<div class="security-grid">
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Identity & access</p><h2>Account policy</h2></div></div>
        <form class="stack" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/security">
            <?= Csrf::field() ?>
            <label>Display name<input name="display_name" maxlength="120" minlength="2" value="<?= e($target['display_name']) ?>" required></label>
            <label>Email<input value="<?= e($target['email']) ?>" disabled><small class="field-help">Email is the immutable sign-in identity in this release.</small></label>
            <label>Role
                <select name="role_id" <?= $isSelf ? 'disabled' : '' ?>>
                    <?php
                    $renderedCurrent = false;
                    foreach ($roles as $role):
                        $selected = (int)$target['role_id'] === (int)$role['id'];
                        $renderedCurrent = $renderedCurrent || $selected;
                    ?>
                        <option value="<?= (int)$role['id'] ?>" <?= $selected ? 'selected' : '' ?>><?= e($role['label']) ?></option>
                    <?php endforeach; ?>
                    <?php if (!$renderedCurrent): ?><option value="<?= (int)$target['role_id'] ?>" selected><?= e($target['role_label']) ?></option><?php endif; ?>
                </select>
                <?php if ($isSelf): ?><input type="hidden" name="role_id" value="<?= (int)$target['role_id'] ?>"><small class="field-help">You cannot change your own role.</small><?php endif; ?>
            </label>
            <label>Status
                <select name="status" <?= $isSelf ? 'disabled' : '' ?>>
                    <option value="active" <?= $target['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="disabled" <?= $target['status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                </select>
                <?php if ($isSelf): ?><input type="hidden" name="status" value="active"><?php endif; ?>
            </label>
            <div class="split-row"><span>Last successful login</span><strong><?= e(Posts::displayDate($target['last_login_at'] ?? null)) ?></strong></div>
            <div class="split-row"><span>Account created</span><strong><?= e(Posts::displayDate($target['created_at'] ?? null, 'j M Y')) ?></strong></div>
            <button class="button" type="submit">Save account policy</button>
        </form>
    </section>

    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Credentials</p><h2>Reset password</h2></div></div>
        <p class="muted">Creates a temporary password, requires a change at next sign in and revokes existing sessions.</p>
        <form class="stack" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/password" data-password-generator>
            <?= Csrf::field() ?>
            <label>Temporary password
                <div class="password-generator-row">
                    <input type="text" name="password" minlength="14" required autocomplete="new-password" data-generated-password>
                    <button class="button secondary compact" type="button" data-generate-password>Generate</button>
                    <button class="button secondary compact" type="button" data-copy-password>Copy</button>
                </div>
            </label>
            <button class="button secondary" type="submit">Set temporary password</button>
        </form>
    </section>
</div>

<section class="card mfa-card" id="mfa-security" data-admin-scroll-section="mfa-security">
    <div class="section-heading">
        <div><p class="eyebrow">Two-factor authentication</p><h2>Authenticator security</h2></div>
        <span class="health-chip <?= (int)$target['mfa_enabled'] === 1 ? 'ok' : 'warning' ?>"><?= (int)$target['mfa_enabled'] === 1 ? 'Enabled' : 'Not enabled' ?></span>
    </div>

    <?php if ((int)$target['mfa_enabled'] === 1): ?>
        <div class="mfa-summary">
            <div><strong>Authenticator app</strong><span>Required after the password during sign in.</span></div>
            <div><strong><?= $recoveryRemaining ?></strong><span>Recovery codes remaining</span></div>
        </div>

        <?php if (!empty($recoveryCodes)): ?>
            <section class="mfa-action-panel recovery-card mfa-recovery-panel" data-recovery-codes>
                <div class="section-heading">
                    <div><p class="eyebrow">Save now</p><h3>Recovery codes</h3></div>
                    <div class="recovery-actions">
                        <span class="health-chip warning">Shown once</span>
                        <button class="button secondary compact" type="button" data-copy-recovery>Copy all</button>
                        <button class="button secondary compact" type="button" data-download-recovery>Download .txt</button>
                    </div>
                </div>
                <p class="muted">Store these somewhere private. Each code can be used once if the authenticator is unavailable. Talvoro stores only hashes and cannot show this same set again.</p>
                <div class="recovery-code-grid">
                    <?php foreach ($recoveryCodes as $code): ?><code><?= e($code) ?></code><?php endforeach; ?>
                </div>
                <p class="field-help" data-recovery-status aria-live="polite"></p>
            </section>
        <?php endif; ?>

        <?php if ($isSelf): ?>
            <div class="mfa-self-actions">
                <section class="mfa-action-panel">
                    <div><p class="eyebrow">Recovery</p><h3>Replace recovery codes</h3><p class="muted">Use this if you did not save the original codes. Generating a new set invalidates every previous recovery code.</p></div>
                    <form class="inline-secure-form" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/mfa/recovery/regenerate" data-return-section="mfa-security">
                        <?= Csrf::field() ?>
                        <input class="password-manager-username" type="email" name="username" value="<?= e($target['email']) ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
                        <label>Current password<input type="password" name="password" required autocomplete="current-password"></label>
                        <label>Authenticator code<input name="code" required inputmode="numeric" autocomplete="one-time-code"></label>
                        <button class="button secondary" type="submit">Generate new codes</button>
                    </form>
                </section>
                <section class="mfa-action-panel danger-panel">
                    <div><p class="eyebrow">Disable</p><h3>Turn off 2FA</h3><p class="muted">Your current browser remains signed in. Other recorded sessions are revoked as a precaution.</p></div>
                    <form class="inline-secure-form" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/mfa/reset" data-return-section="mfa-security">
                        <?= Csrf::field() ?>
                        <input class="password-manager-username" type="email" name="username" value="<?= e($target['email']) ?>" autocomplete="username" readonly tabindex="-1" aria-hidden="true">
                        <label>Current password<input type="password" name="password" required autocomplete="current-password"></label>
                        <label>Authenticator code<input name="code" required inputmode="numeric" autocomplete="one-time-code"></label>
                        <button class="button danger" type="submit">Disable 2FA</button>
                    </form>
                </section>
            </div>
        <?php else: ?>
            <form class="inline-secure-form" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/mfa/reset" data-return-section="mfa-security">
                <?= Csrf::field() ?>
                <label class="confirm-check"><input type="checkbox" name="confirm_reset" value="1" required> Reset this user's 2FA enrollment and revoke sessions.</label>
                <button class="button danger" type="submit">Reset 2FA</button>
            </form>
        <?php endif; ?>
    <?php elseif ($isSelf): ?>
        <?php if ($pendingMfaSecret !== ''): ?>
            <div class="mfa-enroll-grid">
                <div class="totp-qr" data-totp-uri="<?= e($mfaSetupUri) ?>" aria-label="Authenticator QR code"></div>
                <div>
                    <p class="eyebrow">Authenticator setup</p>
                    <h3>Scan the QR code</h3>
                    <p class="muted">Or enter this secret manually in your authenticator app.</p>
                    <code class="secret-code"><?= e($pendingMfaSecret) ?></code>
                    <form class="stack" method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/mfa/enable" data-return-section="mfa-security">
                        <?= Csrf::field() ?>
                        <label>6-digit code<input name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
                        <button class="button" type="submit">Verify and enable 2FA</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p class="muted">Add an authenticator app plus single-use recovery codes. The QR is generated locally; the setup secret is never sent to an external QR service.</p>
            <form method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/mfa/start" data-return-section="mfa-security">
                <?= Csrf::field() ?>
                <button class="button" type="submit">Set up 2FA</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="muted">This user has not enabled 2FA. Enrollment must be completed by the user from their own Security page.</p>
    <?php endif; ?>
</section>

<section class="card">
    <div class="section-heading">
        <div><p class="eyebrow">Sessions</p><h2>CMS sessions</h2></div>
        <form method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/sessions/revoke">
            <?= Csrf::field() ?>
            <button class="button secondary" type="submit">Revoke all sessions</button>
        </form>
    </div>

    <?php if (!$sessions): ?>
        <p class="muted">No recorded sessions yet.</p>
    <?php else: ?>
        <div class="session-list">
            <?php foreach ($sessions as $session): ?>
                <article class="session-row <?= $session['revoked_at'] ? 'revoked' : '' ?>">
                    <div>
                        <strong><?= e($session['user_agent'] ?: 'Unknown client') ?></strong>
                        <small>Last seen <?= e(Posts::displayDate($session['last_seen_at'])) ?> · <?= $session['mfa_verified_at'] ? '2FA verified' : 'Password session' ?></small>
                    </div>
                    <span class="soft-badge"><?= $session['revoked_at'] ? 'Revoked' : (!empty($session['is_current']) ? 'Current' : 'Active') ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card audit-card">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Audit trail</p>
            <h2>Security activity</h2>
            <p class="muted">10 entries per page · <?= (int)($auditPage['total'] ?? 0) ?> total</p>
        </div>
        <?php if ((int)($auditPage['total'] ?? 0) > 0): ?>
            <?php if ($canPurgeAudit): ?>
                <form method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/audit/purge" class="audit-purge-form">
                    <?= Csrf::field() ?>
                    <label class="confirm-check"><input type="checkbox" name="confirm_purge" value="1" required> Confirm delete all</label>
                    <button class="button danger secondary-danger" type="submit">Delete all logs</button>
                </form>
            <?php else: ?>
                <span class="soft-badge">Super Administrator can clear history</span>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php if (!$auditRows): ?><p class="muted">No audit events on this page.</p><?php endif; ?>
    <div class="content-list">
        <?php foreach ($auditRows as $event): ?>
            <div class="content-row">
                <div><strong><?= e($event['action']) ?></strong><small><?= e(Posts::displayDate($event['created_at'])) ?></small></div>
                <span class="soft-badge"><?= e($event['target_type'] ?: 'account') ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($canPurgeAudit && (int)($auditPage['total'] ?? 0) > 0): ?>
        <div class="audit-danger-row">
            <div><strong>Clear this user's audit history</strong><small>This removes the current history, then records a new purge event so the administrative action is not silent.</small></div>
            <form method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/audit/purge" class="audit-purge-form">
                <?= Csrf::field() ?>
                <label class="confirm-check"><input type="checkbox" name="confirm_purge" value="1" required> Confirm delete all</label>
                <button class="button danger" type="submit">Delete all logs</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if (($auditPage['pages'] ?? 1) > 1): ?>
        <nav class="pager audit-pager" aria-label="Audit pages">
            <?php if (($auditPage['page'] ?? 1) > 1): ?><a class="button secondary compact" href="?audit_page=<?= (int)$auditPage['page'] - 1 ?>">← Previous</a><?php else: ?><span></span><?php endif; ?>
            <strong>Page <?= (int)$auditPage['page'] ?> of <?= (int)$auditPage['pages'] ?></strong>
            <?php if (($auditPage['page'] ?? 1) < ($auditPage['pages'] ?? 1)): ?><a class="button secondary compact" href="?audit_page=<?= (int)$auditPage['page'] + 1 ?>">Next →</a><?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php if ($canDelete): ?>
<section class="danger-zone">
    <div><strong>Delete <?= e($target['display_name']) ?></strong><p>Only a Super Administrator can delete Administrator, Editor or Analyst accounts. Owned posts/pages are reassigned to you.</p></div>
    <form method="post" action="<?= e(admin_url()) ?>/users/<?= (int)$target['id'] ?>/delete" class="stack">
        <?= Csrf::field() ?>
        <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> I understand this permanently deletes the user.</label>
        <button class="button danger" type="submit">Delete user</button>
    </form>
</section>
<?php endif; ?>

<script src="/assets/js/password-generator.js?v=<?= e(app_version()) ?>" defer></script>
<?php if ($pendingMfaSecret !== ''): ?><script src="/assets/js/totp-qr.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
