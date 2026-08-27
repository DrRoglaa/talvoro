<?php
declare(strict_types=1);

namespace CMS\Core;

final class HomePage
{
    /** @return array<string,string> */
    public static function defaults(): array
    {
        $defaults = [
            'branding.site_name' => '',
            'branding.tagline' => 'Independent publishing',
            'branding.logo_path' => '',
            'homepage.eyebrow' => 'Make it yours.',
            'homepage.heading' => 'Create a *beautiful* place for what matters.',
            'homepage.intro' => 'Start with a clear message, shape every section around your story, and give visitors an easy path to what matters next.',
            'homepage.primary_enabled' => '1',
            'homepage.primary_label' => 'About us',
            'homepage.primary_url' => '/about',
            'homepage.secondary_enabled' => '1',
            'homepage.secondary_label' => 'Read the journal',
            'homepage.secondary_url' => '/blog',
            'homepage.hero_image_path' => '/assets/demo/talvoro-home-hero.webp',
            'homepage.values_enabled' => '1',
            'homepage.latest_posts_enabled' => '1',
            'homepage.latest_posts_eyebrow' => 'From the journal',
            'homepage.latest_posts_heading' => 'Latest stories',
            'homepage.latest_posts_view_label' => 'Read the journal',
            'homepage.latest_posts_count' => '3',
            'homepage.featured_enabled' => '1',
            'homepage.featured_eyebrow' => 'Selected',
            'homepage.featured_heading' => 'A place for your best work.',
            'homepage.featured_view_label' => 'Explore more',
            'homepage.featured_view_url' => '/about',
            'homepage.cta_enabled' => '1',
            'homepage.cta_eyebrow' => 'Your next step',
            'homepage.cta_heading' => 'Ready to shape this space around your story?',
            'homepage.cta_button_label' => 'Discover more',
            'homepage.cta_button_url' => '/about',
        ];

        $valueDefaults = [
            1 => ['Clear by design', 'Give every visitor an immediate sense of who you are and why your work matters.'],
            2 => ['Made to adapt', 'Shape sections, imagery and content around the way you actually communicate.'],
            3 => ['Thoughtful details', 'Use considered typography, spacing and structure to make every page feel intentional.'],
            4 => ['Built on trust', 'Set clear expectations and guide people toward the next useful step with confidence.'],
            5 => ['Ready to grow', 'Start simple, then add stories, services or new ideas without rebuilding everything.'],
        ];
        foreach ($valueDefaults as $i => [$title, $body]) {
            $defaults['homepage.value' . $i . '_title'] = $title;
            $defaults['homepage.value' . $i . '_body'] = $body;
        }

        $featuredDefaults = [
            1 => ['Latest story', 'Journal', '/blog', '/assets/demo/talvoro-featured-1.webp', 'Open notebook and coffee on a warm desk'],
            2 => ['Featured project', 'Selected', '/about', '/assets/demo/talvoro-featured-2.webp', 'Modern home surrounded by trees and mountains'],
            3 => ['What we offer', 'Services', '/about', '/assets/demo/talvoro-featured-3.webp', 'Warm workspace with a laptop, plant and coffee'],
            4 => ['Our story', 'About', '/about', '/assets/demo/talvoro-featured-4.webp', 'Minimal interior with framed art and a ceramic vase'],
        ];
        foreach ($featuredDefaults as $i => [$title, $meta, $url, $imagePath, $imageAlt]) {
            $defaults['homepage.featured_card' . $i . '_enabled'] = '1';
            $defaults['homepage.featured_card' . $i . '_title'] = $title;
            $defaults['homepage.featured_card' . $i . '_meta'] = $meta;
            $defaults['homepage.featured_card' . $i . '_url'] = $url;
            $defaults['homepage.featured_card' . $i . '_image_path'] = $imagePath;
            $defaults['homepage.featured_card' . $i . '_image_alt'] = $imageAlt;
        }

        return $defaults;
    }

    /** @return array<string,string> */
    public static function current(): array
    {
        $out = [];
        foreach (self::defaults() as $key => $default) {
            $out[$key] = (string)(Settings::get($key, $default) ?? $default);
        }
        return $out;
    }

    /** @return array{data:array<string,string>,errors:list<string>} */
    public static function validateBranding(array $input): array
    {
        $current = self::current();
        $data = [
            'branding.site_name' => self::text($input, 'branding_site_name', 120),
            'branding.tagline' => self::text($input, 'branding_tagline', 160),
            'branding.logo_path' => $current['branding.logo_path'],
        ];
        $errors = [];
        if ($data['branding.site_name'] !== '' && mb_strlen($data['branding.site_name']) < 2) {
            $errors[] = 'Website display name must be at least 2 characters when provided.';
        }
        return ['data' => $data, 'errors' => $errors];
    }

