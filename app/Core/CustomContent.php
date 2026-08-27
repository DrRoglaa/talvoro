<?php
declare(strict_types=1);

namespace CMS\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use RuntimeException;

/**
 * Runtime/editor service for entries defined by ContentModels.
 */
final class CustomContent
{
    public static function publishDue(?int $modelId = null): void
    {
        $db = Database::connection();
        $sql = "SELECT e.id,e.model_id,e.title,m.enable_revisions FROM content_entries e JOIN content_models m ON m.id=e.model_id WHERE e.deleted_at IS NULL AND e.status='scheduled' AND e.published_at IS NOT NULL AND e.published_at<=UTC_TIMESTAMP()";
        $args = [];
        if ($modelId !== null && $modelId > 0) { $sql .= ' AND e.model_id=?'; $args[] = $modelId; }
        $sql .= ' ORDER BY e.id ASC LIMIT 100';
        $due = $db->prepare($sql); $due->execute($args);
        $update = $db->prepare("UPDATE content_entries SET status='published',updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL AND status='scheduled' AND published_at<=UTC_TIMESTAMP()");
        foreach ($due->fetchAll(PDO::FETCH_ASSOC) ?: [] as $entry) {
            try {
                $db->beginTransaction();
                $update->execute([(int)$entry['id']]);
                if ($update->rowCount() === 1 && (int)$entry['enable_revisions'] === 1) {
                    ContentHistory::capture('entry',(int)$entry['id'],null,'scheduled_publish');
                }
                $published = $update->rowCount() === 1;
                $db->commit();
                if ($published) Audit::log('structured_content.publish','entry',(int)$entry['id'],['mode'=>'scheduled','model_id'=>(int)$entry['model_id'],'title'=>(string)$entry['title']]);
            } catch (\Throwable) {
                if ($db->inTransaction()) $db->rollBack();
            }
        }
    }

