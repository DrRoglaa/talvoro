<?php
use CMS\Core\ContactSettings;
use CMS\Core\Gate;
use CMS\Core\MailSettings;

$canManageMail = !empty($canManageMail);
$canManageContact = !empty($canManageContact);
$mailReady = MailSettings::isReady();
$contactDeliveryReady = ContactSettings::deliveryReady();
$contactReady = $canManageContact ? ContactSettings::canAccept() : false;
$contactConfig = is_array($contactConfig ?? null) ? $contactConfig : [];
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Configuration</p>
        <h1><?= $canManageMail && $canManageContact ? 'Email & contact forms' : ($canManageContact ? 'Contact forms' : 'Mail settings') ?></h1>
        <p class="muted"><?= $canManageMail ? 'Talvoro keeps SMTP delivery centralized and encrypts the SMTP password with APP_KEY before storage.' : 'Configure how native Contact Form blocks deliver and retain visitor messages.' ?></p>
    </div>
    <?php if ($canManageContact): ?>
        <span class="health-chip <?= $contactReady ? 'ok' : 'warning' ?>"><?= $contactReady ? 'Contact ready' : 'Contact setup needed' ?></span>
    <?php else: ?>
        <span class="health-chip <?= $mailReady ? 'ok' : 'warning' ?>"><?= $mailReady ? 'Mail ready' : 'Not configured' ?></span>
    <?php endif; ?>
</header>

