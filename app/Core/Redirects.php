<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class Redirects
{
    private const CODES = [301, 302, 307, 308];

    public static function all(): array
    {
        try {
            return Database::connection()->query(
                'SELECT id,source_path,destination,status_code,is_active,hit_count,last_hit_at,created_at
                 FROM redirects
                 ORDER BY id DESC'
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function match(string $path): ?array
    {
        if (self::isProtectedPath($path)) {
            return null;
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT id,source_path,destination,status_code
                 FROM redirects
                 WHERE source_path=? AND is_active=1
                 LIMIT 1'
            );
            $stmt->execute([self::normalizeSource($path)]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return null;
            }

            Database::connection()->prepare(
                'UPDATE redirects
                 SET hit_count=hit_count+1,last_hit_at=UTC_TIMESTAMP()
                 WHERE id=?'
            )->execute([(int)$row['id']]);

            return $row;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    public static function validate(string $source, string $destination, int $code): array
    {
        $errors = [];
        $source = self::normalizeSource($source);
        $destination = trim($destination);

        if ($source === '' || $source[0] !== '/') {
            $errors[] = 'Source must be a local path beginning with /.';
        }

        if (strlen($source) > 191) {
            $errors[] = 'Source path is too long.';
        }

        if (self::isProtectedPath($source)) {
            $errors[] = 'CMS, login, health, asset, robots and sitemap paths cannot be redirected.';
        }

        if (str_contains($destination, "\r") || str_contains($destination, "\n")) {
            $errors[] = 'Destination contains invalid control characters.';
        } elseif ($destination === '') {
            $errors[] = 'Destination is required.';
        } elseif (
            !str_starts_with($destination, '/')
            && filter_var($destination, FILTER_VALIDATE_URL) === false
        ) {
            $errors[] = 'Destination must be a local path or a complete http/https URL.';
        } elseif (
            preg_match('#^https?://#i', $destination) !== 1
            && !str_starts_with($destination, '/')
        ) {
            $errors[] = 'External destinations must use http:// or https://.';
        }

        if ($source === $destination) {
            $errors[] = 'Source and destination cannot be identical.';
        }

        if (!in_array($code, self::CODES, true)) {
            $errors[] = 'Unsupported redirect status code.';
        }

        return $errors;
    }

    public static function create(
        string $source,
        string $destination,
        int $code,
        int $userId
    ): int {
        $source = self::normalizeSource($source);
        $stmt = Database::connection()->prepare(
            'INSERT INTO redirects
             (source_path,destination,status_code,is_active,hit_count,created_by,created_at,updated_at)
             VALUES (?,?,?,1,0,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([$source, trim($destination), $code, $userId]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function upsertPermanentLocal(string $source, string $destination, int $userId): void
    {
        $source = self::normalizeSource($source);
        $destination = self::normalizeSource($destination);
        $errors = self::validate($source, $destination, 301);
        if ($errors !== []) {
            throw new \RuntimeException($errors[0]);
        }
        Database::connection()->prepare(
            'INSERT INTO redirects
             (source_path,destination,status_code,is_active,hit_count,created_by,created_at,updated_at)
             VALUES (?,?,301,1,0,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE destination=VALUES(destination),status_code=301,is_active=1,created_by=VALUES(created_by),updated_at=UTC_TIMESTAMP()'
        )->execute([$source, $destination, $userId]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM redirects WHERE id=?')->execute([$id]);
    }

    public static function issues(): array
    {
        $rows = self::all();
        $map = [];
        foreach ($rows as $row) {
            if ((int)$row['is_active'] !== 1 || !str_starts_with((string)$row['destination'], '/')) {
                continue;
            }
            $map[(string)$row['source_path']] = self::normalizeSource((string)$row['destination']);
        }

        $loops = [];
        foreach (array_keys($map) as $start) {
            $seen = [];
            $current = $start;

            while (isset($map[$current])) {
                if (isset($seen[$current])) {
                    $loops[$start] = true;
                    break;
                }
                $seen[$current] = true;
                $current = $map[$current];
            }
        }

        return array_keys($loops);
    }

    public static function normalizeSource(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : $path;
        $path = '/' . ltrim($path, '/');

        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    private static function isProtectedPath(string $path): bool
    {
        $path = self::normalizeSource($path);
        return AdminPath::isProtectedPublicPath($path)
            || str_starts_with($path, '/assets')
            || str_starts_with($path, '/uploads')
            || str_starts_with($path, '/install')
            || $path === '/health'
            || $path === '/robots.txt'
            || $path === '/sitemap.xml'
            || $path === '/theme.css';
    }

    private function __construct()
    {
    }
}
