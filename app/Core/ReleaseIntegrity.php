<?php
declare(strict_types=1);

namespace CMS\Core;

final class ReleaseIntegrity
{
    public static function report(): array
    {
        $path = base_path('release.json');
        if (!is_file($path)) {
            return ['ok' => false, 'checked' => 0, 'issues' => ['release.json is missing.']];
        }
        try {
            $manifest = json_decode((string)file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['ok' => false, 'checked' => 0, 'issues' => ['release.json is invalid.']];
        }
        if (!is_array($manifest) || !in_array((string)($manifest['product'] ?? ''), ['talvoro','privacy-cms'], true)) {
            return ['ok' => false, 'checked' => 0, 'issues' => ['Release manifest product is invalid.']];
        }
        $issues = [];
        if ((string)($manifest['version'] ?? '') !== app_version()) {
            $issues[] = 'Release manifest version does not match the application version.';
        }
        $files = $manifest['files'] ?? [];
        if (!is_array($files)) {
            return ['ok' => false, 'checked' => 0, 'issues' => ['Release manifest files are invalid.']];
        }
        $checked = 0;
        foreach ($files as $relative => $expected) {
            if (!is_string($relative) || !is_string($expected)) {
                $issues[] = 'Release manifest contains an invalid entry.';
                continue;
            }
            $file = base_path($relative);
            if (!is_file($file)) {
                $issues[] = 'Missing: ' . $relative;
            } elseif (!hash_equals($expected, hash_file('sha256', $file))) {
                $issues[] = 'Changed: ' . $relative;
            }
            $checked++;
            if (count($issues) >= 20) break;
        }
        return ['ok' => !$issues, 'checked' => $checked, 'issues' => $issues];
    }

    private function __construct() {}
}