    /** @return array{data:array<string,string>,errors:list<string>} */
    public static function validate(array $input): array
    {
        $current = self::current();
        $data = [
            'branding.site_name' => self::text($input, 'branding_site_name', 120),
            'branding.tagline' => self::text($input, 'branding_tagline', 160),
            'branding.logo_path' => $current['branding.logo_path'],
            'homepage.eyebrow' => self::text($input, 'homepage_eyebrow', 120),
            'homepage.heading' => self::text($input, 'homepage_heading', 300),
            'homepage.intro' => self::text($input, 'homepage_intro', 1400),
            'homepage.primary_enabled' => isset($input['homepage_primary_enabled']) ? '1' : '0',
            'homepage.primary_label' => self::text($input, 'homepage_primary_label', 80),
            'homepage.primary_url' => trim((string)($input['homepage_primary_url'] ?? '')),
            'homepage.secondary_enabled' => isset($input['homepage_secondary_enabled']) ? '1' : '0',
            'homepage.secondary_label' => self::text($input, 'homepage_secondary_label', 80),
            'homepage.secondary_url' => trim((string)($input['homepage_secondary_url'] ?? '')),
            'homepage.hero_image_path' => $current['homepage.hero_image_path'],
            'homepage.values_enabled' => isset($input['homepage_values_enabled']) ? '1' : '0',
            'homepage.featured_enabled' => isset($input['homepage_featured_enabled']) ? '1' : '0',
            'homepage.featured_eyebrow' => self::text($input, 'homepage_featured_eyebrow', 120),
            'homepage.featured_heading' => self::text($input, 'homepage_featured_heading', 220),
            'homepage.featured_view_label' => self::text($input, 'homepage_featured_view_label', 80),
            'homepage.featured_view_url' => trim((string)($input['homepage_featured_view_url'] ?? '')),
            'homepage.latest_posts_enabled' => isset($input['homepage_latest_posts_enabled']) ? '1' : '0',
            'homepage.latest_posts_eyebrow' => self::text($input, 'homepage_latest_posts_eyebrow', 120),
            'homepage.latest_posts_heading' => self::text($input, 'homepage_latest_posts_heading', 180),
            'homepage.latest_posts_view_label' => self::text($input, 'homepage_latest_posts_view_label', 80),
            'homepage.latest_posts_count' => (string)max(1, min(6, (int)($input['homepage_latest_posts_count'] ?? 3))),
            'homepage.cta_enabled' => isset($input['homepage_cta_enabled']) ? '1' : '0',
            'homepage.cta_eyebrow' => self::text($input, 'homepage_cta_eyebrow', 120),
            'homepage.cta_heading' => self::text($input, 'homepage_cta_heading', 260),
            'homepage.cta_button_label' => self::text($input, 'homepage_cta_button_label', 80),
            'homepage.cta_button_url' => trim((string)($input['homepage_cta_button_url'] ?? '')),
        ];

        for ($i = 1; $i <= 5; $i++) {
            $data['homepage.value' . $i . '_title'] = self::text($input, 'homepage_value' . $i . '_title', 100);
            $data['homepage.value' . $i . '_body'] = self::text($input, 'homepage_value' . $i . '_body', 420);
        }
        for ($i = 1; $i <= 4; $i++) {
            $prefix = 'homepage.featured_card' . $i;
            $form = 'homepage_featured_card' . $i;
            $data[$prefix . '_enabled'] = isset($input[$form . '_enabled']) ? '1' : '0';
            $data[$prefix . '_title'] = self::text($input, $form . '_title', 120);
            $data[$prefix . '_meta'] = self::text($input, $form . '_meta', 80);
            $data[$prefix . '_url'] = trim((string)($input[$form . '_url'] ?? ''));
            $data[$prefix . '_image_path'] = $current[$prefix . '_image_path'];
            $data[$prefix . '_image_alt'] = self::text($input, $form . '_image_alt', 180);
        }

        $errors = [];
        if ($data['branding.site_name'] !== '' && mb_strlen($data['branding.site_name']) < 2) {
            $errors[] = 'Website display name must be at least 2 characters when provided.';
        }
        if ($data['homepage.heading'] === '') $errors[] = 'Homepage heading is required.';
        if ($data['homepage.intro'] === '') $errors[] = 'Homepage introduction is required.';

        foreach ([
            ['homepage.primary_enabled','homepage.primary_label','homepage.primary_url','Primary'],
            ['homepage.secondary_enabled','homepage.secondary_label','homepage.secondary_url','Secondary'],
        ] as [$enabledKey,$labelKey,$urlKey,$label]) {
            if ($data[$enabledKey] === '1') {
                if ($data[$labelKey] === '') $errors[] = $label . ' button label is required when the button is enabled.';
                if (!self::validUrl($data[$urlKey])) $errors[] = $label . ' button URL must be a local path or a complete http/https URL.';
            }
        }

        if ($data['homepage.values_enabled'] === '1') {
            for ($i = 1; $i <= 5; $i++) {
                if ($data['homepage.value' . $i . '_title'] === '' || $data['homepage.value' . $i . '_body'] === '') {
                    $errors[] = 'All five trust/value items need a title and description while the section is enabled.';
                    break;
                }
            }
        }

        if ($data['homepage.featured_enabled'] === '1') {
            if ($data['homepage.featured_heading'] === '') $errors[] = 'Featured section heading is required when the section is enabled.';
            if ($data['homepage.featured_view_url'] !== '' && !self::validUrl($data['homepage.featured_view_url'])) {
                $errors[] = 'Featured section link must be a local path or a complete http/https URL.';
            }
            for ($i = 1; $i <= 4; $i++) {
                $prefix = 'homepage.featured_card' . $i;
                if ($data[$prefix . '_enabled'] !== '1') continue;
                if ($data[$prefix . '_title'] === '') $errors[] = 'Featured card ' . $i . ' needs a title.';
                if ($data[$prefix . '_url'] !== '' && !self::validUrl($data[$prefix . '_url'])) {
                    $errors[] = 'Featured card ' . $i . ' link is invalid.';
                }
            }
        }

        if ($data['homepage.latest_posts_enabled'] === '1' && $data['homepage.latest_posts_heading'] === '') {
            $errors[] = 'Latest posts heading is required when the section is enabled.';
        }
        if ($data['homepage.cta_enabled'] === '1') {
            if ($data['homepage.cta_heading'] === '') $errors[] = 'Call-to-action heading is required when the section is enabled.';
            if ($data['homepage.cta_button_label'] === '') $errors[] = 'Call-to-action button label is required.';
            if (!self::validUrl($data['homepage.cta_button_url'])) $errors[] = 'Call-to-action button URL is invalid.';
        }

        return ['data' => $data, 'errors' => array_values(array_unique($errors))];
    }

