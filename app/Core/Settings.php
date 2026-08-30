<?php
declare(strict_types=1);

namespace CMS\Core;

use DateTimeImmutable;
use DateTimeZone;

final class Settings
{
    private static array $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $stmt = Database::connection()->prepare(
                'SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1'
            );
            $stmt->execute([$key]);
            $value = $stmt->fetchColumn();

            if ($value === false) {
                return self::$cache[$key] = $default;
            }

            return self::$cache[$key] = (string)$value;
        } catch (\Throwable) {
            return $default;
        }
    }

    public static function set(string $key, ?string $value, ?int $userId = null): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO cms_settings (setting_key,setting_value,updated_by,updated_at)
             VALUES (?,?,?,UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                setting_value=VALUES(setting_value),
                updated_by=VALUES(updated_by),
                updated_at=UTC_TIMESTAMP()'
        );
        $stmt->execute([$key, $value, $userId]);
        self::$cache[$key] = $value;
    }

    public static function forget(string $key): void
    {
        Database::connection()->prepare('DELETE FROM cms_settings WHERE setting_key=?')->execute([$key]);
        unset(self::$cache[$key]);
    }

    public static function siteMode(): string
    {
        $mode = self::get('site.mode', 'live');
        return in_array($mode, ['live', 'development'], true) ? $mode : 'live';
    }

    public static function searchHandling(): string
    {
        $value = self::get('site.search_handling', 'prelaunch');
        return in_array($value, ['prelaunch', 'maintenance'], true) ? $value : 'prelaunch';
    }

    public static function developmentHeadline(): string
    {
        return self::get(
            'site.development_headline',
            'A more thoughtful website experience is taking shape.'
        ) ?? '';
    }

    public static function developmentMessage(): string
    {
        return self::get(
            'site.development_message',
            'We are refining the website for its next public preview. Please check back soon.'
        ) ?? '';
    }

    public static function plannedReturnDate(): string
    {
        return self::get('site.return_date', '') ?? '';
    }

    public static function plannedReturnTime(): string
    {
        return self::get('site.return_time', '08:00') ?? '08:00';
    }

    public static function countdownEnabled(): bool
    {
        return self::get('site.countdown_enabled', '1') === '1';
    }

    public static function frontendTheme(): string
    {
        try {
            return (string)(ThemeManager::active()['slug'] ?? 'talvoro-editorial');
        } catch (\Throwable) {
            return 'talvoro-editorial';
        }
    }

    public static function blogEnabled(): bool
    {
        return self::get('blog.enabled', '1') === '1';
    }

    public static function blogArchiveTitle(): string
    {
        return trim((string)self::get('blog.archive_title', 'Thoughts, updates and useful things.'));
    }

    public static function blogArchiveIntro(): string
    {
        return trim((string)self::get('blog.archive_intro', 'A simple publishing space with no ad network, no third-party tracker and no unnecessary noise.'));
    }

    public static function publicFooterText(): string
    {
        return trim((string)self::get('branding.footer_text', 'Thoughtful, independent publishing with your content and your data under your control.'));
    }

    public static function publicFooterNote(string $siteName, ?string $fallback = null): string
    {
        $fallback = $fallback !== null && trim($fallback) !== ''
            ? trim($fallback)
            : '© ' . date('Y') . ' ' . trim($siteName) . '.';
        $value = trim((string)self::get('branding.footer_note', ''));
        return $value !== '' ? $value : $fallback;
    }

    public static function plannedReturnDisplay(): ?string
    {
        $iso = self::plannedReturnIso();

        if ($iso === null) {
            return null;
        }

        try {
            $timezoneName = Env::get('APP_TIMEZONE', 'Europe/Ljubljana') ?: 'Europe/Ljubljana';
            $timezone = new DateTimeZone($timezoneName);
            return (new DateTimeImmutable($iso))
                ->setTimezone($timezone)
                ->format('j M Y, H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    public static function plannedReturnIso(): ?string
    {
        $date = trim(self::plannedReturnDate());
        $time = trim(self::plannedReturnTime());

        if ($date === '' || $time === '') {
            return null;
        }

        try {
            $timezoneName = Env::get('APP_TIMEZONE', 'Europe/Ljubljana') ?: 'Europe/Ljubljana';
            $timezone = new DateTimeZone($timezoneName);

            $input = $date . ' ' . $time;
            $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $input, $timezone);

            if (!$dateTime || $dateTime->format('Y-m-d H:i') !== $input) {
                return null;
            }

            return $dateTime->format(DATE_ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    private function __construct()
    {
    }
}
