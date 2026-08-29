<?php
use CMS\Core\ContactSettings;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\Posts;

$items = is_array($pageData['items'] ?? null) ? $pageData['items'] : [];
$page = max(1, (int)($pageData['page'] ?? 1));
$pages = max(1, (int)($pageData['pages'] ?? 1));
$total = max(0, (int)($pageData['total'] ?? 0));
$status = (string)($pageData['status'] ?? '');
$retention = ContactSettings::config()['retention_days'];
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Content</p>
        <h1>Contact submissions</h1>
        <p class="muted">A focused inbox for messages retained by Talvoro. Visitor IP addresses are not stored with submissions.</p>
    </div>
    <?php if (Gate::allows('contact.manage') || Gate::allows('mail.manage')): ?>
        <div class="header-actions">
            <a class="button secondary" href="<?= e(admin_url('/mail')) ?>">Contact settings</a>
        </div>
    <?php endif; ?>
</header>

<?php if (!empty($deleted)): ?><div class="notice success">Contact submission permanently deleted.</div><?php endif; ?>
<?php if ((int)($bulkDeleted ?? 0) > 0): ?><div class="notice success"><?= (int)$bulkDeleted ?> contact submission<?= (int)$bulkDeleted === 1 ? '' : 's' ?> permanently deleted.</div><?php endif; ?>
<?php if (isset($cleaned) && (int)$cleaned > 0): ?><div class="notice neutral"><?= (int)$cleaned ?> submission<?= (int)$cleaned === 1 ? '' : 's' ?> removed by the retention policy.</div><?php endif; ?>

<section class="card content-card">
    <div class="section-heading">
        <div><p class="eyebrow">Inbox</p><h2><?= $total ?> retained message<?= $total === 1 ? '' : 's' ?></h2></div>
        <span class="soft-badge">Retention <?= (int)$retention ?> days</span>
    </div>

    <nav class="contact-admin-filters" aria-label="Filter contact submissions">
        <?php foreach (['' => 'All', 'new' => 'New', 'read' => 'Read'] as $value => $label): ?>
            <a class="button secondary small<?= $status === $value ? ' is-active' : '' ?>" href="<?= e(admin_url('/contact-submissions' . ($value !== '' ? '?status=' . rawurlencode($value) : ''))) ?>" <?= $status === $value ? 'aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($items === []): ?>
        <div class="empty-state compact">
            <div class="empty-mark">@</div>
            <h2><?= $status !== '' ? 'No messages in this view.' : 'No retained contact messages.' ?></h2>
            <p><?= $status !== '' ? 'Choose another status to review the inbox.' : 'Messages appear here only when local contact submission storage is enabled in Email settings.' ?></p>
        </div>
    <?php else: ?>
        <?php if ($canManage): ?><form method="post" action="<?= e(admin_url('/contact-submissions/bulk-delete')) ?>"><?= Csrf::field() ?><?php endif; ?>
        <div class="contact-submission-list">
            <?php foreach ($items as $submission): ?>
                <article class="contact-submission-row<?= ($submission['status'] ?? '') === 'new' ? ' is-new' : '' ?>">
                    <?php if ($canManage): ?><label><span class="sr-only">Select message from <?= e((string)$submission['sender_name']) ?></span><input type="checkbox" name="submission_ids[]" value="<?= (int)$submission['id'] ?>"></label><?php else: ?><span aria-hidden="true"></span><?php endif; ?>
                    <a href="<?= e(admin_url('/contact-submissions/' . (int)$submission['id'])) ?>">
                        <strong><?= e((string)$submission['sender_name']) ?></strong>
                        <small><?= e((string)$submission['sender_email']) ?></small>
                    </a>
                    <a class="contact-submission-subject" href="<?= e(admin_url('/contact-submissions/' . (int)$submission['id'])) ?>">
                        <strong><?= e(trim((string)($submission['subject'] ?? '')) !== '' ? (string)$submission['subject'] : 'No subject') ?></strong>
                        <small><?= e(Posts::displayDate((string)$submission['created_at'], 'j M Y - H:i')) ?></small>
                    </a>
                    <div class="contact-submission-source"><strong><?= e((string)$submission['source_label']) ?></strong><small><?= e((string)$submission['source_path']) ?></small></div>
                    <div class="contact-submission-meta">
                        <span class="status-badge <?= e((string)$submission['status']) ?>"><?= e(ucfirst((string)$submission['status'])) ?></span>
                        <span class="health-chip <?= ($submission['delivery_status'] ?? '') === 'sent' ? 'ok' : (($submission['delivery_status'] ?? '') === 'failed' ? 'error' : 'warning') ?>"><?= e(ucfirst((string)$submission['delivery_status'])) ?></span>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($canManage): ?>
            <div class="contact-bulk-bar">
                <label class="confirm-check"><input type="checkbox" name="confirm_delete" value="1" required> Permanently delete the selected messages.</label>
                <button class="button danger" type="submit">Delete selected</button>
            </div>
        </form>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Contact submission pages">
            <?php $base = $status !== '' ? ['status' => $status] : []; ?>
            <?php if ($page > 1): ?><a href="<?= e(admin_url('/contact-submissions?' . http_build_query(array_merge($base, ['page' => $page - 1])))) ?>">Previous</a><?php else: ?><span></span><?php endif; ?>
            <span>Page <?= $page ?> of <?= $pages ?> - <?= $total ?> total</span>
            <?php if ($page < $pages): ?><a href="<?= e(admin_url('/contact-submissions?' . http_build_query(array_merge($base, ['page' => $page + 1])))) ?>">Next</a><?php else: ?><span></span><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>

<?php if ($canManage): ?>
<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Privacy</p><h2>Retention cleanup</h2><p class="muted">Remove locally stored submissions older than <?= (int)$retention ?> days now. Talvoro also cleans expired records opportunistically.</p></div></div>
    <form method="post" action="<?= e(admin_url('/contact-submissions/cleanup')) ?>" class="contact-admin-actions">
        <?= Csrf::field() ?>
        <button class="button secondary" type="submit">Run retention cleanup</button>
    </form>
</section>
<?php endif; ?>
