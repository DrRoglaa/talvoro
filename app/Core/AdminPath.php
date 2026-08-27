<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class AdminPath
{
    public const DEFAULT = 'admin';
    private const MIN_LENGTH = 3;
    private const MAX_LENGTH = 64;
    private const HISTORY_LIMIT = 12;

    /** Routes that must never be used as an administrator base path. */
    private const RESERVED = [
        'install','assets','uploads','blog','health','robots.txt','sitemap.xml','theme.css',
        'favicon.ico','favicon.png','favicon-16x16.png','favicon-32x32.png','apple-touch-icon.png',
        'login','verify','logout','account','api',
    ];

    public static function current(): string
    {
        $value = strtolower(trim((string)Settings::get('security.admin_path', self::DEFAULT)));
        return self::isSyntaxValid($value) ? $value : self::DEFAULT;
    }

    public static function normalize(string $value): string
    {
        return strtolower(trim($value, " \t\n\r\0\x0B/"));
    }

    /** @return list<string> */
    public static function validate(string $value, bool $checkDatabaseConflicts = true): array
    {
        $path = self::normalize($value);
        $errors = [];

        if (!self::isSyntaxValid($path)) {
            $errors[] = 'Admin path must be 3–64 characters and contain only lowercase letters, numbers, hyphens and underscores.';
        }
        if (in_array($path, self::RESERVED, true)) {
            $errors[] = 'That admin path is reserved by Talvoro.';
        }

        if ($errors === [] && $checkDatabaseConflicts && self::conflictsWithPublicContent($path)) {
            $errors[] = 'That admin path conflicts with an existing public page or redirect.';
        }

        return $errors;
    }

    public static function set(string $value, ?int $userId = null): string
    {
        $path = self::normalize($value);
        $errors = self::validate($path, true);
        if ($errors !== []) {
            throw new RuntimeException(implode(' ', $errors));
        }

        $old = self::current();
        $history = self::history();
        // Reusing a previous private path is allowed, but it stops being a retired path.
        $history = array_values(array_filter($history, static fn(string $item): bool => $item !== $path));
        if ($old !== $path && self::isSyntaxValid($old) && !in_array($old, $history, true)) {
            array_unshift($history, $old);
        }
        $history = array_slice(array_values(array_unique($history)), 0, self::HISTORY_LIMIT);

        Settings::set('security.admin_path_history', json_encode($history, JSON_UNESCAPED_SLASHES), $userId);
        Settings::set('security.admin_path', $path, $userId);
        return $path;
    }

    /** @return list<string> */
    public static function history(): array
    {
        $raw = (string)Settings::get('security.admin_path_history', '[]');
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) return [];
        $out = [];
        foreach ($decoded as $value) {
            if (!is_string($value)) continue;
            $value = self::normalize($value);
            if (!self::isSyntaxValid($value) || in_array($value, $out, true)) continue;
            $out[] = $value;
            if (count($out) >= self::HISTORY_LIMIT) break;
        }
        return $out;
    }

    public static function baseUrl(): string
    {
        return '/' . self::current();
    }

    public static function url(string $suffix = ''): string
    {
        $suffix = trim($suffix);
        if ($suffix === '' || $suffix === '/') {
            return self::baseUrl();
        }
        return self::baseUrl() . '/' . ltrim($suffix, '/');
    }

    public static function loginUrl(): string
    {
        return self::url('/login');
    }

    public static function verifyUrl(): string
    {
        return self::url('/verify');
    }

    public static function passwordUrl(): string
    {
        return self::url('/account/password');
    }

    public static function logoutUrl(): string
    {
        return self::url('/logout');
    }

    public static function absoluteUrl(string $suffix = ''): string
    {
        $origin = rtrim((string)Env::get('APP_URL', ''), '/');
        if ($origin === '') {
            $origin = Security::currentOrigin();
        }
        return $origin . self::url($suffix);
    }

    public static function isAdminRequest(string $path): bool
    {
        $base = self::baseUrl();
        return $path === $base || str_starts_with($path, $base . '/');
    }

    public static function isAuthRequest(string $path): bool
    {
        return in_array($path, [self::loginUrl(), self::verifyUrl(), self::passwordUrl(), self::logoutUrl()], true);
    }

    public static function isProtectedPublicPath(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        if (
            self::isAdminRequest($path)
            || str_starts_with($path, '/admin/')
            || $path === '/admin'
            || in_array($path, ['/login','/verify','/account/password','/logout'], true)
        ) {
            return true;
        }

        foreach (self::history() as $retired) {
            $base = '/' . $retired;
            if ($path === $base || str_starts_with($path, $base . '/')) return true;
        }
        return false;
    }

    public static function generate(): string
    {
        return 'manage-' . strtolower(bin2hex(random_bytes(8)));
    }

    private static function isSyntaxValid(string $path): bool
    {
        $length = strlen($path);
        return $length >= self::MIN_LENGTH
            && $length <= self::MAX_LENGTH
            && preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $path) === 1;
    }

    private static function conflictsWithPublicContent(string $path): bool
    {
        try {
            $prefix = '/' . $path;
            $db = Database::connection();

            $stmt = $db->prepare('SELECT COUNT(*) FROM pages WHERE path=? OR path LIKE ?');
            $stmt->execute([$prefix, $prefix . '/%']);
            if ((int)$stmt->fetchColumn() > 0) {
                return true;
            }

            $stmt = $db->prepare('SELECT COUNT(*) FROM redirects WHERE source_path=? OR source_path LIKE ?');
            $stmt->execute([$prefix, $prefix . '/%']);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function __construct() {}
}
