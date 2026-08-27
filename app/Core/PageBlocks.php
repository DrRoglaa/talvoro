<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class PageBlocks
{
    private const MAX_BLOCKS = 50;
    private const MAX_JSON_BYTES = 524288;
    private const MAX_ITEMS = 6;

    /** @return array<string,string> */
    public static function icons(): array
    {
        return [
            'heart' => 'Heart',
            'home' => 'Home',
            'award' => 'Award / standards',
            'clock' => 'Clock / experience',
            'shield' => 'Shield',
            'paw' => 'Paw',
            'star' => 'Star',
            'leaf' => 'Leaf',
            'sparkles' => 'Sparkles',
            'support' => 'Support',
        ];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return ['hero','values','cards','gallery','testimonials','faq','stats','custom','latest_posts','collection','cta','pattern'];
    }

    /** @return list<array<string,mixed>> */
    public static function decode(string $json): array
    {
        $json = trim($json);
        if ($json === '') return [];
        if (strlen($json) > self::MAX_JSON_BYTES) return [];
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
        return is_array($decoded) && array_is_list($decoded) ? $decoded : [];
    }

    /** @return array{blocks:list<array<string,mixed>>,json:string,errors:list<string>} */
    public static function validateSubmitted(string $json, bool $allowPatterns = true): array
    {
        if (strlen($json) > self::MAX_JSON_BYTES) {
            return ['blocks' => [], 'json' => '[]', 'errors' => ['Page blocks are too large.']];
        }
        try {
            $decoded = json_decode($json !== '' ? $json : '[]', true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return ['blocks' => [], 'json' => '[]', 'errors' => ['Page blocks could not be read.']];
        }
        if (!is_array($decoded) || !array_is_list($decoded)) {
            return ['blocks' => [], 'json' => '[]', 'errors' => ['Page blocks have an invalid structure.']];
        }
        if (count($decoded) > self::MAX_BLOCKS) {
            return ['blocks' => [], 'json' => '[]', 'errors' => ['A page can contain at most ' . self::MAX_BLOCKS . ' structured blocks.']];
        }

        $blocks = [];
        $errors = [];
        $seenIds = [];
        foreach ($decoded as $index => $raw) {
            if (!is_array($raw)) {
                $errors[] = 'Block ' . ($index + 1) . ' is invalid.';
                continue;
            }
            $type = strtolower(trim((string)($raw['type'] ?? '')));
            if (!in_array($type, self::types(), true) || (!$allowPatterns && $type === 'pattern')) {
                $errors[] = 'Block ' . ($index + 1) . ' uses an unsupported block type.';
                continue;
            }
            $id = strtolower(trim((string)($raw['id'] ?? '')));
            if (!preg_match('/^[a-z0-9]{8,32}$/D', $id) || isset($seenIds[$id])) {
                $errors[] = 'Block ' . ($index + 1) . ' has an invalid identifier.';
                continue;
            }
            $seenIds[$id] = true;
            $block = match ($type) {
                'hero' => self::hero($raw, $id, $errors),
                'values' => self::values($raw, $id, $errors),
                'cards' => self::cards($raw, $id, $errors),
                'gallery' => self::gallery($raw, $id, $errors),
                'testimonials' => self::testimonials($raw, $id, $errors),
                'faq' => self::faq($raw, $id, $errors),
                'stats' => self::stats($raw, $id, $errors),
                'custom' => self::custom($raw, $id, $errors),
                'latest_posts' => self::latestPosts($raw, $id, $errors),
                'collection' => self::collection($raw, $id, $errors),
                'cta' => self::cta($raw, $id, $errors),
                'pattern' => self::pattern($raw, $id, $errors),
            };
            if ($block !== null) {
                if ($type !== 'pattern') {
                    $block = array_merge($block, self::sectionStyle($raw));
                    if (!empty($raw['_detach_assets'])) $block['_detach_assets'] = true;
                }
                $blocks[] = $block;
            }
        }

        $jsonOut = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($jsonOut)) $jsonOut = '[]';
        return ['blocks' => $blocks, 'json' => $jsonOut, 'errors' => array_values(array_unique($errors))];
    }

    /** @param list<array<string,mixed>> $blocks
     *  @return array{blocks:list<array<string,mixed>>,new_assets:list<string>}
     */
    public static function applyUploads(array $blocks, array $files, array $input = [], string $assetKind = 'page-block'): array
    {
        $newAssets = [];
        foreach ($blocks as &$block) {
            $id = (string)$block['id'];
            if ($block['type'] === 'hero') {
                if (!empty($block['remove_image'])) $block['image_path'] = '';
                $field = 'page_block_' . $id . '_image';
                $mediaField = 'page_block_' . $id . '_media_id';
                if (self::hasUpload($files[$field] ?? null)) {
                    $path = SiteAssets::storeImage($files[$field], $assetKind);
                    $block['image_path'] = $path;
                    $newAssets[] = $path;
                } elseif ((int)($input[$mediaField] ?? $block['_media_id'] ?? 0) > 0) {
                    $path = MediaLibrary::duplicateForUsage((int)($input[$mediaField] ?? $block['_media_id']), $assetKind);
                    $block['image_path'] = $path;
                    $newAssets[] = $path;
                }
                unset($block['remove_image'], $block['_media_id']);
            }
            if ($block['type'] === 'custom') {
                if (!empty($block['remove_image'])) $block['image_path'] = '';
                $field = 'page_block_' . $id . '_image';
                $mediaField = 'page_block_' . $id . '_media_id';
                if (self::hasUpload($files[$field] ?? null)) {
                    $path = SiteAssets::storeImage($files[$field], $assetKind);
                    $block['image_path'] = $path;
                    $newAssets[] = $path;
                } elseif ((int)($input[$mediaField] ?? $block['_media_id'] ?? 0) > 0) {
                    $path = MediaLibrary::duplicateForUsage((int)($input[$mediaField] ?? $block['_media_id']), $assetKind);
                    $block['image_path'] = $path;
                    $newAssets[] = $path;
                }
                unset($block['remove_image'], $block['_media_id']);
            }
            if ($block['type'] === 'cards') {
                foreach ($block['items'] as $itemIndex => &$item) {
                    if (!empty($item['remove_image'])) $item['image_path'] = '';
                    $field = 'page_block_' . $id . '_card_' . ($itemIndex + 1) . '_image';
                    $mediaField = 'page_block_' . $id . '_card_' . ($itemIndex + 1) . '_media_id';
                    if (self::hasUpload($files[$field] ?? null)) {
                        $path = SiteAssets::storeImage($files[$field], $assetKind);
                        $item['image_path'] = $path;
                        $newAssets[] = $path;
                    } elseif ((int)($input[$mediaField] ?? $item['_media_id'] ?? 0) > 0) {
                        $path = MediaLibrary::duplicateForUsage((int)($input[$mediaField] ?? $item['_media_id']), $assetKind);
                        $item['image_path'] = $path;
                        $newAssets[] = $path;
                    }
                    unset($item['remove_image'], $item['_media_id']);
                }
                unset($item);
            }
            if ($block['type'] === 'gallery') {
                foreach ($block['items'] as $itemIndex => &$item) {
                    if (!empty($item['remove_image'])) $item['image_path'] = '';
                    $field = 'page_block_' . $id . '_gallery_' . ($itemIndex + 1) . '_image';
                    $mediaField = 'page_block_' . $id . '_gallery_' . ($itemIndex + 1) . '_media_id';
                    if (self::hasUpload($files[$field] ?? null)) {
                        $path = SiteAssets::storeImage($files[$field], $assetKind);
                        $item['image_path'] = $path;
                        $newAssets[] = $path;
                    } elseif ((int)($input[$mediaField] ?? $item['_media_id'] ?? 0) > 0) {
                        $path = MediaLibrary::duplicateForUsage((int)($input[$mediaField] ?? $item['_media_id']), $assetKind);
                        $item['image_path'] = $path;
                        $newAssets[] = $path;
                    }
                    unset($item['remove_image'], $item['_media_id']);
                }
                unset($item);
            }
        }
        unset($block);

        // Blocks pasted from another page carry an explicit detachment marker.
        // Give them their own stored images once, so later edits/deletions on the
        // source page cannot remove assets still needed by this page.
        if (in_array($assetKind, ['page-block','pattern'], true)) {
            foreach ($blocks as &$block) {
                if (empty($block['_detach_assets'])) continue;
                if (in_array(($block['type'] ?? ''), ['hero','custom'], true)) {
                    $path = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
                    if ($path !== '' && !in_array($path, $newAssets, true)) {
                        $block['image_path'] = SiteAssets::duplicateStoredImage($path, $assetKind);
                        $newAssets[] = $block['image_path'];
                    }
                }
                if (in_array(($block['type'] ?? ''), ['cards','gallery'], true)) {
                    foreach (($block['items'] ?? []) as &$item) {
                        if (!is_array($item)) continue;
                        $path = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? ''));
                        if ($path !== '' && !in_array($path, $newAssets, true)) {
                            $item['image_path'] = SiteAssets::duplicateStoredImage($path, $assetKind);
                            $newAssets[] = $item['image_path'];
                        }
                    }
                    unset($item);
                }
                unset($block['_detach_assets']);
            }
            unset($block);
        } else {
            foreach ($blocks as &$block) unset($block['_detach_assets']);
            unset($block);
        }

        // Regular patterns are copied into a page. Give the page its own image
        // files so deleting or editing the source pattern can never break it.
        if ($assetKind === 'page-block') {
            foreach ($blocks as &$block) {
                if (in_array(($block['type'] ?? ''), ['hero','custom'], true)) {
                    $path = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
                    if ($path !== '' && str_starts_with(basename($path), 'pattern-')) {
                        $block['image_path'] = SiteAssets::duplicateStoredImage($path, 'page-block');
                        $newAssets[] = $block['image_path'];
                    }
                }
                if (in_array(($block['type'] ?? ''), ['cards','gallery'], true)) {
                    foreach (($block['items'] ?? []) as &$item) {
                        if (!is_array($item)) continue;
                        $path = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? ''));
                        if ($path !== '' && str_starts_with(basename($path), 'pattern-')) {
                            $item['image_path'] = SiteAssets::duplicateStoredImage($path, 'page-block');
                            $newAssets[] = $item['image_path'];
                        }
                    }
                    unset($item);
                }
            }
            unset($block);
        }
        return ['blocks' => $blocks, 'new_assets' => array_values(array_unique($newAssets))];
    }

    /** @param list<array<string,mixed>> $blocks @return list<string> */
    public static function assetPaths(array $blocks): array
    {
        $paths = [];
        foreach ($blocks as $block) {
            if (in_array(($block['type'] ?? ''), ['hero','custom'], true)) {
                $path = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
                if ($path !== '') $paths[] = $path;
            }
            if (in_array(($block['type'] ?? ''), ['cards','gallery'], true)) {
                foreach (($block['items'] ?? []) as $item) {
                    if (!is_array($item)) continue;
                    $path = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? ''));
                    if ($path !== '') $paths[] = $path;
                }
            }
        }
        return array_values(array_unique($paths));
    }

    /** @return array{pages:int,patterns:int,total:int} */
    public static function modelUsage(string $modelKey): array
    {
        $modelKey = ContentModels::fieldKey($modelKey);
        if ($modelKey === '') return ['pages'=>0,'patterns'=>0,'total'=>0];
        $db = Database::connection();
        $modelNeedle = '%' . $modelKey . '%';
        $counts = ['pages'=>0,'patterns'=>0,'total'=>0];
        foreach ([['pages','pages','deleted_at IS NULL'],['patterns','page_patterns','1=1']] as [$bucket,$table,$where]) {
            try {
                // Keep the SQL pre-filter broad enough to tolerate imported/pretty-printed JSON.
                // The decoded block check below is authoritative, so false positives never count.
                $stmt = $db->prepare("SELECT blocks_json FROM {$table} WHERE {$where} AND blocks_json LIKE ? AND blocks_json LIKE ?");
                $stmt->execute(['%"model_key"%', $modelNeedle]);
                foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [] as $json) {
                    foreach (self::decode((string)$json) as $block) {
                        if (is_array($block) && ($block['type'] ?? '') === 'collection' && ContentModels::fieldKey((string)($block['model_key'] ?? '')) === $modelKey) {
                            $counts[$bucket]++;
                            break;
                        }
                    }
                }
            } catch (\Throwable) {
                // Older installs may not have all Page Builder tables yet.
            }
        }
        $counts['total'] = $counts['pages'] + $counts['patterns'];
        return $counts;
    }

    /** @param list<array<string,mixed>> $blocks @return list<array<string,mixed>> */
    public static function renderBlocks(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || array_key_exists('enabled', $block) && empty($block['enabled'])) continue;
            if (($block['type'] ?? '') === 'collection') {
                $resolved = ContentPresentation::resolveCollection($block);
                if ($resolved !== null) $out[] = $resolved;
                continue;
            }
            if (($block['type'] ?? '') !== 'pattern') {
                $out[] = $block;
                continue;
            }
            $pattern = PagePatterns::find((int)($block['pattern_id'] ?? 0));
            if (!$pattern) continue;
            foreach (($pattern['blocks'] ?? []) as $inner) {
                if (!is_array($inner) || array_key_exists('enabled', $inner) && empty($inner['enabled'])) continue;
                if (($inner['type'] ?? '') === 'pattern') continue;
                if (($inner['type'] ?? '') === 'collection') {
                    $resolved = ContentPresentation::resolveCollection($inner);
                    if ($resolved !== null) $out[] = $resolved;
                    continue;
                }
                $out[] = $inner;
            }
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public static function legacyHome(array $home): array
    {
        $blocks = [];
        $blocks[] = [
            'id' => 'legacyhero01', 'enabled' => true, 'type' => 'hero',
            'eyebrow' => (string)($home['homepage.eyebrow'] ?? ''),
            'heading' => (string)($home['homepage.heading'] ?? ''),
            'intro' => (string)($home['homepage.intro'] ?? ''),
            'primary_enabled' => ($home['homepage.primary_enabled'] ?? '0') === '1',
            'primary_label' => (string)($home['homepage.primary_label'] ?? ''),
            'primary_url' => (string)($home['homepage.primary_url'] ?? '/'),
            'secondary_enabled' => ($home['homepage.secondary_enabled'] ?? '0') === '1',
            'secondary_label' => (string)($home['homepage.secondary_label'] ?? ''),
            'secondary_url' => (string)($home['homepage.secondary_url'] ?? '/'),
            'image_path' => HomePage::safeStoredAssetPath((string)($home['homepage.hero_image_path'] ?? '')),
            'image_alt' => HomePage::safeStoredAssetPath((string)($home['homepage.hero_image_path'] ?? '')) === '/assets/demo/talvoro-home-hero.webp'
                ? 'Warm workspace with a notebook, coffee and leafy branches'
                : '',
            'style_tone' => 'default', 'style_width' => 'wide', 'style_spacing' => 'compact',
            'style_alignment' => 'left', 'style_variant' => 'default',
        ];
        if (($home['homepage.values_enabled'] ?? '0') === '1') {
            $icons = ['sparkles','home','award','shield','support'];
            $items = [];
            for ($i = 1; $i <= 5; $i++) {
                $items[] = [
                    'icon' => $icons[$i - 1],
                    'title' => (string)($home['homepage.value' . $i . '_title'] ?? ''),
                    'body' => (string)($home['homepage.value' . $i . '_body'] ?? ''),
                ];
            }
            $blocks[] = [
                'id' => 'legacyvalues01', 'enabled' => true, 'type' => 'values', 'items' => $items,
                'style_tone' => 'default', 'style_width' => 'wide', 'style_spacing' => 'compact',
                'style_alignment' => 'left', 'style_variant' => 'default',
            ];
        }
        if (($home['homepage.featured_enabled'] ?? '0') === '1') {
            $items = [];
            for ($i = 1; $i <= 4; $i++) {
                $prefix = 'homepage.featured_card' . $i;
                if (($home[$prefix . '_enabled'] ?? '0') !== '1') continue;
                $items[] = [
                    'title' => (string)($home[$prefix . '_title'] ?? ''),
                    'meta' => (string)($home[$prefix . '_meta'] ?? ''),
                    'url' => (string)($home[$prefix . '_url'] ?? ''),
                    'image_path' => HomePage::safeStoredAssetPath((string)($home[$prefix . '_image_path'] ?? '')),
                    'image_alt' => (string)($home[$prefix . '_image_alt'] ?? ''),
                ];
            }
            $blocks[] = [
                'id' => 'legacycards001', 'enabled' => true, 'type' => 'cards',
                'eyebrow' => (string)($home['homepage.featured_eyebrow'] ?? ''),
                'heading' => (string)($home['homepage.featured_heading'] ?? ''),
                'view_label' => (string)($home['homepage.featured_view_label'] ?? ''),
                'view_url' => (string)($home['homepage.featured_view_url'] ?? ''),
                'items' => $items,
                'style_tone' => 'default', 'style_width' => 'wide', 'style_spacing' => 'normal',
                'style_alignment' => 'left', 'style_variant' => 'default',
            ];
        }
        if (($home['homepage.latest_posts_enabled'] ?? '0') === '1') {
            $blocks[] = [
                'id' => 'legacylatest01', 'enabled' => true, 'type' => 'latest_posts',
                'eyebrow' => (string)($home['homepage.latest_posts_eyebrow'] ?? ''),
                'heading' => (string)($home['homepage.latest_posts_heading'] ?? 'Latest news'),
                'view_label' => (string)($home['homepage.latest_posts_view_label'] ?? 'View all news'),
                'count' => max(1, min(6, (int)($home['homepage.latest_posts_count'] ?? 3))),
                'style_tone' => 'default', 'style_width' => 'wide', 'style_spacing' => 'normal',
                'style_alignment' => 'left', 'style_variant' => 'default',
            ];
        }
        if (($home['homepage.cta_enabled'] ?? '0') === '1') {
            $blocks[] = [
                'id' => 'legacycta001', 'enabled' => true, 'type' => 'cta',
                'eyebrow' => (string)($home['homepage.cta_eyebrow'] ?? ''),
                'heading' => (string)($home['homepage.cta_heading'] ?? ''),
                'button_label' => (string)($home['homepage.cta_button_label'] ?? ''),
                'button_url' => (string)($home['homepage.cta_button_url'] ?? ''),
                'style_tone' => 'soft', 'style_width' => 'wide', 'style_spacing' => 'normal',
                'style_alignment' => 'left', 'style_variant' => 'default',
            ];
        }
        return $blocks;
    }

    /** @return array{style_tone:string,style_width:string,style_spacing:string,style_alignment:string,style_variant:string} */
    public static function sectionStyle(array $raw): array
    {
        $type = (string)($raw['type'] ?? '');
        $isLegacyHome = str_starts_with((string)($raw['id'] ?? ''), 'legacy');
        $defaultWidth = $isLegacyHome ? 'wide' : 'normal';
        $defaultSpacing = $isLegacyHome && in_array($type, ['hero','values'], true) ? 'compact' : 'normal';
        $tone = in_array((string)($raw['style_tone'] ?? ''), ['default','soft','accent','dark'], true) ? (string)$raw['style_tone'] : 'default';
        $width = in_array((string)($raw['style_width'] ?? ''), ['normal','wide','full'], true) ? (string)$raw['style_width'] : $defaultWidth;
        $spacing = in_array((string)($raw['style_spacing'] ?? ''), ['compact','normal','spacious'], true) ? (string)$raw['style_spacing'] : $defaultSpacing;
        $alignment = in_array((string)($raw['style_alignment'] ?? ''), ['left','center'], true) ? (string)$raw['style_alignment'] : 'left';
        $variants = self::variants($type);
        $variant = (string)($raw['style_variant'] ?? 'default');
        if (!isset($variants[$variant])) $variant = 'default';
        return ['style_tone' => $tone, 'style_width' => $width, 'style_spacing' => $spacing, 'style_alignment' => $alignment, 'style_variant' => $variant];
    }

    /** @return array<string,string> */
    public static function variants(string $type): array
    {
        return match ($type) {
            'hero' => ['default' => 'Split', 'centered' => 'Centered', 'minimal' => 'Minimal'],
            'cards' => ['default' => 'Standard', 'editorial' => 'Editorial', 'compact' => 'Compact'],
            'testimonials' => ['default' => 'Cards', 'quote' => 'Editorial quote'],
            'stats' => ['default' => 'Cards', 'inline' => 'Inline'],
            'cta' => ['default' => 'Band', 'minimal' => 'Minimal'],
            'collection' => ['default' => 'Standard', 'compact' => 'Compact'],
            default => ['default' => 'Default'],
        };
    }

    public static function sectionClasses(array $block, string $base): string
    {
        $style = self::sectionStyle($block);
        $classes = array_filter(preg_split('/\s+/', trim($base)) ?: []);
        $classes[] = 'talvoro-section';
        $classes[] = 'talvoro-tone-' . $style['style_tone'];
        $classes[] = 'talvoro-width-' . $style['style_width'];
        $classes[] = 'talvoro-spacing-' . $style['style_spacing'];
        $classes[] = 'talvoro-align-' . $style['style_alignment'];
        $classes[] = 'talvoro-variant-' . $style['style_variant'];
        return implode(' ', array_values(array_unique($classes)));
    }

    public static function iconSvg(string $icon): string
    {
        $body = match ($icon) {
            'home' => '<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V21h14V10.5"/><path d="M9 21v-6h6v6"/>',
            'award' => '<circle cx="12" cy="8" r="5"/><path d="m9 13-1.5 8L12 18l4.5 3-1.5-8"/><path d="m10.2 8 1.1 1.1 2.5-2.5"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'shield' => '<path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
            'paw' => '<circle cx="7" cy="8" r="2"/><circle cx="17" cy="8" r="2"/><circle cx="5" cy="13" r="2"/><circle cx="19" cy="13" r="2"/><path d="M8 18c0-3 1.8-5 4-5s4 2 4 5c0 2-1.4 3-4 3s-4-1-4-3Z"/>',
            'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9L12 3Z"/>',
            'leaf' => '<path d="M20 4C10 4 5 8.5 5 15c0 3 2 5 5 5 6.5 0 10-6 10-16Z"/><path d="M4 21c3-6 7-10 13-13"/>',
            'sparkles' => '<path d="m12 3 1.2 3.8L17 8l-3.8 1.2L12 13l-1.2-3.8L7 8l3.8-1.2L12 3Z"/><path d="m19 14 .7 2.3L22 17l-2.3.7L19 20l-.7-2.3L16 17l2.3-.7L19 14Z"/><path d="m5 13 .7 2.3L8 16l-2.3.7L5 19l-.7-2.3L2 16l2.3-.7L5 13Z"/>',
            'support' => '<path d="M4 13c2-4 4-6 8-6s6 2 8 6"/><path d="M4 13v4a2 2 0 0 0 2 2h2v-7H6a2 2 0 0 0-2 1Z"/><path d="M20 13v4a2 2 0 0 1-2 2h-2v-7h2a2 2 0 0 1 2 1Z"/><path d="M16 19c-1 2-2 2-4 2"/>',
            default => '<path d="M20.8 5.9c0 6.2-8.8 13-8.8 13S3.2 12.1 3.2 5.9A4.7 4.7 0 0 1 12 3.6a4.7 4.7 0 0 1 8.8 2.3Z"/>',
        };
        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
    }

    /** @param list<string> $errors */
    private static function hero(array $raw, string $id, array &$errors): array
    {
        $block = [
            'id' => $id, 'type' => 'hero', 'enabled' => !array_key_exists('enabled', $raw) || !empty($raw['enabled']),
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 300),
            'intro' => self::text($raw, 'intro', 1400),
            'primary_enabled' => !empty($raw['primary_enabled']),
            'primary_label' => self::text($raw, 'primary_label', 80),
            'primary_url' => self::url($raw['primary_url'] ?? ''),
            'secondary_enabled' => !empty($raw['secondary_enabled']),
            'secondary_label' => self::text($raw, 'secondary_label', 80),
            'secondary_url' => self::url($raw['secondary_url'] ?? ''),
            'image_path' => HomePage::safeStoredAssetPath((string)($raw['image_path'] ?? '')),
            '_media_id' => max(0, (int)($raw['_media_id'] ?? 0)),
            'image_alt' => self::text($raw, 'image_alt', 180),
            'remove_image' => !empty($raw['remove_image']),
        ];
        if ($block['enabled'] && $block['heading'] === '') $errors[] = 'Hero blocks need a heading.';
        if ($block['enabled'] && $block['primary_enabled'] && ($block['primary_label'] === '' || $block['primary_url'] === '')) $errors[] = 'Hero primary buttons need a label and valid URL.';
        if ($block['enabled'] && $block['secondary_enabled'] && ($block['secondary_label'] === '' || $block['secondary_url'] === '')) $errors[] = 'Hero secondary buttons need a label and valid URL.';
        return $block;
    }

    /** @param list<string> $errors */
    private static function values(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) continue;
            $icon = (string)($item['icon'] ?? 'heart');
            if (!isset(self::icons()[$icon])) $icon = 'heart';
            $title = self::text($item, 'title', 100);
            $body = self::text($item, 'body', 420);
            if ($title === '' || $body === '') continue;
            $items[] = ['icon' => $icon, 'title' => $title, 'body' => $body];
        }
        if ((!array_key_exists('enabled', $raw) || !empty($raw['enabled'])) && count($items) < 1) $errors[] = 'Trust/value blocks need at least one complete item.';
        return ['id' => $id, 'type' => 'values', 'enabled' => !array_key_exists('enabled', $raw) || !empty($raw['enabled']), 'items' => $items];
    }

    /** @param list<string> $errors */
    private static function cards(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) continue;
            $title = self::text($item, 'title', 120);
            if ($title === '') continue;
            $items[] = [
                'title' => $title,
                'meta' => self::text($item, 'meta', 80),
                'url' => self::url($item['url'] ?? ''),
                'image_path' => HomePage::safeStoredAssetPath((string)($item['image_path'] ?? '')),
                '_media_id' => max(0, (int)($item['_media_id'] ?? 0)),
                'image_alt' => self::text($item, 'image_alt', 180),
                'remove_image' => !empty($item['remove_image']),
            ];
        }
        if ((!array_key_exists('enabled', $raw) || !empty($raw['enabled'])) && count($items) < 1) $errors[] = 'Featured card blocks need at least one card with a title.';
        return [
            'id' => $id, 'type' => 'cards', 'enabled' => !array_key_exists('enabled', $raw) || !empty($raw['enabled']),
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'view_label' => self::text($raw, 'view_label', 80),
            'view_url' => self::url($raw['view_url'] ?? ''),
            'items' => $items,
        ];
    }

    /** @param list<string> $errors */
    private static function gallery(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) continue;
            $items[] = [
                'caption' => self::text($item, 'caption', 160),
                'image_path' => HomePage::safeStoredAssetPath((string)($item['image_path'] ?? '')),
                '_media_id' => max(0, (int)($item['_media_id'] ?? 0)),
                'image_alt' => self::text($item, 'image_alt', 180),
                'remove_image' => !empty($item['remove_image']),
            ];
        }
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        if ($enabled && count($items) < 1) $errors[] = 'Gallery blocks need at least one image slot.';
        return [
            'id' => $id, 'type' => 'gallery', 'enabled' => $enabled,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'layout' => in_array((string)($raw['layout'] ?? ''), ['grid','masonry'], true) ? (string)$raw['layout'] : 'grid',
            'items' => $items,
        ];
    }

    /** @param list<string> $errors */
    private static function testimonials(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];
        $items = [];
        foreach ($itemsRaw as $item) {
            if (!is_array($item)) continue;
            $quote = self::text($item, 'quote', 900);
            $name = self::text($item, 'name', 120);
            if ($quote === '' && $name === '') continue;
            $items[] = ['quote' => $quote, 'name' => $name, 'role' => self::text($item, 'role', 160)];
        }
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        if ($enabled && count($items) < 1) $errors[] = 'Testimonial blocks need at least one quote.';
        return [
            'id' => $id, 'type' => 'testimonials', 'enabled' => $enabled,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'items' => $items,
        ];
    }

    /** @param list<string> $errors */
    private static function faq(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, 8) : [];
        $items = [];
        foreach ($itemsRaw as $itemIndex => $item) {
            if (!is_array($item)) continue;
            $question = self::text($item, 'question', 240);
            $answer = self::text($item, 'answer', 1600);
            if ($question === '' && $answer === '') continue;
            if ($question === '' || $answer === '') {
                $errors[] = 'FAQ item ' . ((int)$itemIndex + 1) . ' needs both a question and an answer.';
            }
            $items[] = ['question' => $question, 'answer' => $answer];
        }
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        if ($enabled && count($items) < 1) $errors[] = 'FAQ blocks need at least one question and answer.';
        return [
            'id' => $id, 'type' => 'faq', 'enabled' => $enabled,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'items' => $items,
        ];
    }

    /** @param list<string> $errors */
    private static function stats(array $raw, string $id, array &$errors): array
    {
        $itemsRaw = is_array($raw['items'] ?? null) ? array_slice($raw['items'], 0, self::MAX_ITEMS) : [];
        $items = [];
        foreach ($itemsRaw as $itemIndex => $item) {
            if (!is_array($item)) continue;
            $value = self::text($item, 'value', 60);
            $label = self::text($item, 'label', 120);
            if ($value === '' && $label === '') continue;
            if ($value === '' || $label === '') {
                $errors[] = 'Statistics item ' . ((int)$itemIndex + 1) . ' needs both a value and a label.';
            }
            $items[] = ['value' => $value, 'label' => $label, 'body' => self::text($item, 'body', 300)];
        }
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        if ($enabled && count($items) < 1) $errors[] = 'Statistics blocks need at least one metric.';
        return [
            'id' => $id, 'type' => 'stats', 'enabled' => $enabled,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'items' => $items,
        ];
    }

    /** @param list<string> $errors */
    private static function custom(array $raw, string $id, array &$errors): array
    {
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        $layout = (string)($raw['layout'] ?? 'stacked');
        if (!in_array($layout, ['stacked','centered','split-left','split-right'], true)) $layout = 'stacked';
        $tone = (string)($raw['tone'] ?? 'plain');
        if (!in_array($tone, ['plain','soft','accent'], true)) $tone = 'plain';
        $heading = self::text($raw, 'heading', 300);
        $body = self::text($raw, 'body', 2600);
        $primaryEnabled = !empty($raw['primary_enabled']);
        $secondaryEnabled = !empty($raw['secondary_enabled']);
        $block = [
            'id' => $id, 'type' => 'custom', 'enabled' => $enabled,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => $heading,
            'body' => $body,
            'layout' => $layout,
            'tone' => $tone,
            'primary_enabled' => $primaryEnabled,
            'primary_label' => self::text($raw, 'primary_label', 80),
            'primary_url' => self::url($raw['primary_url'] ?? ''),
            'secondary_enabled' => $secondaryEnabled,
            'secondary_label' => self::text($raw, 'secondary_label', 80),
            'secondary_url' => self::url($raw['secondary_url'] ?? ''),
            'image_path' => HomePage::safeStoredAssetPath((string)($raw['image_path'] ?? '')),
            '_media_id' => max(0, (int)($raw['_media_id'] ?? 0)),
            'image_alt' => self::text($raw, 'image_alt', 180),
            'remove_image' => !empty($raw['remove_image']),
        ];
        if ($enabled && $heading === '' && $body === '') $errors[] = 'Custom sections need a heading or body text.';
        if ($enabled && $primaryEnabled && ($block['primary_label'] === '' || $block['primary_url'] === '')) $errors[] = 'Custom-section primary buttons need a label and valid URL.';
        if ($enabled && $secondaryEnabled && ($block['secondary_label'] === '' || $block['secondary_url'] === '')) $errors[] = 'Custom-section secondary buttons need a label and valid URL.';
        return $block;
    }

    /** @param list<string> $errors */
    private static function latestPosts(array $raw, string $id, array &$errors): array
    {
        $heading = self::text($raw, 'heading', 180);
        if ((!array_key_exists('enabled', $raw) || !empty($raw['enabled'])) && $heading === '') $errors[] = 'Latest blog posts blocks need a heading.';
        return [
            'id' => $id, 'type' => 'latest_posts', 'enabled' => !array_key_exists('enabled', $raw) || !empty($raw['enabled']),
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => $heading,
            'view_label' => self::text($raw, 'view_label', 80),
            'count' => max(1, min(6, (int)($raw['count'] ?? 3))),
        ];
    }

    /** @param list<string> $errors */
    private static function collection(array $raw, string $id, array &$errors): array
    {
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        $modelKey = ContentModels::fieldKey((string)($raw['model_key'] ?? ''));
        $presentation = (string)($raw['presentation'] ?? 'cards');
        if (!isset(ContentPresentation::presentations()[$presentation])) $presentation = 'cards';
        $sort = in_array((string)($raw['sort'] ?? ''), ['newest','oldest','title_asc','title_desc'], true)
            ? (string)$raw['sort'] : 'newest';
        if ($enabled && $modelKey === '') $errors[] = 'Dynamic content blocks need a content model.';
        // Do not make Page Builder JSON validity depend on a live database lookup.
        // A missing/disabled/private model is surfaced in the editor and fails
        // closed at public render time, so an existing page can still be opened
        // and repaired instead of becoming impossible to save.
        return [
            'id' => $id, 'type' => 'collection', 'enabled' => $enabled,
            'model_key' => $modelKey,
            'presentation' => $presentation,
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => self::text($raw, 'heading', 220),
            'view_label' => self::text($raw, 'view_label', 80),
            'view_url' => self::url($raw['view_url'] ?? ''),
            'count' => max(1, min(12, (int)($raw['count'] ?? 6))),
            'sort' => $sort,
            'featured_only' => !empty($raw['featured_only']),
        ];
    }

    /** @param list<string> $errors */
    private static function cta(array $raw, string $id, array &$errors): array
    {
        $heading = self::text($raw, 'heading', 260);
        $label = self::text($raw, 'button_label', 80);
        $url = self::url($raw['button_url'] ?? '');
        if ((!array_key_exists('enabled', $raw) || !empty($raw['enabled'])) && $heading === '') $errors[] = 'Call-to-action blocks need a heading.';
        if ((!array_key_exists('enabled', $raw) || !empty($raw['enabled'])) && ($label === '' || $url === '')) $errors[] = 'Call-to-action blocks need a button label and valid URL.';
        return [
            'id' => $id, 'type' => 'cta', 'enabled' => !array_key_exists('enabled', $raw) || !empty($raw['enabled']),
            'eyebrow' => self::text($raw, 'eyebrow', 120),
            'heading' => $heading,
            'button_label' => $label,
            'button_url' => $url,
        ];
    }

    /** @param list<string> $errors */
    private static function pattern(array $raw, string $id, array &$errors): array
    {
        $enabled = !array_key_exists('enabled', $raw) || !empty($raw['enabled']);
        $patternId = max(0, (int)($raw['pattern_id'] ?? 0));
        if ($enabled && ($patternId < 1 || PagePatterns::find($patternId) === null)) {
            $errors[] = 'A synced pattern used by this page no longer exists.';
        }
        return ['id' => $id, 'type' => 'pattern', 'enabled' => $enabled, 'pattern_id' => $patternId];
    }

    private static function text(array $input, string $key, int $max): string
    {
        $value = trim((string)($input[$key] ?? ''));
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private static function url(mixed $raw): string
    {
        $value = trim((string)$raw);
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) return '';
        if (str_starts_with($value, '/')) return !str_starts_with($value, '//') && strlen($value) <= 1000 ? $value : '';
        if (strlen($value) > 1000 || filter_var($value, FILTER_VALIDATE_URL) === false) return '';
        $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?? ''));
        return in_array($scheme, ['http','https'], true) ? $value : '';
    }

    private static function hasUpload(mixed $file): bool
    {
        return is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    private function __construct() {}
}
