<?php
declare(strict_types=1);

use CMS\Core\Env;
use CMS\Core\Security;

require_once __DIR__ . '/../app/Core/helpers.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'CMS\\';
    if (!str_starts_with($class, $prefix)) return;
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/../app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) require_once $path;
});

// Runtime environment variables remain highest priority. Legacy .env is supported
// for Docker/development, while web-hosting installs use storage/config.php.
Env::load(__DIR__ . '/../.env');
Env::loadConfig(__DIR__ . '/../storage/config.php', true);
if (!is_file(__DIR__ . '/../storage/config.php')) {
    Env::loadConfig(__DIR__ . '/../storage/config.pending.php', true);
}

date_default_timezone_set((string)Env::get('APP_TIMEZONE', 'Europe/Ljubljana'));

if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_name('talvoro');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => Security::isHttps(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
