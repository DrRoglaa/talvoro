<?php
declare(strict_types=1);

namespace CMS\Core;

final class SiteHealth
{
    public static function report(): array
    {
        $checks = [];

        try {
            Database::connection()->query('SELECT 1');
            $checks[] = self::check('Database', 'ok', 'MariaDB is reachable from the application.');
        } catch (\Throwable) {
            $checks[] = self::check('Database', 'error', 'The application cannot reach MariaDB.');
        }

        $checks[] = version_compare(PHP_VERSION, '8.5.0', '>=')
            ? self::check('PHP runtime', 'ok', 'PHP ' . PHP_VERSION . ' meets the CMS requirement.')
            : self::check('PHP runtime', 'error', 'PHP 8.5 or newer is required.');

        $checks[] = strlen((string)Env::get('APP_KEY', '')) >= 32
            ? self::check('Application key', 'ok', 'APP_KEY is configured.')
            : self::check('Application key', 'error', 'APP_KEY is missing or too short.');

        $checks[] = InstallState::isInstalled()
            ? self::check('Installer lock', 'ok', 'Installer is locked after installation.')
            : self::check('Installer lock', 'error', 'Installer is not locked.');

        $configPath = base_path('storage/config.php');
        if (is_file($configPath)) {
            $perms = fileperms($configPath) & 0777;
            $checks[] = ($perms & 0007) === 0
                ? self::check('Bootstrap configuration', 'ok', 'storage/config.php is outside the public root and not world-readable.')
                : self::check('Bootstrap configuration', 'warning', 'Tighten storage/config.php permissions; it should not be world-readable.');
        } else {
            $checks[] = self::check('Bootstrap configuration', 'ok', 'Environment/Docker bootstrap configuration is active.');
        }

        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $publicRoot = realpath(base_path('public')) ?: '';
        $checks[] = ($documentRoot !== '' && $publicRoot !== '' && $documentRoot === $publicRoot)
            ? self::check('Public document root', 'ok', 'Only the /public directory is web-accessible.')
            : self::check('Public document root', 'warning', 'Production hosting should point the domain document root directly to the project public/ directory.');

        $checks[] = ini_get('session.use_strict_mode') === '1'
            ? self::check('Strict sessions', 'ok', 'PHP session strict mode is enabled.')
            : self::check('Strict sessions', 'warning', 'Enable session.use_strict_mode for stronger session fixation protection.');

        $checks[] = UpdateManager::isLocked()
            ? self::check('Update recovery', 'warning', 'An update/recovery lock is active. Review System → Updates & recovery.')
            : self::check('Update recovery', 'ok', 'No interrupted update is detected.');

        $release = ReleaseIntegrity::report();
        $checks[] = $release['ok']
            ? self::check('Release integrity', 'ok', $release['checked'] . ' release files match their SHA-256 manifest.')
            : self::check('Release integrity', 'warning', implode(' ', array_slice($release['issues'], 0, 3)));

        $storageOk = is_writable(base_path('storage/logs'))
            && is_writable(base_path('storage/cache'))
            && is_writable(base_path('storage/sessions'));
        $checks[] = $storageOk
            ? self::check('Runtime storage', 'ok', 'Logs, cache and sessions are writable.')
            : self::check('Runtime storage', 'error', 'One or more runtime storage directories are not writable.');

        try {
            $files = array_map('basename', glob(base_path('database/migrations/*.sql')) ?: []);
            $applied = Database::connection()->query('SELECT migration FROM schema_migrations')->fetchAll(\PDO::FETCH_COLUMN);
            $missing = array_values(array_diff($files, $applied));
            $checks[] = !$missing
                ? self::check('Database migrations', 'ok', 'All migrations are applied.')
                : self::check('Database migrations', 'error', 'Pending: ' . implode(', ', $missing));
        } catch (\Throwable) {
            $checks[] = self::check('Database migrations', 'error', 'Migration state could not be verified.');
        }

        $coverage = SEO::coverage();
        $checks[] = $coverage['percent'] >= 80
            ? self::check('SEO coverage', 'ok', $coverage['configured'] . ' of ' . $coverage['total'] . ' managed pages have title + description.')
            : self::check('SEO coverage', 'warning', $coverage['configured'] . ' of ' . $coverage['total'] . ' managed pages have title + description.');

        $loops = Redirects::issues();
        $checks[] = !$loops
            ? self::check('Redirect integrity', 'ok', 'No active local redirect loops detected.')
            : self::check('Redirect integrity', 'error', 'Redirect loop risk: ' . implode(', ', array_slice($loops, 0, 5)));

        $notFound = NotFoundMonitor::summary();
        if ($notFound['repeated'] > 0) {
            $checks[] = self::check(
                'Recent 404s',
                'warning',
                $notFound['repeated'] . ' genuine missing public URL(s) have been requested at least 5 times.'
            );
        } else {
            $checks[] = self::check(
                'Recent 404s',
                'ok',
                $notFound['total'] > 0
                    ? $notFound['total'] . ' genuine missing URL(s) are recorded; none have repeated 5+ times.'
                    : 'No genuine missing public URLs are currently recorded.'
            );
        }

        $checks[] = Env::bool('ANALYTICS_ENABLED', true)
            ? self::check('First-party analytics', 'ok', 'Analytics is enabled and remains database-only.')
            : self::check('First-party analytics', 'warning', 'Analytics collection is disabled.');

        try {
            $superAdmins = (int)Database::connection()->query(
                "SELECT COUNT(*)
                 FROM users u
                 JOIN roles r ON r.id=u.role_id
                 WHERE r.name='super_administrator'
                   AND u.status='active'"
            )->fetchColumn();

            $checks[] = $superAdmins >= 1
                ? self::check('Super Administrator', 'ok', $superAdmins . ' active Super Administrator account(s).')
                : self::check('Super Administrator', 'error', 'No active Super Administrator account exists.');
        } catch (\Throwable) {
            $checks[] = self::check('Super Administrator', 'error', 'Super Administrator state could not be verified.');
        }

        $theme = Settings::frontendTheme();
        $checks[] = $theme !== ''
            ? self::check('Frontend theme', 'ok', 'Active frontend theme: ' . $theme . '.')
            : self::check('Frontend theme', 'warning', 'No active frontend theme could be resolved.');

        $checks[] = Settings::blogEnabled()
            ? self::check('Blog availability', 'ok', 'Public blog is enabled.')
            : self::check('Blog availability', 'warning', 'Public blog is disabled; posts remain stored privately.');

        $checks[] = MailSettings::isReady()
            ? self::check('Email delivery', 'ok', 'SMTP is configured in CMS settings.')
            : self::check('Email delivery', 'warning', 'SMTP is not configured; automatic user invitation emails are unavailable.');

        $checks[] = class_exists(\DOMDocument::class)
            ? self::check('Rich-text sanitizer', 'ok', 'PHP DOM extension is available for rich-text sanitization.')
            : self::check('Rich-text sanitizer', 'warning', 'PHP DOM extension is unavailable; the fallback sanitizer will be used.');

        $checks[] = class_exists(\ZipArchive::class)
            ? self::check('Theme ZIP import', 'ok', 'PHP Zip extension is available.')
            : self::check('Theme ZIP import', 'warning', 'PHP Zip extension is unavailable; theme ZIP import is disabled.');

        $checks[] = function_exists('stream_socket_client') && function_exists('openssl_encrypt')
            ? self::check('Mail/TLS runtime', 'ok', 'Stream sockets and OpenSSL are available.')
            : self::check('Mail/TLS runtime', 'error', 'Stream sockets and OpenSSL are required for secure SMTP and encrypted secrets.');

        try {
            $superMfa = (int)Database::connection()->query(
                "SELECT COUNT(*) FROM users u
                 JOIN roles r ON r.id=u.role_id
                 WHERE r.name='super_administrator' AND u.status='active' AND u.mfa_enabled=1"
            )->fetchColumn();
            $checks[] = $superMfa >= 1
                ? self::check('Super Administrator 2FA', 'ok', $superMfa . ' active Super Administrator account(s) have 2FA enabled.')
                : self::check('Super Administrator 2FA', 'warning', 'Enable 2FA for at least one Super Administrator account.');
        } catch (\Throwable) {
            $checks[] = self::check('Super Administrator 2FA', 'warning', '2FA status could not be verified.');
        }

        $appUrl = (string)Env::get('APP_URL', '');
        $isLocal = str_contains($appUrl, 'localhost') || str_contains($appUrl, '127.0.0.1');
        $https = str_starts_with($appUrl, 'https://');
        $checks[] = ($https || $isLocal)
            ? self::check('Canonical site URL', 'ok', $appUrl !== '' ? $appUrl : 'APP_URL is configured.')
            : self::check('Canonical site URL', 'warning', 'Production APP_URL should use HTTPS.');

        $mode = Settings::siteMode();
        $handling = Settings::searchHandling();

        $modeDetail = $mode === 'live'
            ? 'The public site is live.'
            : (
                $handling === 'maintenance'
                    ? 'Maintenance mode is active: public requests receive HTTP 503 and noindex.'
                    : 'Pre-launch mode is active: the homepage is HTTP 200 and indexable.'
            );

        $checks[] = self::check(
            'Public site mode',
            $mode === 'live' ? 'ok' : 'warning',
            $modeDetail
        );

        if (
            $mode === 'development'
            && Settings::countdownEnabled()
            && Settings::plannedReturnDate() !== ''
        ) {
            $checks[] = Settings::plannedReturnIso() !== null
                ? self::check(
                    'Development countdown',
                    'ok',
                    'The planned return date and time are valid.'
                )
                : self::check(
                    'Development countdown',
                    'error',
                    'The planned return date or time is invalid.'
                );
        }

        $score = 100;
        foreach ($checks as $check) {
            $score -= $check['status'] === 'error' ? 18 : ($check['status'] === 'warning' ? 6 : 0);
        }
        $score = max(0, $score);

        return [
            'score' => $score,
            'checks' => $checks,
            'ok' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'ok')),
            'warnings' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'warning')),
            'errors' => count(array_filter($checks, static fn(array $c): bool => $c['status'] === 'error')),
        ];
    }

    private static function check(string $label, string $status, string $detail): array
    {
        return compact('label', 'status', 'detail');
    }

    private function __construct()
    {
    }
}
