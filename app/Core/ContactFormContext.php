<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class ContactFormContext
{
    private const MAX_AGE_SECONDS = 21600;

    public static function issue(int $pageId, string $ownerId, string $blockId, ?int $issuedAt = null): string
    {
        if ($pageId < 1 || !self::validBlockId($ownerId) || !self::validBlockId($blockId)) {
            throw new RuntimeException('Contact form context is invalid.');
        }
        $payload = [
            'v' => 1,
            'page_id' => $pageId,
            'owner_id' => $ownerId,
            'block_id' => $blockId,
            'issued_at' => $issuedAt ?? time(),
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $encoded = self::base64UrlEncode($json);
        $signature = self::base64UrlEncode(hash_hmac('sha256', $encoded, self::key(), true));
        return 'v1.' . $encoded . '.' . $signature;
    }

    /** @return array{page_id:int,owner_id:string,block_id:string,issued_at:int}|null */
    public static function verify(string $token, ?int $now = null): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3 || $parts[0] !== 'v1') return null;
        [$version, $encoded, $signature] = $parts;
        unset($version);
        if ($encoded === '' || $signature === '') return null;

        try {
            $expected = self::base64UrlEncode(hash_hmac('sha256', $encoded, self::key(), true));
        } catch (\Throwable) {
            return null;
        }
        if (!hash_equals($expected, $signature)) return null;

        $json = self::base64UrlDecode($encoded);
        if ($json === null) return null;
        try {
            $payload = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (!is_array($payload) || (int)($payload['v'] ?? 0) !== 1) return null;

        $pageId = (int)($payload['page_id'] ?? 0);
        $ownerId = (string)($payload['owner_id'] ?? '');
        $blockId = (string)($payload['block_id'] ?? '');
        $issuedAt = (int)($payload['issued_at'] ?? 0);
        $now ??= time();

        if ($pageId < 1 || !self::validBlockId($ownerId) || !self::validBlockId($blockId) || $issuedAt < 1) return null;
        if ($issuedAt > $now + 60 || ($now - $issuedAt) > self::MAX_AGE_SECONDS) return null;

        return ['page_id' => $pageId, 'owner_id' => $ownerId, 'block_id' => $blockId, 'issued_at' => $issuedAt];
    }

    private static function validBlockId(string $value): bool
    {
        return preg_match('/^[a-z0-9]{8,32}$/D', $value) === 1;
    }

    private static function key(): string
    {
        $key = (string)Env::get('APP_KEY', '');
        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY is required for contact forms.');
        }
        return $key;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/D', $value) !== 1) return null;
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        return is_string($decoded) ? $decoded : null;
    }

    private function __construct() {}
}
