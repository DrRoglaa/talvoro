<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\BootstrapConfig;
use CMS\Core\Csrf;
use CMS\Core\InstallState;
use CMS\Core\Installer;
use CMS\Core\PasswordPolicy;
use CMS\Core\Response;
use CMS\Core\View;

final class InstallerController
{
    public static function index(): Response
    {
        if (InstallState::isInstalled()) {
            return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
        }

        $step = (string)($_GET['step'] ?? (InstallState::isPending() ? 'site' : 'requirements'));
        if (!in_array($step, ['requirements','database','site','complete'], true)) {
            $step = 'requirements';
        }

        if ($step === 'database' && !Installer::canContinue()) {
            $step = 'requirements';
        }
        if ($step === 'site' && !Installer::databaseStep() && !InstallState::isPending()) {
            $step = 'database';
        }

        return self::render($step, []);
    }

    public static function database(): Response
    {
        if (InstallState::isInstalled()) {
            return new Response('Installer locked', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (!Installer::canContinue()) {
            return self::render('requirements', ['error' => 'Server requirements must pass before database setup.'], 422);
        }

        try {
            $config = Installer::testDatabase($_POST);
            Installer::storeDatabaseStep($config);
            return Response::redirect('/install?step=site', 303);
        } catch (\Throwable $e) {
            return self::render('database', [
                'error' => $e->getMessage(),
                'database' => [
                    'db_host' => (string)($_POST['db_host'] ?? 'localhost'),
                    'db_port' => (string)($_POST['db_port'] ?? '3306'),
                    'db_database' => (string)($_POST['db_database'] ?? ''),
                    'db_username' => (string)($_POST['db_username'] ?? ''),
                ],
            ], 422);
        }
    }

    public static function complete(): Response
    {
        if (InstallState::isInstalled()) {
            return new Response('Installer locked', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
        if (!Csrf::valid($_POST['_csrf'] ?? null)) {
            return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        try {
            Installer::install($_POST);
            Csrf::rotate();
            return self::render('complete', ['installed' => true, 'loginUrl' => AdminPath::loginUrl()]);
        } catch (\Throwable $e) {
            return self::render('site', [
                'error' => $e->getMessage(),
                'defaults' => [
                    'site_name' => (string)($_POST['site_name'] ?? 'My Website'),
                    'app_url' => (string)($_POST['app_url'] ?? ''),
                    'timezone' => (string)($_POST['timezone'] ?? 'Europe/Ljubljana'),
                    'admin_name' => (string)($_POST['admin_name'] ?? ''),
                    'admin_email' => (string)($_POST['admin_email'] ?? ''),
                    'admin_path' => (string)($_POST['admin_path'] ?? ''),
                ],
            ], 422);
        }
    }

    private static function render(string $step, array $data, int $status = 200): Response
    {
        $defaults = Installer::defaults();
        $pending = BootstrapConfig::read(BootstrapConfig::pendingPath());
        if ($pending) {
            $defaults['site_name'] = (string)($pending['APP_NAME'] ?? $defaults['site_name']);
            $defaults['app_url'] = (string)($pending['APP_URL'] ?? $defaults['app_url']);
            $defaults['timezone'] = (string)($pending['APP_TIMEZONE'] ?? $defaults['timezone']);
        }

        return new Response(View::render('install/index', array_merge([
            'title' => 'Install Talvoro',
            'step' => $step,
            'preflight' => Installer::preflight(),
            'canContinue' => Installer::canContinue(),
            'database' => Installer::databaseDefaults(),
            'defaults' => $defaults,
            'passwordMinimum' => PasswordPolicy::MIN_LENGTH,
            'error' => null,
            'installed' => false,
        ], $data), 'layouts/install'), $status);
    }

    private function __construct() {}
}
