<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Database;
use CMS\Core\Env;
use CMS\Core\Gate;
use CMS\Core\HomePage;
use CMS\Core\Pages;
use CMS\Core\RateLimiter;
use CMS\Core\Response;
use CMS\Core\SiteAssets;
use CMS\Core\View;
use PDO;

final class AdminSettingsController
{
    public static function security(): Response
    {
        if ($response = self::requireAuth('security.manage')) return $response;
        $user = Auth::user();
        $minutes = max(15, (int)(Env::get('SESSION_LIFETIME', '120') ?? '120'));

        return new Response(View::render('admin/security', [
            'title' => 'Security',
            'saved' => isset($_GET['saved']),
            'error' => null,
            'adminPath' => AdminPath::current(),
            'dashboardUrl' => AdminPath::absoluteUrl(),
            'loginUrl' => AdminPath::absoluteUrl('/login'),
            'mfaEnabled' => (int)($user['mfa_enabled'] ?? 0) === 1,
            'currentUserEmail' => (string)($user['email'] ?? ''),
            'sessionMinutes' => $minutes,
            'loginActivity' => self::loginActivity(),
        ]));
    }

    public static function updateSecurity(): Response
    {
        if ($response = self::requireAuth('security.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $user = Auth::user();
        $email = (string)($user['email'] ?? '');
        if (RateLimiter::tooManySystemAttempts($email)) {
            return self::securityError('Too many sensitive-setting attempts. Please try again later.', 429);
        }

        if (!Auth::verifyCurrentPassword((string)($_POST['current_password'] ?? ''))) {
            RateLimiter::hitSystem($email);
            Audit::log('security.admin_path.reauth_failed', 'security');
            return self::securityError('Current password was not accepted.', 422);
        }

        if ((int)($user['mfa_enabled'] ?? 0) === 1 && !Auth::verifyCurrentSecondFactor((string)($_POST['mfa_code'] ?? ''))) {
            RateLimiter::hitSystem($email);
            Audit::log('security.admin_path.mfa_failed', 'security');
            return self::securityError('Authenticator code was not accepted.', 422);
        }

        try {
            $old = AdminPath::current();
            $new = AdminPath::set((string)($_POST['admin_path'] ?? ''), (int)$user['id']);
            RateLimiter::clearSystem($email);
            Audit::log('security.admin_path.changed', 'security', null, ['from' => $old, 'to' => $new]);
            return Response::redirect(AdminPath::url('/security') . '?saved=1', 303);
        } catch (\Throwable $e) {
            RateLimiter::hitSystem($email);
            return self::securityError($e->getMessage(), 422);
        }
    }

    public static function homepage(): Response
    {
        if ($response = self::requireAuth('site.manage')) return $response;
        $id = Pages::frontPageId();
        if ($id < 1) $id = Pages::ensureHomePage((int)(Auth::user()['id'] ?? 0));
        return Response::redirect(AdminPath::baseUrl() . '/pages/' . $id . '/edit', 303);
    }

    public static function updateHomepage(): Response
    {
        if ($response = self::requireAuth('site.manage')) return $response;
        $id = Pages::frontPageId();
        if ($id < 1) $id = Pages::ensureHomePage((int)(Auth::user()['id'] ?? 0));
        return Response::redirect(AdminPath::baseUrl() . '/pages/' . $id . '/edit', 303);
    }

    private static function securityError(string $message, int $status): Response
    {
        $user = Auth::user();
        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        if ($status === 429) $headers['Retry-After'] = '900';
        return new Response(View::render('admin/security', [
            'title' => 'Security',
            'saved' => false,
            'error' => $message,
            'adminPath' => AdminPath::normalize((string)($_POST['admin_path'] ?? AdminPath::current())),
            'dashboardUrl' => AdminPath::absoluteUrl(),
            'loginUrl' => AdminPath::absoluteUrl('/login'),
            'mfaEnabled' => (int)($user['mfa_enabled'] ?? 0) === 1,
            'currentUserEmail' => (string)($user['email'] ?? ''),
            'sessionMinutes' => max(15, (int)(Env::get('SESSION_LIFETIME', '120') ?? '120')),
            'loginActivity' => self::loginActivity(),
        ]), $status, $headers);
    }

    /** @param array<string,string> $settings */
    private static function homepageError(string $message, array $settings, int $status): Response
    {
        $defaults = HomePage::current();
        foreach ($settings as $key => $value) $defaults[$key] = $value;
        return new Response(View::render('admin/homepage', [
            'title' => 'Homepage',
            'settings' => $defaults,
            'saved' => false,
            'error' => $message,
            'maxUploadMb' => SiteAssets::maxUploadMb(),
        ]), $status);
    }

    private static function loginActivity(): array
    {
        try {
            $stmt = Database::connection()->query(
                "SELECT a.action,a.created_at,u.display_name
                 FROM audit_log a
                 LEFT JOIN users u ON u.id=a.user_id
                 WHERE a.action IN ('auth.login','auth.login.mfa','auth.login.failed','auth.mfa.failed')
                 ORDER BY a.id DESC LIMIT 20"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $labels = [
                'auth.login' => 'Successful sign-in',
                'auth.login.mfa' => 'Successful sign-in with 2FA',
                'auth.login.failed' => 'Failed sign-in',
                'auth.mfa.failed' => 'Failed 2FA verification',
            ];
            foreach ($rows as &$row) $row['label'] = $labels[$row['action']] ?? $row['action'];
            unset($row);
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    private static function requireAuth(string $permission): ?Response
    {
        if (!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if (Auth::requiresPasswordChange()) return Response::redirect(AdminPath::passwordUrl());
        if (!Gate::allows($permission)) return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        return null;
    }

    private function __construct() {}
}
