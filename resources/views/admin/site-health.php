<?php
use CMS\Core\Csrf;
use CMS\Core\Posts;

$notFoundRows = $notFound['rows'] ?? [];
?>
<header class="page-header">
    <div>
        <p class="eyebrow">Technical foundation</p>
        <h1>Site health</h1>
        <p class="muted">Publishing, runtime, search, redirects and broken public URL monitoring in one place.</p>
    </div>
    <span class="health-chip <?= $report['errors'] ? 'error' : ($report['warnings'] ? 'warning' : 'ok') ?>">
        <?= $report['errors'] ? $report['errors'] . ' errors' : ($report['warnings'] ? $report['warnings'] . ' warnings' : 'All clear') ?>
    </span>
</header>

<?php if ($notFoundDismissed): ?><div class="notice success">404 monitor history updated.</div><?php endif; ?>

<section class="card health-overview">
    <div class="health-score">
        <span>Site health</span>
        <strong><?= (int)$report['score'] ?></strong>
        <progress max="100" value="<?= (int)$report['score'] ?>"></progress>
    </div>
    <div class="health-totals">
        <div><strong><?= (int)$report['ok'] ?></strong><span>Passing</span></div>
        <div><strong><?= (int)$report['warnings'] ?></strong><span>Warnings</span></div>
        <div><strong><?= (int)$report['errors'] ?></strong><span>Errors</span></div>
        <div><strong><?= (int)($notFound['total'] ?? 0) ?></strong><span>Recent 404s</span></div>
    </div>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Publishing readiness</p><h2>Checks</h2></div></div>
    <div class="health-checks">
        <?php foreach ($report['checks'] as $check): ?>
            <article class="health-check <?= e($check['status']) ?>">
                <span class="health-icon" aria-hidden="true"><?= $check['status'] === 'ok' ? '&#10003;' : ($check['status'] === 'warning' ? '!' : '&times;') ?></span>
                <div><strong><?= e($check['label']) ?></strong><p><?= e($check['detail']) ?></p></div>
                <span class="health-chip <?= e($check['status']) ?>"><?= e(ucfirst($check['status'])) ?></span>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="card not-found-monitor">
    <div class="section-heading not-found-heading">
        <div>
            <p class="eyebrow">URL hygiene</p>
            <h2>Missing public URLs</h2>
            <p class="muted">Genuine public 404s are retained for 90 days, capped at 1,000 unique paths. Obvious scanner probes are rejected before persistence.</p>
        </div>
        <span class="soft-badge"><?= (int)($notFound['total'] ?? 0) ?> recorded</span>
    </div>

    <?php if (!$notFoundRows): ?>
        <div class="empty-state compact"><div class="empty-mark">&#10003;</div><h3>No missing public URLs recorded.</h3><p>When a normal visitor reaches a genuine 404, it will appear here without storing a raw IP address.</p></div>
    <?php else: ?>
        <?php if ($canManageNotFound): ?>
            <div class="not-found-actions">
                <form method="post" action="<?= e(admin_url()) ?>/site-health/404/dismiss-selected" data-404-bulk>
                    <?= Csrf::field() ?>
                    <input type="hidden" name="page" value="<?= (int)$notFound['page'] ?>">
                    <input type="hidden" name="paths" value="" data-404-paths>
                    <button class="button secondary compact" type="submit" disabled data-404-dismiss-selected>Ignore selected</button>
                </form>
                <?php if ((int)($notFound['scanner_count'] ?? 0) > 0): ?>
                    <form method="post" action="<?= e(admin_url()) ?>/site-health/404/dismiss-scanner" data-confirm="Dismiss recorded scanner noise?">
                        <?= Csrf::field() ?>
                        <button class="button secondary compact" type="submit">Ignore scanner noise</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(admin_url()) ?>/site-health/404/dismiss-all" class="dismiss-all-404">
                    <?= Csrf::field() ?>
                    <label class="confirm-check"><input type="checkbox" name="confirm_all" value="1" required> Confirm clear all</label>
                    <button class="button danger compact" type="submit">Ignore all</button>
                </form>
            </div>
        <?php endif; ?>

        <div class="table-wrap">
            <table class="not-found-table">
                <thead>
                    <tr>
                        <?php if ($canManageNotFound): ?><th class="select-cell"><input type="checkbox" data-404-select-all aria-label="Select this page"></th><?php endif; ?>
                        <th>Path</th><th>Hits</th><th>Referrer</th><th>Last seen</th><?php if ($canManageNotFound): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($notFoundRows as $row): ?>
                        <tr class="<?= !empty($row['likely_scanner']) ? 'scanner-row' : '' ?>">
                            <?php if ($canManageNotFound): ?><td class="select-cell"><input class="js-404-select" type="checkbox" value="<?= e($row['path']) ?>" aria-label="Select <?= e($row['path']) ?>"></td><?php endif; ?>
                            <td><code><?= e($row['path']) ?></code><?php if (!empty($row['likely_scanner'])): ?> <span class="soft-badge">Likely scanner</span><?php endif; ?></td>
                            <td><?= (int)$row['hit_count'] ?></td>
                            <td><?= e($row['referrer_host'] ?: '-') ?></td>
                            <td><?= e(Posts::displayDate($row['last_seen_at'], 'j M Y - H:i')) ?></td>
                            <?php if ($canManageNotFound): ?>
                                <td><div class="not-found-row-actions"><a class="button secondary compact" href="<?= e(admin_url()) ?>/redirects?source=<?= rawurlencode((string)$row['path']) ?>">Fix</a><form method="post" action="<?= e(admin_url()) ?>/site-health/404/dismiss"><?= Csrf::field() ?><input type="hidden" name="page" value="<?= (int)$notFound['page'] ?>"><input type="hidden" name="path" value="<?= e($row['path']) ?>"><button class="button secondary compact" type="submit">Ignore</button></form></div></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (($notFound['pages'] ?? 1) > 1): ?>
            <nav class="pager audit-pager" aria-label="404 monitor pages">
                <?php if (($notFound['page'] ?? 1) > 1): ?><a class="button secondary compact" href="?nf_page=<?= (int)$notFound['page'] - 1 ?>">&larr; Previous</a><?php else: ?><span></span><?php endif; ?>
                <strong>Page <?= (int)$notFound['page'] ?> of <?= (int)$notFound['pages'] ?></strong>
                <?php if (($notFound['page'] ?? 1) < ($notFound['pages'] ?? 1)): ?><a class="button secondary compact" href="?nf_page=<?= (int)$notFound['page'] + 1 ?>">Next &rarr;</a><?php else: ?><span></span><?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<script src="/assets/js/site-health.js?v=<?= e(app_version()) ?>" defer></script>