<?php if (!empty($saved)): ?><div class="notice success">Email delivery settings saved.</div><?php endif; ?>
<?php if (!empty($contactSaved)): ?><div class="notice success">Contact form settings saved.</div><?php endif; ?>
<?php if (($tested ?? '') === 'sent'): ?><div class="notice success">Test email sent successfully.</div><?php endif; ?>
<?php if (($tested ?? '') === 'failed'): ?><div class="notice error">Test email failed. Recheck the host, port, encryption and credentials.</div><?php endif; ?>
<?php if (($tested ?? '') === 'invalid'): ?><div class="notice error">Enter a valid test recipient.</div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="notice error"><ul><?php foreach ($errors as $error): ?><li><?= e((string)$error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!empty($contactErrors)): ?><div class="notice error"><ul><?php foreach ($contactErrors as $error): ?><li><?= e((string)$error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<?php if ($canManageMail): ?>
<section class="card mail-settings-card">
    <div class="section-heading"><div><p class="eyebrow">Delivery</p><h2>SMTP</h2><p class="muted">Used by system email, test messages and Contact Form notifications.</p></div><span class="health-chip <?= $mailReady ? 'ok' : 'warning' ?>"><?= $mailReady ? 'Ready' : 'Not ready' ?></span></div>
    <form method="post" action="<?= e(admin_url()) ?>/mail" class="stack">
        <?= CMS\Core\Csrf::field() ?>
        <label class="switch-setting-row">
            <span><strong>Enable email delivery</strong><small>Application-generated mail is sent only when enabled.</small></span>
            <span class="switch-control"><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></span>
        </label>

        <div class="mail-grid">
            <label>SMTP host<input name="smtp_host" value="<?= e((string)($config['host'] ?? '')) ?>" placeholder="smtp.example.com"></label>
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
            <label>SMTP username<input name="smtp_username" value="<?= e((string)($config['username'] ?? '')) ?>" autocomplete="username"></label>
            <label>SMTP password<input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= !empty($config['password_configured']) ? 'Stored securely - leave blank to keep' : 'Mailbox/app password' ?>"><small class="field-help">The existing password is never displayed back to the browser.</small></label>
        </div>

        <div class="three-fields">
            <label>From name<input name="from_name" maxlength="120" value="<?= e((string)($config['from_name'] ?? 'Talvoro')) ?>"></label>
            <label>From email<input type="email" name="from_email" value="<?= e((string)($config['from_email'] ?? '')) ?>"></label>
            <label>Envelope from<input type="email" name="envelope_from" value="<?= e((string)($config['envelope_from'] ?? '')) ?>" placeholder="Defaults to From email"></label>
        </div>

        <button class="button" type="submit">Save email settings</button>
    </form>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Verification</p><h2>Send test email</h2></div></div>
    <form method="post" action="<?= e(admin_url()) ?>/mail/test" class="mail-test-form">
        <?= CMS\Core\Csrf::field() ?>
        <label>Recipient<input type="email" name="test_recipient" required placeholder="you@example.com"></label>
        <button class="button secondary" type="submit" <?= $mailReady ? '' : 'disabled' ?>>Send test</button>
    </form>
</section>
<?php endif; ?>

<?php if ($canManageContact): ?>
<section class="card mail-settings-card" id="contact-forms">
    <div class="section-heading">
        <div><p class="eyebrow">Contact forms</p><h2>Native contact delivery</h2><p class="muted">Page Builder controls the form copy. Delivery destination, local retention and privacy stay privileged CMS settings.</p></div>
        <?php if (Gate::allows('contact.view')): ?><a class="button secondary small" href="<?= e(admin_url('/contact-submissions')) ?>">Open submissions</a><?php endif; ?>
    </div>

    <?php if (!$contactDeliveryReady && empty($contactConfig['store_submissions'])): ?>
        <div class="notice warning compact"><strong>Contact forms cannot accept messages yet.</strong> Configure SMTP and a recipient, or enable local submission storage.</div>
    <?php elseif (!$contactDeliveryReady && !empty($contactConfig['store_submissions'])): ?>
        <div class="notice neutral compact"><strong>Storage-only mode.</strong> Messages can be accepted into the local inbox, but email notifications will remain failed until SMTP is ready.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(admin_url('/mail/contact')) ?>" class="stack">
        <?= CMS\Core\Csrf::field() ?>
        <div class="two-fields">
            <label>Default contact recipient
                <input type="email" name="contact_recipient" maxlength="254" value="<?= e((string)($contactConfig['recipient'] ?? '')) ?>" placeholder="owner@example.com" autocomplete="email">
                <small class="field-help">Page editors cannot override this address. Leave blank only if you intentionally use the local inbox without email notifications.</small>
            </label>
            <label>Default subject prefix
                <input name="contact_subject_prefix" maxlength="80" value="<?= e((string)($contactConfig['subject_prefix'] ?? 'Website contact')) ?>">
                <small class="field-help">Used when a Contact Form block does not provide a more specific prefix.</small>
            </label>
        </div>

        <label class="switch-setting-row">
            <span><strong>Store submissions locally</strong><small>Off by default. When enabled, Talvoro keeps messages in your own database and can accept a message even if email delivery fails.</small></span>
            <span class="switch-control"><input type="checkbox" name="contact_store_submissions" value="1" <?= !empty($contactConfig['store_submissions']) ? 'checked' : '' ?>><span class="switch-track"><span class="switch-thumb"></span></span></span>
        </label>

        <div class="two-fields">
            <label>Retention period
                <select name="contact_retention_days">
                    <?php foreach (($contactRetentionOptions ?? [7,30,90,180,365]) as $days): ?><option value="<?= (int)$days ?>" <?= (int)($contactConfig['retention_days'] ?? 30) === (int)$days ? 'selected' : '' ?>><?= (int)$days ?> days</option><?php endforeach; ?>
                </select>
                <small class="field-help">Applies only to locally stored submissions. Expired messages are cleaned automatically and can also be cleaned from the inbox.</small>
            </label>
            <div class="block-inset">
                <strong>Privacy-first by design</strong>
                <p class="muted">Talvoro does not add reCAPTCHA, Turnstile, external form processors or permanent visitor IP storage. Rate limiting uses a keyed hash instead of storing the raw address.</p>
            </div>
        </div>

        <button class="button" type="submit">Save contact settings</button>
    </form>
</section>
<?php endif; ?>

<?php if ($canManageMail): ?>
<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Delivery log</p><h2>Recent email</h2></div><span class="soft-badge"><?= count($deliveries ?? []) ?> shown</span></div>
    <?php if (empty($deliveries)): ?>
        <p class="muted">No email delivery attempts have been recorded yet.</p>
    <?php else: ?>
        <div class="mail-delivery-list">
            <?php foreach ($deliveries as $delivery): ?>
                <article class="mail-delivery-row">
                    <div>
                        <strong><?= e((string)$delivery['subject']) ?></strong>
                        <small><?= e((string)$delivery['recipient']) ?> - <?= e((string)$delivery['mail_type']) ?> - <?= e(CMS\Core\Posts::displayDate((string)$delivery['created_at'])) ?></small>
                        <?php if (!empty($delivery['delivery_error'])): ?><small class="delivery-error"><?= e((string)$delivery['delivery_error']) ?></small><?php endif; ?>
                    </div>
                    <span class="health-chip <?= $delivery['delivery_status'] === 'sent' ? 'ok' : 'error' ?>"><?= e(ucfirst((string)$delivery['delivery_status'])) ?></span>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
