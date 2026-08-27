<?php
declare(strict_types=1);

use CMS\Core\ContentModelStarters;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $models = ContentModelStarters::catalog([]);
    $counts = array_map(static fn(array $item): int => (int)($item['field_count'] ?? 0), $models);
    $faq = null;
    foreach ($models as $model) {
        if (($model['key'] ?? '') === 'faq-item') { $faq = $model; break; }
    }

    $assert('Starter field-count validation is behavior-based',
        count($models) >= 15
        && $counts !== []
        && min($counts) > 0
        && is_array($faq)
        && (int)($faq['field_count'] ?? 0) === 4
    );
    $assert('v0.14.5 compatibility check remains available', is_file(base_path('bin/check-v0145.php')));
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.6 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.6 regression checks passed.' . PHP_EOL;
