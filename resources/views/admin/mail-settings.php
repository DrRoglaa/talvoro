<header class="page-header">
    <div>
        <p class="eyebrow">Email delivery</p>
        <h1>Mail settings</h1>
        <p class="muted">SMTP is configured inside the CMS and stored in MySQL. The SMTP password is encrypted with APP_KEY before storage.</p>
    </div>
    <span class="health-chip <?= CMS\Core\MailSettings::isReady() ? 'ok' : 'warning' ?>"><?= CMS\Core\MailSettings::isReady() ? 'Mail ready' : 'Not configured' ?></span>
</header>

<?php if ($saved): ?><div class="notice success">Email delivery settings saved.</div><?php endif; ?>
<?php if (($tested ?? '') === 'sent'): ?><div class="notice success">Test email sent successfully.</div><?php endif; ?>
<?php if (($tested ?? '') === 'failed'): ?><div class="notice error">Test email failed. Recheck the host, port, encryption and credentials.</div><?php endif; ?>
<?php if (($tested ?? '') === 'invalid'): ?><div class="notice error">Enter a valid test recipient.</div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="card mail-settings-card">
    <form method="post" action="<?= e(admin_url()) ?>/mail" class="stack">
        <?= CMS\Core\Csrf::field() ?>
        <label class="switch-setting-row">
            <span><strong>Enable email delivery</strong><small>Welcome emails and CMS mail are sent only when enabled.</small></span>
            <span class="switch-control"><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></span>
        </label>

        <div class="mail-grid">
            <label>SMTP host<input name="smtp_host" value="<?= e($config['host'] ?? '') ?>" placeholder="smtp.example.com"></label>
            <label>SMTP port<input type="number" min="1" max="65535" name="smtp_port" value="<?= (int)($config['port'] ?? 587) ?>"></label>
            <label>Encryption
                <select name="smtp_encryption">
                    <option value="starttls" <?= ($config['encryption'] ?? '') === 'starttls' ? 'selected' : '' ?>>STARTTLS (usually 587)</option>
                    <option value="ssl" <?= ($config['encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (usually 465)</option>
                    <option value="none" <?= ($config['encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (not recommended)</option>
                </select>
            </label>
        </div>

        <div class="two-fields">
            <label>SMTP username<input name="smtp_username" value="<?= e($config['username'] ?? '') ?>" autocomplete="username"></label>
            <label>SMTP password<input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= !empty($config['password_configured']) ? 'Stored securely — leave blank to keep' : 'Mailbox/app password' ?>"><small class="field-help">The existing password is never displayed back to the browser.</small></label>
        </div>

        <div class="three-fields">
            <label>From name<input name="from_name" maxlength="120" value="<?= e($config['from_name'] ?? 'Talvoro') ?>"></label>
            <label>From email<input type="email" name="from_email" value="<?= e($config['from_email'] ?? '') ?>"></label>
            <label>Envelope from<input type="email" name="envelope_from" value="<?= e($config['envelope_from'] ?? '') ?>" placeholder="Defaults to From email"></label>
        </div>

        <button class="button" type="submit">Save email settings</button>
    </form>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Verification</p><h2>Send test email</h2></div></div>
    <form method="post" action="<?= e(admin_url()) ?>/mail/test" class="mail-test-form">
        <?= CMS\Core\Csrf::field() ?>
        <label>Recipient<input type="email" name="test_recipient" required placeholder="you@example.com"></label>
        <button class="button secondary" type="submit" <?= CMS\Core\MailSettings::isReady() ? '' : 'disabled' ?>>Send test</button>
    </form>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Delivery log</p><h2>Recent email</h2></div><span class="soft-badge"><?= count($deliveries ?? []) ?> shown</span></div>
    <?php if (empty($deliveries)): ?>
        <p class="muted">No email delivery attempts have been recorded yet.</p>
    <?php else: ?>
        <div class="mail-delivery-list">
            <?php foreach ($deliveries as $delivery): ?>
                <article class="mail-delivery-row">
                    <div>
                        <strong><?= e($delivery['subject']) ?></strong>
                        <small><?= e($delivery['recipient']) ?> · <?= e($delivery['mail_type']) ?> · <?= e(CMS\Core\Posts::displayDate($delivery['created_at'])) ?></small>
                        <?php if (!empty($delivery['delivery_error'])): ?><small class="delivery-error"><?= e($delivery['delivery_error']) ?></small><?php endif; ?>
                    </div>
                    <span class="health-chip <?= $delivery['delivery_status'] === 'sent' ? 'ok' : 'error' ?>"><?= e(ucfirst($delivery['delivery_status'])) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
