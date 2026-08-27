<?php
declare(strict_types=1);

namespace CMS\Core;

final class Csrf
{
    public static function token(): string
    {
        if (!isset($_SESSION['_csrf']) || !is_string($_SESSION['_csrf']) || strlen($_SESSION['_csrf']) !== 64) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function valid(?string $token): bool
    {
        return is_string($token)
            && strlen($token) === 64
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token)
            && Security::sameOriginRequest();
    }

    public static function rotate(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    private function __construct() {}
}
