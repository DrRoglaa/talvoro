<?php
declare(strict_types=1);

namespace CMS\Core;

final class TwoFactor
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes(max(20, $bytes)));
    }

    public static function verify(string $secret, string $candidate, int $window = 1): bool
    {
        $candidate = preg_replace('/\s+/', '', $candidate) ?? '';
        if (!preg_match('/^\d{6}$/', $candidate)) {
            return false;
        }

        $counter = intdiv(time(), 30);
        for ($drift = -abs($window); $drift <= abs($window); $drift++) {
            $expected = self::hotp($secret, $counter + $drift);
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public static function setupUri(string $secret, string $account): string
    {
        $issuer = (string)'Talvoro';
        $label = $issuer . ':' . $account;

        return 'otpauth://totp/' . rawurlencode($label)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(8)));
            $codes[] = substr($raw, 0, 4) . '-'
                . substr($raw, 4, 4) . '-'
                . substr($raw, 8, 4) . '-'
                . substr($raw, 12, 4);
        }
        return $codes;
    }

    public static function recoveryHash(string $code): string
    {
        $normalized = strtoupper(preg_replace('/[^A-F0-9]/', '', $code) ?? '');
        $key = (string)Env::get('APP_KEY', 'local-dev-key');
        return hash_hmac('sha256', 'recovery:' . $normalized, $key);
    }

    private static function hotp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $high = intdiv($counter, 0x100000000);
        $low = $counter & 0xffffffff;
        $binaryCounter = pack('N2', $high, $low);
        $digest = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($digest[19]) & 0x0f;
        $binary = ((ord($digest[$offset]) & 0x7f) << 24)
            | ((ord($digest[$offset + 1]) & 0xff) << 16)
            | ((ord($digest[$offset + 2]) & 0xff) << 8)
            | (ord($digest[$offset + 3]) & 0xff);

        return str_pad((string)($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= self::BASE32[bindec($chunk)];
        }

        return $out;
    }

    private static function base32Decode(string $value): string
    {
        $clean = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');
        $bits = '';

        foreach (str_split($clean) as $char) {
            $index = strpos(self::BASE32, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }

    private function __construct()
    {
    }
}
