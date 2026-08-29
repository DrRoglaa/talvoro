<?php
declare(strict_types=1);

use CMS\Core\PublicSitePreset;

require __DIR__ . '/../bootstrap/app.php';

$force = in_array('--force', $argv ?? [], true);
$result = PublicSitePreset::apply(null, $force);

printf("status: %s\n", $result['status']);
printf("home replaced: %s\n", $result['home_replaced'] ? 'yes' : 'no');
printf("pages created: %d\n", $result['pages_created']);
printf("menus created: %d\n", $result['menus_created']);
printf("seo entries seeded: %d\n", $result['seo_seeded']);
printf("%s\n", $result['message']);

exit(in_array($result['status'], ['applied','skip'], true) ? 0 : 2);