    /** @return array<string,array{setting:string,kind:string,remove:string}> */
    public static function assetInputs(): array
    {
        $map = [
            'branding_logo' => ['setting' => 'branding.logo_path', 'kind' => 'logo', 'remove' => 'remove_branding_logo'],
            'homepage_hero_image' => ['setting' => 'homepage.hero_image_path', 'kind' => 'hero', 'remove' => 'remove_homepage_hero_image'],
        ];
        for ($i = 1; $i <= 4; $i++) {
            $map['homepage_featured_card' . $i . '_image'] = [
                'setting' => 'homepage.featured_card' . $i . '_image_path',
                'kind' => 'home-card',
                'remove' => 'remove_homepage_featured_card' . $i . '_image',
            ];
        }
        return $map;
    }

    /** @param array<string,string> $data */
    public static function save(array $data, int $userId): void
    {
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            foreach ($data as $key => $value) Settings::set($key, $value, $userId);
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function publicSiteName(): string
    {
        $custom = trim((string)Settings::get('branding.site_name', ''));
        if ($custom !== '') return $custom;
        return trim((string)Env::get('APP_NAME', '')) ?: 'My Website';
    }

    public static function publicTagline(): string
    {
        return trim((string)Settings::get('branding.tagline', 'Independent publishing'));
    }

    public static function logoPath(): string
    {
        return self::safeStoredAssetPath((string)Settings::get('branding.logo_path', ''));
    }

    public static function heroImagePath(): string
    {
        return self::safeStoredAssetPath((string)Settings::get('homepage.hero_image_path', ''));
    }

    public static function safeStoredAssetPath(string $value): string
    {
        $value = trim($value);
        if (preg_match('#^/uploads/site/[a-z0-9/_-]+\.(?:jpe?g|png|webp)$#D', $value) === 1) return $value;
        return preg_match('#^/assets/demo/talvoro-(?:home-hero|featured-[1-4])\.webp$#D', $value) === 1 ? $value : '';
    }

    public static function accentHeadingHtml(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return preg_replace('/\*([^*]+)\*/u', '<em>$1</em>', $escaped) ?? $escaped;
    }

    private static function text(array $input, string $key, int $max): string
    {
        $value = trim((string)($input[$key] ?? ''));
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max) : $value;
    }

    private static function validUrl(string $value): bool
    {
        if ($value === '' || str_contains($value, "\r") || str_contains($value, "\n") || str_contains($value, "\0")) return false;
        if (str_starts_with($value, '/')) return !str_starts_with($value, '//') && strlen($value) <= 1000;
        if (strlen($value) > 1000 || filter_var($value, FILTER_VALIDATE_URL) === false) return false;
        $scheme = strtolower((string)(parse_url($value, PHP_URL_SCHEME) ?? ''));
        return in_array($scheme, ['http','https'], true);
    }

    private function __construct() {}
}
