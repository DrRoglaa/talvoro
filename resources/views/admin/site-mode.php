<?php
use CMS\Core\Csrf;

$isDevelopment = $mode === 'development';
$visitorSummary = !$isDevelopment
    ? 'Public website is live.'
    : (
        $searchHandling === 'maintenance'
            ? 'Maintenance mode: public requests receive HTTP 503 and noindex.'
            : 'Pre-launch mode: the homepage remains HTTP 200 and indexable; other public routes redirect to it.'
    );
?>
<header class="page-header site-mode-header">
    <div>
        <p class="eyebrow">Public availability</p>
        <h1>Site mode</h1>
        <p class="muted">Temporarily replace the public website with a polished development page. Signed-in CMS sessions keep full access to the real site.</p>
    </div>

    <div class="site-mode-status <?= $isDevelopment ? 'development' : 'live' ?>">
        <span class="site-mode-dot" aria-hidden="true"></span>
        <div>
            <strong><?= $isDevelopment ? 'Development page active' : 'Website live' ?></strong>
            <small><?= $isDevelopment ? 'Visitors receive the holding page' : 'Visitors receive the public website' ?></small>
        </div>
    </div>
</header>

<?php if ($saved): ?>
    <div class="notice success">Site mode updated successfully.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="notice error">
        <strong>Site mode could not be saved.</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="<?= e(admin_url()) ?>/site-mode" class="site-mode-form" data-site-mode-form>
    <?= Csrf::field() ?>

    <section class="site-mode-row">
        <div>
            <h2>Under development</h2>
            <p>Show a temporary progress page to normal visitors.</p>
        </div>

        <label class="switch-control" aria-label="Under development">
            <input
                type="checkbox"
                name="development_enabled"
                value="1"
                <?= $isDevelopment ? 'checked' : '' ?>
                data-development-toggle
            >
            <span class="switch-track" aria-hidden="true">
                <span class="switch-thumb"></span>
            </span>
        </label>
    </section>

    <section class="site-mode-row search-handling-row">
        <div>
            <h2>Search handling</h2>
            <p>Use Pre-launch for longer development periods. Use Maintenance only for short temporary outages.</p>
        </div>

        <label class="sr-only" for="search-handling">Search handling</label>
        <select id="search-handling" name="search_handling" data-search-handling>
            <option value="prelaunch" <?= $searchHandling === 'prelaunch' ? 'selected' : '' ?>>
                Pre-launch — HTTP 200, indexable homepage
            </option>
            <option value="maintenance" <?= $searchHandling === 'maintenance' ? 'selected' : '' ?>>
                Maintenance — HTTP 503, noindex
            </option>
        </select>
    </section>

    <section class="site-mode-fields">
        <label>
            Headline
            <input
                type="text"
                name="development_headline"
                maxlength="180"
                value="<?= e($headline) ?>"
                placeholder="A more thoughtful website experience is taking shape."
            >
            <small class="field-hint">Main public development-page headline.</small>
        </label>

        <label>
            Message
            <textarea
                name="development_message"
                rows="5"
                maxlength="1000"
                placeholder="We are refining the website for its next public preview."
            ><?= e($message) ?></textarea>
        </label>

        <div class="site-return-grid">
            <label>
                Planned return date
                <small class="field-label-note">Optional</small>
                <input type="date" name="return_date" value="<?= e($returnDate) ?>">
                <small class="field-hint">Date used for the public countdown.</small>
            </label>

            <label>
                Planned return time
                <small class="field-label-note">24-hour</small>
                <input type="time" name="return_time" value="<?= e($returnTime) ?>">
                <small class="field-hint">Validated in the configured application timezone.</small>
            </label>
        </div>

        <fieldset class="countdown-fieldset">
            <legend>Countdown</legend>
            <label class="check-row">
                <input
                    type="checkbox"
                    name="countdown_enabled"
                    value="1"
                    <?= $countdownEnabled ? 'checked' : '' ?>
                >
                <span>Show days, hours, minutes and seconds when a return date and time are set.</span>
            </label>
        </fieldset>
    </section>

    <footer class="site-mode-footer">
        <div>
            <p class="eyebrow">Visitor experience</p>
            <strong data-visitor-title><?= $isDevelopment ? 'Development holding page' : 'Public website' ?></strong>
            <small data-visitor-summary><?= e($visitorSummary) ?></small>
        </div>

        <div class="site-mode-actions">
            <a class="button secondary" href="/?preview_holding=1" target="_blank" rel="noopener">Preview holding page</a>
            <a class="button secondary" href="/" target="_blank" rel="noopener">Open website</a>
            <button class="button" type="submit">Save site mode</button>
        </div>
    </footer>
</form>

<script src="/assets/js/site-mode-admin.js?v=<?= e(app_version()) ?>" defer></script>
