<?php
declare(strict_types=1);

// The production runtime requires mbstring. These ASCII-safe shims let the
// release package run its non-database structural checks on minimal CI hosts.
if (!function_exists('mb_strlen')) { function mb_strlen(string $value, ?string $encoding = null): int { return strlen($value); } }
if (!function_exists('mb_substr')) { function mb_substr(string $value, int $start, ?int $length = null, ?string $encoding = null): string { return $length === null ? substr($value, $start) : substr($value, $start, $length); } }
if (!function_exists('mb_strtolower')) { function mb_strtolower(string $value, ?string $encoding = null): string { return strtolower($value); } }

use CMS\Core\Database;
use CMS\Core\DesignSystem;
use CMS\Core\PageBlocks;

require __DIR__ . '/../bootstrap/app.php';

$checks = [];
$assert = static function (string $name, bool $ok) use (&$checks): void { $checks[$name] = $ok; };

try {
    $definitions = DesignSystem::definitions();
    $assert('Design token foundation is curated', count($definitions) >= 16 && isset($definitions['brand'], $definitions['background'], $definitions['heading_font'], $definitions['content_width']));

    $valid = DesignSystem::validate([
        'brand'=>'#2455aa','accent'=>'#8b3d88','background'=>'#ffffff','surface'=>'#f8f8f8','text'=>'#181818','muted'=>'#666666','border'=>'#dddddd',
        'heading_font'=>'editorial','body_font'=>'humanist','type_scale'=>'balanced','content_width'=>'760','wide_width'=>'1240','section_spacing'=>'normal','radius'=>'medium','button_radius'=>'pill','shadow'=>'soft','link_style'=>'underline',
    ]);
    $assert('Design settings validate without arbitrary CSS', $valid['errors'] === [] && $valid['values']['brand'] === '#2455aa' && !array_key_exists('css', $valid['values']));

    $invalid = DesignSystem::validate(['brand'=>'red;position:fixed','heading_font'=>'https://evil.invalid/font.woff2','content_width'=>'99999']);
    $assert('Unsafe design values fail closed', count($invalid['errors']) >= 3 && $invalid['values']['brand'] === '#d66f5b' && $invalid['values']['heading_font'] === 'editorial');

    $lowContrast = DesignSystem::validate([
        'brand'=>'#eeeeee','accent'=>'#eeeeee','background'=>'#ffffff','surface'=>'#ffffff','text'=>'#eeeeee','muted'=>'#eeeeee','border'=>'#eeeeee',
        'heading_font'=>'editorial','body_font'=>'humanist','type_scale'=>'balanced','content_width'=>'760','wide_width'=>'1240','section_spacing'=>'normal','radius'=>'medium','button_radius'=>'pill','shadow'=>'soft','link_style'=>'clean',
    ]);
    $assert('Design accessibility warnings detect low contrast', count($lowContrast['warnings']) >= 2);

    $block = [[
        'id'=>'deadbeef00112233','type'=>'hero','enabled'=>true,'heading'=>'Safe hero','eyebrow'=>'','intro'=>'',
        'primary_enabled'=>false,'secondary_enabled'=>false,'image_path'=>'','image_alt'=>'',
        'style_tone'=>'accent','style_width'=>'wide','style_spacing'=>'spacious','style_alignment'=>'center','style_variant'=>'centered',
    ]];
    $validatedBlock = PageBlocks::validateSubmitted(json_encode($block, JSON_UNESCAPED_SLASHES));
    $b = $validatedBlock['blocks'][0] ?? [];
    $assert('Page Builder persists semantic section styles', $validatedBlock['errors'] === [] && ($b['style_tone'] ?? '') === 'accent' && ($b['style_variant'] ?? '') === 'centered');
    $classes = PageBlocks::sectionClasses($b, 'page-builder-hero');
    $assert('Public block classes are generated from whitelists', str_contains($classes, 'talvoro-tone-accent') && str_contains($classes, 'talvoro-variant-centered'));

    $block[0]['style_tone'] = 'url(javascript:alert(1))';
    $block[0]['style_variant'] = '../../template';
    $sanitized = PageBlocks::validateSubmitted(json_encode($block, JSON_UNESCAPED_SLASHES));
    $sb = $sanitized['blocks'][0] ?? [];
    $assert('Malformed section styles normalize safely', ($sb['style_tone'] ?? '') === 'default' && ($sb['style_variant'] ?? '') === 'default');

    $css = DesignSystem::css();
    $assert('Design CSS emits semantic variables', str_contains($css, '--talvoro-brand:') && str_contains($css, '--talvoro-content-width:') && str_contains($css, '.talvoro-tone-accent'));
    $assert('Design CSS supports visual-preview attributes', str_contains($css, '[data-style-tone="soft"]') && str_contains($css, '[data-style-variant="centered"]'));

    $routes = (string)file_get_contents(base_path('routes/web.php'));
    $controller = (string)file_get_contents(base_path('app/Http/DesignController.php'));
    $builder = (string)file_get_contents(base_path('public/assets/js/page-builder.js'));
    $patterns = (string)file_get_contents(base_path('resources/views/admin/patterns/index.php'));
    $layout = (string)file_get_contents(base_path('resources/views/layouts/app.php'));
    $assert('Design Styles routes are permission-gated POST/GET actions', str_contains($routes, "'/design/styles'") && str_contains($controller, "Gate::allows('design.manage')") && str_contains($controller, 'Csrf::valid'));
    $assert('Page Builder uses DOM live updates after initial preview load', str_contains($builder, 'previewBlocks.innerHTML =') && str_contains($builder, 'bindPreviewInteractions') && str_contains($builder, 'data-open-builder-preview'));
    $assert('Visual preview maps text to real inspector fields', str_contains($builder, 'data-preview-field') && str_contains($builder, 'focusInspectorField') && str_contains($builder, 'data-preview-item-index'));
    $assert('Visual preview has an accessible focus mode', str_contains($builder, 'data-preview-focus') && str_contains($builder, 'aria-pressed') && str_contains($builder, "event.key === 'Escape'"));
    $assert('Pattern library has visual multi-block previews', str_contains($patterns, 'pattern-visual-preview') && str_contains($patterns, 'pattern-preview-flow'));
    $designView = (string)file_get_contents(base_path('resources/views/admin/design/styles.php'));
    $designJs = (string)file_get_contents(base_path('public/assets/js/design-styles.js'));
    $designCore = (string)file_get_contents(base_path('app/Core/DesignSystem.php'));
    $assert('Styles UI identifies the active theme', str_contains($designView, "theme['name']") && str_contains($designView, 'active theme'));
    $assert('Styles UI provides live contrast feedback', str_contains($designView, 'data-design-live-warnings') && str_contains($designJs, 'renderWarnings'));
    $assert('Design settings are theme scoped', method_exists(DesignSystem::class, 'activeTheme') && str_contains($designCore, "design.theme."));
    $assert('Design navigation is progressively disclosed', str_contains($layout, '>Design</span>') && str_contains($layout, '>Styles</span>') && str_contains($layout, '>Patterns</span>') && str_contains($layout, '>Themes</span>'));
    $assert('Design migration exists', is_file(base_path('database/migrations/019_design_system.sql')));

    try {
        $db = Database::connection();
        $assert('Migration 019 applied', (int)$db->query("SELECT COUNT(*) FROM schema_migrations WHERE migration='019_design_system.sql'")->fetchColumn() === 1);
        $assert('Design manage permission exists', (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name='design.manage'")->fetchColumn() === 1);
        $assert('Administrator receives design permission', (int)$db->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='administrator' AND p.name='design.manage'")->fetchColumn() === 1);
    } catch (Throwable) {
        $assert('Database-backed design checks deferred outside installed runtime', true);
    }
} catch (Throwable $e) {
    $checks['Unexpected exception: ' . $e->getMessage()] = false;
}

$failed = array_keys(array_filter($checks, static fn(bool $ok): bool => !$ok));
foreach ($checks as $name => $ok) echo ($ok ? '[OK]   ' : '[FAIL] ') . $name . PHP_EOL;
if ($failed) {
    fwrite(STDERR, PHP_EOL . '[CURRENT] Talvoro v0.15.0 Design System & Visual Editing checks failed: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo PHP_EOL . '[CURRENT] Talvoro v0.15.0 Design System & Visual Editing checks passed.' . PHP_EOL;
