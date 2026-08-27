<?php
declare(strict_types=1);

use CMS\Http\InstallerController;

$router->get('/install', [InstallerController::class, 'index']);
$router->post('/install/database', [InstallerController::class, 'database']);
$router->post('/install/complete', [InstallerController::class, 'complete']);
