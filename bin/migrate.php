<?php
declare(strict_types=1);

use CMS\Core\Migrator;

require __DIR__ . '/../bootstrap/app.php';

foreach (Migrator::run() as $row) {
    echo str_pad($row['status'], 6) . $row['migration'] . "\n";
}
echo "Migrations complete.\n";
