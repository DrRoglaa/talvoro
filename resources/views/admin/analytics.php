<?php
$metrics = $data['metrics'] ?? [];
$previous = $data['previous'] ?? [];
$days = (int)($data['days'] ?? 30);

$metrics = array_merge([
    'pageviews' => 0,
    'visitors' => 0,
    'sessions' => 0,
    'engagement_rate' => 0.0,
    'pages_per_session' => 0.0,
], is_array($metrics) ? $metrics : []);

$previous = array_merge([
    'pageviews' => 0,
    'visitors' => 0,
    'sessions' => 0,
], is_array($previous) ? $previous : []);

foreach ([
    'series',
    'top',
    'channels',
    'referrers',
    'devices',
    'browsers',
    'oses',
    'entries',
    'exits',
    'realtime',
    'actions',
    'campaigns',
] as $listKey) {
    if (!isset($data[$listKey]) || !is_array($data[$listKey])) {
        $data[$listKey] = [];
    }
}

$data['active_now'] = (int)($data['active_now'] ?? 0);

$delta = static function (int $current, int $before): string {
    if ($before === 0) {
        return $current === 0 ? '—' : '+100%';
    }
    $value = (($current - $before) / $before) * 100;
    return ($value >= 0 ? '+' : '') . number_format($value, 0) . '%';
};

$series = $data['series'];
$maxValue = 1;
foreach ($series as $row) {
    $maxValue = max($maxValue, (int)$row['pageviews'], (int)$row['sessions']);
}
$points = static function (array $rows, string $key, int $max): string {
    $count = count($rows);
    if ($count === 0) {
        return '';
    }
    $parts = [];
    foreach ($rows as $index => $row) {
        $x = $count === 1 ? 500 : ($index / ($count - 1)) * 1000;
        $y = 220 - (((int)$row[$key] / max(1, $max)) * 190);
        $parts[] = number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
    }
    return implode(' ', $parts);
};

$maxDimension = static function (array $rows): int {
    $max = 1;
    foreach ($rows as $row) {
        $max = max($max, (int)($row['sessions'] ?? 0));
    }
    return $max;
};
?>
<header class="page-header">
    <div>
        <p class="eyebrow">First-party insights</p>
        <h1>Analytics</h1>
        <p class="muted">Database-only traffic insights. No third-party analytics SDK, fingerprinting or cross-site tracking.</p>
    </div>
    <span class="privacy-pill">DNT respected · bots filtered</span>
</header>

<section class="analytics-hero card">
    <div>
        <p class="eyebrow">Realtime</p>
        <h2><?= (int)$data['active_now'] ?> active now</h2>
        <p class="muted">Sessions seen during the last five minutes.</p>
    </div>
    <div class="range-switcher" aria-label="Analytics period">
        <?php foreach ([7,30,90,180,365] as $range): ?>
            <a class="<?= $days === $range ? 'is-active' : '' ?>" href="<?= e(admin_url()) ?>/analytics?days=<?= $range ?>">
                <?= $range === 365 ? '1 year' : $range . ' days' ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<div class="metric-grid analytics-metrics">
    <article class="metric">
        <span>Page views</span>
        <strong><?= number_format((int)$metrics['pageviews']) ?></strong>
        <small><?= e($delta((int)$metrics['pageviews'], (int)$previous['pageviews'])) ?> vs previous period</small>
    </article>
    <article class="metric">
        <span>Private daily visitors</span>
        <strong><?= number_format((int)$metrics['visitors']) ?></strong>
        <small><?= e($delta((int)$metrics['visitors'], (int)$previous['visitors'])) ?> vs previous period</small>
    </article>
    <article class="metric">
        <span>Sessions</span>
        <strong><?= number_format((int)$metrics['sessions']) ?></strong>
        <small><?= e($delta((int)$metrics['sessions'], (int)$previous['sessions'])) ?> vs previous period</small>
    </article>
    <article class="metric">
        <span>Engagement rate</span>
        <strong><?= number_format((float)$metrics['engagement_rate'], 1) ?>%</strong>
        <small>Sessions with 2+ page views</small>
    </article>
    <article class="metric">
        <span>Pages / session</span>
        <strong><?= number_format((float)$metrics['pages_per_session'], 2) ?></strong>
        <small>Average page depth</small>
    </article>
</div>

<section class="card analytics-chart-card">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Traffic over time</p>
            <h2>Page views & sessions</h2>
        </div>
        <div class="chart-legend"><span class="views">Page views</span><span class="sessions">Sessions</span></div>
    </div>

    <?php if (!$series): ?>
        <div class="empty-state compact"><h3>No traffic in this period yet.</h3></div>
    <?php else: ?>
        <svg class="traffic-chart" viewBox="0 0 1000 240" role="img" aria-label="Page views and sessions over time">
            <line x1="0" y1="220" x2="1000" y2="220" class="chart-grid"></line>
            <line x1="0" y1="125" x2="1000" y2="125" class="chart-grid"></line>
            <line x1="0" y1="30" x2="1000" y2="30" class="chart-grid"></line>
            <polyline class="chart-line pageviews" points="<?= e($points($series, 'pageviews', $maxValue)) ?>"></polyline>
            <polyline class="chart-line sessions" points="<?= e($points($series, 'sessions', $maxValue)) ?>"></polyline>
        </svg>
        <div class="chart-axis">
            <span><?= e((string)$series[0]['day']) ?></span>
            <span><?= e((string)$series[array_key_last($series)]['day']) ?></span>
        </div>
    <?php endif; ?>
