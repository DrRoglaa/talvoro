<?php
declare(strict_types=1);

if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); } }

require __DIR__ . '/../bootstrap/app.php';

$checks=[];
$assert=static function(string $name,bool $ok)use(&$checks):void{$checks[$name]=$ok;};
$routes=(string)@file_get_contents(base_path('routes/web.php'));
$controllerPath=base_path('app/Http/StarterSiteController.php');
$controller=is_file($controllerPath)?(string)file_get_contents($controllerPath):'';
$themes=(string)@file_get_contents(base_path('resources/views/admin/themes.php'));
$reviewPath=base_path('resources/views/admin/theme-starter.php');
$review=is_file($reviewPath)?(string)file_get_contents($reviewPath):'';
$css=(string)@file_get_contents(base_path('public/assets/css/talvoro-admin.css'));
$controllers=(string)@file_get_contents(base_path('app/Http/Controllers.php'));
$formState=(string)@file_get_contents(base_path('public/assets/js/admin-form-state.js'));

$assert('Starter review route is GET-only and uses focused controller', str_contains($routes,"/themes/{id}/starter', [StarterSiteController::class, 'review']"));
$assert('Starter install repair and Delete Demo Data are POST-only routes',
    str_contains($routes,"/themes/{id}/starter/install', [StarterSiteController::class, 'install']") &&
    str_contains($routes,"/themes/{id}/starter/repair', [StarterSiteController::class, 'repair']") &&
    str_contains($routes,"/themes/{id}/starter/delete-demo-data', [StarterSiteController::class, 'deleteDemoData']") &&
    !str_contains($routes,'->get($admin . \'/themes/{id}/starter/install\'') &&
    !str_contains($routes,'->get($admin . \'/themes/{id}/starter/repair\'') &&
    !str_contains($routes,'->get($admin . \'/themes/{id}/starter/delete-demo-data\'')
);
$assert('Starter controller exists and requires dedicated permission', is_file($controllerPath) && str_contains($controller,"starter_sites.manage") && str_contains($controller,'Gate::allows'));
$assert('Every starter mutation validates CSRF', substr_count($controller,'Csrf::valid')>=3);
$assert('Install requires explicit confirmation and passes controlled-mutation confirmation', str_contains($controller,'confirm_starter') && str_contains($controller,'confirm_mutations'));
$assert('Delete Demo Data requires explicit destructive confirmation', str_contains($controller,'confirm_delete_demo') && str_contains($controller,'StarterSite::deleteDemoData'));
$assert('Starter actions emit audit records', str_contains($controller,'starter.install') && str_contains($controller,'starter.repair') && str_contains($controller,'starter.delete_demo_data'));
$assert('Themes listing exposes starter availability without granting action permission', str_contains($controllers,'starterDefinition') && str_contains($themes,'Starter Site Available') && str_contains($themes,'starter_sites.manage'));
$assert('Dedicated accessible starter review view exists', is_file($reviewPath) && str_contains($review,'<h1>') && str_contains($review,'<form') && str_contains($review,'Delete Demo Data') && str_contains($review,'aria-live'));
$assert('Starter UI includes non-color-only status and focused admin styles', str_contains($review,'starter-status-text') && str_contains($css,'.starter-site-review'));
$assert('Controlled changes use human-readable review cards', str_contains($review,'starter-change-card') && str_contains($review,'change_title') && str_contains($css,'.starter-change-card'));
$assert('Theme import help documents optional declarative starter package', str_contains($themes,'starter/starter.json') && str_contains($themes,'declarative'));
$assert('Successful theme import returns to the top of the theme library', str_contains($controllers,"/themes?imported=1#theme-library") && str_contains($themes,'id="theme-library"'));
$assert('Explicit admin URL anchors override saved form scroll restoration', str_contains($formState,'if (window.location.hash)') && str_contains($formState,'clearState();') && str_contains($formState,'return;'));
$assert('Theme import does not save its old form scroll position', str_contains($themes,'action="<?= e(admin_url()) ?>/themes/import" enctype="multipart/form-data" class="stack" data-no-scroll-restore'));
$actionsPos=strpos($themes,'<div class="theme-actions">');
$starterSummaryPos=strpos($themes,'<div class="starter-theme-summary">');
$assert('Theme card actions stay visible before optional Starter Site details', $actionsPos !== false && $starterSummaryPos !== false && $actionsPos < $starterSummaryPos && !str_contains((string)@file_get_contents(base_path('public/assets/css/app.css')),'.theme-actions {\n    display: flex;\n    align-items: center;\n    gap: 10px;\n    flex-wrap: wrap;\n    margin-top: auto;'));

$failed=array_keys(array_filter($checks,static fn(bool $ok):bool=>!$ok));
foreach($checks as $name=>$ok)echo($ok?'[OK]   ':'[FAIL] ').$name.PHP_EOL;
if($failed){fwrite(STDERR,PHP_EOL.'Talvoro 0.17.0 starter HTTP/UI checks failed: '.implode(', ',$failed).PHP_EOL);exit(1);}
echo PHP_EOL.'Talvoro 0.17.0 starter HTTP/UI checks passed.'.PHP_EOL;
