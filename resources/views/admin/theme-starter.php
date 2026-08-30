<?php
$theme = is_array($review['theme'] ?? null) ? $review['theme'] : [];
$definition = is_array($review['definition'] ?? null) ? $review['definition'] : [];
$summary = is_array($review['summary'] ?? null) ? $review['summary'] : ['total'=>0,'counts'=>[]];
$state = is_array($review['state'] ?? null) ? $review['state'] : ['code'=>'not_installed'];
$items = is_array($review['items'] ?? null) ? $review['items'] : [];
$decision = is_array($review['decision'] ?? null) ? $review['decision'] : ['conflicts'=>[],'mutations'=>[]];
$stateCode = (string)($state['code'] ?? 'not_installed');
$stateText = match ($stateCode) {
    'installed' => 'Installed',
    'modified' => 'Installed with user changes',
    'repair_available' => 'Repair available',
    'needs_attention' => 'Needs attention',
    'starter_update_available' => 'Starter update available',
    default => 'Not installed',
};
$isActive = (int)($theme['is_active'] ?? 0) === 1;
$hasInstallation = !empty($review['installation']);
$conflicts = array_values(array_filter($items, static fn(array $item): bool => ($item['action'] ?? '') === 'conflict'));
$mutations = array_values(array_filter($items, static fn(array $item): bool => ($item['action'] ?? '') === 'controlled_mutation'));
?>

<header class="page-header starter-site-review-header">
    <div>
        <p class="eyebrow">Appearance · Theme Starter Site</p>
        <h1><?= e((string)($definition['name'] ?? 'Starter Site')) ?></h1>
        <p class="muted"><?= e((string)($definition['description'] ?? 'Optional starter content supplied declaratively by this theme.')) ?></p>
    </div>
    <div class="starter-status-text" aria-label="Starter Site status">
        <span class="health-chip <?= in_array($stateCode,['installed','modified'],true) ? 'ok' : 'warning' ?>"><?= e($stateText) ?></span>
        <small>Starter v<?= e((string)($definition['starter_version'] ?? '')) ?></small>
    </div>
</header>

