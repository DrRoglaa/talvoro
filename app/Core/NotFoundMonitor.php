<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class NotFoundMonitor
{
    private const MAX_ROWS = 1000;
    private const RETENTION_DAYS = 90;

    public static function record(string $path, string $referrer = ''): void
    {
        $path = self::normalizePath($path);
        if ($path === '' || ScannerGuard::isLikelyScannerPath($path)) {
            return;
        }

        $referrerHost = self::referrerHost($referrer);

        try {
            $db = Database::connection();
            $stmt = $db->prepare(
                "INSERT INTO not_found_events
                 (path,hit_count,first_seen_at,last_seen_at,referrer_host)
                 VALUES (?,1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)
                 ON DUPLICATE KEY UPDATE
                    hit_count=hit_count+1,
                    last_seen_at=UTC_TIMESTAMP(),
                    referrer_host=CASE
                        WHEN VALUES(referrer_host) IS NOT NULL AND VALUES(referrer_host)<>''
                        THEN VALUES(referrer_host)
                        ELSE referrer_host
                    END"
            );
            $stmt->execute([$path, $referrerHost !== '' ? $referrerHost : null]);
            self::enforceRetention($db);
        } catch (\Throwable) {
            // A monitoring write must never break a public request.
        }
    }

    public static function page(int $page = 1, int $perPage = 20): array
    {
        $perPage = max(1, min(100, $perPage));
        $page = max(1, $page);

        try {
            $db = Database::connection();
            self::enforceRetention($db);
            $total = (int)$db->query('SELECT COUNT(*) FROM not_found_events')->fetchColumn();
            $pages = max(1, (int)ceil($total / $perPage));
            $page = min($page, $pages);
            $offset = ($page - 1) * $perPage;

            $rows = $db->query(
                'SELECT path,hit_count,first_seen_at,last_seen_at,referrer_host
                 FROM not_found_events
                 ORDER BY last_seen_at DESC
                 LIMIT ' . $perPage . ' OFFSET ' . $offset
            )->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['likely_scanner'] = ScannerGuard::isLikelyScannerPath((string)$row['path']);
            }
            unset($row);

            return [
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'per_page' => $perPage,
                'scanner_count' => count(array_filter($rows, static fn(array $row): bool => !empty($row['likely_scanner']))),
            ];
        } catch (\Throwable) {
            return ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => $perPage, 'scanner_count' => 0];
        }
    }

    public static function summary(): array
    {
        try {
            $rows = Database::connection()->query(
                'SELECT path,hit_count FROM not_found_events ORDER BY last_seen_at DESC LIMIT 1000'
            )->fetchAll(PDO::FETCH_ASSOC);

            $genuine = array_values(array_filter(
                $rows,
                static fn(array $row): bool => !ScannerGuard::isLikelyScannerPath((string)$row['path'])
            ));
            $repeated = count(array_filter(
                $genuine,
                static fn(array $row): bool => (int)$row['hit_count'] >= 5
            ));

            return ['total' => count($genuine), 'repeated' => $repeated];
        } catch (\Throwable) {
            return ['total' => 0, 'repeated' => 0];
        }
    }

    public static function dismiss(string $path): int
    {
        $stmt = Database::connection()->prepare('DELETE FROM not_found_events WHERE path=?');
        $stmt->execute([self::normalizePath($path)]);
        return $stmt->rowCount();
    }

    public static function dismissMany(array $paths): int
    {
        $unique = array_values(array_unique(array_filter(array_map(
            static fn(mixed $value): string => self::normalizePath((string)$value),
            $paths
        ))));
        $unique = array_slice($unique, 0, 200);
        if (!$unique) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $stmt = Database::connection()->prepare(
            'DELETE FROM not_found_events WHERE path IN (' . $placeholders . ')'
        );
        $stmt->execute($unique);
        return $stmt->rowCount();
    }

    public static function dismissScannerNoise(): int
    {
        $rows = Database::connection()->query(
            'SELECT path FROM not_found_events ORDER BY last_seen_at DESC LIMIT 1000'
        )->fetchAll(PDO::FETCH_COLUMN);
        $scanner = array_values(array_filter(
            array_map('strval', $rows),
            static fn(string $path): bool => ScannerGuard::isLikelyScannerPath($path)
        ));
        return self::dismissMany($scanner);
    }

    public static function dismissAll(): int
    {
        return (int)Database::connection()->exec('DELETE FROM not_found_events');
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : $path;
        $path = '/' . ltrim($path, '/');
        return mb_substr($path, 0, 300);
    }

    private static function referrerHost(string $referrer): string
    {
        $referrer = trim($referrer);
        if ($referrer === '') {
            return '';
        }
        $host = parse_url($referrer, PHP_URL_HOST);
        return is_string($host) ? mb_substr(mb_strtolower($host), 0, 255) : '';
    }

    private static function enforceRetention(PDO $db): void
    {
        $db->exec(
            'DELETE FROM not_found_events
             WHERE last_seen_at < (UTC_TIMESTAMP() - INTERVAL ' . self::RETENTION_DAYS . ' DAY)'
        );
        $count = (int)$db->query('SELECT COUNT(*) FROM not_found_events')->fetchColumn();
        if ($count > self::MAX_ROWS) {
            $remove = $count - self::MAX_ROWS;
            $db->exec(
                'DELETE FROM not_found_events
                 ORDER BY last_seen_at ASC
                 LIMIT ' . $remove
            );
        }
    }

    private function __construct()
    {
    }
}
