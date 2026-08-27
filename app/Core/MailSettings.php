<?php
declare(strict_types=1);

namespace CMS\Core;

final class MailSettings
{
    public static function config(bool $includePassword = true): array
    {
        $password = $includePassword
            ? Crypto::decrypt(Settings::get('mail.smtp_password_enc', '') ?? '', 'smtp-password')
            : '';

        return [
            'enabled' => Settings::get('mail.enabled', '0') === '1',
            'host' => trim((string)Settings::get('mail.smtp_host', '')),
            'port' => max(1, min(65535, (int)Settings::get('mail.smtp_port', '587'))),
            'encryption' => self::normalizeEncryption((string)Settings::get('mail.smtp_encryption', 'starttls')),
            'username' => trim((string)Settings::get('mail.smtp_username', '')),
            'password' => $password,
            'password_configured' => trim((string)Settings::get('mail.smtp_password_enc', '')) !== '',
            'from_email' => trim((string)Settings::get('mail.from_email', '')),
            'from_name' => trim((string)Settings::get('mail.from_name', 'Talvoro')),
            'envelope_from' => trim((string)Settings::get('mail.envelope_from', '')),
        ];
    }

    public static function save(array $input, int $userId): array
    {
        $enabled = isset($input['enabled']);
        $host = trim((string)($input['smtp_host'] ?? ''));
        $port = (int)($input['smtp_port'] ?? 587);
        $encryption = self::normalizeEncryption((string)($input['smtp_encryption'] ?? 'starttls'));
        if ($port === 465) {
            $encryption = 'ssl';
        } elseif ($port === 587 && $encryption === 'ssl') {
            $encryption = 'starttls';
        }
        $username = trim((string)($input['smtp_username'] ?? ''));
        $password = (string)($input['smtp_password'] ?? '');
        $fromEmail = mb_strtolower(trim((string)($input['from_email'] ?? '')));
        $fromName = trim((string)($input['from_name'] ?? 'Talvoro'));
        $envelopeFrom = mb_strtolower(trim((string)($input['envelope_from'] ?? '')));

        $errors = [];

        if ($enabled && $host === '') {
            $errors[] = 'SMTP host is required when email delivery is enabled.';
        }
        if ($port < 1 || $port > 65535) {
            $errors[] = 'SMTP port must be between 1 and 65535.';
        }
        if ($fromEmail !== '' && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'From email is invalid.';
        }
        if ($enabled && $fromEmail === '') {
            $errors[] = 'From email is required when email delivery is enabled.';
        }
        if ($envelopeFrom !== '' && !filter_var($envelopeFrom, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Envelope-from email is invalid.';
        }
        if (mb_strlen($fromName) > 120) {
            $errors[] = 'From name must be 120 characters or fewer.';
        }
        if ($username !== '' && $password === '' && !(self::config(false)['password_configured'] ?? false)) {
            $errors[] = 'SMTP password is required when a username is configured.';
        }

        if ($errors) {
            return $errors;
        }

        Settings::set('mail.enabled', $enabled ? '1' : '0', $userId);
        Settings::set('mail.smtp_host', $host, $userId);
        Settings::set('mail.smtp_port', (string)$port, $userId);
        Settings::set('mail.smtp_encryption', $encryption, $userId);
        Settings::set('mail.smtp_username', $username, $userId);
        Settings::set('mail.from_email', $fromEmail, $userId);
        Settings::set('mail.from_name', $fromName, $userId);
        Settings::set('mail.envelope_from', $envelopeFrom, $userId);

        if ($password !== '') {
            Settings::set(
                'mail.smtp_password_enc',
                Crypto::encrypt($password, 'smtp-password'),
                $userId
            );
        }

        return [];
    }

    public static function isReady(): bool
    {
        try {
            $c = self::config();
            return $c['enabled']
                && $c['host'] !== ''
                && $c['port'] > 0
                && filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)
                && ($c['username'] === '' || $c['password'] !== '');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function normalizeEncryption(string $value): string
    {
        return in_array($value, ['starttls','ssl','none'], true) ? $value : 'starttls';
    }

    private function __construct()
    {
    }
}
