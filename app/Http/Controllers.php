<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Analytics;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Categories;
use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\ContactSettings;
use CMS\Core\Csrf;
use CMS\Core\Crypto;
use CMS\Core\Database;
use CMS\Core\Env;
use CMS\Core\Gate;
use CMS\Core\HomePage;
use CMS\Core\MailSettings;
use CMS\Core\Mailer;
use CMS\Core\MediaLibrary;
use CMS\Core\NotFoundMonitor;
use CMS\Core\Posts;
use CMS\Core\Pages;
use CMS\Core\PageBlocks;
use CMS\Core\PasswordPolicy;
use CMS\Core\Redirects;
use CMS\Core\SEO;
use CMS\Core\Settings;
use CMS\Core\SiteHealth;
use CMS\Core\SiteAssets;
use CMS\Core\StarterSite;
use CMS\Core\StarterSiteRepository;
use CMS\Core\ThemeManager;
use CMS\Core\TwoFactor;
use CMS\Core\RateLimiter;
use CMS\Core\Response;
use CMS\Core\View;
use CMS\Core\UserManager;
use PDO;

final class Controllers
{
    public static function home(): Response
    {
        Pages::ensureHomePage();
        $frontPage = Pages::frontPage();
        return new Response(View::render('home', [
            'title' => HomePage::publicSiteName(),
            'page' => $frontPage,
        ]));
    }

