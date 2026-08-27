<?php
declare(strict_types=1);

use CMS\Core\ContentModelStarters;
use CMS\Core\ContentModels;
use CMS\Core\PageBlocks;
use CMS\Core\PagePatternStarters;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $models = ContentModelStarters::catalog();
    $patterns = PagePatternStarters::catalog();

    $modelKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), $models);
    $patternKeys = array_map(static fn(array $item): string => (string)($item['key'] ?? ''), $patterns);

    $requiredModels = [
        'team-member','testimonial','event','portfolio-item','location','product',
        'service','job-opening','resource','partner','award','faq-item','pricing-plan','course','press-mention',
    ];
    $requiredPatterns = [
        'hero-with-cta','feature-highlights','testimonials','faq','key-statistics','call-to-action','latest-posts',
        'about-story','services-grid','process-steps','team-grid','featured-work','pricing-overview','image-gallery',
        'contact-section','trust-guarantees','landing-page-essentials',
    ];

    $assert('Expanded starter model library contains 15 curated models', count($models) >= 15 && count(array_diff($requiredModels, $modelKeys)) === 0);
    $assert('Expanded starter pattern library contains 17 curated patterns', count($patterns) >= 17 && count(array_diff($requiredPatterns, $patternKeys)) === 0);
    $assert('Starter keys stay unique', count(array_unique($modelKeys)) === count($modelKeys) && count(array_unique($patternKeys)) === count($patternKeys));
    $assert('Starter cards expose useful categories',
        count(array_filter($models, static fn(array $item): bool => trim((string)($item['category'] ?? '')) !== '')) === count($models)
        && count(array_filter($patterns, static fn(array $item): bool => trim((string)($item['category'] ?? '')) !== '')) === count($patterns)
    );

    $modelTemplatesMethod = (new ReflectionClass(ContentModelStarters::class))->getMethod('templates');
    /** @var array<string,array<string,mixed>> $modelTemplates */
    $modelTemplates = $modelTemplatesMethod->invoke(null);
    $allowedFieldTypes = array_keys(ContentModels::fieldTypes());
    $modelDefinitionsValid = true;
    foreach ($modelTemplates as $template) {
        $seen = [];
        foreach (($template['fields'] ?? []) as $field) {
            if (!is_array($field)) { $modelDefinitionsValid = false; break 2; }
            $key = ContentModels::fieldKey((string)($field['field_key'] ?? ''));
            $type = (string)($field['field_type'] ?? '');
            if ($key === '' || isset($seen[$key]) || !in_array($type, $allowedFieldTypes, true)) {
                $modelDefinitionsValid = false;
                break 2;
            }
            $seen[$key] = true;
        }
    }
    $assert('Expanded starter model definitions use valid unique field keys and supported types', $modelDefinitionsValid);

    $patternTemplatesMethod = (new ReflectionClass(PagePatternStarters::class))->getMethod('templates');
    /** @var array<string,array<string,mixed>> $patternTemplates */
    $patternTemplates = $patternTemplatesMethod->invoke(null);
    $patternsValidate = true;
    foreach ($patternTemplates as $template) {
        $json = json_encode($template['blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) { $patternsValidate = false; break; }
        $validated = PageBlocks::validateSubmitted($json, false);
        if ($validated['errors']) { $patternsValidate = false; break; }
    }
    $assert('Every starter pattern passes the Page Builder block validator', $patternsValidate);

    $modelIndex = (string)@file_get_contents(base_path('resources/views/admin/content-models/index.php'));
    $patternIndex = (string)@file_get_contents(base_path('resources/views/admin/patterns/index.php'));
    $starterJs = (string)@file_get_contents(base_path('public/assets/js/starter-library.js'));
    $starterCss = (string)@file_get_contents(base_path('public/assets/css/app.css'));

    $assert('Large starter libraries include accessible search controls',
        str_contains($modelIndex, 'data-starter-library-search')
        && str_contains($patternIndex, 'data-starter-library-search')
        && str_contains($modelIndex, 'Find a starter model')
        && str_contains($patternIndex, 'Find a starter pattern')
    );
    $assert('Starter-library filtering is progressively enhanced',
        str_contains($starterJs, 'data-starter-library-card')
        && str_contains($starterJs, "input.addEventListener('input', filter)")
        && str_contains($starterCss, '.starter-library-search')
        && str_contains($starterCss, '.starter-card-badges')
    );
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . 'v0.14.3 checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . 'Talvoro v0.14.3 expanded-starter checks passed.' . PHP_EOL;
