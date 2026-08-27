<?php
declare(strict_types=1);

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(string $path = ''): string
{
    $base = dirname(__DIR__, 2);
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function app_version(): string
{
    static $version = null;

    if (is_string($version)) {
        return $version;
    }

    $file = base_path('VERSION');
    $version = is_file($file) ? trim((string)file_get_contents($file)) : '0.0.0';

    return $version !== '' ? $version : '0.0.0';
}

function admin_url(string $suffix = ''): string
{
    return \CMS\Core\AdminPath::url($suffix);
}

function admin_login_url(): string
{
    return \CMS\Core\AdminPath::loginUrl();
}
