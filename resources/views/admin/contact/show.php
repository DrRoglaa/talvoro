<?php
use CMS\Core\Csrf;
use CMS\Core\Posts;
$id = (int)$submission['id'];
$subject = trim((string)($submission['subject'] ?? ''));
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Contact submissions</p>
        <h1><?= e($subject !== '' ? $subject : 'No subject') ?></h1>
        <p class="muted">Received <?= e(Posts::displayDate((string)$submission['created_at'], 'j M Y - H:i')) ?> from <?= e((string)$submission['sender_name']) ?>.</p>
    </div>
    <div class="header-actions"><a class="button secondary" href="<?= e(admin_url('/contact-submissions')) ?>">Back to inbox</a></div>
</header>

<div class="contact-detail-grid">
    <section class="card content-card">
        <div class="section-heading"><div><p class="eyebrow">Message</p><h2><?= e((string)$submission['sender_name']) ?></h2></div><span class="status-badge <?= e((string)$submission['status']) ?>"><?= e(ucfirst((string)$submission['status'])) ?></span></div>
        <div class="contact-submission-message"><?= e((string)$submission['message']) ?></div>
    </section>

    <aside class="card contact-detail-facts">
        <div class="section-heading"><div><p class="eyebrow">Details</p><h2>Submission</h2></div></div>
        <div class="split-row"><span>Email</span><strong><a href="mailto:<?= e((string)$submission['sender_email']) ?>"><?= e((string)$submission['sender_email']) ?></a></strong></div>
        <div class="split-row"><span>Source</span><strong><?= e((string)$submission['source_label']) ?></strong></div>
        <div class="split-row"><span>Page</span><strong><?= e((string)$submission['source_path']) ?></strong></div>
        <div class="split-row"><span>Notification</span><strong><?= e(ucfirst((string)$submission['delivery_status'])) ?></strong></div>
        <div class="split-row"><span>Received</span><strong><?= e(Posts::displayDate((string)$submission['created_at'], 'j M Y - H:i')) ?></strong></div>

        <form method="post" action="<?= e(admin_url('/contact-submissions/' . $id . '/status')) ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="status" value="<?= ($submission['status'] ?? '') === 'read' ? 'new' : 'read' ?>">
            <button class="button secondary" type="submit">Mark <?= ($submission['status'] ?? '') === 'read' ? 'unread' : 'read' ?></button>
        </form>

        <?php if ($canManage): ?>
            <div class="danger-zone">
                <div><strong>Delete submission</strong><p>Permanently removes this stored message. This cannot be undone.</p></div>
                <form method="post" action="<?= e(admin_url('/contact-submissions/' . $id . '/delete')) ?>">
                    <?= Csrf::field() ?>
                    <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> I understand this message will be permanently deleted.</label>
                    <button class="button danger" type="submit">Delete permanently</button>
                </form>
            </div>
        <?php endif; ?>
    </aside>
</div>