<div class="starter-site-review" aria-live="polite">
    <?php if ($installed): ?><div class="notice success">Starter Site installation completed.</div><?php endif; ?>
    <?php if ($repaired): ?><div class="notice success">Starter Site repair completed. User-modified content was not overwritten.</div><?php endif; ?>
    <?php if ($demoDeleted): ?><div class="notice success">Delete Demo Data completed. Modified or otherwise unsafe-to-delete content was preserved.</div><?php endif; ?>

    <section class="card starter-overview-card" aria-labelledby="starter-summary-heading">
        <div class="section-heading">
            <div><p class="eyebrow">Review</p><h2 id="starter-summary-heading">What this Starter Site manages</h2></div>
        </div>
        <p>This is separate from theme import and activation. Talvoro only creates site content after an explicit installation request.</p>
        <dl class="starter-resource-summary">
            <?php foreach (($summary['counts'] ?? []) as $label=>$count): ?>
                <div><dt><?= e((string)$label) ?></dt><dd><?= (int)$count ?></dd></div>
            <?php endforeach; ?>
        </dl>
        <p class="muted"><?= (int)($summary['total'] ?? 0) ?> declarative resources in total.</p>
    </section>

    <?php if (!$hasInstallation): ?>
        <section class="card" aria-labelledby="starter-preflight-heading">
            <div class="section-heading"><div><p class="eyebrow">Preflight</p><h2 id="starter-preflight-heading">Before installation</h2></div></div>
            <?php if (!$isActive): ?>
                <div class="notice warning">Activate <strong><?= e((string)($theme['name'] ?? 'this theme')) ?></strong> before installing its Starter Site.</div>
            <?php endif; ?>

            <?php if ($conflicts): ?>
                <div class="notice warning">
                    <strong>Conflicts must be resolved first.</strong>
                    <ul>
                        <?php foreach ($conflicts as $item): ?><li><code><?= e((string)$item['key']) ?></code> — <?= e((string)($item['message'] ?? 'Existing content conflicts with this resource.')) ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($mutations): ?>
                <div class="starter-change-list">
                    <h3>Existing content that will change</h3>
                    <p class="muted">Talvoro preserves the current state before applying these changes, so ownership-safe Demo Data removal can restore it where appropriate.</p>
                    <div class="starter-change-grid">
                        <?php foreach ($mutations as $item): ?>
                            <article class="starter-change-card">
                                <div class="starter-change-card-heading">
                                    <h4><?= e((string)($item['change_title'] ?? $item['label'] ?? 'Existing content')) ?></h4>
                                    <span class="health-chip warning">Preserved</span>
                                </div>
                                <?php if (array_key_exists('change_before',$item) || array_key_exists('change_after',$item)): ?>
                                    <div class="starter-change-comparison" aria-label="Current and Starter Site values">
                                        <div><small>Current</small><strong><?= e((string)($item['change_before'] ?? 'Existing value')) ?></strong></div>
                                        <span aria-hidden="true">→</span>
                                        <div><small>Starter Site</small><strong><?= e((string)($item['change_after'] ?? 'New value')) ?></strong></div>
                                    </div>
                                <?php endif; ?>
                                <p><?= e((string)($item['change_note'] ?? $item['message'] ?? 'Talvoro will preserve the previous state.')) ?></p>
                                <details class="starter-change-technical">
                                    <summary>Technical details</summary>
                                    <code><?= e((string)$item['key']) ?></code>
                                </details>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($isActive && !$conflicts): ?>
                <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/starter/install" class="starter-confirm-form">
                    <?= CMS\Core\Csrf::field() ?>
                    <label class="check-row"><input type="checkbox" name="confirm_starter" value="1" required> <span>I understand that this will add Starter Site demo content to this website.</span></label>
                    <?php if ($mutations): ?>
                        <label class="check-row"><input type="checkbox" name="confirm_mutations" value="1" required> <span>I reviewed the existing content changes above and want Talvoro to preserve the current state before replacing it.</span></label>
                    <?php endif; ?>
                    <button class="button" type="submit">Install Starter Site</button>
                </form>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="card" aria-labelledby="starter-maintenance-heading">
            <div class="section-heading"><div><p class="eyebrow">Maintenance</p><h2 id="starter-maintenance-heading">Starter Site ownership</h2></div></div>
            <?php if ($stateCode === 'modified'): ?>
                <p>Some Starter Site content has been edited. Talvoro treats those edits as user content and will not overwrite them during repair.</p>
            <?php elseif ($stateCode === 'repair_available'): ?>
                <p>One or more starter-owned resources are missing. Repair recreates only missing owned resources and does not reset modified content.</p>
                <?php if ($isActive): ?>
                    <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/starter/repair">
                        <?= CMS\Core\Csrf::field() ?>
                        <button class="button secondary" type="submit">Repair Starter Site</button>
                    </form>
                <?php else: ?>
                    <p class="notice warning">Activate this theme before running repair.</p>
                <?php endif; ?>
            <?php elseif ($stateCode === 'starter_update_available'): ?>
                <p>A newer declarative starter definition is present. Talvoro 0.17.0 does not apply starter updates automatically or overwrite existing starter content.</p>
            <?php elseif ($stateCode === 'needs_attention'): ?>
                <p>Some resources have been detached from Starter Site ownership. They remain ordinary site content and will not be changed automatically.</p>
            <?php else: ?>
                <p>The starter-owned content is present. You can keep editing it normally; Talvoro will detect meaningful changes before removal or repair.</p>
            <?php endif; ?>
        </section>

        <section class="card danger-zone starter-delete-zone" aria-labelledby="delete-demo-heading">
            <div class="section-heading"><div><p class="eyebrow">Starter content</p><h2 id="delete-demo-heading">Delete Demo Data</h2></div></div>
            <p>Talvoro removes only resources it can positively prove are still untouched Starter Site content. User-created content is never included. Modified starter records, records with surviving user references, and other unsafe-to-delete resources are preserved and detached from starter ownership.</p>
            <form method="post" action="<?= e(admin_url()) ?>/themes/<?= (int)$theme['id'] ?>/starter/delete-demo-data" class="starter-confirm-form">
                <?= CMS\Core\Csrf::field() ?>
                <label class="check-row"><input type="checkbox" name="confirm_delete_demo" value="1" required> <span>I understand that untouched starter-owned demo content will be permanently removed, while modified or unrelated content will be preserved.</span></label>
                <button class="button danger" type="submit">Delete Demo Data</button>
            </form>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <a class="button secondary" href="<?= e(admin_url()) ?>/themes">Back to Themes</a>
        <a class="button secondary" href="/" target="_blank" rel="noopener">Preview website ↗</a>
    </div>
</div>