</section>

<div class="analytics-two">
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Content</p><h2>Top pages</h2></div></div>
        <div class="data-table">
            <div class="data-row header"><span>Page</span><span>Views</span><span>Sessions</span></div>
            <?php foreach ($data['top'] as $row): ?>
                <div class="data-row"><strong><?= e($row['path']) ?></strong><span><?= (int)$row['views'] ?></span><span><?= (int)($row['sessions'] ?? 0) ?></span></div>
            <?php endforeach; ?>
            <?php if (!$data['top']): ?><p class="muted">No page views yet.</p><?php endif; ?>
        </div>
    </section>

    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Acquisition</p><h2>Traffic channels</h2></div></div>
        <?php $channelMax = $maxDimension($data['channels']); ?>
        <div class="dimension-list">
            <?php foreach ($data['channels'] as $row): ?>
                <div class="dimension-row">
                    <strong><?= e($row['channel']) ?></strong>
                    <progress max="<?= $channelMax ?>" value="<?= (int)$row['sessions'] ?>"></progress>
                    <span><?= (int)($row['sessions'] ?? 0) ?></span>
                </div>
            <?php endforeach; ?>
            <?php if (!$data['channels']): ?><p class="muted">No acquisition data yet.</p><?php endif; ?>
        </div>

        <div class="subsection-heading"><p class="eyebrow">External referrers</p></div>
        <div class="data-table compact-table">
            <?php foreach ($data['referrers'] as $row): ?>
                <div class="data-row"><strong><?= e($row['referrer']) ?></strong><span><?= (int)$row['sessions'] ?> sessions</span></div>
            <?php endforeach; ?>
            <?php if (!$data['referrers']): ?><p class="muted">No external referrers in this period.</p><?php endif; ?>
        </div>
    </section>
</div>

<div class="analytics-three">
    <?php foreach ([
        ['title' => 'Device class', 'rows' => $data['devices']],
        ['title' => 'Browsers', 'rows' => $data['browsers']],
        ['title' => 'Operating system', 'rows' => $data['oses']],
    ] as $dimension): ?>
        <section class="card">
            <div class="section-heading"><div><p class="eyebrow">Audience</p><h2><?= e($dimension['title']) ?></h2></div></div>
            <?php $dimensionMax = $maxDimension($dimension['rows']); ?>
            <div class="dimension-list">
                <?php foreach ($dimension['rows'] as $row): ?>
                    <div class="dimension-row">
                        <strong><?= e((string)($row['label'] ?? $row['device_type'] ?? $row['browser'] ?? $row['os'] ?? 'Other')) ?></strong>
                        <progress max="<?= $dimensionMax ?>" value="<?= (int)$row['sessions'] ?>"></progress>
                        <span><?= (int)($row['sessions'] ?? 0) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<div class="analytics-three">
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Entry</p><h2>Landing pages</h2></div></div>
        <div class="data-table compact-table">
            <?php foreach ($data['entries'] as $row): ?><div class="data-row"><strong><?= e($row['path']) ?></strong><span><?= (int)($row['sessions'] ?? 0) ?></span></div><?php endforeach; ?>
            <?php if (!$data['entries']): ?><p class="muted">No landing-page data yet.</p><?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Exit</p><h2>Exit pages</h2></div></div>
        <div class="data-table compact-table">
            <?php foreach ($data['exits'] as $row): ?><div class="data-row"><strong><?= e($row['path']) ?></strong><span><?= (int)($row['sessions'] ?? 0) ?></span></div><?php endforeach; ?>
            <?php if (!$data['exits']): ?><p class="muted">No exit-page data yet.</p><?php endif; ?>
        </div>
    </section>
    <section class="card">
        <div class="section-heading"><div><p class="eyebrow">Realtime</p><h2>Last 30 minutes</h2></div></div>
        <div class="data-table compact-table">
            <?php foreach ($data['realtime'] as $row): ?><div class="data-row"><strong><?= e($row['path']) ?></strong><span><?= (int)$row['sessions'] ?> sessions</span></div><?php endforeach; ?>
            <?php if (!$data['realtime']): ?><p class="muted">No recent activity.</p><?php endif; ?>
        </div>
    </section>
</div>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Campaigns</p><h2>UTM performance</h2></div></div>
    <?php if (!$data['campaigns']): ?>
        <p class="muted">No UTM-tagged visits in this period yet.</p>
    <?php else: ?>
        <div class="campaign-table">
            <div class="campaign-row header"><span>Source / medium</span><span>Campaign</span><span>Sessions</span><span>Views</span></div>
            <?php foreach ($data['campaigns'] as $row): ?>
                <div class="campaign-row">
                    <strong><?= e($row['source']) ?> / <?= e($row['medium']) ?></strong>
                    <span><?= e($row['campaign']) ?></span>
                    <span><?= (int)($row['sessions'] ?? 0) ?></span>
                    <span><?= (int)$row['views'] ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="section-heading"><div><p class="eyebrow">Actions</p><h2>Events & conversions</h2></div></div>
    <?php if (!$data['actions']): ?>
        <p class="muted">No custom events are being recorded yet. Page views remain the only analytics event in this release.</p>
    <?php else: ?>
        <div class="data-table">
            <?php foreach ($data['actions'] as $row): ?>
                <div class="data-row"><strong><?= e($row['event_type']) ?></strong><span><?= (int)$row['events'] ?> events</span><span><?= (int)$row['sessions'] ?> sessions</span></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
