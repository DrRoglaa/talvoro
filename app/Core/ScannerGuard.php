<?php
declare(strict_types=1);

namespace CMS\Core;

final class ScannerGuard
{
    public static function isLikelyScannerPath(string $value): bool
    {
        $path = self::normalize($value);
        if ($path === '') {
            return false;
        }

        $patterns = [
            '#(?:^|/)[^/]+\.(?:php(?:[34578])?|phtml|phar)(?:/|$)#i',
            '#^/(?:wordpress/)?(?:wp-admin|wp-content|wp-includes|wp-json|wp-login(?:\.php)?|xmlrpc(?:\.php)?)(?:/|$)#i',
            '#^/wordpress(?:/|$)#i',
            '#^/(?:phpmyadmin(?:[-_.][a-z0-9.-]+)?|pma|myadmin)(?:/|$)#i',
            '#(?:^|/)\.env(?:[._-][^/]*)?(?:/|$)#i',
            '#(?:^|/)vendor/phpunit(?:/|$)#i',
            '#^/(?:administrator|adminer(?:\.php)?)(?:/|$)#i',
            '#(?:^|/)\.git(?:/|$)#i',
            '#(?:^|/)\.svn(?:/|$)#i',
            '#(?:^|/)\.hg(?:/|$)#i',
            '#^/(?:server-status|server-info)(?:/|$)#i',
            '#wlwmanifest\.xml$#i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private static function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $decoded = rawurldecode($value);
        $decoded = str_replace('\\', '/', $decoded);
        return strtolower($decoded);
    }

    private function __construct()
    {
    }
}
