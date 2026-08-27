<?php
declare(strict_types=1);

use CMS\Core\ContentModelStarters;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $models = ContentModelStarters::catalog([]);
    $faq = null;
    foreach ($models as $model) {
        if (($model['key'] ?? '') === 'faq-item') { $faq = $model; break; }
    }

    $assert('Expanded starter library exposes positive field counts',
        count($models) >= 15
        && count(array_filter($models, static fn(array $item): bool => array_key_exists('field_count', $item) && (int)$item['field_count'] > 0)) === count($models)
    );
    $assert('Compact FAQ starter remains valid with four fields', is_array($faq) && (int)($faq['field_count'] ?? 0) === 4);

    $fieldCounts = array_map(static fn(array $item): int => (int)($item['field_count'] ?? 0), $models);
    $customContent = (string)@file_get_contents(base_path('app/Core/CustomContent.php'));
    $assert('v0.14.2 starter-count contract accepts compact valid starters',
        $fieldCounts !== []
        && min($fieldCounts) > 0
        && in_array(4, $fieldCounts, true)
        && is_array($faq)
        && (int)($faq['field_count'] ?? 0) === 4
    );
    $assert('Structured SEO robots default is warning-safe',
        str_contains($customContent, "\$robotsInput = (string)(\$input['robots'] ?? 'index,follow');")
        && str_contains($customContent, 'in_array($robotsInput')
        && !str_contains($customContent, "? (string)\$input['robots'] : 'index,follow'")
    );
    $contentModels = (string)@file_get_contents(base_path('app/Core/ContentModels.php'));
    $assert('Schema and entry defaults avoid undefined-key ternaries',
        str_contains($customContent, "\$statusInput = (string)(\$input['status'] ?? 'draft');")
        && str_contains($contentModels, "\$statusInput = (string)(\$input['status'] ?? 'active');")
        && str_contains($contentModels, "\$iconInput = (string)(\$input['icon'] ?? 'collection');")
        && !str_contains($contentModels, "? (string)\$input['status'] : 'active'")
        && !str_contains($customContent, "? (string)\$input['status'] : 'draft'")
    );
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.5 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.5 compatibility checks passed.' . PHP_EOL;
