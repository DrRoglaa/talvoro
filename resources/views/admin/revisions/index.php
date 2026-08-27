<?php
use CMS\Core\Csrf;
use CMS\Core\Posts;
$base = $contentType === 'page' ? 'pages' : 'posts';
$defaultHistoryBase = admin_url('/' . $base . '/' . $contentId . '/edit/revisions');
$historyBaseUrl = isset($historyBaseUrl) && is_string($historyBaseUrl) ? $historyBaseUrl : $defaultHistoryBase;
$restoreBaseUrl = isset($restoreBaseUrl) && is_string($restoreBaseUrl) ? $restoreBaseUrl : $defaultHistoryBase;
$actionLabel = static function (string $action): string {
    return match ($action) {
        'baseline' => 'Initial version',
        'save' => 'Saved changes',
        'draft_save' => 'Saved draft',
        'schedule' => 'Scheduled',
        'publish' => 'Published',
        'restore' => 'Restored version',
        default => ucwords(str_replace(['_', '-'], ' ', $action)),
    };
};
$groups = [];
foreach ($changes as $change) {
    $group = (string)($change['group'] ?? 'Changes');
    $groups[$group][] = $change;
}
$changeCount = count($changes);
?>
<header class="page-header revision-page-header">
    <div>
        <a class="back-link" href="<?= e($editUrl) ?>">← Back to editor</a>
        <p class="eyebrow">Content safety</p>
        <h1>Revision history</h1>
        <p class="muted"><?= e($contentTitle) ?> · Review earlier saved versions in plain language and restore one if needed.</p>
    </div>
    <a class="button secondary" href="<?= e($editUrl) ?>">Open editor</a>
</header>

<?php if (!empty($restored)): ?><div class="notice success">Revision restored. Talvoro kept the revision history, so you can change back again if needed.</div><?php endif; ?>

<div class="revision-layout">
    <section class="card revision-list-card">
        <div class="section-heading"><div><p class="eyebrow">History</p><h2><?= count($revisions) ?> <?= count($revisions) === 1 ? 'revision' : 'revisions' ?></h2></div></div>
        <?php if (!$revisions): ?>
            <div class="empty-state"><h3>No revisions yet.</h3><p>The first revision is created when this content is saved.</p></div>
        <?php else: ?>
            <div class="revision-list">
                <?php foreach ($revisions as $item):
                    $active = $selectedRevision && (int)$selectedRevision['id'] === (int)$item['id'];
                    $url = $historyBaseUrl . '?revision=' . (int)$item['id'];
                ?>
                    <a class="revision-row<?= $active ? ' active' : '' ?>" href="<?= e($url) ?>">
                        <span>
                            <strong>Revision <?= (int)$item['revision_no'] ?></strong>
                            <small><?= e($actionLabel((string)$item['action'])) ?></small>
                        </span>
                        <span class="revision-row-meta">
                            <strong><?= e($item['author_name'] ?: 'System') ?></strong>
                            <small><?= e(Posts::displayDate((string)$item['created_at'], 'j M Y · H:i')) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="card revision-compare-card">
        <?php if (!$selectedRevision): ?>
            <div class="empty-state revision-empty"><div class="empty-mark">↺</div><h2>Select a revision</h2><p>Choose a saved version on the left. Talvoro will explain what changed compared with the version you have now.</p></div>
        <?php else: ?>
            <div class="section-heading revision-compare-heading">
                <div>
                    <p class="eyebrow">What changed?</p>
                    <h2>Revision <?= (int)$selectedRevision['revision_no'] ?> compared with current</h2>
                </div>
                <span class="health-chip <?= $changes ? 'warning' : 'ok' ?>"><?= $changes ? $changeCount . ' ' . ($changeCount === 1 ? 'change' : 'changes') : 'No changes' ?></span>
            </div>
            <p class="muted revision-saved-meta"><?= e($actionLabel((string)$selectedRevision['action'])) ?> · <?= e(Posts::displayDate((string)$selectedRevision['created_at'])) ?> · <?= e($selectedRevision['author_name'] ?: 'System') ?></p>

            <?php if (!$changes): ?>
                <div class="notice neutral">This revision currently matches the saved content.</div>
            <?php else: ?>
                <div class="revision-change-summary">
                    <strong><?= $changeCount ?> <?= $changeCount === 1 ? 'difference' : 'differences' ?> found</strong>
                    <span>Only changed content is shown below. Technical data such as block JSON is hidden from the normal revision view.</span>
                </div>
                <div class="revision-groups">
                    <?php foreach ($groups as $groupName => $groupChanges): ?>
                        <section class="revision-group">
                            <div class="revision-group-heading">
                                <h3><?= e($groupName) ?></h3>
                                <span><?= count($groupChanges) ?></span>
                            </div>
                            <div class="revision-diff-list">
                                <?php foreach ($groupChanges as $change):
                                    $kind = (string)($change['kind'] ?? 'changed');
                                ?>
                                    <article class="revision-diff revision-diff-<?= e($kind) ?>">
                                        <div class="revision-diff-header">
                                            <div>
                                                <strong><?= e((string)$change['label']) ?></strong>
                                                <p><?= e((string)($change['summary'] ?? 'Changed.')) ?></p>
                                            </div>
                                            <span class="revision-kind revision-kind-<?= e($kind) ?>"><?= e(ucfirst($kind)) ?></span>
                                        </div>
                                        <?php if (!empty($change['show_values'])): ?>
                                            <div class="revision-values">
                                                <div class="revision-value-old">
                                                    <small>In this revision</small>
                                                    <p><?= nl2br(e((string)$change['revision'])) ?></p>
                                                </div>
                                                <div class="revision-change-arrow" aria-hidden="true">→</div>
                                                <div class="revision-value-current">
                                                    <small>Current version</small>
                                                    <p><?= nl2br(e((string)$change['current'])) ?></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e($restoreBaseUrl . '/' . (int)$selectedRevision['id'] . '/restore') ?>" class="revision-restore-form">
                <?= Csrf::field() ?>
                <div class="revision-restore-copy">
                    <strong>Restore Revision <?= (int)$selectedRevision['revision_no'] ?>?</strong>
                    <p>This will make that saved version the current editable content. Talvoro keeps revision history, so you can restore a later version again if needed.</p>
                </div>
                <label class="confirm-check"><input type="checkbox" name="confirm_restore" value="1" required> I understand and want to restore this revision.</label>
                <button class="button" type="submit">Restore this revision</button>
            </form>
        <?php endif; ?>
    </section>
</div>
