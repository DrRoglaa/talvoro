<?php
declare(strict_types=1);

namespace CMS\Core;

final class RateLimiter
{
    public static function tooManyLoginAttempts(string $email): bool
    {
        return self::tooMany('login-account:' . self::account($email), 5)
            || self::tooMany('login-ip:' . self::ip(), 20);
    }

    public static function hitLogin(string $email): void
    {
        self::hit('login-account:' . self::account($email));
        self::hit('login-ip:' . self::ip());
    }

    public static function clearLogin(string $email): void
    {
        // Clear the account bucket after a successful login. The IP bucket is
        // intentionally allowed to age out so one valid account cannot reset a
        // distributed guessing sequence coming from the same source address.
        self::clear('login-account:' . self::account($email));
    }

    public static function tooManyMfaAttempts(string $email): bool
    {
        return self::tooMany('mfa-account:' . self::account($email), 8)
            || self::tooMany('mfa-ip:' . self::ip(), 30);
    }

    public static function hitMfa(string $email): void
    {
        self::hit('mfa-account:' . self::account($email));
        self::hit('mfa-ip:' . self::ip());
    }

    public static function clearMfa(string $email): void
    {
        self::clear('mfa-account:' . self::account($email));
    }

    public static function tooManySystemAttempts(string $email): bool
    {
        return self::tooMany('system-account:' . self::account($email), 5)
            || self::tooMany('system-ip:' . self::ip(), 15);
    }

    public static function hitSystem(string $email): void
    {
        self::hit('system-account:' . self::account($email));
        self::hit('system-ip:' . self::ip());
    }

    public static function clearSystem(string $email): void
    {
        self::clear('system-account:' . self::account($email));
    }

    public static function tooManyContactAttempts(): bool
    {
        return self::tooMany('contact-ip:' . self::ip(), 5);
    }

    public static function hitContact(): void
    {
        self::hit('contact-ip:' . self::ip());
    }

    private static function tooMany(string $value, int $limit): bool
    {
        self::cleanup();
        $stmt = Database::connection()->prepare(
            'SELECT COUNT(*) FROM login_attempts
             WHERE attempt_key=? AND attempted_at >= (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)'
        );
        $stmt->execute([self::key($value)]);
        return (int)$stmt->fetchColumn() >= $limit;
    }

    private static function hit(string $value): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts (attempt_key,attempted_at) VALUES (?,UTC_TIMESTAMP())'
        )->execute([self::key($value)]);
        self::cleanup();
    }

    private static function cleanup(): void
    {
        Database::connection()->exec(
            'DELETE FROM login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL 1 DAY)'
        );
    }

    private static function clear(string $value): void
    {
        Database::connection()->prepare('DELETE FROM login_attempts WHERE attempt_key=?')
            ->execute([self::key($value)]);
    }

    private static function account(string $email): string
    {
        $email = mb_strtolower(trim($email));
        return $email !== '' ? $email : 'unknown';
    }

    private static function ip(): string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($ip === '' || strlen($ip) > 64 || preg_match('/[\x00-\x20\x7F]/', $ip)) {
            return 'unknown';
        }
        return strtolower($ip);
    }

    private static function key(string $value): string
    {
        $appKey = (string)Env::get('APP_KEY', '');
        return hash_hmac('sha256', $value, $appKey !== '' ? $appKey : 'local-dev-key');
    }

    private function __construct() {}
}
