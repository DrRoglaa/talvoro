<?php
declare(strict_types=1);

use CMS\Core\ContentModelStarters;
use CMS\Core\ContentModels;
use CMS\Core\PagePatternStarters;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $models = ContentModelStarters::catalog();
    $patterns = PagePatternStarters::catalog();

    $modelKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), $models);
    $patternKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), $patterns);
    $assert('Starter model library has useful breadth', count($models) >= 6 && count(array_unique($modelKeys)) === count($modelKeys));
    $assert('Starter pattern library has useful breadth', count($patterns) >= 6 && count(array_unique($patternKeys)) === count($patternKeys));
    $assert('Starter models expose field counts', count(array_filter($models, static fn(array $item): bool => array_key_exists('field_count', $item) && (int)$item['field_count'] > 0)) === count($models));

    $installedModelIds = array_values(array_filter(array_map(static fn(array $item): int => (int)($item['installed_id'] ?? 0), $models)));
    $installedModelsValid = true;
    foreach ($installedModelIds as $id) {
        if (ContentModels::find($id) === null || count(ContentModels::fields($id)) < 1) { $installedModelsValid = false; break; }
    }
    $assert('Installed starter models resolve to normal Talvoro models', $installedModelsValid);

    $routes = (string)@file_get_contents(base_path('routes/web.php'));
    $modelIndex = (string)@file_get_contents(base_path('resources/views/admin/content-models/index.php'));
    $patternIndex = (string)@file_get_contents(base_path('resources/views/admin/patterns/index.php'));
    $modelForm = (string)@file_get_contents(base_path('resources/views/admin/content-models/form.php'));
    $css = (string)@file_get_contents(base_path('public/assets/css/app.css'));

    $assert('Starter installation routes are POST-only admin actions',
        str_contains($routes, "post(\$admin . '/content-models/starters/install'")
        && str_contains($routes, "post(\$admin . '/patterns/starters/install'")
    );
    $assert('Starter libraries use CSRF-protected forms',
        str_contains($modelIndex, 'Csrf::field()') && str_contains($patternIndex, 'Csrf::field()')
    );
    $assert('Content model public-behavior copy is polished',
        str_contains($modelForm, 'Visibility & URLs')
        && str_contains($modelForm, 'Show published entries on the public website.')
        && !str_contains($modelForm, 'Public contentAllow')
    );
    $assert('Content model toggle layout has dedicated responsive CSS',
        str_contains($css, '.toggle-grid')
        && str_contains($css, '.toggle-card')
        && str_contains($css, 'grid-template-columns: 20px minmax(0, 1fr)')
        && str_contains($css, '.starter-card-grid')
    );
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.2 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.2 focused checks passed.' . PHP_EOL;