    public static function adminList(int $modelId, string $search = '', string $status = '', bool $trashed = false): array
    {
        return self::adminPage($modelId, $search, $status, $trashed, 1, 500)['items'];
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function adminPage(int $modelId, string $search = '', string $status = '', bool $trashed = false, int $page = 1, int $perPage = 50): array
    {
        self::publishDue($modelId);
        $perPage = max(10, min(100, $perPage));
        $where = 'e.model_id=? AND ' . ($trashed ? 'e.deleted_at IS NOT NULL' : 'e.deleted_at IS NULL');
        $args = [$modelId];
        if ($search !== '') {
            $where .= ' AND (e.title LIKE ? OR e.slug LIKE ? OR EXISTS (SELECT 1 FROM content_search_values sv WHERE sv.entry_id=e.id AND sv.value_text LIKE ?))';
            $needle = '%' . trim($search) . '%'; $args[] = $needle; $args[] = $needle; $args[] = $needle;
        }
        if (in_array($status, ['draft','scheduled','published'], true)) { $where .= ' AND e.status=?'; $args[] = $status; }

        $count = Database::connection()->prepare('SELECT COUNT(*) FROM content_entries e WHERE ' . $where);
        $count->execute($args);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT e.id,e.title,e.slug,e.status,e.published_at,e.updated_at,e.deleted_at,u.display_name author_name '
             . 'FROM content_entries e JOIN users u ON u.id=e.author_id WHERE ' . $where
             . " ORDER BY e.updated_at DESC,e.id DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = Database::connection()->prepare($sql); $stmt->execute($args);
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    public static function find(int $id, bool $includeDeleted = false): ?array
    {
        if ($id < 1) return null;
        $sql = 'SELECT e.*,m.singular_name,m.plural_name,m.slug model_slug,m.is_public,m.has_archive,m.has_urls,m.searchable,m.sitemap_enabled,m.enable_revisions,m.enable_autosave,m.enable_trash,m.enable_seo,m.enable_featured_image,m.enable_scheduling '
             . 'FROM content_entries e JOIN content_models m ON m.id=e.model_id WHERE e.id=?';
        if (!$includeDeleted) $sql .= ' AND e.deleted_at IS NULL';
        $sql .= ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql); $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['values'] = self::decodedValues((string)$row['field_values_json']);
        $row['values'] = self::hydrateRelations($id, (int)$row['model_id'], $row['values']);
        return $row;
    }

    public static function rawFind(int $id, bool $includeDeleted = true): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM content_entries WHERE id=?' . ($includeDeleted ? '' : ' AND deleted_at IS NULL') . ' LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findPublishedBySlug(int $modelId, string $slug): ?array
    {
        self::publishDue($modelId);
        $stmt = Database::connection()->prepare(
            "SELECT e.id FROM content_entries e JOIN content_models m ON m.id=e.model_id
             WHERE e.model_id=? AND e.slug=? AND e.deleted_at IS NULL AND e.status='published'
               AND e.published_at IS NOT NULL AND e.published_at<=UTC_TIMESTAMP()
               AND m.status='active' AND m.is_public=1 AND m.has_urls=1 LIMIT 1"
        );
        $stmt->execute([$modelId, self::slug($slug)]);
        $id = (int)$stmt->fetchColumn();
        return $id > 0 ? self::find($id) : null;
    }

    public static function publicEntries(int $modelId, int $limit = 100): array
    {
        self::publishDue($modelId);
        $limit = max(1, min(1000, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT id FROM content_entries WHERE model_id=? AND deleted_at IS NULL AND status='published'
             AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP() ORDER BY published_at DESC,id DESC LIMIT {$limit}"
        );
        $stmt->execute([$modelId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $entry = self::find((int)$id); if ($entry) $rows[] = $entry;
        }
        return $rows;
    }

    /**
     * Lightweight published-entry query for Page Builder collections.
     * It deliberately avoids relation hydration and other per-entry lookups.
     *
     * @return list<array<string,mixed>>
     */
    public static function publicCollectionEntries(int $modelId, int $limit = 6, string $sort = 'newest', bool $featuredOnly = false): array
    {
        self::publishDue($modelId);
        $limit = max(1, min(12, $limit));
        $order = match ($sort) {
            'oldest' => 'e.published_at ASC,e.id ASC',
            'title_asc' => 'e.title ASC,e.id ASC',
            'title_desc' => 'e.title DESC,e.id DESC',
            default => 'e.published_at DESC,e.id DESC',
        };
        // Pull a bounded candidate window when filtering featured records in PHP.
        // This keeps the query portable across supported MariaDB/MySQL versions and
        // avoids dynamic JSON-path SQL assembled from user-created field keys.
        $candidateLimit = $featuredOnly ? max($limit, min(120, $limit * 12)) : $limit;
        $stmt = Database::connection()->prepare(
            "SELECT e.id,e.model_id,e.title,e.slug,e.featured_media_id,e.field_values_json,e.published_at,e.updated_at
             FROM content_entries e
             WHERE e.model_id=? AND e.deleted_at IS NULL AND e.status='published'
               AND e.published_at IS NOT NULL AND e.published_at<=UTC_TIMESTAMP()
             ORDER BY {$order} LIMIT {$candidateLimit}"
        );
        $stmt->execute([$modelId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $row['values'] = self::decodedValues((string)($row['field_values_json'] ?? ''));
            if ($featuredOnly && empty($row['values']['featured']) && empty($row['values']['highlighted'])) continue;
            $out[] = $row;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function publicPage(int $modelId, int $page = 1, int $perPage = 12): array
    {
        self::publishDue($modelId);
        $perPage = max(4, min(48, $perPage));
        $where = "model_id=? AND deleted_at IS NULL AND status='published' AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()";
        $count = Database::connection()->prepare('SELECT COUNT(*) FROM content_entries WHERE ' . $where);
        $count->execute([$modelId]);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($pages, $page));
        $offset = ($page - 1) * $perPage;
        $stmt = Database::connection()->prepare(
            "SELECT id,model_id,title,slug,featured_media_id,field_values_json,published_at,updated_at FROM content_entries WHERE {$where} ORDER BY published_at DESC,id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$modelId]);
        return ['items'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'page'=>$page,'pages'=>$pages,'per_page'=>$perPage];
    }

    /** @return list<string> */
    public static function publicSlugs(int $modelId, int $limit = 50000): array
    {
        self::publishDue($modelId);
        $limit = max(1, min(50000, $limit));
        $stmt = Database::connection()->prepare(
            "SELECT slug FROM content_entries WHERE model_id=? AND deleted_at IS NULL AND status='published'
             AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()
             AND (robots IS NULL OR robots NOT LIKE 'noindex%') ORDER BY published_at DESC,id DESC LIMIT {$limit}"
        );
        $stmt->execute([$modelId]);
        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    }

    public static function publicUrl(array $model, array $entry): string
    {
        return '/' . rawurlencode((string)$model['slug']) . '/' . rawurlencode((string)$entry['slug']);
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validateEntry(array $input, array $model, ?int $existingId, bool $canPublish, bool $allowPastSchedule = false): array
    {
        $modelId = (int)$model['id'];
        $existingRaw = $existingId ? self::rawFind($existingId, false) : null;
        $existing = $existingId ? self::find($existingId) : null;
        $storedValues = $existingRaw ? self::decodedValues((string)$existingRaw['field_values_json']) : [];
        $title = self::clip($input['title'] ?? '', 255);
        $rawSlug = self::clip($input['slug'] ?? '', 191);
        if ($existingId === null && $rawSlug === '') $slug = self::uniqueSlug($modelId, $title, null);
        elseif ($existingId !== null && $rawSlug === '') $slug = (string)($existing['slug'] ?? self::uniqueSlug($modelId, $title, $existingId));
        else $slug = self::slug($rawSlug);
        $allowedStatuses = (int)($model['enable_scheduling'] ?? 1) === 1 ? ['draft','scheduled','published'] : ['draft','published'];
        $statusInput = (string)($input['status'] ?? 'draft');
        $status = in_array($statusInput, $allowedStatuses, true) ? $statusInput : 'draft';
        $publishLocal = trim((string)($input['published_at_local'] ?? ''));
        $publishedAt = null;
        $seoEnabled = (int)($model['enable_seo'] ?? 1) === 1;
        $seoTitle = $seoEnabled ? self::clip($input['seo_title'] ?? '', 255) : '';
        $seoDescription = $seoEnabled ? self::clip($input['seo_description'] ?? '', 500) : '';
        $canonical = $seoEnabled ? self::clip($input['canonical_url'] ?? '', 500) : '';
        $robotsInput = (string)($input['robots'] ?? 'index,follow');
        $robots = $seoEnabled && in_array($robotsInput, ['index,follow','index,nofollow','noindex,follow','noindex,nofollow'], true) ? $robotsInput : 'index,follow';
        $socialTitle = $seoEnabled ? self::clip($input['social_title'] ?? '', 255) : '';
        $socialDescription = $seoEnabled ? self::clip($input['social_description'] ?? '', 500) : '';
        $socialMediaId = $seoEnabled ? max(0,(int)($input['social_media_id'] ?? 0)) : 0;
        $featuredMediaId = (int)($model['enable_featured_image'] ?? 0) === 1 ? max(0,(int)($input['featured_media_id'] ?? 0)) : 0;
        $submitted = is_array($input['fields'] ?? null) ? $input['fields'] : [];
        $values = []; $relations = []; $errors = [];

        // Archived fields are intentionally hidden from everyday editors, but
        // their data remains part of the entry until an administrator makes a
        // deliberate destructive schema change. Preserve those values (and
        // normalized relation/media references) across ordinary edits and
        // revision restores so schema evolution can never erase data merely
        // because a field is no longer displayed in the form.
        if ($existingId !== null) {
            foreach (ContentModels::fields($modelId, true) as $archivedField) {
                if (empty($archivedField['archived_at'])) continue;
                $archivedKey = (string)$archivedField['field_key'];
                $archivedValue = array_key_exists($archivedKey, $submitted)
                    ? $submitted[$archivedKey]
                    : ($storedValues[$archivedKey] ?? null);
                if ($archivedValue === null) continue;
                $values[$archivedKey] = $archivedValue;
                if ((string)$archivedField['field_type'] === 'relation') {
                    $relations[$archivedKey] = self::preservedRelationIds($archivedField, $archivedValue, $existingId);
                }
            }
        }

        if (mb_strlen($title) < 2) $errors[] = (string)$model['singular_name'] . ' title must be at least 2 characters.';
        if ($slug === '' || strlen($slug) > 191) $errors[] = 'A valid URL slug is required.';
        if (self::slugExists($modelId, $slug, $existingId)) $errors[] = 'That URL slug is already used by another ' . mb_strtolower((string)$model['singular_name']) . '.';
        if ($status !== 'draft' && !$canPublish) { $errors[] = 'You do not have permission to schedule or publish structured content.'; $status = 'draft'; }
        if ($status === 'scheduled') {
            if ($publishLocal === '') $errors[] = 'Scheduled content requires a publish date and time.';
            else {
                $publishedAt = self::localInputToUtc($publishLocal,$errors);
                if (!$allowPastSchedule && $publishedAt !== null && strtotime($publishedAt . ' UTC') <= time()) $errors[] = 'Scheduled publish time must be in the future.';
            }
        } elseif ($status === 'published') {
            if ($publishLocal !== '') {
                $publishedAt = self::localInputToUtc($publishLocal,$errors);
                if ($publishedAt !== null && strtotime($publishedAt . ' UTC') > time()) $errors[] = 'Use Scheduled for a future publish date.';
            } else $publishedAt = (string)($existing['published_at'] ?? '') ?: gmdate('Y-m-d H:i:s');
        }
        if ($canonical !== '' && !self::validCanonical($canonical)) $errors[] = 'Canonical URL must be a complete http(s) URL.';
        if ($featuredMediaId > 0 && !MediaLibrary::find($featuredMediaId)) $errors[] = 'Featured image references a missing Media Library item.';
        if ($socialMediaId > 0 && !MediaLibrary::find($socialMediaId)) $errors[] = 'Social image references a missing Media Library item.';

        foreach (ContentModels::fields($modelId) as $field) {
            $key = (string)$field['field_key'];
            $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
            $rawPresent = array_key_exists($key,$submitted);
            $value = $rawPresent ? $submitted[$key] : ($existingId === null ? ($settings['default_value'] ?? null) : null);
            if ($existingId !== null && in_array((string)$field['field_type'], ['component','repeater'], true)) {
                $value = self::mergeArchivedComponentValues($field, $value, $storedValues[$key] ?? null);
            }
            $validated = self::validateFieldValue($field, $value, $modelId, $key, $existingId);
            $values[$key] = $validated['value'];
            if ($field['field_type'] === 'relation') $relations[$key] = (array)$validated['relation_ids'];
            if ((int)($settings['unique'] ?? 0) === 1 && !self::isEmptyValue($validated['value']) && self::uniqueValueExists($modelId,$key,$validated['value'],$existingId)) {
                $errors[] = (string)$field['label'] . ': this value must be unique.';
            }
            foreach ($validated['errors'] as $error) $errors[] = (string)$field['label'] . ': ' . $error;
        }

        $usage = self::mediaUsageForValues($modelId,$values);
        if ($featuredMediaId > 0) $usage[] = ['field_key'=>'__featured_image','media_id'=>$featuredMediaId,'sort_order'=>1];
        if ($socialMediaId > 0) $usage[] = ['field_key'=>'__social_image','media_id'=>$socialMediaId,'sort_order'=>1];

        return ['data' => [
            'model_id'=>$modelId,'title'=>$title,'slug'=>$slug,'status'=>$status,'published_at'=>$publishedAt,'published_at_local'=>$publishLocal,
            'field_values'=>$values,'field_values_json'=>json_encode($values,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}',
            'relations'=>$relations,'media_usage'=>$usage,'featured_media_id'=>$featuredMediaId ?: null,
            'seo_title'=>$seoTitle,'seo_description'=>$seoDescription,'canonical_url'=>$canonical,'robots'=>$robots,
            'social_title'=>$socialTitle,'social_description'=>$socialDescription,'social_media_id'=>$socialMediaId ?: null,
        ], 'errors'=>array_values(array_unique($errors))];
    }

    public static function create(array $data, int $authorId): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO content_entries (model_id,author_id,title,slug,status,field_values_json,featured_media_id,seo_title,seo_description,canonical_url,robots,social_title,social_description,social_media_id,published_at,created_at,updated_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $data['model_id'],$authorId,$data['title'],$data['slug'],$data['status'],$data['field_values_json'],$data['featured_media_id'],
            self::nullable($data['seo_title']),self::nullable($data['seo_description']),self::nullable($data['canonical_url']),$data['robots'],
            self::nullable($data['social_title']),self::nullable($data['social_description']),$data['social_media_id'],self::nullable($data['published_at']),
        ]);
        $id = (int)Database::connection()->lastInsertId();
        self::syncRelations($id,$data['relations']);
        self::syncMediaUsage($id,(array)($data['media_usage'] ?? []));
        self::syncSearchAndUniqueValues($id,(int)$data['model_id'],(array)$data['field_values']);
        return $id;
    }

    public static function update(int $id, array $data, ?string $existingPublishedAt): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE content_entries SET title=?,slug=?,status=?,field_values_json=?,featured_media_id=?,seo_title=?,seo_description=?,canonical_url=?,robots=?,social_title=?,social_description=?,social_media_id=?,published_at=?,updated_at=UTC_TIMESTAMP() '
            . 'WHERE id=? AND model_id=? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $data['title'],$data['slug'],$data['status'],$data['field_values_json'],$data['featured_media_id'],self::nullable($data['seo_title']),
            self::nullable($data['seo_description']),self::nullable($data['canonical_url']),$data['robots'],self::nullable($data['social_title']),
            self::nullable($data['social_description']),$data['social_media_id'],self::nullable($data['published_at']),$id,$data['model_id'],
        ]);
        $check = Database::connection()->prepare('SELECT 1 FROM content_entries WHERE id=? AND model_id=? AND deleted_at IS NULL');
        $check->execute([$id,$data['model_id']]);
        if (!$check->fetchColumn()) throw new RuntimeException('Structured content entry not found.');
        self::syncRelations($id,$data['relations']);
        self::syncMediaUsage($id,(array)($data['media_usage'] ?? []));
        self::syncSearchAndUniqueValues($id,(int)$data['model_id'],(array)$data['field_values']);
    }

    /** @param array<string,list<int>> $relations */
    public static function syncRelations(int $entryId, array $relations): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM content_relations WHERE source_entry_id=?')->execute([$entryId]);
        $insert = $db->prepare('INSERT INTO content_relations (source_entry_id,field_key,target_entry_id,sort_order) VALUES (?,?,?,?)');
        foreach ($relations as $fieldKey => $ids) {
            $position = 10;
            foreach (array_values(array_unique(array_map('intval', $ids))) as $targetId) {
                if ($targetId < 1 || $targetId === $entryId) continue;
                $insert->execute([$entryId, $fieldKey, $targetId, $position]);
                $position += 10;
            }
        }
    }

    /**
     * Return normalized Media Library references for the current structured values.
     *
     * @return list<array{field_key:string,media_id:int,sort_order:int}>
     */
    public static function mediaUsageForValues(int $modelId, array $values): array
    {
        $usage = [];
        foreach (ContentModels::fields($modelId, true) as $field) {
            $key = (string)$field['field_key'];
            self::collectMediaUsage($field, $values[$key] ?? null, $key, $usage);
        }
        $deduped = [];
        foreach ($usage as $row) {
            $row['field_key'] = self::mediaUsagePath((string)$row['field_key']);
            $fingerprint = $row['field_key'] . ':' . $row['media_id'];
            if (!isset($deduped[$fingerprint])) $deduped[$fingerprint] = $row;
        }
        return array_values($deduped);
    }

    /** @param list<array{field_key:string,media_id:int,sort_order:int}> $usage */
    public static function syncMediaUsage(int $entryId, array $usage): void
    {
        $db = Database::connection();
        try {
            $db->prepare('DELETE FROM content_media_usage WHERE entry_id=?')->execute([$entryId]);
            $insert = $db->prepare('INSERT INTO content_media_usage (entry_id,field_key,media_id,sort_order) VALUES (?,?,?,?)');
            foreach ($usage as $row) {
                $mediaId = max(0, (int)($row['media_id'] ?? 0));
                $fieldKey = mb_substr(trim((string)($row['field_key'] ?? '')), 0, 191);
                if ($mediaId < 1 || $fieldKey === '') continue;
                $insert->execute([$entryId, $fieldKey, $mediaId, max(0, (int)($row['sort_order'] ?? 100))]);
            }
        } catch (\Throwable $e) {
            // Before migration 016 exists there is no structured content to sync.
            // Once the table exists, failures must remain fatal so an entry can
            // never silently lose Media Library referential protection.
            try {
                $exists = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='content_media_usage'")->fetchColumn() === 1;
            } catch (\Throwable) { $exists = false; }
            if ($exists) throw $e;
        }
    }

    /** @param list<array{field_key:string,media_id:int,sort_order:int}> $usage */
    private static function collectMediaUsage(array $field, mixed $value, string $path, array &$usage): void
    {
        if ($value === null || $value === '' || $value === []) return;
        $type = (string)($field['field_type'] ?? '');
        if ($type === 'media') {
            $id = max(0, (int)$value);
            if ($id > 0) $usage[] = ['field_key'=>$path,'media_id'=>$id,'sort_order'=>10];
            return;
        }
        if ($type === 'gallery') {
            $order = 10;
            foreach ((array)$value as $id) {
                $id = max(0, (int)$id);
                if ($id > 0) { $usage[] = ['field_key'=>$path,'media_id'=>$id,'sort_order'=>$order]; $order += 10; }
            }
            return;
        }
        if (!in_array($type, ['component','repeater'], true)) return;
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
        $componentId = (int)($settings['component_id'] ?? 0);
        if ($componentId < 1) return;
        $subfields = ContentModels::componentFields($componentId, true);
        $items = $type === 'repeater' ? array_values(is_array($value) ? $value : []) : [is_array($value) ? $value : []];
        foreach ($items as $index => $item) {
            if (!is_array($item)) continue;
            foreach ($subfields as $subfield) {
                $subKey = (string)$subfield['field_key'];
                $subPath = $type === 'repeater' ? $path . '.' . $index . '.' . $subKey : $path . '.' . $subKey;
                self::collectMediaUsage($subfield, $item[$subKey] ?? null, $subPath, $usage);
            }
        }
    }

    private static function mediaUsagePath(string $path): string
    {
        $path = trim($path);
        if (strlen($path) <= 191) return $path;
        // Keep the path useful for diagnostics while guaranteeing that deeply
        // nested/repeated components cannot collide after database truncation.
        return substr($path, 0, 166) . ':' . substr(hash('sha256', $path), 0, 24);
    }

    public static function counts(int $modelId): array
    {
        $stmt = Database::connection()->prepare('SELECT status,COUNT(*) total FROM content_entries WHERE model_id=? AND deleted_at IS NULL GROUP BY status');
        $stmt->execute([$modelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['total'=>array_sum(array_map('intval',$rows)),'draft'=>(int)($rows['draft']??0),'scheduled'=>(int)($rows['scheduled']??0),'published'=>(int)($rows['published']??0)];
    }

    public static function trashCount(int $modelId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM content_entries WHERE model_id=? AND deleted_at IS NOT NULL');
        $stmt->execute([$modelId]); return (int)$stmt->fetchColumn();
    }

    public static function relationOptions(int $targetModelId, int $limit = 50): array
    {
        return self::searchRelationOptions($targetModelId,'',$limit);
    }

    public static function searchRelationOptions(int $targetModelId, string $query = '', int $limit = 30): array
    {
        $limit = max(1,min(50,$limit));
        $where = 'model_id=? AND deleted_at IS NULL'; $args = [$targetModelId];
        $query = trim($query);
        if ($query !== '') { $where .= ' AND (title LIKE ? OR slug LIKE ? OR EXISTS (SELECT 1 FROM content_search_values sv WHERE sv.entry_id=content_entries.id AND sv.value_text LIKE ?))'; $like = '%' . $query . '%'; $args[] = $like; $args[] = $like; $args[] = $like; }
        $stmt = Database::connection()->prepare("SELECT id,title,status,slug FROM content_entries WHERE {$where} ORDER BY CASE status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END,title ASC LIMIT {$limit}");
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function relationReferencesCount(int $entryId): int
    {
        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM content_relations WHERE target_entry_id=?');
        $stmt->execute([$entryId]);
        return (int)$stmt->fetchColumn();
    }

    public static function relationTargets(int $entryId, string $fieldKey): array
    {
        $stmt = Database::connection()->prepare(
            'SELECT e.id,e.title,e.slug,e.status,e.model_id,e.deleted_at,m.slug model_slug,m.singular_name,m.is_public,m.has_urls FROM content_relations r '
            . 'JOIN content_entries e ON e.id=r.target_entry_id JOIN content_models m ON m.id=e.model_id '
            . 'WHERE r.source_entry_id=? AND r.field_key=? ORDER BY r.sort_order ASC,r.id ASC'
        );
        $stmt->execute([$entryId,$fieldKey]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function relatedTo(int $entryId, int $limit = 50): array
    {
        $limit = max(1,min(200,$limit));
        $stmt = Database::connection()->prepare(
            "SELECT s.id,s.title,s.slug,s.status,s.model_id,m.singular_name,m.plural_name,m.slug model_slug,m.is_public,m.has_urls,r.field_key
             FROM content_relations r JOIN content_entries s ON s.id=r.source_entry_id JOIN content_models m ON m.id=s.model_id
             WHERE r.target_entry_id=? AND s.deleted_at IS NULL ORDER BY s.updated_at DESC LIMIT {$limit}"
        );
        $stmt->execute([$entryId]); return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string,mixed> */
    public static function decodedValues(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function uniqueSlug(int $modelId, string $title, ?int $existingId): string
    {
        $base = self::slug($title); if ($base === '') $base = 'item';
        for ($i=1;$i<=9999;$i++) {
            $candidate = $base . ($i===1 ? '' : '-' . $i);
            if (!self::slugExists($modelId,$candidate,$existingId)) return $candidate;
        }
        throw new RuntimeException('Talvoro could not create a unique URL slug.');
    }

    public static function slug(string $value): string
    {
        return ContentModels::slug($value);
    }

    /** @return array{value:mixed,relation_ids:list<int>,errors:list<string>} */
    private static function validateFieldValue(array $field, mixed $raw, int $modelId, string $fieldKey, ?int $currentEntryId = null): array
    {
        $type = (string)$field['field_type'];
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
        $required = (int)$field['is_required'] === 1;
        $errors = []; $value = null; $relationIds = [];

        switch ($type) {
            case 'text': case 'textarea':
                $value = trim((string)($raw ?? ''));
                $max = (int)($settings['max_length'] ?? ($type === 'text' ? 1000 : 20000));
                if ($max > 0 && mb_strlen($value) > $max) $errors[] = "must be {$max} characters or fewer.";
                break;
            case 'rich_text':
                $value = RichText::sanitize((string)($raw ?? ''));
                break;
            case 'number':
                $text = trim((string)($raw ?? ''));
                if ($text === '') $value = null;
                elseif (!is_numeric($text)) { $errors[] = 'must be a number.'; $value = null; }
                else {
                    $value = (float)$text;
                    if (isset($settings['min']) && $value < (float)$settings['min']) $errors[] = 'is below the allowed minimum.';
                    if (isset($settings['max']) && $value > (float)$settings['max']) $errors[] = 'is above the allowed maximum.';
                }
                break;
            case 'boolean':
                $value = !empty($raw) ? 1 : 0;
                break;
            case 'date':
                $value = trim((string)($raw ?? ''));
                if ($value !== '' && !self::validDate($value,'Y-m-d')) $errors[] = 'must be a valid date.';
                break;
            case 'datetime':
                $value = trim((string)($raw ?? ''));
                if ($value !== '') {
                    $candidate = str_replace('T',' ',$value);
                    if (!self::validDate($candidate,'Y-m-d H:i')) $errors[] = 'must be a valid date and time.';
                    $value = $candidate;
                }
                break;
            case 'select':
                $value = trim((string)($raw ?? ''));
                if ($value !== '' && !in_array($value,(array)($settings['options']??[]),true)) $errors[] = 'contains an unavailable option.';
                break;
            case 'multiselect':
                $requested = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw]);
                $allowed = (array)($settings['options']??[]); $value=[];
                foreach ($requested as $item) {
                    $item=trim((string)$item); if ($item!=='' && in_array($item,$allowed,true) && !in_array($item,$value,true)) $value[]=$item;
                }
                break;
            case 'media':
                $id = max(0,(int)$raw); $value = $id > 0 && MediaLibrary::find($id) ? $id : null;
                if ($id > 0 && $value === null) $errors[] = 'references a missing media item.';
                break;
            case 'gallery':
                $ids = is_array($raw) ? $raw : []; $value=[];
                foreach (array_values(array_unique(array_map('intval',$ids))) as $id) if ($id>0 && MediaLibrary::find($id)) $value[]=$id;
                break;
            case 'url':
                $value = trim((string)($raw ?? ''));
                if ($value !== '' && !self::validUrl($value)) $errors[] = 'must be a complete http(s) URL or a local /path.';
                break;
            case 'email':
                $value = trim((string)($raw ?? ''));
                if ($value !== '' && filter_var($value,FILTER_VALIDATE_EMAIL)===false) $errors[] = 'must be a valid email address.';
                break;
            case 'relation':
                $requested = is_array($raw) ? $raw : ($raw === null || $raw === '' ? [] : [$raw]);
                $targetModel = (int)($settings['target_model_id']??0);
                $multiple = ContentModels::relationAllowsMultiple($settings);
                $exclusiveTargets = ContentModels::relationUsesExclusiveTargets($settings);
                foreach (array_values(array_unique(array_map('intval',$requested))) as $id) {
                    if ($id<1) continue;
                    if ($currentEntryId !== null && $id === $currentEntryId) { $errors[]='cannot relate an entry to itself.'; continue; }
                    $target=self::rawFind($id,true);
                    if (!$target || (int)$target['model_id']!==$targetModel) { $errors[]='contains a missing or invalid relation.'; continue; }
                    if (!empty($target['deleted_at']) && !self::relationAlreadyLinked($currentEntryId,$fieldKey,$id)) {
                        $errors[]='contains an entry that is currently in Trash.';
                        continue;
                    }
                    if ($exclusiveTargets && self::relationTargetClaimed($modelId,$fieldKey,$id,$currentEntryId)) {
                        $errors[]='contains an entry that is already assigned by another relation of this type.';
                        continue;
                    }
                    $relationIds[]=$id; if (!$multiple) break;
                }
                $value = $multiple ? $relationIds : ($relationIds[0]??null);
                break;
            case 'component': case 'repeater':
                $componentId=(int)($settings['component_id']??0); $component=ContentModels::component($componentId);
                if (!$component) { $errors[]='uses a missing component definition.'; $value=$type==='repeater'?[]:[]; break; }
                $items = $type==='repeater' ? (is_array($raw)?array_values($raw):[]) : [is_array($raw)?$raw:[]];
                $cleanItems=[];
                foreach (array_slice($items,0,100) as $index=>$item) {
                    if (!is_array($item) || !self::hasMeaningfulRaw($item)) continue;
                    $clean=[];
                    foreach (ContentModels::componentFields($componentId, true) as $subfield) {
                        $subKey=(string)$subfield['field_key'];
                        if (!empty($subfield['archived_at'])) {
                            if (array_key_exists($subKey,$item)) $clean[$subKey]=$item[$subKey];
                            continue;
                        }
                        $sub=self::validateFieldValue($subfield,$item[$subKey]??null,$modelId,$fieldKey.'.'.$subKey,$currentEntryId);
                        $clean[$subKey]=$sub['value'];
                        foreach ($sub['errors'] as $err) $errors[]='item '.($index+1).' / '.(string)$subfield['label'].': '.$err;
                    }
                    $cleanItems[]=$clean;
                }
                $value = $type==='repeater' ? $cleanItems : ($cleanItems[0]??[]);
                break;
            default:
                $value = trim((string)($raw ?? ''));
        }

        if ($required && self::isEmptyValue($value)) $errors[] = 'is required.';
        return ['value'=>$value,'relation_ids'=>$relationIds,'errors'=>$errors];
    }

    public static function reindexModel(int $modelId): void
    {
        if ($modelId < 1) return;
        $stmt = Database::connection()->prepare('SELECT id,field_values_json FROM content_entries WHERE model_id=? ORDER BY id ASC');
        $stmt->execute([$modelId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $entry) {
            self::syncSearchAndUniqueValues((int)$entry['id'],$modelId,self::decodedValues((string)$entry['field_values_json']));
        }
    }

    public static function syncSearchAndUniqueValues(int $entryId, int $modelId, array $values): void
    {
        $db = Database::connection();
        $db->prepare('DELETE FROM content_search_values WHERE entry_id=?')->execute([$entryId]);
        $db->prepare('DELETE FROM content_unique_values WHERE entry_id=?')->execute([$entryId]);
        $search = $db->prepare('INSERT INTO content_search_values (entry_id,model_id,field_key,value_text,created_at,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $unique = $db->prepare('INSERT INTO content_unique_values (entry_id,model_id,field_key,value_hash,value_text,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        foreach (ContentModels::fields($modelId) as $field) {
            $key = (string)$field['field_key'];
            $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
            $value = $values[$key] ?? null;
            $text = self::valueToIndexText($value);
            if ($text !== '' && (int)($settings['searchable'] ?? 0) === 1) $search->execute([$entryId,$modelId,$key,mb_substr($text,0,20000)]);
            if ($text !== '' && (int)($settings['unique'] ?? 0) === 1) $unique->execute([$entryId,$modelId,$key,hash('sha256',mb_strtolower($text)),mb_substr($text,0,500)]);
        }
    }

    private static function uniqueValueExists(int $modelId, string $fieldKey, mixed $value, ?int $existingId): bool
    {
        $text = self::valueToIndexText($value); if ($text === '') return false;
        $hash = hash('sha256',mb_strtolower($text));
        $sql = 'SELECT 1 FROM content_unique_values WHERE model_id=? AND field_key=? AND value_hash=?' . ($existingId ? ' AND entry_id<>?' : '') . ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql); $args = [$modelId,$fieldKey,$hash]; if ($existingId) $args[] = $existingId; $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }

    private static function valueToIndexText(mixed $value): string
    {
        if ($value === null) return '';
        if (is_bool($value)) return $value ? '1' : '0';
        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_array($value)) return trim(implode(' ',array_map(static fn($v): string => is_scalar($v) ? (string)$v : '', $value)));
        $text = html_entity_decode(strip_tags((string)$value),ENT_QUOTES|ENT_HTML5,'UTF-8');
        return trim(preg_replace('/\s+/u',' ',$text) ?: '');
    }

    private static function hydrateRelations(int $entryId, int $modelId, array $values): array
    {
        foreach (ContentModels::fields($modelId) as $field) {
            if ((string)$field['field_type'] !== 'relation') continue;
            $key=(string)$field['field_key']; $targets=self::relationTargets($entryId,$key); $ids=array_map(static fn(array $r):int=>(int)$r['id'],$targets);
            $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null);
            $values[$key]=ContentModels::relationAllowsMultiple($settings)?$ids:($ids[0]??null);
        }
        return $values;
    }


    /** @return list<int> */
    private static function preservedRelationIds(array $field, mixed $value, int $sourceEntryId): array
    {
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
        $targetModelId = (int)($settings['target_model_id'] ?? 0);
        $multiple = ContentModels::relationAllowsMultiple($settings);
        $requested = is_array($value) ? $value : ($value === null || $value === '' ? [] : [$value]);
        $ids = [];
        foreach (array_values(array_unique(array_map('intval',$requested))) as $id) {
            if ($id < 1 || $id === $sourceEntryId) continue;
            $target = self::rawFind($id,true);
            if (!$target || (int)$target['model_id'] !== $targetModelId) continue;
            $ids[] = $id;
            if (!$multiple) break;
        }
        return $ids;
    }

    private static function mergeArchivedComponentValues(array $field, mixed $submitted, mixed $stored): mixed
    {
        $type = (string)($field['field_type'] ?? '');
        if (!in_array($type,['component','repeater'],true)) return $submitted;
        $settings = is_array($field['settings'] ?? null) ? $field['settings'] : ContentModels::decodeSettings($field['settings_json'] ?? null);
        $componentId = (int)($settings['component_id'] ?? 0);
        if ($componentId < 1) return $submitted;
        $archived = array_values(array_filter(ContentModels::componentFields($componentId,true), static fn(array $row): bool => !empty($row['archived_at'])));
        if (!$archived) return $submitted;

        if ($type === 'component') {
            $current = is_array($submitted) ? $submitted : [];
            $old = is_array($stored) ? $stored : [];
            foreach ($archived as $subfield) {
                $key=(string)$subfield['field_key'];
                if (!array_key_exists($key,$current) && array_key_exists($key,$old)) $current[$key]=$old[$key];
            }
            return $current;
        }

        $currentItems = is_array($submitted) ? array_values($submitted) : [];
        $oldItems = is_array($stored) ? array_values($stored) : [];
        foreach ($currentItems as $index => &$item) {
            if (!is_array($item)) continue;
            $old = is_array($oldItems[$index] ?? null) ? $oldItems[$index] : [];
            foreach ($archived as $subfield) {
                $key=(string)$subfield['field_key'];
                if (!array_key_exists($key,$item) && array_key_exists($key,$old)) $item[$key]=$old[$key];
            }
        }
        unset($item);
        return $currentItems;
    }

    private static function relationAlreadyLinked(?int $sourceEntryId, string $fieldKey, int $targetEntryId): bool
    {
        if (!$sourceEntryId || $sourceEntryId < 1 || $targetEntryId < 1) return false;
        $stmt=Database::connection()->prepare('SELECT 1 FROM content_relations WHERE source_entry_id=? AND field_key=? AND target_entry_id=? LIMIT 1');
        $stmt->execute([$sourceEntryId,$fieldKey,$targetEntryId]);
        return (bool)$stmt->fetchColumn();
    }

    private static function relationTargetClaimed(int $sourceModelId, string $fieldKey, int $targetEntryId, ?int $currentEntryId): bool
    {
        $sql='SELECT 1 FROM content_relations r JOIN content_entries s ON s.id=r.source_entry_id '
            . 'WHERE s.model_id=? AND r.field_key=? AND r.target_entry_id=?';
        $args=[$sourceModelId,$fieldKey,$targetEntryId];
        if ($currentEntryId) { $sql.=' AND r.source_entry_id<>?'; $args[]=$currentEntryId; }
        $sql.=' LIMIT 1';
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }

    private static function slugExists(int $modelId, string $slug, ?int $existingId): bool
    {
        $sql='SELECT 1 FROM content_entries WHERE model_id=? AND slug=?' . ($existingId?' AND id<>?':'') . ' LIMIT 1';
        $stmt=Database::connection()->prepare($sql); $args=[$modelId,$slug]; if ($existingId) $args[]=$existingId; $stmt->execute($args);
        return (bool)$stmt->fetchColumn();
    }

    public static function utcToLocalInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        try {
            $utc = new DateTimeZone('UTC');
            $tz = new DateTimeZone((string)Env::get('APP_TIMEZONE','Europe/Ljubljana'));
            $date = new DateTimeImmutable($value,$utc);
            return $date->setTimezone($tz)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function localInputToUtc(string $value, array &$errors): ?string
    {
        try { $tz = new DateTimeZone((string)Env::get('APP_TIMEZONE','Europe/Ljubljana')); }
        catch (\Throwable) { $tz = new DateTimeZone('UTC'); }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value,$tz);
        $last = DateTimeImmutable::getLastErrors();
        if (!$date || (is_array($last) && (($last['warning_count'] ?? 0) > 0 || ($last['error_count'] ?? 0) > 0)) || $date->format('Y-m-d\TH:i') !== $value) {
            $errors[] = 'Publish date and time is invalid for the configured site timezone.'; return null;
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function validCanonical(string $value): bool
    {
        if (filter_var($value,FILTER_VALIDATE_URL) === false) return false;
        return in_array(strtolower((string)parse_url($value,PHP_URL_SCHEME)),['http','https'],true);
    }

    private static function validDate(string $value,string $format): bool
    {
        $dt=\DateTimeImmutable::createFromFormat('!'.$format,$value,new \DateTimeZone('UTC'));
        return $dt instanceof \DateTimeImmutable && $dt->format($format)===$value;
    }

    private static function validUrl(string $value): bool
    {
        if (str_starts_with($value,'/') && !str_starts_with($value,'//')) return Security::validRequestPath(parse_url($value,PHP_URL_PATH) ?: '/');
        if (filter_var($value,FILTER_VALIDATE_URL)===false) return false;
        $scheme=strtolower((string)parse_url($value,PHP_URL_SCHEME));
        return in_array($scheme,['http','https'],true);
    }

    private static function hasMeaningfulRaw(mixed $value): bool
    {
        if ($value === null) return false;
        if (is_array($value)) {
            foreach ($value as $item) if (self::hasMeaningfulRaw($item)) return true;
            return false;
        }
        if (is_bool($value)) return $value;
        if (is_int($value) || is_float($value)) return true;
        return trim(strip_tags((string)$value)) !== '';
    }

    private static function isEmptyValue(mixed $value): bool
    {
        if ($value===null) return true;
        if (is_string($value)) return trim(strip_tags($value))==='';
        if (is_array($value)) return count($value)===0;
        return false;
    }

    private static function clip(mixed $value,int $max): string { return mb_substr(trim((string)$value),0,$max); }
    private static function nullable(mixed $value): ?string { $v=trim((string)($value??'')); return $v===''?null:$v; }

    private function __construct() {}
}
