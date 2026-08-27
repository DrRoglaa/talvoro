<?php
declare(strict_types=1);

use CMS\Core\AdminPath;
use CMS\Core\Database;

require __DIR__ . '/../bootstrap/app.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$command = strtolower(trim((string)($argv[1] ?? 'show')));

try {
    Database::connection()->query('SELECT 1');

    if ($command === 'show') {
        echo 'Current admin path: ' . AdminPath::baseUrl() . PHP_EOL;
        echo 'Login URL: ' . AdminPath::absoluteUrl('/login') . PHP_EOL;
        exit(0);
    }

    if ($command === 'reset') {
        AdminPath::set(AdminPath::DEFAULT, null);
        echo "Admin path reset to /admin\n";
        echo 'Login URL: ' . AdminPath::absoluteUrl('/login') . PHP_EOL;
        exit(0);
    }

    if ($command === 'set') {
        $value = (string)($argv[2] ?? '');
        $path = AdminPath::set($value, null);
        echo 'Admin path set to /' . $path . PHP_EOL;
        echo 'Login URL: ' . AdminPath::absoluteUrl('/login') . PHP_EOL;
        exit(0);
    }

    fwrite(STDERR, "Usage: php bin/admin-path.php [show|reset|set <path>]\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
