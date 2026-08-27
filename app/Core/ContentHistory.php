<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

/**
 * Generic revision/autosave engine for Talvoro content entities.
 *
 * The current release registers Pages, Posts and structured entries. Future content types can join
 * the same history tables without creating type-specific revision tables.
 */
final class ContentHistory
{
    private const TYPES = ['page', 'post', 'entry'];

    public static function capture(string $type, int $contentId, ?int $authorId, string $action = 'save'): ?int
    {
        self::assertType($type);
        if ($contentId < 1) return null;
        $snapshot = self::snapshot($type, $contentId);
        if ($snapshot === null) return null;
        $json = self::encode($snapshot);
        $hash = hash('sha256', $json);
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            // Lock the newest revision (or its index gap when none exists) so
            // concurrent editors cannot race on the same revision number.
            $latest = $db->prepare(
                'SELECT id,revision_no,content_hash FROM content_revisions WHERE content_type=? AND content_id=? ORDER BY revision_no DESC LIMIT 1 FOR UPDATE'
            );
            $latest->execute([$type, $contentId]);
            $row = $latest->fetch(PDO::FETCH_ASSOC);
            if ($row && hash_equals((string)$row['content_hash'], $hash) && in_array($action, ['save', 'draft_save', 'schedule'], true)) {
                $revisionId = (int)$row['id'];
                if ($type === 'entry') self::syncEntryRevisionMediaUsage($revisionId, $snapshot);
                if ($ownsTransaction) $db->commit();
                return $revisionId;
            }

            $revisionNo = $row ? ((int)$row['revision_no'] + 1) : 1;
            $stmt = $db->prepare(
                'INSERT INTO content_revisions (content_type,content_id,revision_no,author_id,action,snapshot_json,content_hash,created_at) '
                . 'VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP())'
            );
            $stmt->execute([$type, $contentId, $revisionNo, $authorId ?: null, self::safeAction($action), $json, $hash]);
            $revisionId = (int)$db->lastInsertId();
            if ($type === 'entry') self::syncEntryRevisionMediaUsage($revisionId, $snapshot);
            if ($ownsTransaction) $db->commit();
            return $revisionId;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function list(string $type, int $contentId, int $limit = 100): array
    {
        self::assertType($type);
        $limit = max(1, min(250, $limit));
        $stmt = Database::connection()->prepare(
            'SELECT r.id,r.revision_no,r.action,r.created_at,r.author_id,u.display_name author_name '
            . 'FROM content_revisions r LEFT JOIN users u ON u.id=r.author_id '
            . 'WHERE r.content_type=? AND r.content_id=? ORDER BY r.revision_no DESC LIMIT ' . $limit
        );
        $stmt->execute([$type, $contentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function count(string $type, int $contentId): int
    {
        self::assertType($type);
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM content_revisions WHERE content_type=? AND content_id=?');
        $stmt->execute([$type, $contentId]);
        return (int)$stmt->fetchColumn();
    }

    public static function revision(string $type, int $contentId, int $revisionId): ?array
    {
        self::assertType($type);
        $stmt = Database::connection()->prepare(
            'SELECT r.*,u.display_name author_name FROM content_revisions r LEFT JOIN users u ON u.id=r.author_id '
            . 'WHERE r.id=? AND r.content_type=? AND r.content_id=? LIMIT 1'
        );
        $stmt->execute([$revisionId, $type, $contentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $decoded = json_decode((string)$row['snapshot_json'], true);
        if (!is_array($decoded)) return null;
        $row['snapshot'] = $decoded;
        return $row;
    }

    public static function compareToCurrent(string $type, int $contentId, array $snapshot): array
    {
        $current = self::snapshot($type, $contentId);
        if ($current === null) return [];
        return self::compareSnapshots($type, $snapshot, $current);
    }

    /**
     * Build an editor-facing change list between two snapshots.
     *
     * Revision history is a safety feature for people editing a website, not a
     * database inspector. Structured values such as page-builder JSON are
     * therefore translated into block/field changes before they reach the UI.
     *
     * @return list<array{group:string,label:string,kind:string,summary:string,revision:string,current:string,show_values:bool}>
     */
    public static function compareSnapshots(string $type, array $revision, array $current): array
    {
        self::assertType($type);
        $changes = [];

        if ($type === 'page') {
            $fields = [
                ['fields.title', 'Page details', 'Title', 'text'],
                ['fields.path', 'Page details', 'URL path', 'text'],
                ['fields.eyebrow', 'Page details', 'Eyebrow', 'text'],
                ['fields.excerpt', 'Page details', 'Excerpt', 'text'],
                ['fields.body_html', 'Page details', 'Page content', 'content'],
                ['fields.status', 'Publishing', 'Status', 'status'],
                ['fields.show_in_navigation', 'Navigation', 'Main menu', 'visibility'],
                ['fields.navigation_label', 'Navigation', 'Main menu label', 'text'],
                ['fields.navigation_order', 'Navigation', 'Main menu order', 'number'],
                ['fields.show_in_footer', 'Navigation', 'Footer', 'visibility'],
                ['fields.footer_label', 'Navigation', 'Footer label', 'text'],
                ['fields.footer_order', 'Navigation', 'Footer order', 'number'],
                ['fields.published_at', 'Publishing', 'Published date', 'date'],
                ['seo.meta_title', 'SEO', 'SEO title', 'text'],
                ['seo.meta_description', 'SEO', 'SEO description', 'text'],
                ['branding.branding.site_name', 'Branding', 'Website name', 'text'],
                ['branding.branding.tagline', 'Branding', 'Website tagline', 'text'],
                ['branding.branding.logo_path', 'Branding', 'Website logo', 'image'],
            ];
            self::compareFields($changes, $revision, $current, $fields);
            self::comparePageBlocks(
                $changes,
                PageBlocks::decode((string)(self::pathValue($revision, 'fields.blocks_json') ?? '[]')),
                PageBlocks::decode((string)(self::pathValue($current, 'fields.blocks_json') ?? '[]'))
            );
        } elseif ($type === 'post') {
            $fields = [
                ['fields.title', 'Post details', 'Title', 'text'],
                ['fields.slug', 'Post details', 'URL slug', 'text'],
                ['fields.excerpt', 'Post details', 'Excerpt', 'text'],
                ['fields.body_html', 'Post details', 'Post content', 'content'],
                ['fields.featured_image_path', 'Post details', 'Featured image', 'image'],
                ['fields.status', 'Publishing', 'Status', 'status'],
                ['fields.published_at', 'Publishing', 'Publish date', 'date'],
            ];
            self::compareFields($changes, $revision, $current, $fields);

            $oldCategories = array_values(array_filter(array_map('intval', (array)(self::pathValue($revision, 'categories.category_ids') ?? [])), static fn(int $id): bool => $id > 0));
            $newCategories = array_values(array_filter(array_map('intval', (array)(self::pathValue($current, 'categories.category_ids') ?? [])), static fn(int $id): bool => $id > 0));
            $oldCategorySet = $oldCategories; sort($oldCategorySet, SORT_NUMERIC);
            $newCategorySet = $newCategories; sort($newCategorySet, SORT_NUMERIC);
            if (self::normalizeCompare($oldCategorySet) !== self::normalizeCompare($newCategorySet)) {
                self::appendChange($changes, 'Categories', 'Categories', self::categoryListLabel($oldCategories), self::categoryListLabel($newCategories));
            }

            $oldPrimary = (int)(self::pathValue($revision, 'categories.primary_category_id') ?? 0);
            $newPrimary = (int)(self::pathValue($current, 'categories.primary_category_id') ?? 0);
            if ($oldPrimary !== $newPrimary) {
                self::appendChange($changes, 'Categories', 'Primary category', self::categoryLabel($oldPrimary), self::categoryLabel($newPrimary));
            }
        } else {
            $modelId = (int)(self::pathValue($current, 'fields.model_id') ?? self::pathValue($revision, 'fields.model_id') ?? 0);
            $model = $modelId > 0 ? ContentModels::find($modelId) : null;
            $group = is_array($model) ? (string)$model['singular_name'] . ' details' : 'Content details';
            self::compareFields($changes, $revision, $current, [
                ['fields.title', $group, 'Title', 'text'],
                ['fields.slug', $group, 'URL slug', 'text'],
                ['fields.status', 'Publishing', 'Status', 'status'],
                ['fields.published_at', 'Publishing', 'Publish date', 'date'],
                ['fields.featured_media_id', $group, 'Featured image', 'media'],
                ['fields.seo_title', 'SEO', 'SEO title', 'text'],
                ['fields.seo_description', 'SEO', 'SEO description', 'text'],
                ['fields.canonical_url', 'SEO', 'Canonical URL', 'text'],
                ['fields.robots', 'SEO', 'Search indexing', 'text'],
                ['fields.social_title', 'Social sharing', 'Social title', 'text'],
                ['fields.social_description', 'Social sharing', 'Social description', 'text'],
                ['fields.social_media_id', 'Social sharing', 'Social image', 'media'],
            ]);
            if ($model) {
                $oldValues = is_array($revision['values'] ?? null) ? $revision['values'] : [];
                $newValues = is_array($current['values'] ?? null) ? $current['values'] : [];
                foreach (ContentModels::fields($modelId, true) as $field) {
                    $key = (string)$field['field_key'];
                    $old = $oldValues[$key] ?? null;
                    $new = $newValues[$key] ?? null;
                    if (self::normalizeCompare($old) === self::normalizeCompare($new)) continue;
                    self::appendChange(
                        $changes,
                        'Structured fields',
                        (string)$field['label'],
                        self::friendlyStructuredValue($field, $old),
                        self::friendlyStructuredValue($field, $new)
                    );
                }
            }
        }

        return $changes;
    }

    /** @param list<array> $changes @param list<array{0:string,1:string,2:string,3:string}> $fields */
    private static function compareFields(array &$changes, array $revision, array $current, array $fields): void
    {
        foreach ($fields as [$path, $group, $label, $format]) {
            $oldRaw = self::pathValue($revision, $path);
            $newRaw = self::pathValue($current, $path);
            if (self::normalizeCompare($oldRaw) === self::normalizeCompare($newRaw)) continue;
            self::appendChange(
                $changes,
                $group,
                $label,
                self::friendlyValue($oldRaw, $format),
                self::friendlyValue($newRaw, $format)
            );
        }
    }

    /** @param list<array> $changes @param list<array<string,mixed>> $oldBlocks @param list<array<string,mixed>> $newBlocks */
    private static function comparePageBlocks(array &$changes, array $oldBlocks, array $newBlocks): void
    {
        $oldById = [];
        $newById = [];
        foreach ($oldBlocks as $index => $block) {
            if (!is_array($block)) continue;
            $id = (string)($block['id'] ?? '');
            if ($id !== '') $oldById[$id] = ['block' => $block, 'index' => $index];
        }
        foreach ($newBlocks as $index => $block) {
            if (!is_array($block)) continue;
            $id = (string)($block['id'] ?? '');
            if ($id !== '') $newById[$id] = ['block' => $block, 'index' => $index];
        }

        foreach ($oldById as $id => $oldInfo) {
            $old = $oldInfo['block'];
            $name = self::blockLabel((string)($old['type'] ?? ''));
            if (!isset($newById[$id])) {
                $changes[] = [
                    'group' => 'Page sections', 'label' => $name, 'kind' => 'removed',
                    'summary' => $name . ' was removed from the current page.',
                    'revision' => 'Present', 'current' => 'Not present', 'show_values' => false,
                ];
                continue;
            }
            $newInfo = $newById[$id];
            $new = $newInfo['block'];
            if ((int)$oldInfo['index'] !== (int)$newInfo['index']) {
                $changes[] = [
                    'group' => 'Page sections', 'label' => $name, 'kind' => 'moved',
                    'summary' => $name . ' moved from position ' . ((int)$oldInfo['index'] + 1) . ' to ' . ((int)$newInfo['index'] + 1) . '.',
                    'revision' => 'Position ' . ((int)$oldInfo['index'] + 1),
                    'current' => 'Position ' . ((int)$newInfo['index'] + 1),
                    'show_values' => false,
                ];
            }
            self::compareBlockFields($changes, $old, $new, $name);
        }

        foreach ($newById as $id => $newInfo) {
            if (isset($oldById[$id])) continue;
            $name = self::blockLabel((string)($newInfo['block']['type'] ?? ''));
            $changes[] = [
                'group' => 'Page sections', 'label' => $name, 'kind' => 'added',
                'summary' => $name . ' was added to the current page.',
                'revision' => 'Not present', 'current' => 'Present', 'show_values' => false,
            ];
        }
    }

    /** @param list<array> $changes */
    private static function compareBlockFields(array &$changes, array $old, array $new, string $name): void
    {
        $type = (string)($new['type'] ?? $old['type'] ?? '');
        if (self::normalizeCompare($old['enabled'] ?? true) !== self::normalizeCompare($new['enabled'] ?? true)) {
            self::appendChange($changes, 'Page sections', $name . ' → Visibility', self::friendlyValue($old['enabled'] ?? true, 'enabled'), self::friendlyValue($new['enabled'] ?? true, 'enabled'));
        }
        $simple = match ($type) {
            'hero' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'], ['intro', 'Introduction', 'text'],
                ['primary_enabled', 'Primary button', 'enabled'], ['primary_label', 'Primary button label', 'text'],
                ['primary_url', 'Primary button URL', 'text'], ['secondary_enabled', 'Secondary button', 'enabled'],
                ['secondary_label', 'Secondary button label', 'text'], ['secondary_url', 'Secondary button URL', 'text'],
                ['image_path', 'Hero image', 'image'], ['image_alt', 'Hero image alt text', 'text'],
            ],
            'cards' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
                ['view_label', 'View-all label', 'text'], ['view_url', 'View-all URL', 'text'],
            ],
            'gallery' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'], ['layout', 'Gallery layout', 'text'],
            ],
            'testimonials' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
            ],
            'faq' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
            ],
            'stats' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
            ],
            'custom' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'], ['body', 'Body', 'content'],
                ['layout', 'Layout', 'text'], ['tone', 'Tone', 'text'],
                ['primary_enabled', 'Primary button', 'enabled'], ['primary_label', 'Primary button label', 'text'], ['primary_url', 'Primary button URL', 'text'],
                ['secondary_enabled', 'Secondary button', 'enabled'], ['secondary_label', 'Secondary button label', 'text'], ['secondary_url', 'Secondary button URL', 'text'],
                ['image_path', 'Image', 'image'], ['image_alt', 'Image alt text', 'text'],
            ],
            'latest_posts' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
                ['view_label', 'View-all label', 'text'], ['count', 'Number of posts', 'number'],
            ],
            'cta' => [
                ['eyebrow', 'Eyebrow', 'text'], ['heading', 'Heading', 'text'],
                ['button_label', 'Button label', 'text'], ['button_url', 'Button URL', 'text'],
            ],
            'pattern' => [
                ['pattern_id', 'Synced pattern', 'pattern'],
            ],
            default => [],
        };

        foreach ($simple as [$key, $label, $format]) {
            $oldRaw = $old[$key] ?? null;
            $newRaw = $new[$key] ?? null;
            if (self::normalizeCompare($oldRaw) === self::normalizeCompare($newRaw)) continue;
            self::appendChange(
                $changes,
                'Page sections',
                $name . ' → ' . $label,
                self::friendlyValue($oldRaw, $format),
                self::friendlyValue($newRaw, $format)
            );
        }

        if ($type === 'values') {
            self::compareRepeatedItems(
                $changes,
                $name,
                (array)($old['items'] ?? []),
                (array)($new['items'] ?? []),
                [['icon', 'Icon', 'icon'], ['title', 'Title', 'text'], ['body', 'Description', 'text']]
            );
        }
        if ($type === 'cards') {
            self::compareRepeatedItems(
                $changes,
                $name,
                (array)($old['items'] ?? []),
                (array)($new['items'] ?? []),
                [['title', 'Title', 'text'], ['meta', 'Small label', 'text'], ['url', 'Link', 'text'], ['image_path', 'Image', 'image'], ['image_alt', 'Image alt text', 'text']]
            );
        }
        if ($type === 'gallery') {
            self::compareRepeatedItems($changes, $name, (array)($old['items'] ?? []), (array)($new['items'] ?? []), [['caption', 'Caption', 'text'], ['image_path', 'Image', 'image'], ['image_alt', 'Image alt text', 'text']]);
        }
        if ($type === 'testimonials') {
            self::compareRepeatedItems($changes, $name, (array)($old['items'] ?? []), (array)($new['items'] ?? []), [['quote', 'Quote', 'text'], ['name', 'Name', 'text'], ['role', 'Role / location', 'text']]);
        }
        if ($type === 'faq') {
            self::compareRepeatedItems($changes, $name, (array)($old['items'] ?? []), (array)($new['items'] ?? []), [['question', 'Question', 'text'], ['answer', 'Answer', 'text']]);
        }
        if ($type === 'stats') {
            self::compareRepeatedItems($changes, $name, (array)($old['items'] ?? []), (array)($new['items'] ?? []), [['value', 'Value', 'text'], ['label', 'Label', 'text'], ['body', 'Description', 'text']]);
        }
    }

    /** @param list<array> $changes @param list<array> $oldItems @param list<array> $newItems @param list<array{0:string,1:string,2:string}> $fields */
    private static function compareRepeatedItems(array &$changes, string $blockName, array $oldItems, array $newItems, array $fields): void
    {
        $max = max(count($oldItems), count($newItems));
        for ($i = 0; $i < $max; $i++) {
            $old = isset($oldItems[$i]) && is_array($oldItems[$i]) ? $oldItems[$i] : null;
            $new = isset($newItems[$i]) && is_array($newItems[$i]) ? $newItems[$i] : null;
            $itemLabel = $blockName . ' → Item ' . ($i + 1);
            if ($old === null && $new !== null) {
                $title = trim((string)($new['title'] ?? ''));
                $changes[] = [
                    'group' => 'Page sections', 'label' => $itemLabel, 'kind' => 'added',
                    'summary' => 'Item ' . ($i + 1) . ($title !== '' ? ' (“' . self::clipDisplay($title, 80) . '”)' : '') . ' was added.',
                    'revision' => 'Not present', 'current' => 'Present', 'show_values' => false,
                ];
                continue;
            }
            if ($old !== null && $new === null) {
                $title = trim((string)($old['title'] ?? ''));
                $changes[] = [
                    'group' => 'Page sections', 'label' => $itemLabel, 'kind' => 'removed',
                    'summary' => 'Item ' . ($i + 1) . ($title !== '' ? ' (“' . self::clipDisplay($title, 80) . '”)' : '') . ' was removed.',
                    'revision' => 'Present', 'current' => 'Not present', 'show_values' => false,
                ];
                continue;
            }
            if ($old === null || $new === null) continue;
            foreach ($fields as [$key, $label, $format]) {
                $oldRaw = $old[$key] ?? null;
                $newRaw = $new[$key] ?? null;
                if (self::normalizeCompare($oldRaw) === self::normalizeCompare($newRaw)) continue;
                self::appendChange(
                    $changes,
                    'Page sections',
                    $itemLabel . ' → ' . $label,
                    self::friendlyValue($oldRaw, $format),
                    self::friendlyValue($newRaw, $format)
                );
            }
        }
    }

    /** @param list<array> $changes */
    private static function appendChange(array &$changes, string $group, string $label, string $revision, string $current): void
    {
        $kind = 'changed';
        if ($revision === '—' && $current !== '—') $kind = 'added';
        if ($revision !== '—' && $current === '—') $kind = 'removed';
        $summary = match ($kind) {
            'added' => $label . ' was added.',
            'removed' => $label . ' was removed.',
            default => $label . ' changed.',
        };
        $changes[] = [
            'group' => $group,
            'label' => $label,
            'kind' => $kind,
            'summary' => $summary,
            'revision' => $revision,
            'current' => $current,
            'show_values' => true,
        ];
    }

    private static function friendlyValue(mixed $value, string $format): string
    {
        return match ($format) {
            'content' => self::contentText($value),
            'status' => self::statusLabel($value),
            'visibility' => ((int)$value === 1 ? 'Shown' : 'Hidden'),
            'enabled' => !empty($value) ? 'Enabled' : 'Disabled',
            'number' => trim((string)($value ?? '')) === '' ? '—' : (string)(int)$value,
            'date' => self::dateLabel($value),
            'image' => self::imageLabel($value),
            'media' => self::mediaLabel((int)$value),
            'icon' => self::iconLabel($value),
            'pattern' => self::patternLabel((int)$value),
            default => self::displayValue($value),
        };
    }

    private static function contentText(mixed $value): string
    {
        $text = html_entity_decode(strip_tags((string)($value ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\\s+/u', ' ', $text) ?: '';
        $text = trim($text);
        return $text === '' ? '—' : self::clipDisplay($text, 500);
    }

    private static function statusLabel(mixed $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') return '—';
        return match ($value) {
            'draft' => 'Draft', 'scheduled' => 'Scheduled', 'published' => 'Published',
            default => ucwords(str_replace(['_', '-'], ' ', $value)),
        };
    }

    private static function dateLabel(mixed $value): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') return '—';
        try { return Posts::displayDate($value, 'j M Y · H:i'); }
        catch (\Throwable) { return self::displayValue($value); }
    }

    private static function imageLabel(mixed $value): string
    {
        $path = trim((string)($value ?? ''));
        if ($path === '') return '—';
        $base = basename(parse_url($path, PHP_URL_PATH) ?: $path);
        return $base !== '' ? 'Image · ' . self::clipDisplay($base, 90) : 'Image set';
    }

    private static function mediaLabel(int $id): string
    {
        if ($id < 1) return '—';
        try { $asset = MediaLibrary::find($id); } catch (\Throwable) { $asset = null; }
        return is_array($asset) ? (string)($asset['original_name'] ?? ('Media #' . $id)) : 'Missing media #' . $id;
    }

    private static function iconLabel(mixed $value): string
    {
        $icon = trim((string)($value ?? ''));
        if ($icon === '') return '—';
        $icons = PageBlocks::icons();
        return $icons[$icon] ?? ucwords(str_replace(['_', '-'], ' ', $icon));
    }

    /** @param array<int,mixed> $ids */
    private static function patternLabel(int $id): string
    {
        if ($id < 1) return '—';
        try { $pattern = PagePatterns::find($id); } catch (\Throwable) { $pattern = null; }
        return is_array($pattern) ? (string)$pattern['name'] : 'Deleted pattern #' . $id;
    }

    private static function categoryListLabel(array $ids): string
    {
        $labels = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0))) as $id) {
            $labels[] = self::categoryLabel($id);
        }
        return $labels ? implode(', ', $labels) : '—';
    }

    private static function categoryLabel(int $id): string
    {
        if ($id < 1) return '—';
        static $cache = [];
        if (!array_key_exists($id, $cache)) {
            try { $cache[$id] = Categories::find($id); }
            catch (\Throwable) { $cache[$id] = null; }
        }
        $row = $cache[$id];
        return is_array($row) && trim((string)($row['name'] ?? '')) !== ''
            ? (string)$row['name']
            : 'Deleted category #' . $id;
    }

    private static function blockLabel(string $type): string
    {
        return match ($type) {
            'hero' => 'Hero banner',
            'values' => 'Trust / value strip',
            'cards' => 'Featured cards',
            'gallery' => 'Image gallery',
            'testimonials' => 'Testimonials',
            'faq' => 'FAQ',
            'stats' => 'Statistics',
            'custom' => 'Custom section',
            'latest_posts' => 'Latest blog posts',
            'cta' => 'Call to action',
            'pattern' => 'Synced pattern',
            default => 'Page section',
        };
    }

    private static function clipDisplay(string $value, int $length): string
    {
        $value = trim($value);
        return mb_strlen($value) > $length ? mb_substr($value, 0, max(1, $length - 1)) . '…' : $value;
    }

    public static function restore(string $type, int $contentId, int $revisionId, int $userId): void
    {
        $revision = self::revision($type, $contentId, $revisionId);
        if (!$revision) throw new RuntimeException('Revision not found.');
        $snapshot = $revision['snapshot'];
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            if ($type === 'page') self::restorePage($contentId, $snapshot, $userId);
            elseif ($type === 'post') self::restorePost($contentId, $snapshot);
            else self::restoreEntry($contentId, $snapshot);
            // Keep the restored content and the audit snapshot atomic. A failed
            // history write must never leave content changed without a revision.
            self::capture($type, $contentId, $userId, 'restore');
            self::clearAutosave($type, $contentId, $userId);
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function saveAutosave(string $type, int $contentId, int $userId, array $input): array
    {
        self::assertType($type);
        if ($contentId < 1 || $userId < 1) throw new RuntimeException('Autosave requires saved content.');
        $payload = self::autosavePayload($type, $input, $contentId);
        $json = self::encode($payload);
        $hash = hash('sha256', $json);
        $identity = 'id:' . $contentId;
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO content_autosaves (content_type,content_id,identity_key,user_id,payload_json,content_hash,saved_at) '
            . 'VALUES (?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE content_id=VALUES(content_id),payload_json=VALUES(payload_json),content_hash=VALUES(content_hash),saved_at=UTC_TIMESTAMP()'
        );
        $stmt->execute([$type, $contentId, $identity, $userId, $json, $hash]);
        return ['hash' => $hash, 'saved_at' => gmdate('Y-m-d H:i:s')];
    }

    public static function latestAutosave(string $type, int $contentId, int $userId, ?string $updatedAt = null): ?array
    {
        self::assertType($type);
        if ($contentId < 1 || $userId < 1) return null;
        $stmt = Database::connection()->prepare(
            'SELECT payload_json,content_hash,saved_at FROM content_autosaves WHERE content_type=? AND content_id=? AND user_id=? LIMIT 1'
        );
        $stmt->execute([$type, $contentId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        if ($updatedAt && strtotime((string)$row['saved_at'] . ' UTC') <= strtotime($updatedAt . ' UTC')) return null;
        $payload = json_decode((string)$row['payload_json'], true);
        if (!is_array($payload)) return null;
        return ['payload' => $payload, 'hash' => (string)$row['content_hash'], 'saved_at' => (string)$row['saved_at']];
    }

    public static function clearAutosave(string $type, int $contentId, int $userId): void
    {
        self::assertType($type);
        $stmt = Database::connection()->prepare('DELETE FROM content_autosaves WHERE content_type=? AND content_id=? AND user_id=?');
        $stmt->execute([$type, $contentId, $userId]);
    }

    /** @return list<string> */
    public static function assetPathsForContent(string $type, int $contentId): array
    {
        self::assertType($type);
        $stmt = Database::connection()->prepare('SELECT snapshot_json FROM content_revisions WHERE content_type=? AND content_id=?');
        $stmt->execute([$type, $contentId]);
        $paths = [];
        while (($json = $stmt->fetchColumn()) !== false) {
            $snapshot = json_decode((string)$json, true);
            if (!is_array($snapshot)) continue;
            $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
            if ($type === 'post') {
                $path = HomePage::safeStoredAssetPath((string)($fields['featured_image_path'] ?? ''));
                if ($path !== '') $paths[] = $path;
                continue;
            }
            if ($type === 'entry') continue; // Media Library assets are referenced, not owned copies.
            foreach (PageBlocks::assetPaths(PageBlocks::decode((string)($fields['blocks_json'] ?? '[]'))) as $path) $paths[] = $path;
            $branding = is_array($snapshot['branding'] ?? null) ? $snapshot['branding'] : [];
            $logo = HomePage::safeStoredAssetPath((string)($branding['branding.logo_path'] ?? ''));
            if ($logo !== '') $paths[] = $logo;
        }
        return array_values(array_unique($paths));
    }

    public static function purgeForContent(string $type, int $contentId): void
    {
        self::assertType($type);
        $db = Database::connection();
        $db->prepare('DELETE FROM content_autosaves WHERE content_type=? AND content_id=?')->execute([$type, $contentId]);
        $db->prepare('DELETE FROM content_revisions WHERE content_type=? AND content_id=?')->execute([$type, $contentId]);
    }

    public static function snapshot(string $type, int $contentId): ?array
    {
        self::assertType($type);
        return match ($type) {
            'page' => self::snapshotPage($contentId),
            'post' => self::snapshotPost($contentId),
            default => self::snapshotEntry($contentId),
        };
    }

    private static function snapshotPage(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pages WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $page = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$page) return null;
        $fields = [];
        foreach ([
            'author_id','title','path','page_template','excerpt','eyebrow','body','body_html','blocks_json','status',
            'show_in_navigation','navigation_label','navigation_order','show_in_footer','footer_label','footer_order','published_at'
        ] as $key) $fields[$key] = $page[$key] ?? null;
        $snapshot = ['schema' => 1, 'type' => 'page', 'fields' => $fields, 'seo' => SEO::editable((string)$page['path'])];
        if ((string)$page['path'] === '/') {
            $current = HomePage::current();
            $snapshot['branding'] = [
                'branding.site_name' => (string)($current['branding.site_name'] ?? ''),
                'branding.tagline' => (string)($current['branding.tagline'] ?? ''),
                'branding.logo_path' => (string)($current['branding.logo_path'] ?? ''),
            ];
        }
        return $snapshot;
    }

    private static function snapshotPost(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM posts WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) return null;
        $fields = [];
        foreach (['author_id','title','slug','excerpt','featured_image_path','body','body_html','status','published_at'] as $key) {
            $fields[$key] = $post[$key] ?? null;
        }
        $categories = Categories::forPost($id, false);
        $ids = [];
        $primary = 0;
        foreach ($categories as $category) {
            $cid = (int)$category['id'];
            $ids[] = $cid;
            if ((int)$category['is_primary'] === 1) $primary = $cid;
        }
        if ($primary === 0 && $ids) $primary = $ids[0];
        return [
            'schema' => 1, 'type' => 'post', 'fields' => $fields,
            'categories' => ['category_ids' => $ids, 'primary_category_id' => $primary],
        ];
    }

    private static function snapshotEntry(int $id): ?array
    {
        $entry = CustomContent::find($id, true);
        if (!$entry) return null;
        $fields = [];
        foreach (['model_id','author_id','title','slug','status','featured_media_id','seo_title','seo_description','canonical_url','robots','social_title','social_description','social_media_id','published_at'] as $key) {
            $fields[$key] = $entry[$key] ?? null;
        }
        return [
            'schema' => 1,
            'type' => 'entry',
            'fields' => $fields,
            'values' => is_array($entry['values'] ?? null) ? $entry['values'] : [],
        ];
    }

    private static function restoreEntry(int $id, array $snapshot): void
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $modelId = (int)($fields['model_id'] ?? 0);
        $model = ContentModels::find($modelId);
        if (!$model) throw new RuntimeException('The content model used by this revision no longer exists.');
        $values = is_array($snapshot['values'] ?? null) ? $snapshot['values'] : [];

        // Revalidate the historic payload against today's schema before writing
        // it back. Content models may have gained new required fields or tighter
        // validation since the revision was created; restoring must never leave
        // the current entry in a state the editor could not save today.
        $publishedLocal = !empty($fields['published_at']) ? CustomContent::utcToLocalInput((string)$fields['published_at']) : '';
        $validated = CustomContent::validateEntry([
            'title' => (string)($fields['title'] ?? ''),
            'slug' => (string)($fields['slug'] ?? ''),
            'status' => (string)($fields['status'] ?? 'draft'),
            'published_at_local' => $publishedLocal,
            'featured_media_id' => (int)($fields['featured_media_id'] ?? 0),
            'seo_title' => (string)($fields['seo_title'] ?? ''),
            'seo_description' => (string)($fields['seo_description'] ?? ''),
            'canonical_url' => (string)($fields['canonical_url'] ?? ''),
            'robots' => (string)($fields['robots'] ?? 'index,follow'),
            'social_title' => (string)($fields['social_title'] ?? ''),
            'social_description' => (string)($fields['social_description'] ?? ''),
            'social_media_id' => (int)($fields['social_media_id'] ?? 0),
            'fields' => $values,
        ], $model, $id, true, true);
        if ($validated['errors']) {
            throw new RuntimeException(
                'This revision no longer matches the current content model: ' . implode(' ', array_slice($validated['errors'], 0, 4))
            );
        }
        $data = $validated['data'];
        $json = self::encode((array)$data['field_values']);
        $stmt = Database::connection()->prepare(
            'UPDATE content_entries SET author_id=?,title=?,slug=?,status=?,field_values_json=?,featured_media_id=?,seo_title=?,seo_description=?,canonical_url=?,robots=?,social_title=?,social_description=?,social_media_id=?,published_at=?,updated_at=UTC_TIMESTAMP() '
            . 'WHERE id=? AND model_id=? AND deleted_at IS NULL'
        );
        $stmt->execute([
            (int)($fields['author_id'] ?? 0), (string)$data['title'], (string)$data['slug'],
            (string)$data['status'], $json, $data['featured_media_id'] ?? null, self::nullable($data['seo_title'] ?? null),
            self::nullable($data['seo_description'] ?? null), self::nullable($data['canonical_url'] ?? null), (string)($data['robots'] ?? 'index,follow'),
            self::nullable($data['social_title'] ?? null), self::nullable($data['social_description'] ?? null), $data['social_media_id'] ?? null,
            self::nullable($data['published_at'] ?? null), $id, $modelId,
        ]);
        $exists = Database::connection()->prepare('SELECT 1 FROM content_entries WHERE id=? AND model_id=? AND deleted_at IS NULL');
        $exists->execute([$id,$modelId]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Structured content is in Trash or no longer exists. Restore it from Trash first.');
        CustomContent::syncRelations($id, (array)$data['relations']);
        CustomContent::syncMediaUsage($id, (array)($data['media_usage'] ?? []));
        CustomContent::syncSearchAndUniqueValues($id, $modelId, (array)$data['field_values']);
    }

    private static function syncEntryRevisionMediaUsage(int $revisionId, array $snapshot): void
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $modelId = (int)($fields['model_id'] ?? 0);
        $values = is_array($snapshot['values'] ?? null) ? $snapshot['values'] : [];
        if ($revisionId < 1 || $modelId < 1) return;
        $usage = CustomContent::mediaUsageForValues($modelId, $values);
        $featuredId = max(0, (int)($fields['featured_media_id'] ?? 0));
        $socialId = max(0, (int)($fields['social_media_id'] ?? 0));
        if ($featuredId > 0) $usage[] = ['field_key'=>'__featured_image','media_id'=>$featuredId,'sort_order'=>1];
        if ($socialId > 0) $usage[] = ['field_key'=>'__social_image','media_id'=>$socialId,'sort_order'=>1];
        $db = Database::connection();
        $db->prepare('DELETE FROM content_revision_media_usage WHERE revision_id=?')->execute([$revisionId]);
        $insert = $db->prepare('INSERT INTO content_revision_media_usage (revision_id,field_key,media_id,sort_order) VALUES (?,?,?,?)');
        foreach ($usage as $row) {
            $mediaId = max(0, (int)($row['media_id'] ?? 0));
            $fieldKey = mb_substr(trim((string)($row['field_key'] ?? '')), 0, 191);
            if ($mediaId < 1 || $fieldKey === '') continue;
            $insert->execute([$revisionId,$fieldKey,$mediaId,max(0,(int)($row['sort_order'] ?? 100))]);
        }
    }

    private static function friendlyStructuredValue(array $field, mixed $value): string
    {
        $type = (string)($field['field_type'] ?? 'text');
        if ($value === null || $value === '' || $value === []) return '—';
        if ($type === 'rich_text') return self::contentText($value);
        if ($type === 'boolean') return !empty($value) ? 'Yes' : 'No';
        if ($type === 'media') {
            $asset = MediaLibrary::find((int)$value);
            return $asset ? (string)$asset['original_name'] : 'Missing media #' . (int)$value;
        }
        if ($type === 'gallery') {
            $names = [];
            foreach ((array)$value as $id) {
                $asset = MediaLibrary::find((int)$id);
                if ($asset) $names[] = (string)$asset['original_name'];
            }
            return $names ? implode(', ', array_slice($names, 0, 12)) : '—';
        }
        if ($type === 'relation') {
            $ids = is_array($value) ? $value : [$value];
            $names = [];
            foreach ($ids as $id) {
                $entry = CustomContent::find((int)$id);
                if ($entry) $names[] = (string)$entry['title'];
            }
            return $names ? implode(', ', $names) : '—';
        }
        if (in_array($type, ['component','repeater'], true)) {
            $items = $type === 'repeater' ? (array)$value : [$value];
            return count($items) . (count($items) === 1 ? ' item' : ' items');
        }
        if (is_array($value)) return implode(', ', array_map('strval', $value));
        return self::displayValue($value);
    }

    private static function restorePage(int $id, array $snapshot, int $userId): void
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $current = Database::connection()->prepare('SELECT path FROM pages WHERE id=? LIMIT 1');
        $current->execute([$id]);
        $oldPath = (string)($current->fetchColumn() ?: '');
        if ($oldPath === '') throw new RuntimeException('Page no longer exists.');
        if ($oldPath === '/') {
            $fields['path'] = '/';
            $fields['page_template'] = 'home';
            $fields['title'] = 'Home';
            $fields['status'] = 'published';
        }
        $stmt = Database::connection()->prepare(
            'UPDATE pages SET author_id=?,title=?,path=?,page_template=?,excerpt=?,eyebrow=?,body=?,body_html=?,blocks_json=?,status=?,show_in_navigation=?,navigation_label=?,navigation_order=?,show_in_footer=?,footer_label=?,footer_order=?,published_at=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL'
        );
        $stmt->execute([
            (int)($fields['author_id'] ?? $userId), (string)($fields['title'] ?? ''), (string)($fields['path'] ?? ''),
            (string)($fields['page_template'] ?? 'standard'), self::nullable($fields['excerpt'] ?? null), self::nullable($fields['eyebrow'] ?? null),
            (string)($fields['body'] ?? ''), (string)($fields['body_html'] ?? ''), (string)($fields['blocks_json'] ?? '[]'),
            (string)($fields['status'] ?? 'draft'), (int)($fields['show_in_navigation'] ?? 0), self::nullable($fields['navigation_label'] ?? null),
            (int)($fields['navigation_order'] ?? 100), (int)($fields['show_in_footer'] ?? 0), self::nullable($fields['footer_label'] ?? null),
            (int)($fields['footer_order'] ?? 100), self::nullable($fields['published_at'] ?? null), $id,
        ]);
        if ($stmt->rowCount() < 1 && $oldPath !== '/') {
            $exists = Database::connection()->prepare('SELECT 1 FROM pages WHERE id=? AND deleted_at IS NULL');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) throw new RuntimeException('Page is in Trash or no longer exists. Restore it from Trash first.');
        }
        $newPath = (string)($fields['path'] ?? $oldPath);
        if ($oldPath !== $newPath) {
            try { Database::connection()->prepare('DELETE FROM seo_pages WHERE path=?')->execute([$newPath]); } catch (\Throwable) {}
            try { Database::connection()->prepare('UPDATE seo_pages SET path=? WHERE path=?')->execute([$newPath, $oldPath]); } catch (\Throwable) {}
        }
        if (is_array($snapshot['seo'] ?? null)) {
            $seo = $snapshot['seo'];
            $seo['path'] = $newPath;
            SEO::save($seo, $userId);
        }
        if ($newPath === '/' && is_array($snapshot['branding'] ?? null)) HomePage::save($snapshot['branding'], $userId);
    }

    private static function restorePost(int $id, array $snapshot): void
    {
        $fields = is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [];
        $stmt = Database::connection()->prepare(
            'UPDATE posts SET author_id=?,title=?,slug=?,excerpt=?,featured_image_path=?,body=?,body_html=?,status=?,published_at=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL'
        );
        $stmt->execute([
            (int)($fields['author_id'] ?? 0), (string)($fields['title'] ?? ''), (string)($fields['slug'] ?? ''), self::nullable($fields['excerpt'] ?? null),
            self::nullable($fields['featured_image_path'] ?? null), (string)($fields['body'] ?? ''), (string)($fields['body_html'] ?? ''),
            (string)($fields['status'] ?? 'draft'), self::nullable($fields['published_at'] ?? null), $id,
        ]);
        $exists = Database::connection()->prepare('SELECT 1 FROM posts WHERE id=? AND deleted_at IS NULL');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) throw new RuntimeException('Post is in Trash or no longer exists. Restore it from Trash first.');
        $categories = is_array($snapshot['categories'] ?? null) ? $snapshot['categories'] : [];
        // Categories may have been deleted since an old revision was created.
        // Re-run today's category validation so a historic snapshot can still be
        // restored safely and falls back to General when needed.
        $selection = Categories::validateSelection([
            'category_ids' => (array)($categories['category_ids'] ?? []),
            'primary_category_id' => (string)($categories['primary_category_id'] ?? ''),
        ]);
        if (!$selection['category_ids'] || $selection['primary_category_id'] < 1) {
            throw new RuntimeException('A valid blog category is required before this revision can be restored.');
        }
        Categories::syncPostCategories(
            Database::connection(),
            $id,
            $selection['category_ids'],
            (int)$selection['primary_category_id']
        );
    }

    private static function autosavePayload(string $type, array $input, int $contentId = 0): array
    {
        if ($type === 'page') {
            $blocksJson = self::autosaveBlocks((string)($input['page_blocks_json'] ?? '[]'));
            return [
                'title' => self::clip($input['title'] ?? '', 255), 'path' => self::clip($input['path'] ?? '', 191),
                'eyebrow' => self::clip($input['eyebrow'] ?? '', 120), 'excerpt' => self::clip($input['excerpt'] ?? '', 1000),
                'body_html' => RichText::sanitize((string)($input['body_html'] ?? '')), 'page_blocks_json' => $blocksJson,
                'status' => self::clip($input['status'] ?? 'draft', 20), 'navigation_placement' => self::clip($input['navigation_placement'] ?? 'hidden', 20),
                'navigation_label' => self::clip($input['navigation_label'] ?? '', 120), 'navigation_order' => (int)($input['navigation_order'] ?? 100),
                'footer_label' => self::clip($input['footer_label'] ?? '', 120), 'footer_order' => (int)($input['footer_order'] ?? 100),
                'seo_title' => self::clip($input['seo_title'] ?? '', 255), 'seo_description' => self::clip($input['seo_description'] ?? '', 500),
                'branding_site_name' => self::clip($input['branding_site_name'] ?? '', 120), 'branding_tagline' => self::clip($input['branding_tagline'] ?? '', 160),
                'branding_logo_media_id' => max(0, (int)($input['branding_logo_media_id'] ?? 0)),
                'remove_branding_logo' => !empty($input['remove_branding_logo']) ? '1' : '0',
            ];
        }
        if ($type === 'post') {
            $categoryIds = array_values(array_filter(array_map('intval', (array)($input['category_ids'] ?? [])), static fn(int $v): bool => $v > 0));
            return [
                'title' => self::clip($input['title'] ?? '', 255), 'slug' => self::clip($input['slug'] ?? '', 191),
                'excerpt' => self::clip($input['excerpt'] ?? '', 1000),
                'featured_image_path' => self::clip($input['featured_image_path'] ?? '', 255),
                'featured_media_id' => max(0, (int)($input['featured_media_id'] ?? 0)),
                'remove_featured_image' => !empty($input['remove_featured_image']) ? '1' : '0',
                'body_html' => RichText::sanitize((string)($input['body_html'] ?? '')),
                'status' => self::clip($input['status'] ?? 'draft', 20), 'published_at_local' => self::clip($input['published_at_local'] ?? '', 40),
                'category_ids' => $categoryIds, 'primary_category_id' => (int)($input['primary_category_id'] ?? 0),
            ];
        }
        $modelId = max(0, (int)($input['model_id'] ?? 0));
        $model = $modelId > 0 ? ContentModels::find($modelId) : null;
        $data = $model ? CustomContent::validateEntry($input, $model, $contentId > 0 ? $contentId : null, true)['data'] : [
            'title' => self::clip($input['title'] ?? '', 255), 'slug' => self::clip($input['slug'] ?? '', 191),
            'status' => self::clip($input['status'] ?? 'draft', 20), 'field_values' => [],
            'seo_title' => self::clip($input['seo_title'] ?? '', 255), 'seo_description' => self::clip($input['seo_description'] ?? '', 500),
        ];
        return [
            'model_id' => $modelId, 'title' => (string)$data['title'], 'slug' => (string)$data['slug'],
            'status' => (string)$data['status'], 'published_at_local' => (string)($data['published_at_local'] ?? ''),
            'featured_media_id' => (int)($data['featured_media_id'] ?? 0),
            'seo_title' => (string)($data['seo_title'] ?? ''), 'seo_description' => (string)($data['seo_description'] ?? ''),
            'canonical_url' => (string)($data['canonical_url'] ?? ''), 'robots' => (string)($data['robots'] ?? 'index,follow'),
            'social_title' => (string)($data['social_title'] ?? ''), 'social_description' => (string)($data['social_description'] ?? ''),
            'social_media_id' => (int)($data['social_media_id'] ?? 0), 'fields' => (array)$data['field_values'],
        ];
    }

    private static function autosaveBlocks(string $json): string
    {
        $validated = PageBlocks::validateSubmitted($json);
        $blocks = $validated['blocks'];
        $rawBlocks = PageBlocks::decode($json);
        $rawById = [];
        foreach ($rawBlocks as $raw) {
            if (!is_array($raw)) continue;
            $id = (string)($raw['id'] ?? '');
            if ($id !== '') $rawById[$id] = $raw;
        }
        foreach ($blocks as &$block) {
            $raw = $rawById[(string)($block['id'] ?? '')] ?? null;
            if (!is_array($raw)) continue;
            if (in_array(($block['type'] ?? ''), ['hero','custom'], true)) {
                $mediaId = max(0, (int)($raw['_media_id'] ?? 0));
                if ($mediaId > 0) $block['_media_id'] = $mediaId;
            }
            if (in_array(($block['type'] ?? ''), ['cards','gallery'], true)) {
                $rawItems = is_array($raw['items'] ?? null) ? $raw['items'] : [];
                foreach ($block['items'] as $index => &$item) {
                    $rawItem = is_array($rawItems[$index] ?? null) ? $rawItems[$index] : [];
                    $mediaId = max(0, (int)($rawItem['_media_id'] ?? 0));
                    if ($mediaId > 0) $item['_media_id'] = $mediaId;
                }
                unset($item);
            }
        }
        unset($block);
        return self::encode($blocks);
    }

    private static function pathValue(array $data, string $path): mixed
    {
        $cursor = $data;
        $parts = explode('.', $path);
        foreach ($parts as $index => $part) {
            if (!is_array($cursor)) return null;
            $remaining = implode('.', array_slice($parts, $index));
            if (array_key_exists($remaining, $cursor)) return $cursor[$remaining];
            if (!array_key_exists($part, $cursor)) return null;
            $cursor = $cursor[$part];
        }
        return $cursor;
    }

    private static function normalizeCompare(mixed $value): string
    {
        if (is_array($value)) return self::encode($value);
        if ($value === null) return '';
        return trim((string)$value);
    }

    private static function displayValue(mixed $value): string
    {
        if (is_array($value)) return implode(', ', array_map('strval', $value));
        $text = trim((string)($value ?? ''));
        if ($text === '') return '—';
        $text = strip_tags($text);
        if (mb_strlen($text) > 260) $text = mb_substr($text, 0, 257) . '…';
        return $text;
    }

    private static function encode(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return $json;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private static function clip(mixed $value, int $length): string
    {
        return mb_substr(trim((string)$value), 0, $length);
    }

    private static function safeAction(string $action): string
    {
        $action = preg_replace('/[^a-z0-9_-]+/i', '-', $action) ?: 'save';
        return mb_substr(mb_strtolower(trim($action, '-')), 0, 40) ?: 'save';
    }

    private static function assertType(string $type): void
    {
        if (!in_array($type, self::TYPES, true)) throw new RuntimeException('Unsupported content type.');
    }

    private function __construct() {}
}
