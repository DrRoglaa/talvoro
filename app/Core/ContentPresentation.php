<?php
declare(strict_types=1);

namespace CMS\Core;

/**
 * Presentation adapter for structured content.
 *
 * Content Models stay schema-first. This service translates published entries
 * into safe, theme-neutral view models that Page Builder collections and public
 * archives can render without hardcoding one controller/template per model.
 */
final class ContentPresentation
{
    /** @return array<string,string> */
    public static function presentations(): array
    {
        return [
            'cards' => 'Cards',
            'people' => 'People',
            'testimonials' => 'Testimonials',
            'pricing' => 'Pricing',
            'events' => 'Events',
            'resources' => 'Resources',
            'faq' => 'FAQ',
            'logos' => 'Partners / trust',
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function builderModels(bool $respectPermissions = true): array
    {
        $out = [];
        foreach (ContentModels::all(true) as $model) {
            if ($respectPermissions && !Gate::allowsModel((int)($model['id'] ?? 0), 'view')) continue;
            $key = (string)($model['model_key'] ?? '');
            if ($key === '') continue;
            $out[] = [
                'id' => (int)$model['id'],
                'model_key' => $key,
                'singular_name' => (string)$model['singular_name'],
                'plural_name' => (string)$model['plural_name'],
                'slug' => (string)$model['slug'],
                'is_public' => (int)$model['is_public'] === 1,
                'has_archive' => (int)$model['has_archive'] === 1,
                'has_urls' => (int)$model['has_urls'] === 1,
                'entry_count' => (int)($model['entry_count'] ?? 0),
                'recommended_presentation' => self::recommendedPresentation($key),
            ];
        }
        return $out;
    }

    public static function recommendedPresentation(string $modelKey): string
    {
        return match ($modelKey) {
            'team_member' => 'people',
            'testimonial' => 'testimonials',
            'pricing_plan' => 'pricing',
            'event', 'course_programme' => 'events',
            'resource', 'job_opening' => 'resources',
            'faq_item' => 'faq',
            'partner', 'award_certification', 'press_mention' => 'logos',
            default => 'cards',
        };
    }

    /**
     * Resolve a Page Builder collection block to safe public view data.
     * Returns null when the target is unavailable/private or no entries exist.
     *
     * @return array<string,mixed>|null
     */
    public static function resolveCollection(array $block): ?array
    {
        try {
            $modelKey = ContentModels::fieldKey((string)($block['model_key'] ?? ''));
            if ($modelKey === '') return null;
            $model = ContentModels::findByKey($modelKey);
            if (!$model || (string)$model['status'] !== 'active' || (int)$model['is_public'] !== 1) return null;

            $presentation = (string)($block['presentation'] ?? self::recommendedPresentation($modelKey));
            if (!isset(self::presentations()[$presentation])) $presentation = self::recommendedPresentation($modelKey);
            $count = max(1, min(12, (int)($block['count'] ?? 6)));
            $sort = in_array((string)($block['sort'] ?? ''), ['newest','oldest','title_asc','title_desc'], true)
                ? (string)$block['sort'] : 'newest';
            $featuredOnly = !empty($block['featured_only']);

            $entries = CustomContent::publicCollectionEntries((int)$model['id'], $count, $sort, $featuredOnly);
            if (!$entries) return null;

            $items = self::presentEntries($model, $entries, $presentation);

            $viewUrl = (string)($block['view_url'] ?? '');
            if ($viewUrl === '' && (int)$model['has_archive'] === 1) $viewUrl = '/' . rawurlencode((string)$model['slug']);

            $block['_collection'] = [
                'model' => [
                    'id' => (int)$model['id'],
                    'model_key' => $modelKey,
                    'singular_name' => (string)$model['singular_name'],
                    'plural_name' => (string)$model['plural_name'],
                    'slug' => (string)$model['slug'],
                    'has_archive' => (int)$model['has_archive'] === 1,
                    'has_urls' => (int)$model['has_urls'] === 1,
                ],
                'presentation' => $presentation,
                'items' => $items,
                'view_url' => $viewUrl,
            ];
            return $block;
        } catch (\Throwable) {
            // Public pages should fail closed when a collection dependency is
            // unavailable during an upgrade or temporary database issue.
            return null;
        }
    }

    /**
     * Normalize structured entries for public cards/listings with one batched media lookup.
     *
     * @param list<array<string,mixed>> $entries
     * @return list<array<string,mixed>>
     */
    public static function presentEntries(array $model, array $entries, ?string $presentation = null): array
    {
        $modelKey = (string)($model['model_key'] ?? '');
        $presentation = $presentation ?: self::recommendedPresentation($modelKey);
        if (!isset(self::presentations()[$presentation])) $presentation = 'cards';
        $mediaIds = [];
        foreach ($entries as $entry) {
            $mid = (int)($entry['featured_media_id'] ?? 0);
            if ($mid > 0) $mediaIds[] = $mid;
        }
        $media = MediaLibrary::responsiveBatch($mediaIds);
        $out = [];
        foreach ($entries as $entry) $out[] = self::presentEntry($model, $entry, $presentation, $media);
        return $out;
    }

    /** @param array<int,array<string,mixed>> $media */
    private static function presentEntry(array $model, array $entry, string $presentation, array $media): array
    {
        $values = is_array($entry['values'] ?? null)
            ? $entry['values']
            : CustomContent::decodedValues((string)($entry['field_values_json'] ?? ''));
        $modelKey = (string)$model['model_key'];
        $hasUrl = (int)$model['has_urls'] === 1;
        $url = $hasUrl ? CustomContent::publicUrl($model, $entry) : '';
        $featuredId = (int)($entry['featured_media_id'] ?? 0);

        $summary = self::firstText($values, [
            'summary','short_description','short_intro','excerpt','description_text','introduction','short_answer','role_description',
        ], 420);
        $meta = self::metaFor($modelKey, $values);
        $badge = self::badgeFor($modelKey, $values);
        $externalUrl = self::firstUrl($values, ['cta_url','registration_url','application_url','external_url','article_url','project_url','website','verification_url','map_url']);

        $item = [
            'id' => (int)$entry['id'],
            'title' => (string)$entry['title'],
            'url' => $url,
            'external_url' => $externalUrl,
            'summary' => $summary,
            'meta' => $meta,
            'badge' => $badge,
            'image' => $featuredId > 0 ? ($media[$featuredId] ?? null) : null,
            'published_at' => (string)($entry['published_at'] ?? ''),
            'highlighted' => !empty($values['highlighted']) || !empty($values['featured']),
        ];

        if ($presentation === 'testimonials') {
            $item['quote'] = self::firstText($values, ['quote','excerpt','summary'], 900);
            $item['person'] = self::firstText($values, ['person','author_name'], 160) ?: (string)$entry['title'];
            $item['role'] = self::firstText($values, ['role_company','role','company'], 180);
            $item['rating'] = max(0, min(5, (int)($values['rating'] ?? 0)));
        } elseif ($presentation === 'pricing') {
            $price = $values['price'] ?? $values['starting_price'] ?? null;
            $item['price'] = is_numeric($price) ? (float)$price : null;
            $item['currency'] = self::cleanText($values['currency'] ?? '', 12);
            $item['period'] = self::cleanText($values['billing_period'] ?? '', 60);
            $item['features'] = self::plainLines($values['features'] ?? '', 1000);
            $item['cta_label'] = self::cleanText($values['cta_label'] ?? '', 80) ?: 'Learn more';
            $item['cta_url'] = self::firstUrl($values, ['cta_url']) ?: $url;
        } elseif ($presentation === 'events') {
            $item['date'] = self::firstDate($values, ['starts_at','starts_on','resource_date','published_on','project_date','awarded_on']);
            $item['location'] = self::joinNonEmpty([
                self::cleanText($values['venue'] ?? '', 180),
                self::cleanText($values['location'] ?? '', 220),
            ], ' · ');
        } elseif ($presentation === 'faq') {
            $item['answer'] = self::firstText($values, ['answer','short_answer','summary'], 1600);
        } elseif ($presentation === 'people') {
            $item['role'] = self::firstText($values, ['role','role_company','department'], 180);
            $item['location'] = self::firstText($values, ['location'], 180);
        }

        return $item;
    }

    private static function metaFor(string $modelKey, array $values): string
    {
        return match ($modelKey) {
            'team_member' => self::joinNonEmpty([self::value($values,'role'), self::value($values,'location')]),
            'event' => self::joinNonEmpty([self::formatDate(self::value($values,'starts_at')), self::value($values,'venue'), self::value($values,'location')]),
            'portfolio_item' => self::joinNonEmpty([self::value($values,'client'), self::formatDate(self::value($values,'project_date'),'Y')]),
            'location' => self::joinNonEmpty([self::value($values,'city'), self::value($values,'country')]),
            'product' => self::joinNonEmpty([self::formatMoney($values['price'] ?? null, self::value($values,'currency')), self::value($values,'availability')]),
            'service' => self::formatMoney($values['starting_price'] ?? null, self::value($values,'currency'), 'From '),
            'job_opening' => self::joinNonEmpty([self::value($values,'department'), self::value($values,'location'), self::value($values,'workplace')]),
            'resource' => self::joinNonEmpty([self::value($values,'resource_type'), self::formatDate(self::value($values,'resource_date'))]),
            'partner' => self::value($values,'partner_type'),
            'award_certification' => self::joinNonEmpty([self::value($values,'issuer'), self::formatDate(self::value($values,'awarded_on'),'Y')]),
            'pricing_plan' => self::joinNonEmpty([self::formatMoney($values['price'] ?? null, self::value($values,'currency')), self::value($values,'billing_period')]),
            'course_programme' => self::joinNonEmpty([self::value($values,'level'), self::value($values,'format'), self::formatDate(self::value($values,'starts_on'))]),
            'press_mention' => self::joinNonEmpty([self::value($values,'publication'), self::formatDate(self::value($values,'published_on'))]),
            default => '',
        };
    }

    private static function badgeFor(string $modelKey, array $values): string
    {
        return match ($modelKey) {
            'product' => self::value($values,'availability'),
            'resource' => self::value($values,'resource_type'),
            'job_opening' => self::value($values,'employment_type'),
            'course_programme' => self::value($values,'format'),
            'partner' => self::value($values,'partner_type'),
            default => '',
        };
    }

    /** @return list<string> */
    private static function candidateFeaturedKeys(): array
    {
        return ['featured','highlighted'];
    }

    public static function isFeaturedValues(array $values): bool
    {
        foreach (self::candidateFeaturedKeys() as $key) if (!empty($values[$key])) return true;
        return false;
    }

    private static function firstText(array $values, array $keys, int $max): string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $values)) continue;
            $text = self::plainText($values[$key], $max);
            if ($text !== '') return $text;
        }
        return '';
    }

    private static function firstUrl(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            $raw = trim((string)($values[$key] ?? ''));
            if ($raw === '') continue;
            if (str_starts_with($raw, '/') && !str_starts_with($raw, '//') && !str_contains($raw, "\0")) return mb_substr($raw, 0, 1000);
            if (filter_var($raw, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string)parse_url($raw, PHP_URL_SCHEME)), ['http','https'], true)) return mb_substr($raw, 0, 1000);
        }
        return '';
    }

    private static function firstDate(array $values, array $keys): string
    {
        foreach ($keys as $key) {
            $raw = trim((string)($values[$key] ?? ''));
            if ($raw !== '') return self::formatDate($raw);
        }
        return '';
    }

    private static function value(array $values, string $key): string
    {
        $value = $values[$key] ?? '';
        if (is_array($value)) return self::joinNonEmpty(array_map('strval', $value), ', ');
        return self::cleanText($value, 220);
    }

    private static function plainText(mixed $value, int $max): string
    {
        if (is_array($value)) $value = implode(', ', array_map('strval', $value));
        $html = (string)$value;
        $html = preg_replace('~<(?:br\s*/?|/p|/li|/div|/h[1-6])>~i', ' ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return self::cleanText($text, $max);
    }

    private static function plainLines(mixed $value, int $max): string
    {
        if (is_array($value)) $value = implode("\n", array_map('strval', $value));
        $html = (string)$value;
        $html = preg_replace('~<(?:br\s*/?|/p|/li|/div|/h[1-6])>~i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;
        return self::cleanText(trim($text), $max);
    }

    private static function cleanText(mixed $value, int $max): string
    {
        $value = trim((string)$value);
        return mb_strlen($value) > $max ? rtrim(mb_substr($value, 0, max(1, $max - 1))) . '…' : $value;
    }

    private static function formatMoney(mixed $value, string $currency, string $prefix = ''): string
    {
        if ($value === null || $value === '' || !is_numeric($value)) return '';
        $number = (float)$value;
        $formatted = fmod($number, 1.0) === 0.0 ? number_format($number, 0, '.', ',') : number_format($number, 2, '.', ',');
        $currency = self::cleanText($currency, 12);
        return trim($prefix . ($currency !== '' ? $currency . ' ' : '') . $formatted);
    }

    private static function formatDate(string $value, string $format = 'j M Y'): string
    {
        if ($value === '') return '';
        try { return (new \DateTimeImmutable($value))->format($format); }
        catch (\Throwable) { return self::cleanText($value, 80); }
    }

    private static function joinNonEmpty(array $parts, string $separator = ' · '): string
    {
        $out = [];
        foreach ($parts as $part) {
            $part = trim((string)$part);
            if ($part !== '' && !in_array($part, $out, true)) $out[] = $part;
        }
        return implode($separator, $out);
    }

    private function __construct() {}
}
