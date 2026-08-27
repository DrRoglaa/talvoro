<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class BootstrapConfig
{
    public static function path(): string
    {
        return base_path('storage/config.php');
    }

    public static function pendingPath(): string
    {
        return base_path('storage/config.pending.php');
    }

    public static function read(?string $path = null): array
    {
        $path ??= self::path();
        if (!is_file($path)) {
            return [];
        }
        $data = require $path;
        return is_array($data) ? $data : [];
    }

    public static function write(array $values, bool $pending = false): void
    {
        $target = $pending ? self::pendingPath() : self::path();
        $directory = dirname($target);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Could not create secure storage directory.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Storage directory is not writable.');
        }

        $allowed = [
            'INSTALL_STATE','APP_NAME','APP_ENV','APP_DEBUG','APP_KEY','APP_URL','APP_TIMEZONE',
            'SESSION_LIFETIME','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','ANALYTICS_ENABLED',
        ];
        $clean = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $values)) {
                $value = (string)$values[$key];
                if (str_contains($value, "\0")) {
                    throw new RuntimeException('Configuration contains an invalid null byte.');
                }
                $clean[$key] = $value;
            }
        }

        $php = "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($clean, true) . ";\n";
        $tmp = $target . '.tmp.' . bin2hex(random_bytes(6));
        if (file_put_contents($tmp, $php, LOCK_EX) === false) {
            throw new RuntimeException('Could not write secure bootstrap configuration.');
        }
        @chmod($tmp, 0600);
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            throw new RuntimeException('Could not activate secure bootstrap configuration.');
        }
        @chmod($target, 0600);
    }

    public static function promotePending(): void
    {
        $pending = self::pendingPath();
        if (!is_file($pending)) {
            return;
        }
        $data = self::read($pending);
        $data['INSTALL_STATE'] = 'installed';
        self::write($data, false);
        @unlink($pending);
    }

    public static function discardPending(): void
    {
        @unlink(self::pendingPath());
    }

    private function __construct() {}
}
