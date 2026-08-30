<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class DesignSystem
{
    private const SETTINGS_PREFIX = 'design.theme.';
    private const LEGACY_SETTINGS_PREFIX = 'design.';

    /** @return array<string,array<string,mixed>> */
    public static function definitions(): array
    {
        return [
            'brand' => ['label' => 'Primary accent', 'type' => 'color', 'default' => '#ff6b52'],
            'accent' => ['label' => 'Sea glass accent', 'type' => 'color', 'default' => '#3bcfc8'],
            'depth' => ['label' => 'Depth accent', 'type' => 'color', 'default' => '#8d7af0'],
            'background' => ['label' => 'Page background', 'type' => 'color', 'default' => '#faf7f3'],
            'surface' => ['label' => 'Surface', 'type' => 'color', 'default' => '#fffdfa'],
            'text' => ['label' => 'Text', 'type' => 'color', 'default' => '#26221f'],
            'muted' => ['label' => 'Muted text', 'type' => 'color', 'default' => '#8d837c'],
            'border' => ['label' => 'Border', 'type' => 'color', 'default' => '#eae6e2'],
            'heading_font' => ['label' => 'Heading font', 'type' => 'choice', 'default' => 'system', 'options' => self::fontPresets()],
            'body_font' => ['label' => 'Body font', 'type' => 'choice', 'default' => 'system', 'options' => self::fontPresets()],
            'type_scale' => ['label' => 'Typography scale', 'type' => 'choice', 'default' => 'balanced', 'options' => ['compact' => 'Compact', 'balanced' => 'Balanced', 'expressive' => 'Expressive']],
            'content_width' => ['label' => 'Content width', 'type' => 'integer', 'default' => '760', 'min' => 600, 'max' => 1040],
            'wide_width' => ['label' => 'Wide content width', 'type' => 'integer', 'default' => '1240', 'min' => 900, 'max' => 1600],
            'section_spacing' => ['label' => 'Default section spacing', 'type' => 'choice', 'default' => 'normal', 'options' => ['compact' => 'Compact', 'normal' => 'Normal', 'spacious' => 'Spacious']],
            'radius' => ['label' => 'Surface radius', 'type' => 'choice', 'default' => 'medium', 'options' => ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']],
            'button_radius' => ['label' => 'Button shape', 'type' => 'choice', 'default' => 'pill', 'options' => ['small' => 'Soft corners', 'medium' => 'Rounded', 'pill' => 'Pill']],
            'shadow' => ['label' => 'Surface shadow', 'type' => 'choice', 'default' => 'soft', 'options' => ['none' => 'None', 'soft' => 'Soft', 'strong' => 'Strong']],
            'link_style' => ['label' => 'Link style', 'type' => 'choice', 'default' => 'clean', 'options' => ['clean' => 'Clean', 'underline' => 'Underlined']],
        ];
    }

    /** @return array<string,string> */
    public static function values(): array
    {
        $out = [];
        foreach (self::definitions() as $key => $definition) {
            $themeValue = Settings::get(self::settingKey($key));
            // The legacy unscoped key is only a compatibility bridge for any
            // pre-release v0.15 data. New saves are always theme scoped.
            $legacyValue = $themeValue === null ? Settings::get(self::LEGACY_SETTINGS_PREFIX . $key) : null;
            $out[$key] = (string)($themeValue ?? $legacyValue ?? $definition['default']);
        }
        return self::normalize($out);
    }

    /**  array<string,string> */
    public static function valuesForTheme(string $slug): array
    {
        $slug = self::normalizeThemeSlug($slug);
        $out = [];
        foreach (self::definitions() as $key => $definition) {
            $value = Settings::get(self::settingKeyForTheme($slug, $key));
            $out[$key] = (string)($value ?? $definition['default']);
        }
        return self::normalize($out);
    }

    /** @return array{id:int,name:string,slug:string,version:string} */
    public static function activeTheme(): array
    {
        $theme = ThemeManager::active();
        return [
            'id' => (int)($theme['id'] ?? 0),
            'name' => (string)($theme['name'] ?? 'Theme'),
            'slug' => self::normalizeThemeSlug((string)($theme['slug'] ?? 'theme')),
            'version' => (string)($theme['version'] ?? ''),
        ];
    }


    /** @return array{values:array<string,string>,errors:list<string>,warnings:list<string>} */
    public static function validate(array $input): array
    {
        $values = [];
        $errors = [];
        foreach (self::definitions() as $key => $definition) {
            $raw = trim((string)($input[$key] ?? $definition['default']));
            if ($definition['type'] === 'color') {
                if (!preg_match('/^#[0-9a-fA-F]{6}$/D', $raw)) {
                    $errors[] = $definition['label'] . ' must be a six-digit hex color.';
                    $raw = (string)$definition['default'];
                }
                $raw = strtolower($raw);
            } elseif ($definition['type'] === 'choice') {
                if (!isset($definition['options'][$raw])) {
                    $errors[] = $definition['label'] . ' uses an unsupported option.';
                    $raw = (string)$definition['default'];
                }
            } elseif ($definition['type'] === 'integer') {
                $number = filter_var($raw, FILTER_VALIDATE_INT);
                if ($number === false || $number < (int)$definition['min'] || $number > (int)$definition['max']) {
                    $errors[] = sprintf('%s must be between %d and %d pixels.', $definition['label'], $definition['min'], $definition['max']);
                    $number = (int)$definition['default'];
                }
                $raw = (string)$number;
            }
            $values[$key] = $raw;
        }

        if ((int)$values['wide_width'] < (int)$values['content_width'] + 120) {
            $errors[] = 'Wide content width must be at least 120 px wider than normal content.';
        }

        return ['values' => $values, 'errors' => array_values(array_unique($errors)), 'warnings' => self::warnings($values)];
    }

    /** @param array<string,string> $values */
    public static function save(array $values, int $userId): void
    {
        $validated = self::validate($values);
        if ($validated['errors']) {
            throw new RuntimeException(implode(' ', $validated['errors']));
        }
        foreach ($validated['values'] as $key => $value) {
            Settings::set(self::settingKey($key), $value, $userId);
        }
    }

    /**  array<string,string> $values */
    public static function saveForTheme(string $slug, array $values, int $userId): void
    {
        $slug = self::normalizeThemeSlug($slug);
        $validated = self::validate($values);
        if ($validated['errors']) {
            throw new RuntimeException(implode(' ', $validated['errors']));
        }
        foreach ($validated['values'] as $key => $value) {
            Settings::set(self::settingKeyForTheme($slug, $key), $value, $userId);
        }
    }

    public static function reset(int $userId): void
    {
        foreach (self::definitions() as $key => $definition) {
            Settings::set(self::settingKey($key), (string)$definition['default'], $userId);
        }
    }

    /** @return list<string> */
    public static function warnings(?array $values = null): array
    {
        $values = $values === null ? self::values() : self::normalize($values);
        $warnings = [];
        $pairs = [
            ['text', 'background', 'Main text against page background'],
            ['text', 'surface', 'Main text against cards and surfaces'],
            ['muted', 'background', 'Muted text against page background'],
            ['muted', 'surface', 'Muted text against cards and surfaces'],
        ];
        foreach ($pairs as [$foreground, $background, $label]) {
            $ratio = self::contrastRatio($values[$foreground], $values[$background]);
            if ($ratio < 4.5) $warnings[] = $label . ' has low contrast (' . number_format($ratio, 2) . ':1). Aim for at least 4.5:1 for normal text.';
        }
        $brandWhite = self::contrastRatio('#ffffff', $values['brand']);
        if ($brandWhite < 4.5) {
            $warnings[] = 'Primary accent with white action text has low contrast (' . number_format($brandWhite, 2) . ':1). Choose a darker primary accent or use a different action treatment.';
        }
        return $warnings;
    }

    public static function css(): string
    {
        $v = self::values();
        $fonts = self::fontStacks();
        $headingFont = $fonts[$v['heading_font']] ?? $fonts['system'];
        $bodyFont = $fonts[$v['body_font']] ?? $fonts['system'];
        $scale = match ($v['type_scale']) {
            'compact' => ['h1' => 'clamp(2.15rem,5vw,4.35rem)', 'h2' => 'clamp(1.65rem,3.5vw,2.85rem)', 'h3' => 'clamp(1.2rem,2vw,1.55rem)'],
            'expressive' => ['h1' => 'clamp(3rem,8vw,7rem)', 'h2' => 'clamp(2.15rem,5vw,4.3rem)', 'h3' => 'clamp(1.35rem,2.5vw,1.85rem)'],
            default => ['h1' => 'clamp(2.6rem,6.5vw,5.6rem)', 'h2' => 'clamp(1.9rem,4vw,3.45rem)', 'h3' => 'clamp(1.25rem,2.2vw,1.7rem)'],
        };
        $spacing = ['compact' => 'clamp(1.5rem,3vw,2.5rem)', 'normal' => 'clamp(2.5rem,5vw,4.75rem)', 'spacious' => 'clamp(4rem,8vw,7.5rem)'];
        $radius = ['small' => '10px', 'medium' => '22px', 'large' => '36px'];
        $button = ['small' => '10px', 'medium' => '18px', 'pill' => '999px'];
        $shadow = ['none' => 'none', 'soft' => '0 16px 48px rgba(86,61,43,.07)', 'strong' => '0 28px 80px rgba(86,61,43,.13)'];
        $brandSoft = self::mixWithWhite($v['brand'], 0.88);
        $accentSoft = self::mixWithWhite($v['accent'], 0.9);
        $brandText = self::contrastRatio('#ffffff', $v['brand']) >= self::contrastRatio($v['text'], $v['brand']) ? '#ffffff' : $v['text'];
        $onBrand = self::contrastRatio('#ffffff', $v['brand']) >= self::contrastRatio('#111111', $v['brand']) ? '#ffffff' : '#111111';
        $darkText = self::contrastRatio('#ffffff', $v['text']) >= self::contrastRatio('#111111', $v['text']) ? '#ffffff' : '#111111';
        $linkDecoration = $v['link_style'] === 'underline' ? 'underline' : 'none';

        return <<<CSS
/* Talvoro Design System v0.15 — semantic tokens; no editor-provided CSS. */
:root{
  --talvoro-brand:{$v['brand']};--talvoro-on-brand:{$onBrand};--talvoro-accent:{$v['accent']};--talvoro-depth:{$v['depth']};--talvoro-bg:{$v['background']};--talvoro-surface:{$v['surface']};
  --talvoro-text:{$v['text']};--talvoro-muted:{$v['muted']};--talvoro-border:{$v['border']};--talvoro-brand-soft:{$brandSoft};--talvoro-accent-soft:{$accentSoft};--talvoro-action:#d85f4a;--talvoro-action-hover:#c94f3d;
  --talvoro-font-heading:{$headingFont};--talvoro-font-body:{$bodyFont};--talvoro-content-width:{$v['content_width']}px;--talvoro-wide-width:{$v['wide_width']}px;
  --talvoro-section-space:{$spacing[$v['section_spacing']]};--talvoro-radius:{$radius[$v['radius']]};--talvoro-button-radius:{$button[$v['button_radius']]};--talvoro-shadow:{$shadow[$v['shadow']]};
  --talvoro-h1:{$scale['h1']};--talvoro-h2:{$scale['h2']};--talvoro-h3:{$scale['h3']};
}
body.public-body{background:radial-gradient(900px 580px at 4% -12%,rgba(255,111,82,.12),transparent 64%),radial-gradient(760px 520px at 96% 3%,rgba(59,207,200,.10),transparent 62%),linear-gradient(180deg,#fbf8f5 0%,#f8f4f0 54%,#f4eee8 100%);color:var(--talvoro-text);font-family:var(--talvoro-font-body);}
:where(body.public-body h1,body.public-body h2,body.public-body h3,body.public-body h4){font-family:var(--talvoro-font-heading);color:inherit;}
:where(body.public-body h1){font-size:var(--talvoro-h1);}:where(body.public-body h2){font-size:var(--talvoro-h2);}:where(body.public-body h3){font-size:var(--talvoro-h3);}
body.public-body .public-main a:not(.home-pill){color:var(--talvoro-action);text-decoration:{$linkDecoration};text-decoration-thickness:.08em;text-underline-offset:.16em;}
body.public-body .home-pill,body.public-body .button{border-radius:var(--talvoro-button-radius);}
body.public-body .home-pill.primary{background:var(--talvoro-action);border-color:var(--talvoro-action);color:#ffffff;}
body.public-body .home-pill.secondary{border-color:var(--talvoro-border);color:var(--talvoro-text);}
body.public-body :where(.home-featured-card,.home-news-card,.page-testimonial,.page-faq-list details,.collection-card,.collection-price-card){border-radius:var(--talvoro-radius);box-shadow:var(--talvoro-shadow);}
.page-blocks .talvoro-section{box-sizing:border-box;width:min(calc(100% - 2rem),var(--talvoro-content-width));margin-inline:auto;padding-block:var(--talvoro-section-space);padding-inline:clamp(1rem,2.4vw,2rem);}
.page-blocks .talvoro-section.talvoro-width-wide{width:min(calc(100% - 2rem),var(--talvoro-wide-width));}
.page-blocks .talvoro-section.talvoro-width-full{width:100%;max-width:none;border-radius:0;}
.page-blocks .talvoro-section.talvoro-spacing-compact{padding-block:clamp(1.25rem,3vw,2.25rem);}
.page-blocks .talvoro-section.talvoro-spacing-normal{padding-block:var(--talvoro-section-space);}
.page-blocks .talvoro-section.talvoro-spacing-spacious{padding-block:clamp(4rem,9vw,8rem);}
.page-blocks .talvoro-section.talvoro-align-center{text-align:center;}
.page-blocks .talvoro-section.talvoro-align-center :where(.home-section-heading,.home-hero-actions){justify-content:center;}
.page-blocks .talvoro-section.talvoro-tone-soft{background:var(--talvoro-brand-soft);}
.page-blocks .talvoro-section.talvoro-tone-accent{background:var(--talvoro-brand-soft);color:var(--talvoro-text);}
.page-blocks .talvoro-section.talvoro-tone-accent :where(a,.home-section-link,.text-link){color:var(--talvoro-action);}
.page-blocks .talvoro-section.talvoro-tone-dark{background:var(--talvoro-text);color:{$darkText};}
.page-blocks .talvoro-section.talvoro-tone-dark :where(a,.home-section-link,.text-link){color:{$darkText};}
.page-blocks .page-builder-hero.talvoro-variant-centered{grid-template-columns:1fr;text-align:center;}
.page-blocks .page-builder-hero.talvoro-variant-centered .spottina-home-hero-copy{max-width:780px;margin-inline:auto;}
.page-blocks .page-builder-hero.talvoro-variant-centered .spottina-home-hero-media{max-width:860px;margin-inline:auto;}
.page-blocks .page-builder-hero.talvoro-variant-minimal .spottina-home-hero-media{display:none;}
.page-blocks .page-builder-hero.talvoro-variant-minimal{grid-template-columns:1fr;}
.page-blocks .page-builder-cards.talvoro-variant-editorial .home-featured-card:first-child{grid-column:span 2;}
.page-blocks .page-builder-cards.talvoro-variant-compact .home-featured-grid{gap:.75rem;}
.page-blocks .page-builder-cards.talvoro-variant-compact .home-featured-caption{padding:.75rem;}
.page-blocks .page-builder-testimonials.talvoro-variant-quote .page-testimonial-grid{grid-template-columns:1fr;max-width:900px;margin-inline:auto;}
.page-blocks .page-builder-testimonials.talvoro-variant-quote .page-testimonial{font-size:1.15em;}
.page-blocks .page-builder-stats.talvoro-variant-inline .page-stats-grid{display:flex;flex-wrap:wrap;justify-content:center;}
.page-blocks .page-builder-stats.talvoro-variant-inline .page-stats-grid article{box-shadow:none;background:transparent;border:0;min-width:160px;}
.page-blocks .page-builder-cta.talvoro-variant-minimal{background:transparent!important;box-shadow:none;border:0;}
.page-blocks .page-collection.talvoro-variant-compact .collection-grid{gap:.8rem;}
.page-blocks .talvoro-section.talvoro-tone-default{background:transparent;}
.talvoro-builder-preview-blocks [data-preview-block-id]{box-sizing:border-box;width:min(calc(100% - 2rem),var(--talvoro-content-width));margin-inline:auto;padding-block:var(--talvoro-section-space);padding-inline:clamp(1rem,2.4vw,2rem);}
.talvoro-builder-preview-blocks .page-builder-hero[data-style-variant="centered"]{grid-template-columns:1fr;text-align:center;}
.talvoro-builder-preview-blocks .page-builder-hero[data-style-variant="minimal"] .spottina-home-hero-media{display:none;}
.talvoro-builder-preview-blocks .page-builder-hero[data-style-variant="minimal"]{grid-template-columns:1fr;}
.talvoro-builder-preview-blocks .page-builder-cards[data-style-variant="editorial"] .home-featured-card:first-child{grid-column:span 2;}
.talvoro-builder-preview-blocks .page-builder-cards[data-style-variant="compact"] .home-featured-grid{gap:.75rem;}
.talvoro-builder-preview-blocks .page-builder-testimonials[data-style-variant="quote"] .page-testimonial-grid{grid-template-columns:1fr;}
.talvoro-builder-preview-blocks .page-builder-stats[data-style-variant="inline"] .page-stats-grid{display:flex;flex-wrap:wrap;justify-content:center;}
.talvoro-builder-preview-blocks .page-builder-cta[data-style-variant="minimal"]{background:transparent!important;box-shadow:none;border:0;}
.talvoro-builder-preview-blocks [data-style-width="wide"]{width:min(calc(100% - 2rem),var(--talvoro-wide-width));}
.talvoro-builder-preview-blocks [data-style-width="full"]{width:100%;max-width:none;border-radius:0;}
.talvoro-builder-preview-blocks [data-style-spacing="compact"]{padding-block:clamp(1.25rem,3vw,2.25rem);}
.talvoro-builder-preview-blocks [data-style-spacing="spacious"]{padding-block:clamp(4rem,9vw,8rem);}
.talvoro-builder-preview-blocks [data-style-alignment="center"]{text-align:center;}
.talvoro-builder-preview-blocks [data-style-alignment="center"] :where(.home-section-heading,.home-hero-actions){justify-content:center;}
.talvoro-builder-preview-blocks [data-style-tone="soft"]{background:var(--talvoro-brand-soft);}
.talvoro-builder-preview-blocks [data-style-tone="accent"]{background:var(--talvoro-brand-soft);color:var(--talvoro-text);}
.talvoro-builder-preview-blocks [data-style-tone="accent"] :where(a,.home-section-link,.text-link){color:var(--talvoro-action);}
.talvoro-builder-preview-blocks [data-style-tone="dark"]{background:var(--talvoro-text);color:{$darkText};}
.talvoro-builder-preview-blocks [data-style-tone="dark"] :where(a,.home-section-link,.text-link){color:{$darkText};}
@media(max-width:720px){.page-blocks .talvoro-section{width:min(calc(100% - 1rem),var(--talvoro-content-width));padding-inline:1rem;}}
CSS;
    }

    /** @return array<string,string> */
    public static function tokenExport(): array
    {
        $v = self::values();
        return [
            'color.brand' => $v['brand'], 'color.accent' => $v['accent'], 'color.depth' => $v['depth'], 'color.background' => $v['background'], 'color.surface' => $v['surface'],
            'color.text' => $v['text'], 'color.muted' => $v['muted'], 'color.border' => $v['border'],
            'font.heading' => $v['heading_font'], 'font.body' => $v['body_font'], 'typography.scale' => $v['type_scale'],
            'size.content' => $v['content_width'] . 'px', 'size.wide' => $v['wide_width'] . 'px', 'spacing.section' => $v['section_spacing'],
            'radius.surface' => $v['radius'], 'radius.button' => $v['button_radius'], 'shadow.surface' => $v['shadow'], 'link.style' => $v['link_style'],
        ];
    }

    private static function settingKey(string $token): string
    {
        return self::SETTINGS_PREFIX . self::activeTheme()['slug'] . '.' . $token;
    }

    private static function settingKeyForTheme(string $slug, string $token): string
    {
        return self::SETTINGS_PREFIX . self::normalizeThemeSlug($slug) . '.' . $token;
    }

    private static function normalizeThemeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-_');
        return $slug !== '' ? substr($slug, 0, 96) : 'theme';
    }

    /** @return array<string,string> */
    private static function fontPresets(): array
    {
        return ['system' => 'System', 'humanist' => 'Humanist', 'editorial' => 'Editorial', 'modern' => 'Modern', 'mono' => 'Monospace'];
    }

    /** @return array<string,string> */
    private static function fontStacks(): array
    {
        return [
            'system' => '-apple-system,BlinkMacSystemFont,"SF Pro Display","SF Pro Text",Inter,"Segoe UI",sans-serif',
            'humanist' => '"Avenir Next",Avenir,"Segoe UI",system-ui,sans-serif',
            'editorial' => 'Iowan Old Style,"Palatino Linotype",Palatino,"Times New Roman",serif',
            'modern' => 'Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif',
            'mono' => 'ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace',
        ];
    }

    /** @param array<string,string> $values @return array<string,string> */
    private static function normalize(array $values): array
    {
        $out = [];
        foreach (self::definitions() as $key => $definition) {
            $raw = (string)($values[$key] ?? $definition['default']);
            if ($definition['type'] === 'color' && !preg_match('/^#[0-9a-fA-F]{6}$/D', $raw)) $raw = (string)$definition['default'];
            if ($definition['type'] === 'choice' && !isset($definition['options'][$raw])) $raw = (string)$definition['default'];
            if ($definition['type'] === 'integer') {
                $n = filter_var($raw, FILTER_VALIDATE_INT);
                if ($n === false || $n < (int)$definition['min'] || $n > (int)$definition['max']) $n = (int)$definition['default'];
                $raw = (string)$n;
            }
            $out[$key] = strtolower($raw[0] ?? '') === '#' ? strtolower($raw) : $raw;
        }
        return $out;
    }

    private static function contrastRatio(string $a, string $b): float
    {
        $la = self::luminance($a); $lb = self::luminance($b);
        $light = max($la, $lb); $dark = min($la, $lb);
        return ($light + 0.05) / ($dark + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $parts = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        $linear = array_map(static function (int $c): float {
            $s = $c / 255;
            return $s <= 0.04045 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4;
        }, $parts);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    private static function mixWithWhite(string $hex, float $whiteWeight): string
    {
        $hex = ltrim($hex, '#'); $weight = max(0.0, min(1.0, $whiteWeight)); $rgb = [];
        for ($i = 0; $i < 3; $i++) {
            $channel = hexdec(substr($hex, $i * 2, 2));
            $rgb[] = (int)round($channel * (1 - $weight) + 255 * $weight);
        }
        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    private function __construct() {}
}