    public static function loginForm(): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::requiresPasswordChange() ? AdminPath::passwordUrl() : AdminPath::baseUrl());
        }

        if (Auth::hasPendingMfa()) {
            return Response::redirect(AdminPath::verifyUrl());
        }

        return new Response(View::render('auth/login', ['title' => 'Sign in']));
    }

    public static function login(): Response
    {
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if (RateLimiter::tooManyLoginAttempts($email)) {
            return new Response(View::render('auth/login', [
                'title' => 'Sign in',
                'error' => 'Too many attempts. Please try again in about 15 minutes.',
            ]), 429, ['Content-Type' => 'text/html; charset=UTF-8', 'Retry-After' => '900']);
        }

        $result = Auth::attempt($email, $password);
        if ($result === 'invalid') {
            RateLimiter::hitLogin($email);
            Audit::log('auth.login.failed', 'auth', null, [
                'account_hash' => hash_hmac('sha256', mb_strtolower($email), (string)Env::get('APP_KEY', 'local-dev-key')),
            ]);
            return new Response(View::render('auth/login', [
                'title' => 'Sign in',
                'error' => 'Invalid email or password.',
            ]), 422);
        }

        RateLimiter::clearLogin($email);

        if ($result === 'mfa_required') {
            return Response::redirect(AdminPath::verifyUrl());
        }

        Audit::log('auth.login');
        return Response::redirect(Auth::requiresPasswordChange() ? AdminPath::passwordUrl() : AdminPath::baseUrl());
    }

    public static function mfaForm(): Response
    {
        if (Auth::check()) {
            return Response::redirect(Auth::requiresPasswordChange() ? AdminPath::passwordUrl() : AdminPath::baseUrl());
        }
        if (!Auth::hasPendingMfa()) {
            return Response::redirect(AdminPath::loginUrl());
        }

        return new Response(View::render('auth/mfa', [
            'title' => 'Two-factor verification',
            'email' => Auth::pendingMfaEmail(),
        ]));
    }

    public static function verifyMfa(): Response
    {
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $email = Auth::pendingMfaEmail() ?? '';
        if ($email === '' || !Auth::hasPendingMfa()) {
            return Response::redirect(AdminPath::loginUrl());
        }

        if (RateLimiter::tooManyMfaAttempts($email)) {
            return new Response(View::render('auth/mfa', [
                'title' => 'Two-factor verification',
                'email' => $email,
                'error' => 'Too many verification attempts. Please try again later.',
            ]), 429, ['Content-Type' => 'text/html; charset=UTF-8', 'Retry-After' => '900']);
        }

        if (!Auth::verifyPendingMfa((string)($_POST['code'] ?? ''))) {
            RateLimiter::hitMfa($email);
            Audit::log('auth.mfa.failed', 'auth');
            return new Response(View::render('auth/mfa', [
                'title' => 'Two-factor verification',
                'email' => $email,
                'error' => 'Authenticator or recovery code was not accepted.',
            ]), 422);
        }

        RateLimiter::clearMfa($email);
        Audit::log('auth.login.mfa');
        return Response::redirect(Auth::requiresPasswordChange() ? AdminPath::passwordUrl() : AdminPath::baseUrl());
    }

    public static function passwordChangeForm(): Response
    {
        if (!Auth::check()) {
            return Response::redirect(AdminPath::loginUrl());
        }
        if (!Auth::requiresPasswordChange()) {
            return Response::redirect(AdminPath::baseUrl());
        }

        return new Response(View::render('auth/change-password', ['title' => 'Choose a new password', 'email' => (string)(Auth::user()['email'] ?? '')]));
    }

    public static function changeTemporaryPassword(): Response
    {
        if (!Auth::check()) {
            return Response::redirect(AdminPath::loginUrl());
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $password = (string)($_POST['password'] ?? '');
        $confirm = (string)($_POST['password_confirm'] ?? '');
        $user = Auth::user();
        $passwordErrors = PasswordPolicy::validate($password, (string)($user['email'] ?? ''), (string)($user['display_name'] ?? ''));
        if ($password !== $confirm || $passwordErrors) {
            return new Response(View::render('auth/change-password', [
                'title' => 'Choose a new password',
                'email' => (string)($user['email'] ?? ''),
                'error' => $password !== $confirm ? 'Passwords do not match.' : implode(' ', $passwordErrors),
            ]), 422);
        }

        UserManager::setOwnPassword((int)$user['id'], $password);
        Audit::log('user.password.initial_changed', 'user', (int)$user['id']);
        return Response::redirect(AdminPath::baseUrl());
    }

    public static function logout(): Response
    {
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        Audit::log('auth.logout');
        Auth::logout();

        return Response::redirect(AdminPath::loginUrl());
    }

    private static function requireAuth(?string $permission = null): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect(AdminPath::loginUrl());
        }

        if (Auth::requiresPasswordChange()) {
            return Response::redirect(AdminPath::passwordUrl());
        }

        if ($permission !== null && !Gate::allows($permission)) {
            return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        }

        return null;
    }

    /** @param list<string> $permissions */
    private static function requireAnyPermission(array $permissions): ?Response
    {
        if ($response = self::requireAuth()) return $response;
        foreach ($permissions as $permission) {
            if (Gate::allows($permission)) return null;
        }
        return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
    }

    public static function dashboard(): Response
    {
        if ($response = self::requireAuth('dashboard.view')) {
            return $response;
        }

        $postCounts = Posts::counts();
        $stats = [
            'users' => (int)Database::connection()->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
            'events' => (int)Database::connection()->query("SELECT COUNT(*) FROM analytics_events WHERE occurred_at >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)")->fetchColumn(),
            'posts' => $postCounts['total'],
            'published' => $postCounts['published'],
        ];

        return new Response(View::render('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'postCounts' => $postCounts,
            'recentPosts' => Posts::recent(),
        ]));
    }

    public static function users(): Response
    {
        if ($response = self::requireAuth('users.manage')) {
            return $response;
        }

        $actor = Auth::user();
        return new Response(View::render('admin/users', [
            'title' => 'Users',
            'users' => UserManager::all(),
            'roles' => UserManager::availableRoles($actor),
            'actor' => $actor,
            'created' => isset($_GET['created']),
            'mailStatus' => (string)($_GET['mail'] ?? ''),
            'mailReady' => MailSettings::isReady(),
        ]));
    }

    public static function createUser(): Response
    {
        if ($response = self::requireAuth('users.manage')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
        $name = trim((string)($_POST['display_name'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $roleId = (int)($_POST['role_id'] ?? 0);

        $passwordErrors = PasswordPolicy::validate($password, $email, $name);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($name) < 2 || mb_strlen($name) > 120 || $passwordErrors || $roleId < 1) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not create user',
                'message' => !filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? 'Use a valid email address.'
                    : ($passwordErrors ? implode(' ', $passwordErrors) : 'Use a valid display name and allowed role.'),
            ]), 422);
        }

        try {
            $actor = Auth::user();
            $id = UserManager::create($actor, $email, $name, $password, $roleId);
            $created = UserManager::find($id);
            Audit::log('user.create', 'user', $id, ['email' => $email]);

            $mailStatus = 'not_configured';
            if ($created && MailSettings::isReady()) {
                try {
                    Mailer::sendWelcome($created, $password);
                    $mailStatus = 'sent';
                    Audit::log('user.welcome_email.sent', 'user', $id);
                } catch (\Throwable $mailError) {
                    $mailStatus = 'failed';
                    Audit::log('user.welcome_email.failed', 'user', $id, ['error' => mb_substr($mailError->getMessage(), 0, 240)]);
                }
            }
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not create user',
                'message' => $e->getMessage() === 'You cannot assign that role.' ? $e->getMessage() : 'That email may already exist.',
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/users?created=1&mail=' . rawurlencode($mailStatus));
    }

    public static function userSecurity(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        $userId = self::positiveId($id);
        if (!$userId) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        return self::renderUserSecurity($userId);
    }

    public static function updateUserSecurity(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        $actor = Auth::user();

        try {
            UserManager::updateSecurity(
                $actor,
                $target,
                (string)($_POST['display_name'] ?? $target['display_name']),
                (int)($_POST['role_id'] ?? 0),
                (string)($_POST['status'] ?? 'active')
            );
            Audit::log('user.security.update', 'user', $userId, [
                'display_name' => (string)($_POST['display_name'] ?? ''),
                'role_id' => (int)($_POST['role_id'] ?? 0),
                'status' => (string)($_POST['status'] ?? 'active'),
            ]);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not update user',
                'message' => $e->getMessage(),
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?saved=1');
    }

    public static function resetUserPassword(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }

        try {
            $actor = Auth::user();
            UserManager::resetPassword($actor, $target, (string)($_POST['password'] ?? ''));
            Audit::log('user.password.reset', 'user', $userId);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not reset password',
                'message' => $e->getMessage(),
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?password_reset=1');
    }

    public static function revokeUserSessions(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }

        $actor = Auth::user();
        if (!UserManager::canManage($actor, $target)) {
            return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        }

        Auth::revokeSessionsForUser($userId);
        Audit::log('user.sessions.revoke', 'user', $userId);

        return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?sessions_revoked=1');
    }

    public static function purgeUserAudit(string $id): Response
    {
        if ($response = self::requireAuth('audit.purge')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (($_POST['confirm_purge'] ?? '') !== '1') {
            return new Response(View::render('errors/message', [
                'title' => 'Purge not confirmed',
                'message' => 'Confirm deletion of this user audit history.',
            ]), 422);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }

        try {
            $actor = Auth::user();
            $deleted = UserManager::purgeAudit($actor, $target);
            Audit::log('user.audit.purged', 'user', $userId, ['deleted' => $deleted]);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not purge audit log',
                'message' => $e->getMessage(),
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?audit_purged=1');
    }

    public static function startUserMfa(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        try {
            UserManager::startMfa(Auth::user(), $target);
            Audit::log('mfa.enrollment.started', 'user', $userId);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not start 2FA', 'message' => $e->getMessage()]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?mfa_setup=1');
    }

    public static function enableUserMfa(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        try {
            $codes = UserManager::enableMfa(Auth::user(), $target, (string)($_POST['code'] ?? ''));
            self::stashMfaRecoveryCodes($userId, $codes);
            Audit::log('mfa.enabled', 'user', $userId);
            return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?mfa_enabled=1');
        } catch (\Throwable $e) {
            return self::renderUserSecurity($userId, ['mfaError' => $e->getMessage()]);
        }
    }

    public static function regenerateUserMfaRecovery(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        $actor = Auth::user();
        if ((int)$actor['id'] !== $userId) {
            return self::renderUserSecurity($userId, ['mfaError' => 'Each user must regenerate their own recovery codes.']);
        }
        if (!Auth::verifyCurrentPassword((string)($_POST['password'] ?? ''))) {
            return self::renderUserSecurity($userId, ['mfaError' => 'Current password was not accepted.']);
        }
        if (!Auth::verifyCurrentSecondFactor((string)($_POST['code'] ?? ''))) {
            return self::renderUserSecurity($userId, ['mfaError' => 'Authenticator code was not accepted.']);
        }

        try {
            $codes = UserManager::regenerateRecoveryCodes($actor, $target);
            self::stashMfaRecoveryCodes($userId, $codes);
            Audit::log('mfa.recovery.regenerated', 'user', $userId);
            return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?recovery_regenerated=1');
        } catch (\Throwable $e) {
            return self::renderUserSecurity($userId, ['mfaError' => $e->getMessage()]);
        }
    }

    public static function resetUserMfa(string $id): Response
    {
        if ($response = self::requireAuth('users.security')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }
        $actor = Auth::user();

        if ((int)$actor['id'] === $userId) {
            if (!Auth::verifyCurrentPassword((string)($_POST['password'] ?? ''))) {
                return self::renderUserSecurity($userId, ['mfaError' => 'Current password was not accepted.']);
            }
            $secret = Crypto::decrypt((string)($target['mfa_secret_encrypted'] ?? ''), 'mfa');
            $code = (string)($_POST['code'] ?? '');
            if ($secret === '' || !TwoFactor::verify($secret, $code)) {
                return self::renderUserSecurity($userId, ['mfaError' => 'Authenticator code was not accepted.']);
            }
        } elseif (($_POST['confirm_reset'] ?? '') !== '1') {
            return self::renderUserSecurity($userId, ['mfaError' => 'Confirm the administrator 2FA reset.']);
        }

        try {
            $isSelf = (int)$actor['id'] === $userId;
            UserManager::resetMfa($actor, $target);
            if ($isSelf) {
                // Keep this verified browser signed in, but revoke every older session
                // and rotate the current session identifier after the MFA state change.
                Auth::rotateCurrentSessionAfterSecurityChange($userId, false);
                Audit::log('mfa.disabled', 'user', $userId, ['self' => true]);
                return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?mfa_disabled=1');
            }

            Auth::revokeSessionsForUser($userId);
            Audit::log('mfa.reset', 'user', $userId, ['self' => false]);
            return Response::redirect(AdminPath::baseUrl() . '/users/' . $userId . '/security?mfa_reset=1');
        } catch (\Throwable $e) {
            return self::renderUserSecurity($userId, ['mfaError' => $e->getMessage()]);
        }
    }

    public static function deleteUser(string $id): Response
    {
        if ($response = self::requireAuth('users.delete')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (($_POST['confirm_delete'] ?? '') !== '1') {
            return new Response(View::render('errors/message', [
                'title' => 'Delete not confirmed',
                'message' => 'Confirm permanent deletion before deleting this user.',
            ]), 422);
        }

        $userId = self::positiveId($id);
        $target = $userId ? UserManager::find($userId) : null;
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }

        $actor = Auth::user();

        try {
            Audit::log('user.delete', 'user', $userId, [
                'email' => $target['email'],
                'role' => $target['role_name'],
            ]);
            UserManager::delete($actor, $target);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not delete user',
                'message' => $e->getMessage(),
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/users?deleted=1');
    }

    public static function pages(): Response
    {
        if ($response = self::requireAuth('pages.view')) {
            return $response;
        }

        $search = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $trashed = (string)($_GET['view'] ?? '') === 'trash';
        try { $maintenance = ContentLifecycle::maybePurgeExpired(); $expiredPurged = (int)$maintenance['page']; } catch (\Throwable) { $expiredPurged = 0; }

        return new Response(View::render('admin/pages/index', [
            'title' => 'Pages',
            'pages' => Pages::adminList($search, $status, $trashed),
            'search' => $search,
            'status' => $status,
            'trashed' => $trashed,
            'trashCount' => ContentLifecycle::trashCount('page'),
            'trashedNow' => isset($_GET['trashed']),
            'restored' => isset($_GET['restored']),
            'purged' => isset($_GET['purged']),
            'expiredPurged' => $expiredPurged,
            'trashRetentionDays' => ContentLifecycle::retentionDays(),
        ]));
    }

    public static function newPage(): Response
    {
        if ($response = self::requireAuth('pages.edit')) return $response;

        return new Response(View::render('admin/pages/form', [
            'title' => 'New page',
            'heading' => 'Create page',
            'action' => AdminPath::baseUrl() . '/pages/new',
            'page' => [
                'title' => '', 'path' => '', 'page_template' => 'standard', 'excerpt' => '', 'eyebrow' => '',
                'body' => '', 'body_html' => '', 'blocks_json' => '[]', 'blocks' => [], 'status' => 'draft',
                'show_in_navigation' => 0, 'navigation_label' => '', 'navigation_order' => 100,
                'show_in_footer' => 0, 'footer_label' => '', 'footer_order' => 100,
                'navigation_placement' => 'hidden',
            ],
            'seo' => ['meta_title' => '', 'meta_description' => ''],
            'errors' => [],
            'canPublish' => Gate::allows('pages.publish'),
            'canManageRedirects' => Gate::allows('redirects.manage'),
            'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
            'isEdit' => false,
            'isHome' => false,
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            'patterns' => \CMS\Core\PagePatterns::picker(),
            'maxUploadMb' => SiteAssets::maxUploadMb(),
        ]));
    }

    public static function createPage(): Response
    {
        if ($response = self::requireAuth('pages.edit')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validated = Pages::validate($_POST, null, Gate::allows('pages.publish'));
        if ($validated['errors']) {
            return new Response(View::render('admin/pages/form', [
                'title' => 'New page','heading' => 'Create page','action' => AdminPath::baseUrl() . '/pages/new',
                'page' => $validated['data'],
                'seo' => ['meta_title' => (string)($_POST['seo_title'] ?? ''), 'meta_description' => (string)($_POST['seo_description'] ?? '')],
                'errors' => $validated['errors'],'canPublish' => Gate::allows('pages.publish'),
                'canManageRedirects' => Gate::allows('redirects.manage'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','isEdit' => false,'isHome' => false,
                'home' => [], 'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'patterns' => \CMS\Core\PagePatterns::picker(),
            ]), 422);
        }

        $actor = Auth::user();
        $newAssets = [];
        try {
            $uploadResult = PageBlocks::applyUploads($validated['data']['blocks'], $_FILES, $_POST);
            $validated['data']['blocks'] = $uploadResult['blocks'];
            $newAssets = $uploadResult['new_assets'];
            $validated['data']['blocks_json'] = json_encode($uploadResult['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';

            $db = Database::connection();
            $db->beginTransaction();
            $id = Pages::create($validated['data'], (int)$actor['id']);
            SEO::save([
                'path' => $validated['data']['path'],
                'search_phrase' => '',
                'meta_title' => trim((string)($_POST['seo_title'] ?? '')),
                'meta_description' => trim((string)($_POST['seo_description'] ?? '')),
                'social_title' => '', 'social_description' => '', 'canonical_url' => '',
                'robots' => 'index,follow', 'sitemap_enabled' => '1',
            ], (int)$actor['id']);
            ContentHistory::capture('page', $id, (int)$actor['id'], 'create');
            $db->commit();
            Audit::log('page.create', 'page', $id, ['title' => $validated['data']['title'], 'path' => $validated['data']['path'], 'status' => $validated['data']['status']]);
            return Response::redirect(AdminPath::baseUrl() . '/pages/' . $id . '/edit?created=1');
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
            foreach ($newAssets as $path) SiteAssets::remove($path);
            return new Response(View::render('admin/pages/form', [
                'title' => 'New page','heading' => 'Create page','action' => AdminPath::baseUrl() . '/pages/new',
                'page' => $validated['data'],
                'seo' => ['meta_title' => (string)($_POST['seo_title'] ?? ''), 'meta_description' => (string)($_POST['seo_description'] ?? '')],
                'errors' => [$e->getMessage()], 'canPublish' => Gate::allows('pages.publish'),
                'canManageRedirects' => Gate::allows('redirects.manage'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','isEdit' => false,'isHome' => false,
                'home' => [], 'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'patterns' => \CMS\Core\PagePatterns::picker(),
            ]), 422);
        }
    }

    public static function editPage(string $id): Response
    {
        if ($response = self::requireAuth('pages.edit')) return $response;
        $pageId = self::positiveId($id);
        $page = $pageId ? Pages::find($pageId) : null;
        if (!$page) return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);

        $isHome = ($page['path'] ?? '') === '/' || ($page['page_template'] ?? '') === 'home';
        if (ContentHistory::count('page', $pageId) === 0) ContentHistory::capture('page', $pageId, null, 'baseline');
        $actor = Auth::user();
        $autosave = ContentHistory::latestAutosave('page', $pageId, (int)$actor['id'], (string)($page['updated_at'] ?? ''));
        return new Response(View::render('admin/pages/form', [
            'title' => $isHome ? 'Edit Home page' : 'Edit page',
            'heading' => $isHome ? 'Home page' : 'Edit page',
            'action' => AdminPath::baseUrl() . '/pages/' . $pageId . '/edit',
            'page' => $page,
            'seo' => SEO::editable((string)$page['path']),
            'errors' => [],
            'canPublish' => Gate::allows('pages.publish'),
            'canManageRedirects' => Gate::allows('redirects.manage'),
            'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
            'isEdit' => true,
            'isHome' => $isHome,
            'home' => $isHome ? HomePage::current() : [],
            'maxUploadMb' => SiteAssets::maxUploadMb(),
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            'patterns' => \CMS\Core\PagePatterns::picker(),
            'created' => isset($_GET['created']),
            'saved' => isset($_GET['saved']),
            'revisionCount' => ContentHistory::count('page', $pageId),
            'autosave' => $autosave,
            'autosaveUrl' => AdminPath::baseUrl() . '/pages/' . $pageId . '/autosave',
        ]));
    }

    public static function updatePage(string $id): Response
    {
        if ($response = self::requireAuth('pages.edit')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        $pageId = self::positiveId($id);
        $existing = $pageId ? Pages::find($pageId) : null;
        if (!$existing) return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);

        $isHome = ($existing['path'] ?? '') === '/' || ($existing['page_template'] ?? '') === 'home';
        $validated = Pages::validate($_POST, $pageId, Gate::allows('pages.publish'));
        $brandingValidated = $isHome ? HomePage::validateBranding($_POST) : ['data' => [], 'errors' => []];
        $errors = array_values(array_unique(array_merge($validated['errors'], $brandingValidated['errors'])));
        if ($errors) {
            return new Response(View::render('admin/pages/form', [
                'title' => $isHome ? 'Edit Home page' : 'Edit page',
                'heading' => $isHome ? 'Home page' : 'Edit page',
                'action' => AdminPath::baseUrl() . '/pages/' . $pageId . '/edit',
                'page' => array_merge($existing, $validated['data']),
                'seo' => ['meta_title' => (string)($_POST['seo_title'] ?? ''), 'meta_description' => (string)($_POST['seo_description'] ?? '')],
                'errors' => $errors, 'canPublish' => Gate::allows('pages.publish'),
                'canManageRedirects' => Gate::allows('redirects.manage'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
                'isEdit' => true, 'isHome' => $isHome,
                'home' => $isHome ? array_merge(HomePage::current(), $brandingValidated['data']) : [],
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'patterns' => \CMS\Core\PagePatterns::picker(),
                'revisionCount' => ContentHistory::count('page', $pageId),
                'autosave' => ContentHistory::latestAutosave('page', $pageId, (int)Auth::user()['id'], (string)($existing['updated_at'] ?? '')),
                'autosaveUrl' => AdminPath::baseUrl() . '/pages/' . $pageId . '/autosave',
            ]), 422);
        }

        $actor = Auth::user();
        $oldBlockAssets = PageBlocks::assetPaths($existing['blocks'] ?? []);
        $newAssets = [];
        $oldLogo = '';
        $newLogo = '';
        try {
            $uploadResult = PageBlocks::applyUploads($validated['data']['blocks'], $_FILES, $_POST);
            $validated['data']['blocks'] = $uploadResult['blocks'];
            $newAssets = $uploadResult['new_assets'];
            $validated['data']['blocks_json'] = json_encode($uploadResult['blocks'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';

            if ($isHome) {
                $branding = $brandingValidated['data'];
                $oldLogo = (string)($branding['branding.logo_path'] ?? '');
                $newLogo = $oldLogo;
                $logoFile = $_FILES['branding_logo'] ?? null;
                if (is_array($logoFile) && (int)($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $newLogo = SiteAssets::storeImage($logoFile, 'logo');
                    $newAssets[] = $newLogo;
                } elseif ((int)($_POST['branding_logo_media_id'] ?? 0) > 0) {
                    $newLogo = MediaLibrary::duplicateForUsage((int)$_POST['branding_logo_media_id'], 'logo');
                    $newAssets[] = $newLogo;
                } elseif (isset($_POST['remove_branding_logo'])) {
                    $newLogo = '';
                }
                $branding['branding.logo_path'] = $newLogo;
            }

            $db = Database::connection();
            $db->beginTransaction();
            Pages::update($pageId, $validated['data'], $existing['published_at']);
            $pathChanged = !$isHome && (string)($existing['path'] ?? '') !== (string)$validated['data']['path'];
            if ($pathChanged && Gate::allows('redirects.manage') && isset($_POST['create_path_redirect'])) {
                Redirects::upsertPermanentLocal((string)$existing['path'], (string)$validated['data']['path'], (int)$actor['id']);
            }
            if ($isHome) HomePage::save($branding, (int)$actor['id']);
            SEO::save([
                'path' => $validated['data']['path'], 'search_phrase' => '',
                'meta_title' => trim((string)($_POST['seo_title'] ?? '')),
                'meta_description' => trim((string)($_POST['seo_description'] ?? '')),
                'social_title' => '', 'social_description' => '', 'canonical_url' => '',
                'robots' => 'index,follow', 'sitemap_enabled' => '1',
            ], (int)$actor['id']);
            ContentHistory::capture('page', $pageId, (int)$actor['id'], $validated['data']['status'] === 'published' ? 'save' : 'draft_save');
            ContentHistory::clearAutosave('page', $pageId, (int)$actor['id']);
            $db->commit();

            // Previous page assets remain available to revision restore. Talvoro can
            // garbage-collect unreferenced revision assets in a later maintenance pass.
            Audit::log($isHome ? 'homepage.blocks.updated' : 'page.update', 'page', $pageId, [
                'title' => $validated['data']['title'], 'path' => $validated['data']['path'], 'status' => $validated['data']['status'],
            ]);
            return Response::redirect(AdminPath::baseUrl() . '/pages/' . $pageId . '/edit?saved=1');
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
            foreach ($newAssets as $path) SiteAssets::remove($path);
            return new Response(View::render('admin/pages/form', [
                'title' => $isHome ? 'Edit Home page' : 'Edit page',
                'heading' => $isHome ? 'Home page' : 'Edit page',
                'action' => AdminPath::baseUrl() . '/pages/' . $pageId . '/edit',
                'page' => array_merge($existing, $validated['data']),
                'seo' => ['meta_title' => (string)($_POST['seo_title'] ?? ''), 'meta_description' => (string)($_POST['seo_description'] ?? '')],
                'errors' => [$e->getMessage()], 'canPublish' => Gate::allows('pages.publish'),
                'canManageRedirects' => Gate::allows('redirects.manage'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
                'isEdit' => true, 'isHome' => $isHome,
                'home' => $isHome ? array_merge(HomePage::current(), $brandingValidated['data']) : [],
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'patterns' => \CMS\Core\PagePatterns::picker(),
                'revisionCount' => ContentHistory::count('page', $pageId),
                'autosave' => ContentHistory::latestAutosave('page', $pageId, (int)Auth::user()['id'], (string)($existing['updated_at'] ?? '')),
                'autosaveUrl' => AdminPath::baseUrl() . '/pages/' . $pageId . '/autosave',
            ]), 422);
        }
    }

    public static function deletePage(string $id): Response
    {
        if ($response = self::requireAuth('pages.edit')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if (($_POST['confirm_delete'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Move to Trash not confirmed', 'message' => 'Confirm before moving this page to Trash.']), 422);
        $pageId = self::positiveId($id);
        $page = $pageId ? Pages::find($pageId) : null;
        if (!$page) return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        try {
            $actor = Auth::user();
            ContentLifecycle::moveToTrash('page', $pageId, (int)$actor['id']);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not move page to Trash', 'message' => $e->getMessage()]), 422);
        }
        Audit::log('page.trash', 'page', $pageId, ['title' => $page['title'], 'path' => $page['path']]);
        return Response::redirect(AdminPath::baseUrl() . '/pages?trashed=1');
    }

    public static function publicPage(string $pagePath): Response
    {
        $path = '/' . ltrim($pagePath, '/');
        if (AdminPath::isProtectedPublicPath($path)) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }
        $page = Pages::findPublishedByPath($path);

        if (!$page) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }

        return new Response(View::render('page/show', [
            'title' => $page['title'],
            'page' => $page,
        ]));
    }

    public static function themes(): Response
    {
        if ($response = self::requireAuth('themes.manage')) {
            return $response;
        }
        $themes = ThemeManager::all();
        foreach ($themes as &$theme) {
            $theme['starterDefinition'] = null;
            $theme['starterState'] = null;
            try {
                $definition = StarterSiteRepository::definitionForTheme((int)$theme['id']);
                if ($definition) {
                    $theme['starterDefinition'] = ['name'=>(string)$definition['name'],'starter_version'=>(string)$definition['starter_version']];
                    $theme['starterState'] = StarterSite::status((int)$theme['id']);
                }
            } catch (\Throwable) {}
        }
        unset($theme);
        return new Response(View::render('admin/themes', [
            'title' => 'Frontend themes',
            'themes' => $themes,
            'importLimits' => ThemeManager::importLimits(),
            'created' => isset($_GET['created']),
            'imported' => isset($_GET['imported']),
            'activated' => isset($_GET['activated']),
            'deactivated' => isset($_GET['deactivated']),
            'deleted' => isset($_GET['deleted']),
        ]));
    }

    public static function createTheme(): Response
    {
        if ($response = self::requireAuth('themes.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        try {
            $actor = Auth::user();
            $id = ThemeManager::create($_POST, (int)$actor['id']);
            Audit::log('theme.create', 'theme', $id);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not create theme', 'message' => $e->getMessage()]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/themes?created=1');
    }

    public static function importTheme(): Response
    {
        if ($response = self::requireAuth('themes.import')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        try {
            $actor = Auth::user();
            $id = ThemeManager::importZip($_FILES['theme_zip'] ?? [], (int)$actor['id']);
            Audit::log('theme.import', 'theme', $id);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not import theme', 'message' => $e->getMessage()]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/themes?imported=1#theme-library');
    }

    public static function activateTheme(string $id): Response
    {
        if ($response = self::requireAuth('themes.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $themeId = self::positiveId($id);
        if (!$themeId) return new Response(View::render('errors/404', ['title' => 'Theme not found']), 404);
        try { ThemeManager::activate($themeId); Audit::log('theme.activate', 'theme', $themeId); }
        catch (\Throwable $e) { return new Response(View::render('errors/message', ['title' => 'Could not activate theme','message' => $e->getMessage()]), 422); }
        return Response::redirect(AdminPath::baseUrl() . '/themes?activated=1');
    }

    public static function deactivateTheme(string $id): Response
    {
        if ($response = self::requireAuth('themes.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $themeId = self::positiveId($id);
        if (!$themeId) return new Response(View::render('errors/404', ['title' => 'Theme not found']), 404);
        try { ThemeManager::deactivate($themeId); Audit::log('theme.deactivate', 'theme', $themeId); }
        catch (\Throwable $e) { return new Response(View::render('errors/message', ['title' => 'Could not deactivate theme','message' => $e->getMessage()]), 422); }
        return Response::redirect(AdminPath::baseUrl() . '/themes?deactivated=1');
    }

    public static function deleteTheme(string $id): Response
    {
        if ($response = self::requireAuth('themes.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $themeId = self::positiveId($id);
        if (!$themeId) return new Response(View::render('errors/404', ['title' => 'Theme not found']), 404);
        try { ThemeManager::delete($themeId); Audit::log('theme.delete', 'theme', $themeId); }
        catch (\Throwable $e) { return new Response(View::render('errors/message', ['title' => 'Could not delete theme','message' => $e->getMessage()]), 422); }
        return Response::redirect(AdminPath::baseUrl() . '/themes?deleted=1');
    }

    public static function themeCss(): Response
    {
        return new Response(ThemeManager::css() . "\n" . \CMS\Core\DesignSystem::css(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'no-cache, max-age=0',
        ]);
    }

    public static function mailSettings(): Response
    {
        if ($response = self::requireAnyPermission(['mail.manage', 'contact.manage'])) return $response;
        $canManageMail = Gate::allows('mail.manage');
        $canManageContact = Gate::allows('contact.manage');
        return new Response(View::render('admin/mail-settings', [
            'title' => 'Email & contact forms',
            'config' => $canManageMail ? MailSettings::config(false) : [],
            'contactConfig' => $canManageContact ? ContactSettings::config() : [],
            'contactRetentionOptions' => ContactSettings::retentionOptions(),
            'canManageMail' => $canManageMail,
            'canManageContact' => $canManageContact,
            'saved' => isset($_GET['saved']),
            'contactSaved' => isset($_GET['contact_saved']),
            'tested' => (string)($_GET['tested'] ?? ''),
            'errors' => [],
            'contactErrors' => [],
            'deliveries' => $canManageMail ? Mailer::recentLog(20) : [],
        ]));
    }

    public static function updateMailSettings(): Response
    {
        if ($response = self::requireAuth('mail.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $actor = Auth::user();
        try {
            $errors = MailSettings::save($_POST, (int)$actor['id']);
        } catch (\Throwable $e) {
            $errors = [$e->getMessage()];
        }
        if ($errors) {
            return new Response(View::render('admin/mail-settings', [
                'title' => 'Email & contact forms',
                'config' => array_merge(MailSettings::config(false), $_POST),
                'contactConfig' => Gate::allows('contact.manage') ? ContactSettings::config() : [],
                'contactRetentionOptions' => ContactSettings::retentionOptions(),
                'canManageMail' => true,
                'canManageContact' => Gate::allows('contact.manage'),
                'saved' => false,
                'contactSaved' => false,
                'tested' => '',
                'errors' => $errors,
                'contactErrors' => [],
                'deliveries' => Mailer::recentLog(20),
            ]), 422);
        }
        Audit::log('mail.settings.update');
        return Response::redirect(AdminPath::baseUrl() . '/mail?saved=1');
    }

    public static function testMail(): Response
    {
        if ($response = self::requireAuth('mail.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $recipient = trim((string)($_POST['test_recipient'] ?? ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return Response::redirect(AdminPath::baseUrl() . '/mail?tested=invalid');
        }
        try {
            Mailer::sendTest($recipient);
            Audit::log('mail.test.sent');
            return Response::redirect(AdminPath::baseUrl() . '/mail?tested=sent');
        } catch (\Throwable $e) {
            Audit::log('mail.test.failed', null, null, ['error' => mb_substr($e->getMessage(), 0, 240)]);
            return Response::redirect(AdminPath::baseUrl() . '/mail?tested=failed');
        }
    }

    public static function blogSettings(): Response
    {
        if ($response = self::requireAuth('blog.manage')) return $response;
        return new Response(View::render('admin/blog-settings', [
            'title' => 'Blog settings','enabled' => Settings::blogEnabled(),'archiveTitle' => Settings::blogArchiveTitle(),'archiveIntro' => Settings::blogArchiveIntro(),'saved' => isset($_GET['saved']),
        ]));
    }

    public static function updateBlogSettings(): Response
    {
        if ($response = self::requireAuth('blog.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $actor = Auth::user();
        $enabled = isset($_POST['blog_enabled']);
        Settings::set('blog.enabled', $enabled ? '1' : '0', (int)$actor['id']);
        Settings::set('blog.archive_title', mb_substr(trim((string)($_POST['blog_archive_title'] ?? '')), 0, 255), (int)$actor['id']);
        Settings::set('blog.archive_intro', mb_substr(trim((string)($_POST['blog_archive_intro'] ?? '')), 0, 500), (int)$actor['id']);
        Audit::log('blog.settings.update', 'site', null, ['enabled' => $enabled]);
        return Response::redirect(AdminPath::baseUrl() . '/blog-settings?saved=1');
    }

    public static function analytics(): Response
    {
        if ($response = self::requireAuth('analytics.view')) {
            return $response;
        }

        $days = (int)($_GET['days'] ?? 30);

        return new Response(View::render('admin/analytics', [
            'title' => 'Analytics',
            'data' => Analytics::overview($days),
        ]));
    }

    public static function siteMode(): Response
    {
        if ($response = self::requireAuth('site.manage')) {
            return $response;
        }

        return new Response(View::render('admin/site-mode', [
            'title' => 'Site mode',
            'mode' => Settings::siteMode(),
            'searchHandling' => Settings::searchHandling(),
            'headline' => Settings::developmentHeadline(),
            'message' => Settings::developmentMessage(),
            'returnDate' => Settings::plannedReturnDate(),
            'returnTime' => Settings::plannedReturnTime(),
            'countdownEnabled' => Settings::countdownEnabled(),
            'saved' => isset($_GET['saved']),
            'errors' => [],
        ]));
    }

    public static function updateSiteMode(): Response
    {
        if ($response = self::requireAuth('site.manage')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response(
                'Invalid CSRF token',
                419,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $mode = isset($_POST['development_enabled']) ? 'development' : 'live';
        $searchHandling = trim((string)($_POST['search_handling'] ?? 'prelaunch'));
        $headline = trim((string)($_POST['development_headline'] ?? ''));
        $message = trim((string)($_POST['development_message'] ?? ''));
        $returnDate = trim((string)($_POST['return_date'] ?? ''));
        $returnTime = trim((string)($_POST['return_time'] ?? ''));
        $countdownEnabled = isset($_POST['countdown_enabled']);

        $errors = [];

        if (!in_array($searchHandling, ['prelaunch', 'maintenance'], true)) {
            $errors[] = 'Choose a valid search-handling mode.';
        }

        if ($mode === 'development' && $headline === '') {
            $errors[] = 'Add a headline for the development page.';
        }

        if (mb_strlen($headline) > 180) {
            $errors[] = 'The development headline must be 180 characters or fewer.';
        }

        if (mb_strlen($message) > 1000) {
            $errors[] = 'The development message must be 1,000 characters or fewer.';
        }

        if ($returnDate !== '' || $returnTime !== '') {
            if ($returnDate === '') {
                $errors[] = 'Choose a planned return date if you set a return time.';
            } elseif ($returnTime === '') {
                $errors[] = 'Choose a planned return time if you set a return date.';
            } else {
                try {
                    $timezoneName = Env::get('APP_TIMEZONE', 'Europe/Ljubljana') ?: 'Europe/Ljubljana';
                    $timezone = new \DateTimeZone($timezoneName);
                    $input = $returnDate . ' ' . $returnTime;
                    $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input, $timezone);

                    if (!$dateTime || $dateTime->format('Y-m-d H:i') !== $input) {
                        $errors[] = 'Choose a valid planned return date and time.';
                    }
                } catch (\Throwable) {
                    $errors[] = 'The planned return date and time could not be validated.';
                }
            }
        }

        if ($errors) {
            return new Response(View::render('admin/site-mode', [
                'title' => 'Site mode',
                'mode' => $mode,
                'searchHandling' => $searchHandling,
                'headline' => $headline,
                'message' => $message,
                'returnDate' => $returnDate,
                'returnTime' => $returnTime,
                'countdownEnabled' => $countdownEnabled,
                'saved' => false,
                'errors' => $errors,
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets(300) : [],
            ]), 422);
        }

        $user = Auth::user();
        $userId = (int)$user['id'];

        Settings::set('site.mode', $mode, $userId);
        Settings::set('site.search_handling', $searchHandling, $userId);
        Settings::set('site.development_headline', $headline, $userId);
        Settings::set('site.development_message', $message, $userId);
        Settings::set('site.return_date', $returnDate, $userId);
        Settings::set('site.return_time', $returnTime, $userId);
        Settings::set('site.countdown_enabled', $countdownEnabled ? '1' : '0', $userId);

        Audit::log('site.mode.update', 'site', null, [
            'mode' => $mode,
            'search_handling' => $searchHandling,
            'return_date' => $returnDate !== '' ? $returnDate : null,
            'return_time' => $returnTime !== '' ? $returnTime : null,
            'countdown_enabled' => $countdownEnabled,
        ]);

        return Response::redirect(AdminPath::baseUrl() . '/site-mode?saved=1');
    }

    public static function seo(): Response
    {
        if ($response = self::requireAuth('seo.manage')) {
            return $response;
        }

        $inventory = SEO::inventory();
        $requested = trim((string)($_GET['path'] ?? ''));
        $selectedPath = $requested !== '' ? $requested : ($inventory[0]['path'] ?? '/');

        return new Response(View::render('admin/seo', [
            'title' => 'SEO',
            'inventory' => $inventory,
            'coverage' => SEO::coverage(),
            'selected' => SEO::editable($selectedPath),
            'saved' => isset($_GET['saved']),
            'errors' => [],
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets(300) : [],
        ]));
    }

    public static function saveSeo(): Response
    {
        if ($response = self::requireAuth('seo.manage')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (!Gate::allows('media.view')) {
            $existingSeo=SEO::get((string)($_POST['path']??'/'));
            $_POST['social_media_id']=(string)(int)($existingSeo['social_media_id']??0);
        }
        $errors = SEO::validate($_POST);
        if ($errors) {
            return new Response(View::render('admin/seo', [
                'title' => 'SEO',
                'inventory' => SEO::inventory(),
                'coverage' => SEO::coverage(),
                'selected' => array_merge(SEO::editable((string)($_POST['path'] ?? '/')), $_POST),
                'saved' => false,
                'errors' => $errors,
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets(300) : [],
            ]), 422);
        }

        $user = Auth::user();
        SEO::save($_POST, (int)$user['id']);
        Audit::log('seo.update', 'seo', null, ['path' => (string)$_POST['path']]);

        return Response::redirect(AdminPath::baseUrl() . '/seo?path=' . rawurlencode((string)$_POST['path']) . '&saved=1');
    }

    public static function redirects(): Response
    {
        if ($response = self::requireAuth('redirects.manage')) {
            return $response;
        }

        $prefillSource = trim((string)($_GET['source'] ?? ''));
        return new Response(View::render('admin/redirects', [
            'title' => 'Redirects',
            'redirects' => Redirects::all(),
            'created' => isset($_GET['created']),
            'deleted' => isset($_GET['deleted']),
            'old' => [
                'source_path' => $prefillSource,
                'destination' => '',
                'status_code' => 301,
            ],
        ]));
    }

    public static function createRedirect(): Response
    {
        if ($response = self::requireAuth('redirects.manage')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $source = (string)($_POST['source_path'] ?? '');
        $destination = trim((string)($_POST['destination'] ?? ''));
        $code = (int)($_POST['status_code'] ?? 301);
        $errors = Redirects::validate($source, $destination, $code);

        if ($errors) {
            return new Response(View::render('admin/redirects', [
                'title' => 'Redirects',
                'redirects' => Redirects::all(),
                'errors' => $errors,
                'old' => [
                    'source_path' => $source,
                    'destination' => $destination,
                    'status_code' => $code,
                ],
            ]), 422);
        }

        try {
            $user = Auth::user();
            $id = Redirects::create($source, $destination, $code, (int)$user['id']);
            NotFoundMonitor::dismiss(Redirects::normalizeSource($source));
            Audit::log('redirect.create', 'redirect', $id, [
                'source' => Redirects::normalizeSource($source),
                'destination' => $destination,
                'status_code' => $code,
            ]);
        } catch (\Throwable) {
            return new Response(View::render('admin/redirects', [
                'title' => 'Redirects',
                'redirects' => Redirects::all(),
                'errors' => ['That source path already has a redirect.'],
                'old' => [
                    'source_path' => $source,
                    'destination' => $destination,
                    'status_code' => $code,
                ],
            ]), 422);
        }

        return Response::redirect(AdminPath::baseUrl() . '/redirects?created=1');
    }

    public static function deleteRedirect(string $id): Response
    {
        if ($response = self::requireAuth('redirects.manage')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $redirectId = self::positiveId($id);
        if (!$redirectId) {
            return new Response(View::render('errors/404', ['title' => 'Redirect not found']), 404);
        }

        Redirects::delete($redirectId);
        Audit::log('redirect.delete', 'redirect', $redirectId);

        return Response::redirect(AdminPath::baseUrl() . '/redirects?deleted=1');
    }

    public static function siteHealth(): Response
    {
        if ($response = self::requireAuth('sitehealth.view')) {
            return $response;
        }

        $notFoundPage = max(1, (int)($_GET['nf_page'] ?? 1));

        return new Response(View::render('admin/site-health', [
            'title' => 'Site health',
            'report' => SiteHealth::report(),
            'notFound' => NotFoundMonitor::page($notFoundPage, 20),
            'canManageNotFound' => Gate::allows('sitehealth.manage'),
            'notFoundDismissed' => isset($_GET['nf_dismissed']),
        ]));
    }

    public static function dismissNotFound(): Response
    {
        if ($response = self::requireAuth('sitehealth.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $path = trim((string)($_POST['path'] ?? ''));
        if ($path !== '') {
            $deleted = NotFoundMonitor::dismiss($path);
            Audit::log('sitehealth.404.dismiss', 'site', null, ['path' => $path, 'deleted' => $deleted]);
        }
        $page = max(1, (int)($_POST['page'] ?? 1));
        return Response::redirect(AdminPath::baseUrl() . '/site-health?nf_page=' . $page . '&nf_dismissed=1');
    }

    public static function dismissSelectedNotFound(): Response
    {
        if ($response = self::requireAuth('sitehealth.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $paths = preg_split('/\\R+/', (string)($_POST['paths'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $deleted = NotFoundMonitor::dismissMany($paths);
        Audit::log('sitehealth.404.dismiss_selected', 'site', null, ['deleted' => $deleted]);
        $page = max(1, (int)($_POST['page'] ?? 1));
        return Response::redirect(AdminPath::baseUrl() . '/site-health?nf_page=' . $page . '&nf_dismissed=1');
    }

    public static function dismissScannerNotFound(): Response
    {
        if ($response = self::requireAuth('sitehealth.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $deleted = NotFoundMonitor::dismissScannerNoise();
        Audit::log('sitehealth.404.dismiss_scanner', 'site', null, ['deleted' => $deleted]);
        return Response::redirect(AdminPath::baseUrl() . '/site-health?nf_dismissed=1');
    }

    public static function dismissAllNotFound(): Response
    {
        if ($response = self::requireAuth('sitehealth.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if (($_POST['confirm_all'] ?? '') !== '1') {
            return new Response(View::render('errors/message', ['title' => 'Dismiss not confirmed', 'message' => 'Confirm clearing all recorded 404 monitor history.']), 422);
        }
        $deleted = NotFoundMonitor::dismissAll();
        Audit::log('sitehealth.404.dismiss_all', 'site', null, ['deleted' => $deleted]);
        return Response::redirect(AdminPath::baseUrl() . '/site-health?nf_dismissed=1');
    }

    public static function media(): Response
    {
        if ($response = self::requireAuth('media.view')) return $response;
        $search = trim((string)($_GET['q'] ?? ''));
        return new Response(View::render('admin/media/index', [
            'title' => 'Media',
            'assets' => MediaLibrary::all($search),
            'totalCount' => MediaLibrary::countAll(),
            'search' => $search,
            'canManage' => Gate::allows('media.manage'),
            'uploaded' => isset($_GET['uploaded']),
            'saved' => isset($_GET['saved']),
            'deleted' => isset($_GET['deleted']),
            'maxUploadMb' => SiteAssets::maxUploadMb(),
        ]));
    }

    public static function uploadMedia(): Response
    {
        if ($response = self::requireAuth('media.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        try {
            $user = Auth::user();
            $id = MediaLibrary::upload($_FILES['image'] ?? [], (string)($_POST['alt_text'] ?? ''), (int)$user['id']);
            Audit::log('media.create', 'media', $id);
        } catch (\Throwable $e) {
            return new Response(View::render('admin/media/index', [
                'title' => 'Media', 'assets' => MediaLibrary::all(), 'totalCount' => MediaLibrary::countAll(), 'search' => '', 'canManage' => true,
                'error' => $e->getMessage(), 'maxUploadMb' => SiteAssets::maxUploadMb(),
            ]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/media?uploaded=1');
    }

    public static function updateMedia(string $id): Response
    {
        if ($response = self::requireAuth('media.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $mediaId = self::positiveId($id);
        if (!$mediaId || !MediaLibrary::find($mediaId)) return new Response(View::render('errors/404', ['title' => 'Media not found']), 404);
        try {
            MediaLibrary::updateAlt($mediaId, (string)($_POST['alt_text'] ?? ''));
            Audit::log('media.update', 'media', $mediaId);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not update media', 'message' => $e->getMessage()]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/media?saved=1');
    }

    public static function deleteMedia(string $id): Response
    {
        if ($response = self::requireAuth('media.manage')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if (($_POST['confirm_delete'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Delete not confirmed', 'message' => 'Confirm permanent deletion before deleting this media item.']), 422);
        $mediaId = self::positiveId($id);
        if (!$mediaId || !MediaLibrary::find($mediaId)) return new Response(View::render('errors/404', ['title' => 'Media not found']), 404);
        try {
            MediaLibrary::delete($mediaId);
            Audit::log('media.delete', 'media', $mediaId);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Could not delete media',
                'message' => $e->getMessage(),
            ]), 422);
        }
        return Response::redirect(AdminPath::baseUrl() . '/media?deleted=1');
    }

    public static function posts(): Response
    {
        if ($response = self::requireAuth('content.view')) {
            return $response;
        }

        $search = trim((string)($_GET['q'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));
        $page = max(1, (int)($_GET['page'] ?? 1));
        $trashed = (string)($_GET['view'] ?? '') === 'trash';
        try { $maintenance = ContentLifecycle::maybePurgeExpired(); $expiredPurged = (int)$maintenance['post']; } catch (\Throwable) { $expiredPurged = 0; }

        return new Response(View::render('admin/posts/index', [
            'title' => 'Posts',
            'listing' => Posts::adminList($search, $status, $page, 10, $trashed),
            'search' => $search,
            'status' => $status,
            'trashed' => $trashed,
            'trashCount' => ContentLifecycle::trashCount('post'),
            'trashedNow' => isset($_GET['trashed']),
            'restored' => isset($_GET['restored']),
            'purged' => isset($_GET['purged']),
            'expiredPurged' => $expiredPurged,
            'trashRetentionDays' => ContentLifecycle::retentionDays(),
        ]));
    }

    public static function newPost(): Response
    {
        if ($response = self::requireAuth('content.edit')) {
            return $response;
        }

        return new Response(View::render('admin/posts/form', [
            'title' => 'New post',
            'heading' => 'Create post',
            'action' => AdminPath::baseUrl() . '/posts/new',
            'post' => [
                'title' => '',
                'slug' => '',
                'excerpt' => '',
                'featured_image_path' => '',
                'body' => '',
                'body_html' => '',
                'status' => 'draft',
                'published_at_local' => '',
            ],
            'errors' => [],
            'canPublish' => Gate::allows('content.publish'),
            'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
            'categories' => Categories::all(),
            'isEdit' => false,
            'maxUploadMb' => SiteAssets::maxUploadMb(),
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
        ]));
    }

    public static function createPost(): Response
    {
        if ($response = self::requireAuth('content.edit')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);

        $validated = Posts::validate($_POST, null, Gate::allows('content.publish'));
        $data = $validated['data'];
        if ($validated['errors']) {
            return new Response(View::render('admin/posts/form', [
                'title' => 'New post','heading' => 'Create post','action' => AdminPath::baseUrl() . '/posts/new',
                'post' => $data,'errors' => $validated['errors'],'canPublish' => Gate::allows('content.publish'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','categories' => Categories::all(),'isEdit' => false,
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            ]), 422);
        }

        $newImage = '';
        try {
            $file = $_FILES['featured_image'] ?? null;
            if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newImage = SiteAssets::storeImage($file, 'post');
                $data['featured_image_path'] = $newImage;
            } elseif ((int)($_POST['featured_media_id'] ?? 0) > 0) {
                $newImage = MediaLibrary::duplicateForUsage((int)$_POST['featured_media_id'], 'post');
                $data['featured_image_path'] = $newImage;
            }
            $user = Auth::user();
            $db = Database::connection();
            $db->beginTransaction();
            $id = Posts::create($data, (int)$user['id']);
            ContentHistory::capture('post', $id, (int)$user['id'], 'create');
            $db->commit();
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
            if ($newImage !== '') SiteAssets::remove($newImage);
            return new Response(View::render('admin/posts/form', [
                'title' => 'New post','heading' => 'Create post','action' => AdminPath::baseUrl() . '/posts/new',
                'post' => $data,'errors' => [$e->getMessage()],'canPublish' => Gate::allows('content.publish'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','categories' => Categories::all(),'isEdit' => false,
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            ]), 422);
        }

        Audit::log('post.create', 'post', $id, ['title' => $data['title'], 'slug' => $data['slug'], 'status' => $data['status']]);
        if ($data['status'] === 'published') Audit::log('post.publish', 'post', $id, ['mode' => 'manual']);
        elseif ($data['status'] === 'scheduled') Audit::log('post.schedule', 'post', $id, ['published_at' => $data['published_at']]);
        return Response::redirect(AdminPath::baseUrl() . '/posts/' . $id . '/edit?created=1');
    }

    public static function editPost(string $id): Response
    {
        if ($response = self::requireAuth('content.edit')) {
            return $response;
        }

        $postId = self::positiveId($id);
        $post = $postId ? Posts::find($postId) : null;
        if (!$post) {
            return new Response(View::render('errors/404', ['title' => 'Post not found']), 404);
        }

        $post['published_at_local'] = Posts::localDateTime($post['published_at']);
        $post['body_html'] = Posts::editorHtml($post);
        if (ContentHistory::count('post', $postId) === 0) ContentHistory::capture('post', $postId, null, 'baseline');
        $actor = Auth::user();
        $autosave = ContentHistory::latestAutosave('post', $postId, (int)$actor['id'], (string)($post['updated_at'] ?? ''));

        return new Response(View::render('admin/posts/form', [
            'title' => 'Edit post',
            'heading' => 'Edit post',
            'action' => AdminPath::baseUrl() . '/posts/' . $postId . '/edit',
            'post' => $post,
            'errors' => [],
            'canPublish' => Gate::allows('content.publish'),
            'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator',
            'categories' => Categories::all(),
            'isEdit' => true,
            'created' => isset($_GET['created']),
            'saved' => isset($_GET['saved']),
            'maxUploadMb' => SiteAssets::maxUploadMb(),
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            'revisionCount' => ContentHistory::count('post', $postId),
            'autosave' => $autosave,
            'autosaveUrl' => AdminPath::baseUrl() . '/posts/' . $postId . '/autosave',
        ]));
    }

    public static function updatePost(string $id): Response
    {
        if ($response = self::requireAuth('content.edit')) return $response;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);

        $postId = self::positiveId($id);
        $existing = $postId ? Posts::find($postId) : null;
        if (!$existing) return new Response(View::render('errors/404', ['title' => 'Post not found']), 404);

        $validated = Posts::validate($_POST, $postId, Gate::allows('content.publish'));
        $data = $validated['data'];
        $data['featured_image_path'] = (string)($existing['featured_image_path'] ?? '');
        if ($validated['errors']) {
            return new Response(View::render('admin/posts/form', [
                'title' => 'Edit post','heading' => 'Edit post','action' => AdminPath::baseUrl() . '/posts/' . $postId . '/edit',
                'post' => array_merge($existing, $data),'errors' => $validated['errors'],'canPublish' => Gate::allows('content.publish'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','categories' => Categories::all(),'isEdit' => true,
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'revisionCount' => ContentHistory::count('post', $postId),
                'autosave' => ContentHistory::latestAutosave('post', $postId, (int)Auth::user()['id'], (string)($existing['updated_at'] ?? '')),
                'autosaveUrl' => AdminPath::baseUrl() . '/posts/' . $postId . '/autosave',
            ]), 422);
        }

        $oldImage = (string)($existing['featured_image_path'] ?? '');
        $newImage = '';
        try {
            $file = $_FILES['featured_image'] ?? null;
            if (is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $newImage = SiteAssets::storeImage($file, 'post');
                $data['featured_image_path'] = $newImage;
            } elseif ((int)($_POST['featured_media_id'] ?? 0) > 0) {
                $newImage = MediaLibrary::duplicateForUsage((int)$_POST['featured_media_id'], 'post');
                $data['featured_image_path'] = $newImage;
            } elseif (isset($_POST['remove_featured_image'])) {
                $data['featured_image_path'] = '';
            }
            $actor = Auth::user();
            $db = Database::connection();
            $db->beginTransaction();
            Posts::update($postId, $data);
            ContentHistory::capture('post', $postId, (int)$actor['id'], $data['status'] === 'published' ? 'save' : ($data['status'] === 'scheduled' ? 'schedule' : 'draft_save'));
            ContentHistory::clearAutosave('post', $postId, (int)$actor['id']);
            $db->commit();
            // Keep previous featured-image copies available for revision restore.
        } catch (\Throwable $e) {
            if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
            if ($newImage !== '') SiteAssets::remove($newImage);
            return new Response(View::render('admin/posts/form', [
                'title' => 'Edit post','heading' => 'Edit post','action' => AdminPath::baseUrl() . '/posts/' . $postId . '/edit',
                'post' => array_merge($existing, $data),'errors' => [$e->getMessage()],'canPublish' => Gate::allows('content.publish'),
                'canHtml' => (Auth::user()['role_name'] ?? '') === 'super_administrator','categories' => Categories::all(),'isEdit' => true,
                'maxUploadMb' => SiteAssets::maxUploadMb(),
                'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
                'revisionCount' => ContentHistory::count('post', $postId),
                'autosave' => ContentHistory::latestAutosave('post', $postId, (int)Auth::user()['id'], (string)($existing['updated_at'] ?? '')),
                'autosaveUrl' => AdminPath::baseUrl() . '/posts/' . $postId . '/autosave',
            ]), 422);
        }

        Audit::log('post.update', 'post', $postId, ['title' => $data['title'], 'slug' => $data['slug'], 'from_status' => $existing['status'], 'to_status' => $data['status']]);
        if ($existing['status'] !== 'published' && $data['status'] === 'published') Audit::log('post.publish', 'post', $postId, ['mode' => 'manual']);
        elseif ($data['status'] === 'scheduled' && ($existing['status'] !== 'scheduled' || $existing['published_at'] !== $data['published_at'])) Audit::log('post.schedule', 'post', $postId, ['published_at' => $data['published_at']]);
        return Response::redirect(AdminPath::baseUrl() . '/posts/' . $postId . '/edit?saved=1');
    }

    public static function deletePost(string $id): Response
    {
        if ($response = self::requireAuth('content.edit')) {
            return $response;
        }

        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (($_POST['confirm_delete'] ?? '') !== '1') {
            return new Response(
                View::render('errors/message', [
                    'title' => 'Move to Trash not confirmed',
                    'message' => 'Confirm before moving this post to Trash.',
                ]),
                422
            );
        }

        $postId = self::positiveId($id);
        $post = $postId ? Posts::find($postId) : null;
        if (!$post) {
            return new Response(View::render('errors/404', ['title' => 'Post not found']), 404);
        }

        try {
            $actor = Auth::user();
            ContentLifecycle::moveToTrash('post', $postId, (int)$actor['id']);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not move post to Trash', 'message' => $e->getMessage()]), 422);
        }
        Audit::log('post.trash', 'post', $postId, [
            'title' => $post['title'],
            'slug' => $post['slug'],
            'status' => $post['status'],
        ]);

        return Response::redirect(AdminPath::baseUrl() . '/posts?trashed=1');
    }

    public static function blogCategories(): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        return new Response(View::render('admin/categories/index', [
            'title' => 'Blog categories',
            'categories' => Categories::adminList(),
            'created' => isset($_GET['created']),
            'updated' => isset($_GET['updated']),
            'deleted' => isset($_GET['deleted']),
        ]));
    }

    public static function newBlogCategory(): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        return new Response(View::render('admin/categories/form', [
            'title' => 'New blog category',
            'heading' => 'Create category',
            'action' => AdminPath::baseUrl() . '/blog-categories/new',
            'category' => [
                'name' => '',
                'slug' => '',
                'description' => '',
                'seo_title' => '',
                'meta_description' => '',
                'status' => 'active',
                'sort_order' => 100,
                'is_default' => 0,
            ],
            'errors' => [],
            'isEdit' => false,
        ]));
    }

    public static function createBlogCategory(): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $validated = Categories::validate($_POST);
        if ($validated['errors']) {
            return new Response(View::render('admin/categories/form', [
                'title' => 'New blog category',
                'heading' => 'Create category',
                'action' => AdminPath::baseUrl() . '/blog-categories/new',
                'category' => array_merge($_POST, $validated['data'], ['is_default' => 0]),
                'errors' => $validated['errors'],
                'isEdit' => false,
            ]), 422);
        }

        $id = Categories::create($validated['data']);
        Audit::log('blog.category.create', 'blog_category', $id, [
            'name' => $validated['data']['name'],
            'slug' => $validated['data']['slug'],
        ]);
        return Response::redirect(AdminPath::baseUrl() . '/blog-categories?created=1');
    }

    public static function editBlogCategory(string $id): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        $categoryId = self::positiveId($id);
        $category = $categoryId ? Categories::find($categoryId) : null;
        if (!$category) {
            return new Response(View::render('errors/404', ['title' => 'Category not found']), 404);
        }
        return new Response(View::render('admin/categories/form', [
            'title' => 'Edit blog category',
            'heading' => 'Edit category',
            'action' => AdminPath::baseUrl() . '/blog-categories/' . $categoryId . '/edit',
            'category' => $category,
            'errors' => [],
            'isEdit' => true,
            'saved' => isset($_GET['saved']),
        ]));
    }

    public static function updateBlogCategory(string $id): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $categoryId = self::positiveId($id);
        $existing = $categoryId ? Categories::find($categoryId) : null;
        if (!$existing) {
            return new Response(View::render('errors/404', ['title' => 'Category not found']), 404);
        }

        $validated = Categories::validate($_POST, $categoryId);
        if ($validated['errors']) {
            return new Response(View::render('admin/categories/form', [
                'title' => 'Edit blog category',
                'heading' => 'Edit category',
                'action' => AdminPath::baseUrl() . '/blog-categories/' . $categoryId . '/edit',
                'category' => array_merge($existing, $validated['data']),
                'errors' => $validated['errors'],
                'isEdit' => true,
            ]), 422);
        }

        Categories::update($categoryId, $validated['data']);
        Audit::log('blog.category.update', 'blog_category', $categoryId, [
            'name' => $validated['data']['name'],
            'slug' => $validated['data']['slug'],
            'status' => $validated['data']['status'],
        ]);
        return Response::redirect(AdminPath::baseUrl() . '/blog-categories/' . $categoryId . '/edit?saved=1');
    }

    public static function deleteBlogCategory(string $id): Response
    {
        if ($response = self::requireAuth('blog.manage')) {
            return $response;
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (($_POST['confirm_delete'] ?? '') !== '1') {
            return new Response(View::render('errors/message', [
                'title' => 'Delete not confirmed',
                'message' => 'Confirm permanent deletion before deleting this category.',
            ]), 422);
        }

        $categoryId = self::positiveId($id);
        $category = $categoryId ? Categories::find($categoryId) : null;
        if (!$category) {
            return new Response(View::render('errors/404', ['title' => 'Category not found']), 404);
        }
        try {
            Categories::delete($categoryId);
        } catch (\RuntimeException $e) {
            return new Response(View::render('errors/message', [
                'title' => 'Category cannot be deleted',
                'message' => $e->getMessage(),
            ]), 422);
        }
        Audit::log('blog.category.delete', 'blog_category', $categoryId, [
            'name' => $category['name'],
            'slug' => $category['slug'],
        ]);
        return Response::redirect(AdminPath::baseUrl() . '/blog-categories?deleted=1');
    }

    public static function blog(): Response
    {
        if (!Settings::blogEnabled()) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        return new Response(View::render('blog/index', [
            'title' => 'Blog',
            'listing' => Posts::publicList($page),
            'category' => null,
            'archiveCategories' => Categories::publicArchiveCategories(),
        ]));
    }

    public static function blogCategory(string $slug): Response
    {
        if (!Settings::blogEnabled()) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }
        $category = Categories::findPublicBySlug($slug);
        if (!$category) {
            return new Response(View::render('errors/404', ['title' => 'Category not found']), 404);
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        return new Response(View::render('blog/index', [
            'title' => (string)$category['name'],
            'listing' => Posts::publicListByCategory((int)$category['id'], $page),
            'category' => $category,
            'archiveCategories' => Categories::publicArchiveCategories(),
        ]));
    }

    public static function blogPost(string $slug): Response
    {
        if (!Settings::blogEnabled()) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }
        $post = Posts::findPublishedBySlug($slug);
        if (!$post) {
            return new Response(View::render('errors/404', ['title' => 'Post not found']), 404);
        }
        return new Response(View::render('blog/show', ['title' => $post['title'],'post' => $post]));
    }

    public static function robots(): Response
    {
        return new Response(
            SEO::robots(),
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8']
        );
    }

    public static function sitemap(): Response
    {
        return new Response(
            SEO::sitemap(),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }

    public static function health(): Response
    {
        try {
            Database::connection()->query('SELECT 1');

            return new Response(
                json_encode([
                    'status' => 'ok',
                    'database' => 'ok',
                ], JSON_UNESCAPED_SLASHES),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        } catch (\Throwable) {
            return new Response(
                json_encode([
                    'status' => 'error',
                    'database' => 'unavailable',
                ], JSON_UNESCAPED_SLASHES),
                503,
                ['Content-Type' => 'application/json; charset=UTF-8']
            );
        }
    }

    private static function renderUserSecurity(int $userId, array $extra = []): Response
    {
        $target = UserManager::find($userId);
        if (!$target) {
            return new Response(View::render('errors/404', ['title' => 'User not found']), 404);
        }

        $actor = Auth::user();
        if (!UserManager::canManage($actor, $target)) {
            return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        }

        $auditPage = UserManager::auditPage($userId, max(1, (int)($_GET['audit_page'] ?? 1)), 10);
        $pendingSecret = UserManager::pendingMfaSecret($target);
        $setupUri = $pendingSecret !== '' ? TwoFactor::setupUri($pendingSecret, (string)$target['email']) : '';

        return new Response(View::render('admin/user-security', array_merge([
            'title' => 'User security','target' => $target,'actor' => $actor,
            'roles' => UserManager::availableRoles($actor),
            'sessions' => Auth::sessionsForUser($userId),'auditPage' => $auditPage,
            'canDelete' => UserManager::canDelete($actor, $target),
            'canPurgeAudit' => (string)($actor['role_name'] ?? '') === 'super_administrator',
            'pendingMfaSecret' => $pendingSecret,'mfaSetupUri' => $setupUri,
            'saved' => isset($_GET['saved']),'passwordReset' => isset($_GET['password_reset']),
            'sessionsRevoked' => isset($_GET['sessions_revoked']),'auditPurged' => isset($_GET['audit_purged']),
            'mfaEnabledNow' => isset($_GET['mfa_enabled']),'mfaDisabled' => isset($_GET['mfa_disabled']),
            'mfaReset' => isset($_GET['mfa_reset']),'recoveryRegenerated' => isset($_GET['recovery_regenerated']),
            'mfaError' => null,'recoveryCodes' => self::pullMfaRecoveryCodes($userId),
        ], $extra)));
    }

    private static function stashMfaRecoveryCodes(int $userId, array $codes): void
    {
        $_SESSION['_mfa_recovery_once'] = [
            'user_id' => $userId,
            'created_at' => time(),
            'codes' => array_values(array_filter($codes, 'is_string')),
        ];
    }

    private static function pullMfaRecoveryCodes(int $userId): array
    {
        $flash = $_SESSION['_mfa_recovery_once'] ?? null;
        if (!is_array($flash)) {
            return [];
        }

        $createdAt = (int)($flash['created_at'] ?? 0);
        if ($createdAt <= 0 || (time() - $createdAt) > 300) {
            unset($_SESSION['_mfa_recovery_once']);
            return [];
        }
        if ((int)($flash['user_id'] ?? 0) !== $userId) {
            return [];
        }

        unset($_SESSION['_mfa_recovery_once']);
        $codes = $flash['codes'] ?? [];
        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }

    private static function positiveId(string $value): ?int
    {
        if ($value === '' || !ctype_digit($value)) {
            return null;
        }

        $id = (int)$value;
        return $id > 0 ? $id : null;
    }
}
