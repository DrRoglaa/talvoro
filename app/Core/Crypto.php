<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class Crypto
{
    public static function encrypt(string $plaintext, string $purpose): string
    {
        if ($plaintext === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('OpenSSL is required to protect secrets.');
        }

        $key = self::key($purpose);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($ciphertext === false || $tag === '') {
            throw new RuntimeException('Secret encryption failed.');
        }

        return implode('.', [
            self::b64url($iv),
            self::b64url($tag),
            self::b64url($ciphertext),
        ]);
    }

    public static function decrypt(?string $payload, string $purpose): string
    {
        $payload = trim((string)$payload);
        if ($payload === '') {
            return '';
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        $parts = explode('.', $payload);
        if (count($parts) !== 3) {
            return '';
        }

        [$ivText, $tagText, $cipherText] = $parts;
        $iv = self::b64urlDecode($ivText);
        $tag = self::b64urlDecode($tagText);
        $cipher = self::b64urlDecode($cipherText);

        if ($iv === false || $tag === false || $cipher === false) {
            return '';
        }

        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            self::key($purpose),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return is_string($plain) ? $plain : '';
    }

    private static function key(string $purpose): string
    {
        $appKey = (string)Env::get('APP_KEY', '');
        if (strlen($appKey) < 32) {
            throw new RuntimeException('APP_KEY must be configured before encrypted CMS secrets can be used.');
        }

        return hash('sha256', 'privacy-cms:' . $purpose . ':' . $appKey, true);
    }

    private static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $value): string|false
    {
        $padding = (4 - strlen($value) % 4) % 4;
        return base64_decode(strtr($value, '-_', '+/') . str_repeat('=', $padding), true);
    }

    private function __construct()
    {
    }
}
