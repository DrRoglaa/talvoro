<?php
declare(strict_types=1);

namespace CMS\Core;

final class Security
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (string)($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function currentOrigin(): string
    {
        $scheme = self::isHttps() ? 'https' : 'http';
        $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        if ($host === '' || strlen($host) > 255 || !preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host)) {
            $host = 'localhost';
        }
        return $scheme . '://' . $host;
    }

    public static function sameOriginRequest(): bool
    {
        $configured = rtrim((string)Env::get('APP_URL', ''), '/');
        $expected = self::originFromUrl($configured) ?: self::currentOrigin();
        $allowed = [strtolower($expected)];
        if (strtolower((string)Env::get('APP_ENV', 'production')) !== 'production') {
            $allowed[] = strtolower(self::currentOrigin());
        }
        $allowed = array_values(array_unique($allowed));

        foreach (['HTTP_ORIGIN','HTTP_REFERER'] as $header) {
            $value = trim((string)($_SERVER[$header] ?? ''));
            if ($value === '') continue;
            $parts = parse_url($value);
            if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return false;
            $origin = strtolower($parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''));
            $matched = false;
            foreach ($allowed as $candidate) {
                if (hash_equals($candidate, $origin)) { $matched = true; break; }
            }
            if (!$matched) return false;
        }
        return true;
    }

    public static function validRequestPath(string $path): bool
    {
        if ($path === '' || strlen($path) > 2048 || str_contains($path, "\0")) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }
        return preg_match('//u', $path) === 1;
    }

    private static function originFromUrl(string $url): ?string
    {
        if ($url === '') return null;
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) return null;
        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, ['http','https'], true)) return null;
        return $scheme . '://' . strtolower((string)$parts['host']) . (isset($parts['port']) ? ':' . (int)$parts['port'] : '');
    }

    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(preg_replace('/:\d+$/', '', trim($host)) ?? '');
        return in_array($host, ['localhost','127.0.0.1','::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local');
    }

    public static function secureHeaders(): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
            'Content-Security-Policy' => "default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'",
        ];
        if (self::isHttps()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }
        return $headers;
    }

    private function __construct() {}
}
