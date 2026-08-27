<?php
declare(strict_types=1);

namespace CMS\Core;

final class PasswordPolicy
{
    public const MIN_LENGTH = 14;
    public const MAX_LENGTH = 128;

    /** @return list<string> */
    public static function validate(string $password, string $email = '', string $name = ''): array
    {
        $errors = [];
        $length = strlen($password);
        if ($length < self::MIN_LENGTH) {
            $errors[] = 'Use at least ' . self::MIN_LENGTH . ' characters.';
        }
        if ($length > self::MAX_LENGTH) {
            $errors[] = 'Password is too long.';
        }
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $password)) {
            $errors[] = 'Password contains unsupported control characters.';
        }
        $lower = mb_strtolower($password);
        $emailLocal = mb_strtolower((string)strtok($email, '@'));
        if ($emailLocal !== '' && mb_strlen($emailLocal) >= 4 && str_contains($lower, $emailLocal)) {
            $errors[] = 'Password must not contain your email name.';
        }
        $compactName = preg_replace('/\s+/', '', mb_strtolower($name)) ?? '';
        if (mb_strlen($compactName) >= 4 && str_contains(str_replace(' ', '', $lower), $compactName)) {
            $errors[] = 'Password must not contain your display name.';
        }
        $common = ['password','password123','qwerty123','letmein123','administrator','admin1234','welcome123','talvoro','privacycms'];
        if (in_array($lower, $common, true)) {
            $errors[] = 'Choose a less common password.';
        }
        return array_values(array_unique($errors));
    }

    public static function generate(int $length = 24): string
    {
        $length = max(20, min(64, $length));
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%*-_+=';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    private function __construct() {}
}
