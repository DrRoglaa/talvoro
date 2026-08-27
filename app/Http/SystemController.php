<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\InstallState;
use CMS\Core\Migrator;
use CMS\Core\RateLimiter;
use CMS\Core\ReleaseIntegrity;
use CMS\Core\Response;
use CMS\Core\UpdateManager;
use CMS\Core\View;

final class SystemController
{
    public static function index(): Response
    {
        if ($r = self::requireSuperAdmin()) return $r;
        $user = Auth::user();
        return new Response(View::render('admin/system/index', [
            'title' => 'System & updates',
            'pendingMigrations' => Migrator::pending(),
            'staged' => UpdateManager::staged(),
            'recovery' => UpdateManager::lockData(),
            'installerLocked' => InstallState::isInstalled(),
            'configFile' => is_file(base_path('storage/config.php')),
            'message' => (string)($_GET['message'] ?? ''),
            'currentUserEmail' => (string)($user['email'] ?? ''),
        ]));
    }

    public static function stage(): Response
    {
        if ($r = self::requireSuperAdmin()) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if ($r = self::reauth()) return $r;

        $integrity = ReleaseIntegrity::report();
        if (!$integrity['ok']) {
            return new Response(View::render('errors/message', [
                'title' => 'Current installation integrity warning',
                'message' => 'Core release files do not match the installed manifest. Resolve Site Health release-integrity warnings before applying a web update.',
            ]), 422);
        }

        try {
            $manifest = UpdateManager::stage($_FILES['update_zip'] ?? []);
            Audit::log('system.update.staged', 'system', null, ['to' => $manifest['version']]);
            return Response::redirect(AdminPath::baseUrl() . '/system?message=staged', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Update package rejected', 'message' => $e->getMessage()]), 422);
        }
    }

    public static function apply(): Response
    {
        if ($r = self::requireSuperAdmin()) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if ($r = self::reauth()) return $r;

        try {
            $user = Auth::user();
            $result = UpdateManager::apply((int)$user['id']);
            Audit::log('system.update.completed', 'system', null, ['from' => $result['from'], 'to' => $result['to']]);
            return Response::redirect(AdminPath::baseUrl() . '/system?message=updated', 303);
        } catch (\Throwable $e) {
            try { Audit::log('system.update.failed', 'system', null, ['error' => mb_substr($e->getMessage(), 0, 500)]); } catch (\Throwable) {}
            return new Response(View::render('errors/message', ['title' => 'Update failed', 'message' => $e->getMessage() . ' Recovery data was preserved.']), 500);
        }
    }

    public static function restore(): Response
    {
        if ($r = self::requireSuperAdmin()) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if ($r = self::reauth()) return $r;

        try {
            $result = UpdateManager::restoreRecovery();
            Audit::log('system.update.recovered', 'system', null, ['files' => $result['files'], 'database_statements' => $result['database_statements']]);
            return Response::redirect(AdminPath::baseUrl() . '/system?message=restored', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Recovery failed', 'message' => $e->getMessage()]), 500);
        }
    }

    private static function requireSuperAdmin(): ?Response
    {
        $user = Auth::user();
        if (!$user) return Response::redirect(AdminPath::loginUrl());
        if (($user['role_name'] ?? '') !== 'super_administrator' || !Gate::allows('system.manage')) {
            return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        }
        return null;
    }

    private static function reauth(): ?Response
    {
        $user = Auth::user();
        if (!$user) return Response::redirect(AdminPath::loginUrl());
        $email = (string)($user['email'] ?? '');
        if (RateLimiter::tooManySystemAttempts($email)) {
            return new Response(View::render('errors/message', ['title' => 'Too many verification attempts', 'message' => 'System security verification is temporarily locked. Try again in about 15 minutes.']), 429, ['Content-Type' => 'text/html; charset=UTF-8', 'Retry-After' => '900']);
        }
        if (!Auth::verifyCurrentPassword((string)($_POST['current_password'] ?? ''))) {
            RateLimiter::hitSystem($email);
            return new Response(View::render('errors/message', ['title' => 'Re-authentication failed', 'message' => 'Current password was not accepted.']), 422);
        }
        if ((int)($user['mfa_enabled'] ?? 0) !== 1) {
            RateLimiter::hitSystem($email);
            return new Response(View::render('errors/message', ['title' => '2FA required', 'message' => 'Enable two-factor authentication on your Super Administrator account before using web updates or recovery.']), 422);
        }
        if (!Auth::verifyCurrentSecondFactor((string)($_POST['mfa_code'] ?? ''))) {
            RateLimiter::hitSystem($email);
            return new Response(View::render('errors/message', ['title' => '2FA verification failed', 'message' => 'Enter a current authenticator code.']), 422);
        }
        RateLimiter::clearSystem($email);
        return null;
    }

    private function __construct() {}
}
