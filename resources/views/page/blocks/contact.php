<?php
use CMS\Core\ContactFormContext;
use CMS\Core\ContactFormState;
use CMS\Core\ContactSettings;
use CMS\Core\Csrf;
use CMS\Core\PageBlocks;

$ownerId = (string)($block['_render_owner_id'] ?? $block['id'] ?? '');
$blockId = (string)($block['_render_block_id'] ?? $block['id'] ?? '');
$formId = 'contact-' . $ownerId . '-' . $blockId;
$state = ContactFormState::pull((int)($page['id'] ?? 0), $ownerId, $blockId);
$old = is_array($state['old'] ?? null) ? $state['old'] : [];
$errors = is_array($state['errors'] ?? null) ? $state['errors'] : [];
$fieldErrors = is_array($state['field_errors'] ?? null) ? $state['field_errors'] : [];
$success = trim((string)($state['success'] ?? ''));
$focus = (string)($state['focus'] ?? '');
$showSubject = !array_key_exists('show_subject', $block) || !empty($block['show_subject']);
$requireSubject = $showSubject && !empty($block['require_subject']);
$canAccept = ContactSettings::canAccept();
$context = '';
if ($canAccept) {
    try {
        $context = ContactFormContext::issue((int)($page['id'] ?? 0), $ownerId, $blockId);
    } catch (\Throwable) {
        $canAccept = false;
    }
}
$fieldAttrs = static function (string $name) use ($fieldErrors, $formId): string {
    if (!isset($fieldErrors[$name])) return '';
    return ' aria-invalid="true" aria-describedby="' . e($formId . '-error-' . $name) . '"';
};
?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-builder-contact contact-section')) ?>" id="<?= e($formId) ?>">
    <div class="contact-shell">
        <div class="contact-hero">
            <div class="contact-copy">
                <span class="contact-eyebrow">Contact</span>
                <?php if (trim((string)($block['heading'] ?? '')) !== ''): ?>
                    <h2><?= e((string)$block['heading']) ?></h2>
                <?php endif; ?>
                <?php if (trim((string)($block['intro'] ?? '')) !== ''): ?>
                    <p><?= nl2br(e((string)$block['intro'])) ?></p>
                <?php endif; ?>
            </div>

            <div class="contact-assurances" aria-label="What to expect">
                <div class="contact-assurance-item">
                    <span class="contact-assurance-number" aria-hidden="true">01</span>
                    <div><strong>Direct</strong><p>Your message goes straight to the site contact recipient.</p></div>
                </div>
                <div class="contact-assurance-item">
                    <span class="contact-assurance-number" aria-hidden="true">02</span>
                    <div><strong>Useful context</strong><p>A clear subject and message help make the reply more useful.</p></div>
                </div>
                <div class="contact-assurance-item">
                    <span class="contact-assurance-number" aria-hidden="true">03</span>
                    <div><strong>Privacy-first</strong><p>No third-party form processor is required to send your message.</p></div>
                </div>
            </div>
        </div>

        <div class="contact-form-panel">
            <div class="contact-form-heading">
                <div>
                    <span class="contact-form-kicker">Send a message</span>
                    <h3>Start the conversation.</h3>
                </div>
                <p>Tell us what you need and include the details that will help us respond.</p>
            </div>

            <?php if ($success !== ''): ?>
                <div class="contact-feedback success" role="status" tabindex="-1" <?= $focus === 'success' ? 'data-contact-focus' : '' ?>>
                    <strong>Message received</strong>
                    <p><?= e($success) ?></p>
                </div>
            <?php endif; ?>

            <?php if ($errors !== []): ?>
                <div class="contact-feedback error" role="alert" tabindex="-1" <?= $focus === 'error' ? 'data-contact-focus' : '' ?>>
                    <strong>Check your message</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?><li><?= e((string)$error) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$canAccept): ?>
                <div class="contact-feedback neutral" role="status">
                    <strong>Contact form unavailable</strong>
                    <p>This form is temporarily unavailable. Please try again later.</p>
                </div>
            <?php else: ?>
                <form class="contact-form" method="post" action="/_talvoro/contact" novalidate>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="contact_context" value="<?= e($context) ?>">

                    <div class="contact-fields-row">
                        <div class="contact-field">
                            <label for="<?= e($formId) ?>-name">Name <span class="contact-required-star" aria-hidden="true">*</span><span class="sr-only"> required</span></label>
                            <input id="<?= e($formId) ?>-name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= e((string)($old['name'] ?? '')) ?>"<?= $fieldAttrs('name') ?>>
                            <?php if (isset($fieldErrors['name'])): ?><small class="contact-field-error" id="<?= e($formId) ?>-error-name"><?= e((string)$fieldErrors['name']) ?></small><?php endif; ?>
                        </div>

                        <div class="contact-field">
                            <label for="<?= e($formId) ?>-email">Email <span class="contact-required-star" aria-hidden="true">*</span><span class="sr-only"> required</span></label>
                            <input id="<?= e($formId) ?>-email" name="email" type="email" autocomplete="email" inputmode="email" required maxlength="254" value="<?= e((string)($old['email'] ?? '')) ?>"<?= $fieldAttrs('email') ?>>
                            <?php if (isset($fieldErrors['email'])): ?><small class="contact-field-error" id="<?= e($formId) ?>-error-email"><?= e((string)$fieldErrors['email']) ?></small><?php endif; ?>
                        </div>
                    </div>

                    <?php if ($showSubject): ?>
                        <div class="contact-field">
                            <label for="<?= e($formId) ?>-subject">Subject<?= $requireSubject ? ' <span class="contact-required-star" aria-hidden="true">*</span><span class="sr-only"> required</span>' : ' <span class="contact-optional">optional</span>' ?></label>
                            <input id="<?= e($formId) ?>-subject" name="subject" type="text" maxlength="200" value="<?= e((string)($old['subject'] ?? '')) ?>" <?= $requireSubject ? 'required' : '' ?><?= $fieldAttrs('subject') ?>>
                            <?php if (isset($fieldErrors['subject'])): ?><small class="contact-field-error" id="<?= e($formId) ?>-error-subject"><?= e((string)$fieldErrors['subject']) ?></small><?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="contact-field">
                        <label for="<?= e($formId) ?>-message">Message <span class="contact-required-star" aria-hidden="true">*</span><span class="sr-only"> required</span></label>
                        <textarea id="<?= e($formId) ?>-message" name="message" rows="7" required maxlength="10000"<?= $fieldAttrs('message') ?>><?= e((string)($old['message'] ?? '')) ?></textarea>
                        <?php if (isset($fieldErrors['message'])): ?><small class="contact-field-error" id="<?= e($formId) ?>-error-message"><?= e((string)$fieldErrors['message']) ?></small><?php endif; ?>
                    </div>

                    <div class="contact-honeypot" aria-hidden="true">
                        <label for="<?= e($formId) ?>-website">Company website</label>
                        <input id="<?= e($formId) ?>-website" name="company_website" type="text" tabindex="-1" autocomplete="off" maxlength="200">
                    </div>

                    <div class="contact-form-footer">
                        <button class="button contact-submit" type="submit"><?= e((string)($block['submit_label'] ?? 'Send message')) ?></button>
                        <p class="contact-privacy-note">Your details are used only to respond to this message. Talvoro does not require a third-party form service.</p>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
