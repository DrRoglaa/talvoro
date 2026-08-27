<?php
use CMS\Core\Env;

$appName = (string)Env::get('APP_NAME', 'Talvoro');
$isMaintenance = ($searchHandling ?? 'prelaunch') === 'maintenance';
?>
<section class="dev-page-shell">
    <div class="dev-page-inner">

        <header class="dev-brand-row">
            <div class="dev-brand">
                <span class="dev-brand-mark" aria-hidden="true">
                    <span class="dev-mountain"></span>
                    <span class="dev-route"></span>
                </span>

                <strong><?= e($appName) ?></strong>
            </div>

            <span class="dev-status-pill <?= $isMaintenance ? 'maintenance' : 'development' ?>">
                <span class="dev-status-dot" aria-hidden="true"></span>
                <?= $isMaintenance ? 'Maintenance' : 'Under development' ?>
            </span>
        </header>

        <div class="dev-copy">
            <p class="dev-kicker"><?= $isMaintenance ? 'Temporary maintenance' : 'Public preview' ?></p>

            <h1><?= e($headline ?: 'A more thoughtful experience is taking shape.') ?></h1>

            <p class="dev-message">
                <?= e($message ?: 'We are refining the website for its next public preview. Please check back soon.') ?>
            </p>
        </div>

        <?php if (!empty($returnDisplay)): ?>
            <p class="dev-return-label">
                Planned return
                <span aria-hidden="true">·</span>
                <?= e($returnDisplay) ?>
            </p>
        <?php endif; ?>

        <?php if (!empty($countdownEnabled) && !empty($returnAtIso)): ?>
            <div
                class="dev-countdown"
                data-countdown
                data-countdown-at="<?= e($returnAtIso) ?>"
                aria-label="Countdown to planned return"
            >
                <div class="dev-countdown-unit">
                    <strong data-days>—</strong>
                    <span>Days</span>
                </div>

                <span class="dev-countdown-separator" aria-hidden="true">:</span>

                <div class="dev-countdown-unit">
                    <strong data-hours>—</strong>
                    <span>Hours</span>
                </div>

                <span class="dev-countdown-separator" aria-hidden="true">:</span>

                <div class="dev-countdown-unit">
                    <strong data-minutes>—</strong>
                    <span>Minutes</span>
                </div>

                <span class="dev-countdown-separator" aria-hidden="true">:</span>

                <div class="dev-countdown-unit">
                    <strong data-seconds>—</strong>
                    <span>Seconds</span>
                </div>
            </div>
        <?php endif; ?>

        <footer class="dev-page-footer">
            Independent software, built with <span aria-label="heart">♥</span>.
        </footer>

    </div>
</section>

<?php if (!empty($countdownEnabled) && !empty($returnAtIso)): ?>
<script src="/assets/js/holding-page.js?v=<?= e(app_version()) ?>" defer></script>
<?php endif; ?>
