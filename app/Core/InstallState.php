<?php
declare(strict_types=1);

namespace CMS\Core;

final class InstallState
{
    public static function lockPath(): string
    {
        return base_path('storage/installed.lock');
    }

    public static function isInstalled(): bool
    {
        if (is_file(self::lockPath())) {
            return true;
        }

        $config = BootstrapConfig::read();
        if (($config['INSTALL_STATE'] ?? '') === 'installed') {
            return true;
        }

        // Preserve existing Docker/development installations that predate the web installer.
        if (!$config && strlen((string)Env::get('APP_KEY', '')) >= 32 && (string)Env::get('DB_PASSWORD', '') !== '') {
            return true;
        }

        return false;
    }

    public static function isPending(): bool
    {
        $config = BootstrapConfig::read(BootstrapConfig::pendingPath());
        return ($config['INSTALL_STATE'] ?? '') === 'pending';
    }

    public static function lock(): void
    {
        $path = self::lockPath();
        $data = json_encode([
            'installed_at' => gmdate(DATE_ATOM),
            'version' => app_version(),
            'fingerprint' => bin2hex(random_bytes(16)),
        ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (file_put_contents($path, $data . "\n", LOCK_EX) === false) {
            throw new \RuntimeException('Could not write installer lock file.');
        }
        @chmod($path, 0600);
    }

    private function __construct() {}
}
