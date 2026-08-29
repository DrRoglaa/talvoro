<?php
declare(strict_types=1);

namespace CMS\Core;

final class ContactSettings
{
    private const RETENTION_DAYS = [7, 30, 90, 180, 365];

    /** @return array{recipient:string,subject_prefix:string,store_submissions:bool,retention_days:int} */
    public static function config(): array
    {
        $retention = (int)Settings::get('contact.retention_days', '30');
        if (!in_array($retention, self::RETENTION_DAYS, true)) {
            $retention = 30;
        }

        return [
            'recipient' => self::normalizeEmail((string)Settings::get('contact.default_recipient', '')),
            'subject_prefix' => self::cleanHeaderText((string)Settings::get('contact.subject_prefix', 'Website contact'), 80),
            'store_submissions' => Settings::get('contact.store_submissions', '0') === '1',
            'retention_days' => $retention,
        ];
    }

    /** @return list<string> */
    public static function save(array $input, int $userId): array
    {
        $recipient = self::normalizeEmail((string)($input['contact_recipient'] ?? ''));
        $prefix = self::cleanHeaderText((string)($input['contact_subject_prefix'] ?? 'Website contact'), 80);
        $store = isset($input['contact_store_submissions']);
        $retention = (int)($input['contact_retention_days'] ?? 30);

        $errors = [];
        if ($recipient !== '' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Default contact recipient is invalid.';
        }
        if ($prefix === '') {
            $errors[] = 'Contact subject prefix is required.';
        }
        if (!in_array($retention, self::RETENTION_DAYS, true)) {
            $errors[] = 'Choose a valid contact submission retention period.';
        }
        if ($errors !== []) {
            return $errors;
        }

        Settings::set('contact.default_recipient', $recipient, $userId);
        Settings::set('contact.subject_prefix', $prefix, $userId);
        Settings::set('contact.store_submissions', $store ? '1' : '0', $userId);
        Settings::set('contact.retention_days', (string)$retention, $userId);
        return [];
    }

    public static function deliveryReady(): bool
    {
        $recipient = self::config()['recipient'];
        return $recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false && MailSettings::isReady();
    }

    public static function canAccept(): bool
    {
        $config = self::config();
        return $config['store_submissions'] || self::deliveryReady();
    }

    /** @return list<int> */
    public static function retentionOptions(): array
    {
        return self::RETENTION_DAYS;
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim(str_replace(["\r", "\n", "\0"], '', $email)));
    }

    private static function cleanHeaderText(string $value, int $max): string
    {
        $value = trim((string)(preg_replace('/[\r\n\0]+/', ' ', $value) ?? ''));
        return self::slice($value, $max);
    }

    private static function slice(string $value, int $max): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    private function __construct() {}
}
