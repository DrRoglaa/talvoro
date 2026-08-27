<?php
declare(strict_types=1);

use CMS\Core\Env;
use CMS\Core\Installer;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $view = (string)@file_get_contents(base_path('resources/views/admin/content-models/form.php'));
    $css = (string)@file_get_contents(base_path('public/assets/css/app.css'));
    $compose = (string)@file_get_contents(base_path('compose.yaml'));
    $rootHtaccess = (string)@file_get_contents(base_path('.htaccess'));
    $installerSource = (string)@file_get_contents(base_path('app/Core/Installer.php'));
    $manifestPath = base_path('release.json');
    $manifest = is_file($manifestPath) ? json_decode((string)file_get_contents($manifestPath), true) : null;
    $distribution = is_array($manifest)
        ? (string)($manifest['distribution'] ?? 'source')
        : (is_dir(base_path('scripts/release')) ? 'source' : '');

    $assert('Role access uses semantic tabular markup',
        str_contains($view, '<table class="permission-matrix">')
        && str_contains($view, '<th scope="col">View</th>')
        && str_contains($view, '<th scope="row"><?= e($role[\'label\']) ?></th>')
        && str_contains($css, '.permission-matrix-scroll')
        && str_contains($css, 'table-layout:fixed')
    );

    $options = Installer::databasePdoOptions();
    $assert('Installer PDO uses strict prepared-statement options',
        ($options[PDO::ATTR_EMULATE_PREPARES] ?? null) === false
        && ($options[PDO::ATTR_STRINGIFY_FETCHES] ?? null) === false
        && ($options[PDO::ATTR_ERRMODE] ?? null) === PDO::ERRMODE_EXCEPTION
    );
    if (PHP_VERSION_ID >= 80500 && class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')) {
        $assert('Installer uses PHP 8.5 namespaced MySQL multi-statement option',
            array_key_exists(constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS'), $options)
            && $options[constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')] === false
        );
    } else {
        $assert('Installer PHP 8.5 PDO compatibility path is present',
            str_contains($installerSource, "defined('Pdo\\\\Mysql::ATTR_MULTI_STATEMENTS')")
        );
    }

    $directories = Installer::requiredWritableDirectories();
    $assert('Installer covers private and public runtime directories',
        isset($directories['storage'], $directories['storage_backups'], $directories['storage_update'], $directories['uploads'], $directories['uploads_site'], $directories['uploads_themes'])
        && str_ends_with($directories['storage'], '/storage')
        && str_ends_with($directories['uploads'], '/public/uploads')
    );

    $tempConfig = base_path('storage/check-v0147-env.php');
    if (!is_dir(dirname($tempConfig))) @mkdir(dirname($tempConfig), 0750, true);
    file_put_contents($tempConfig, "<?php return ['TALVORO_EMPTY_ENV_TEST' => 'from-config'];\n");
    Env::loadConfig($tempConfig, true);
    putenv('TALVORO_EMPTY_ENV_TEST=');
    $emptyFallback = Env::get('TALVORO_EMPTY_ENV_TEST') === 'from-config';
    putenv('TALVORO_EMPTY_ENV_TEST');
    @unlink($tempConfig);
    $assert('Protected config can fill empty Docker placeholders', $emptyFallback);

    if ($distribution !== 'webhosting') {
        $appSection = preg_match('/services:\s*\n\s*app:(.*?)(?:\n\s*db:)/s', $compose, $match) ? (string)$match[1] : '';
        $assert('Docker app does not receive MariaDB root password',
            $appSection !== ''
            && !str_contains($appSection, 'DB_ROOT_PASSWORD')
            && str_contains($compose, 'MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}')
        );
        $assert('Docker browser-install template exists',
            is_file(base_path('.env.docker.example'))
            && !str_contains((string)file_get_contents(base_path('.env.docker.example')), 'APP_KEY=')
        );
    } else {
        $assert('Web-hosting distribution has no Docker bootstrap environment', !is_file(base_path('.env.docker.example')));
    }

    $assert('Private project paths include routes and docs protection',
        str_contains($rootHtaccess, '|routes|docs)')
        && is_file(base_path('routes/.htaccess'))
        && is_file(base_path('docs/.htaccess'))
    );
    $guidesOk = is_file(base_path('docs/DISTRIBUTIONS.md'));
    if ($distribution === 'docker') {
        $guidesOk = $guidesOk && is_file(base_path('docs/INSTALL-DOCKER.md'));
    } elseif ($distribution === 'webhosting') {
        $guidesOk = $guidesOk && is_file(base_path('docs/INSTALL-WEB-HOSTING.md'));
    } else {
        $guidesOk = $guidesOk
            && is_file(base_path('docs/INSTALL-DOCKER.md'))
            && is_file(base_path('docs/INSTALL-WEB-HOSTING.md'));
    }
    $assert('Distribution install guides are present', $guidesOk);

    $assert('Release distribution metadata is recognized', in_array($distribution, ['source','docker','webhosting'], true));
    if ($distribution === 'webhosting') {
        $assert('Web-hosting distribution omits Docker runtime files',
            !is_file(base_path('Dockerfile')) && !is_file(base_path('compose.yaml')) && !is_dir(base_path('docker'))
        );
    } else {
        $assert('Source/Docker distribution retains Docker runtime files',
            is_file(base_path('Dockerfile')) && is_file(base_path('compose.yaml')) && is_dir(base_path('docker'))
        );
    }
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.7 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.7 deployment and installer checks passed.' . PHP_EOL;
