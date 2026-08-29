<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

/**
 * Structured content schema registry.
 *
 * Content models describe editor-facing entities (Dogs, Events, Testimonials, ...).
 * Components are reusable groups of fields that can be embedded once or repeated.
 */
final class ContentModels
{
    private const RESERVED_SLUGS = [
        'admin','login','api','install','assets','uploads','blog','pages','posts','media','system','security','themes',
        'health','robots.txt','sitemap.xml','theme.css','account','content','_talvoro'
    ];

    private const RESERVED_KEYS = [
        'admin','login','api','install','assets','uploads','blog','pages','posts','media','system','security','themes','content',
        // Stable framework/content properties. User-defined schemas must never
        // shadow these names because they are also used by revisions, routing,
        // permissions, APIs and lifecycle code.
        'id','model_id','author_id','title','slug','status','published_at','created_at','updated_at','deleted_at','deleted_by',
        'field_values','field_values_json','featured_media_id','seo_title','seo_description','canonical_url','robots',
        'social_title','social_description','social_media_id'
    ];

    private const ICONS = [
        'collection' => 'Collection', 'paw' => 'Animal / pet', 'calendar' => 'Event', 'quote' => 'Testimonial',
        'person' => 'Person / team', 'product' => 'Product', 'pin' => 'Location', 'portfolio' => 'Portfolio',
        'star' => 'Featured', 'heart' => 'Community',
    ];

    /** @return array<string,string> */
    public static function fieldTypes(): array
    {
        return [
            'text' => 'Single-line text',
            'textarea' => 'Multi-line text',
            'rich_text' => 'Rich text',
            'number' => 'Number',
            'boolean' => 'Yes / No',
            'date' => 'Date',
            'datetime' => 'Date & time',
            'select' => 'Select',
            'multiselect' => 'Multi-select',
            'media' => 'Media',
            'gallery' => 'Media gallery',
            'url' => 'URL',
            'email' => 'Email',
            'relation' => 'Relation',
            'component' => 'Component',
            'repeater' => 'Repeater',
        ];
    }

    /** @return array<string,string> */
    public static function icons(): array
    {
        return self::ICONS;
    }

    public static function relationType(array $settings): string
    {
        $type=(string)($settings['relation_type']??'');
        if (in_array($type,['one_to_one','many_to_one','one_to_many','many_to_many'],true)) return $type;
        return (int)($settings['multiple']??0)===1 ? 'many_to_many' : 'many_to_one';
    }

    public static function relationAllowsMultiple(array $settings): bool
    {
        return in_array(self::relationType($settings),['one_to_many','many_to_many'],true);
    }

    public static function relationUsesExclusiveTargets(array $settings): bool
    {
        return in_array(self::relationType($settings),['one_to_one','one_to_many'],true);
    }

    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT m.*,u.display_name created_by_name,
                       (SELECT COUNT(*) FROM content_fields f WHERE f.model_id=m.id AND f.archived_at IS NULL) field_count,
                       (SELECT COUNT(*) FROM content_entries e WHERE e.model_id=m.id AND e.deleted_at IS NULL) entry_count
                FROM content_models m LEFT JOIN users u ON u.id=m.created_by';
        if ($activeOnly) $sql .= " WHERE m.status='active'";
        $sql .= ' ORDER BY m.plural_name ASC,m.id ASC';
        try { return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
        catch (\Throwable) { return []; }
    }

    public static function publicModels(): array
    {
        try {
            return Database::connection()->query(
                "SELECT * FROM content_models WHERE status='active' AND is_public=1 ORDER BY plural_name ASC,id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM content_models WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findByKey(string $modelKey): ?array
    {
        $modelKey = self::fieldKey($modelKey);
        if ($modelKey === '') return null;
        $stmt = Database::connection()->prepare('SELECT * FROM content_models WHERE LOWER(model_key)=LOWER(?) LIMIT 1');
        $stmt->execute([$modelKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findBySlug(string $slug, bool $activeOnly = false): ?array
    {
        $slug = self::slug($slug);
        if ($slug === '') return null;
        $sql = 'SELECT * FROM content_models WHERE slug=?';
        if ($activeOnly) $sql .= " AND status='active'";
        $sql .= ' LIMIT 1';
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$slug]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validateModel(array $input, ?int $existingId = null): array
    {
        $existing = $existingId ? self::find($existingId) : null;
        $singular = self::clip($input['singular_name'] ?? '', 120);
        $plural = self::clip($input['plural_name'] ?? '', 120);
        $rawSlug = self::clip($input['slug'] ?? '', 100);
        $slug = self::slug($rawSlug !== '' ? $rawSlug : $plural);
        $requestedKey = self::fieldKey((string)($input['model_key'] ?? ''));
        $modelKey = $existing ? (string)($existing['model_key'] ?? self::fieldKey((string)$existing['slug'])) : ($requestedKey !== '' ? $requestedKey : self::fieldKey($singular));
        $description = self::clip($input['description'] ?? '', 500);
        $statusInput = (string)($input['status'] ?? 'active');
        $status = in_array($statusInput, ['active','disabled'], true) ? $statusInput : 'active';
        $iconInput = (string)($input['icon'] ?? 'collection');
        $icon = array_key_exists($iconInput, self::ICONS) ? $iconInput : 'collection';
        $isPublic = isset($input['is_public']) ? 1 : 0;
        $hasArchive = isset($input['has_archive']) ? 1 : 0;
        $hasUrls = isset($input['has_urls']) ? 1 : 0;
        $searchable = isset($input['searchable']) ? 1 : 0;
        $sitemap = isset($input['sitemap_enabled']) ? 1 : 0;
        $revisions = isset($input['enable_revisions']) ? 1 : 0;
        $autosave = isset($input['enable_autosave']) ? 1 : 0;
        $trash = isset($input['enable_trash']) ? 1 : 0;
        $seo = isset($input['enable_seo']) ? 1 : 0;
        $featured = isset($input['enable_featured_image']) ? 1 : 0;
        $scheduling = isset($input['enable_scheduling']) ? 1 : 0;

        $errors = [];
        if (mb_strlen($singular) < 2) $errors[] = 'Singular name must be at least 2 characters.';
        if (mb_strlen($plural) < 2) $errors[] = 'Plural name must be at least 2 characters.';
        if ($slug === '' || strlen($slug) > 100) $errors[] = 'Choose a short URL base using letters, numbers and hyphens.';
        if ($slug !== '' && self::slugIsReserved($slug, $existingId)) $errors[] = 'That URL base is reserved or already in use.';
        if ($modelKey === '' || !preg_match('/^[a-z][a-z0-9_]{0,99}$/', $modelKey)) $errors[] = 'The internal key must start with a letter and use only lowercase letters, numbers and underscores.';
        if (in_array($modelKey, self::RESERVED_KEYS, true)) $errors[] = 'That internal key is reserved by Talvoro.';
        if ($modelKey !== '') {
            $sql = 'SELECT id FROM content_models WHERE LOWER(model_key)=LOWER(?)' . ($existingId ? ' AND id<>?' : '') . ' LIMIT 1';
            $stmt = Database::connection()->prepare($sql); $args = [$modelKey]; if ($existingId) $args[] = $existingId; $stmt->execute($args);
            if ($stmt->fetchColumn()) $errors[] = 'That internal key is already used by another content model.';
        }
        if ($existing && (string)$existing['slug'] !== $slug && self::entryCount($existingId ?? 0, false) > 0) {
            $errors[] = 'The URL base cannot be changed after content has been created. This prevents broken public URLs.';
        }
        if (!$isPublic) {
            $hasArchive = 0; $hasUrls = 0; $sitemap = 0; $seo = 0; $scheduling = 0;
        }
        if (!$hasUrls) $seo = 0;
        if (!$revisions) $autosave = 0;

        return ['data' => [
            'singular_name' => $singular, 'plural_name' => $plural, 'model_key' => $modelKey, 'slug' => $slug, 'icon' => $icon,
            'description' => $description, 'status' => $status, 'is_public' => $isPublic, 'has_archive' => $hasArchive,
            'has_urls' => $hasUrls, 'searchable' => $searchable, 'sitemap_enabled' => $sitemap,
            'enable_revisions' => $revisions, 'enable_autosave' => $autosave, 'enable_trash' => $trash, 'enable_seo' => $seo,
            'enable_featured_image' => $featured, 'enable_scheduling' => $scheduling,
        ], 'errors' => array_values(array_unique($errors))];
    }

    public static function createModel(array $data, int $userId): int
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            'INSERT INTO content_models (singular_name,plural_name,model_key,slug,icon,description,status,is_public,has_archive,has_urls,searchable,sitemap_enabled,enable_revisions,enable_autosave,enable_trash,enable_seo,enable_featured_image,enable_scheduling,created_by,created_at,updated_at) '
            . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $data['singular_name'],$data['plural_name'],$data['model_key'],$data['slug'],$data['icon'],self::nullable($data['description']),$data['status'],
            (int)$data['is_public'],(int)$data['has_archive'],(int)$data['has_urls'],(int)$data['searchable'],(int)$data['sitemap_enabled'],
            (int)$data['enable_revisions'],(int)$data['enable_autosave'],(int)$data['enable_trash'],(int)$data['enable_seo'],(int)$data['enable_featured_image'],(int)$data['enable_scheduling'],$userId,
        ]);
        $id = (int)$db->lastInsertId();
        self::seedModelPermissions($id);
        return $id;
    }

    public static function updateModel(int $id, array $data): void
    {
        $stmt = Database::connection()->prepare(
            'UPDATE content_models SET singular_name=?,plural_name=?,slug=?,icon=?,description=?,status=?,is_public=?,has_archive=?,has_urls=?,searchable=?,sitemap_enabled=?,enable_revisions=?,enable_autosave=?,enable_trash=?,enable_seo=?,enable_featured_image=?,enable_scheduling=?,updated_at=UTC_TIMESTAMP() WHERE id=?'
        );
        $stmt->execute([
            $data['singular_name'],$data['plural_name'],$data['slug'],$data['icon'],self::nullable($data['description']),$data['status'],
            (int)$data['is_public'],(int)$data['has_archive'],(int)$data['has_urls'],(int)$data['searchable'],(int)$data['sitemap_enabled'],
            (int)$data['enable_revisions'],(int)$data['enable_autosave'],(int)$data['enable_trash'],(int)$data['enable_seo'],(int)$data['enable_featured_image'],(int)$data['enable_scheduling'],$id,
        ]);
        if ($stmt->rowCount() < 1 && !self::find($id)) throw new RuntimeException('Content model not found.');
    }

    public static function deleteModel(int $id): void
    {
        $model = self::find($id);
        if (!$model) throw new RuntimeException('Content model not found.');
        if (self::entryCount($id, false) > 0) throw new RuntimeException('Delete the model content first. Talvoro will not destroy structured content implicitly.');
        if (self::modelReferencedByRelation($id)) throw new RuntimeException('This model is still used as the target of a relation field. Change or remove that relation first.');

        $usage = PageBlocks::modelUsage((string)($model['model_key'] ?? ''));
        if ($usage['total'] > 0) {
            $parts = [];
            if ($usage['pages'] > 0) $parts[] = $usage['pages'] . ' page' . ($usage['pages'] === 1 ? '' : 's');
            if ($usage['patterns'] > 0) $parts[] = $usage['patterns'] . ' pattern' . ($usage['patterns'] === 1 ? '' : 's');
            throw new RuntimeException('This model is still used by ' . implode(' and ', $parts) . '. Remove those Connected content blocks first.');
        }

        $stmt = Database::connection()->prepare('DELETE FROM content_models WHERE id=?');
        $stmt->execute([$id]);
    }

    public static function entryCount(int $modelId, bool $activeOnly = true): int
    {
        if ($modelId < 1) return 0;
        $sql = 'SELECT COUNT(*) FROM content_entries WHERE model_id=?';
        if ($activeOnly) $sql .= ' AND deleted_at IS NULL';
        try {
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute([$modelId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable) { return 0; }
    }

    public static function fields(int $modelId, bool $includeArchived = false): array
    {
        if ($modelId < 1) return [];
        $sql = 'SELECT * FROM content_fields WHERE model_id=?' . ($includeArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY sort_order ASC,id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$modelId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['settings'] = self::decodeSettings($row['settings_json'] ?? null);
        unset($row);
        return $rows;
    }

    public static function fieldByKey(int $modelId, string $fieldKey): ?array
    {
        $fieldKey = self::fieldKey($fieldKey);
        if ($modelId < 1 || $fieldKey === '') return null;
        $stmt = Database::connection()->prepare('SELECT * FROM content_fields WHERE model_id=? AND field_key=? AND archived_at IS NULL LIMIT 1');
        $stmt->execute([$modelId,$fieldKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['settings'] = self::decodeSettings($row['settings_json'] ?? null);
        return $row;
    }

    public static function field(int $modelId, int $fieldId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM content_fields WHERE id=? AND model_id=? LIMIT 1');
        $stmt->execute([$fieldId,$modelId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['settings'] = self::decodeSettings($row['settings_json'] ?? null);
        return $row;
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validateField(array $input, int $modelId, ?int $fieldId = null): array
    {
        $existing = $fieldId ? self::field($modelId, $fieldId) : null;
        $label = self::clip($input['label'] ?? '', 120);
        $key = $existing ? (string)$existing['field_key'] : self::fieldKey((string)($input['field_key'] ?? $label));
        $type = trim((string)($input['field_type'] ?? 'text'));
        $help = self::clip($input['help_text'] ?? '', 500);
        $placeholder = self::clip($input['placeholder'] ?? '', 255);
        $required = isset($input['is_required']) ? 1 : 0;
        $sort = max(0, min(10000, (int)($input['sort_order'] ?? 100)));
        $settings = self::settingsFromInput($type, $input);
        $errors = [];

        if ($label === '') $errors[] = 'Field label is required.';
        if ($key === '') $errors[] = 'Field key could not be generated.';
        if ($key !== '' && !preg_match('/^[a-z][a-z0-9_]{0,99}$/', $key)) $errors[] = 'The field key must start with a letter and use only lowercase letters, numbers and underscores.';
        if (in_array($key, self::RESERVED_KEYS, true)) $errors[] = 'That field key is reserved by Talvoro.';
        if (!isset(self::fieldTypes()[$type])) $errors[] = 'Choose a supported field type.';
        if ($existing && (string)$existing['field_type'] !== $type && self::entryCount($modelId, false) > 0) {
            $errors[] = 'Field type cannot be changed after entries exist. Create a new field instead to protect existing data.';
            $type = (string)$existing['field_type'];
            $settings = self::decodeSettings($existing['settings_json'] ?? null);
        }
        if ($existing && self::entryCount($modelId, false) > 0 && (string)$existing['field_type'] === $type) {
            $existingSettings = self::decodeSettings($existing['settings_json'] ?? null);
            if ($type === 'relation') {
                $structuralChanged = (int)($existingSettings['target_model_id'] ?? 0) !== (int)($settings['target_model_id'] ?? 0)
                    || self::relationType($existingSettings) !== self::relationType($settings);
                if ($structuralChanged) {
                    $errors[] = 'Relation target and cardinality cannot be changed after entries exist. Create a new relation field instead to protect saved relationships.';
                    $settings = $existingSettings;
                }
            }
            if (in_array($type, ['component','repeater'], true)
                && (int)($existingSettings['component_id'] ?? 0) !== (int)($settings['component_id'] ?? 0)) {
                $errors[] = 'The reusable component cannot be changed after entries exist. Create a new field instead to protect saved structured content.';
                $settings = $existingSettings;
            }
        }
        $stmt = Database::connection()->prepare('SELECT id FROM content_fields WHERE model_id=? AND field_key=?' . ($fieldId ? ' AND id<>?' : '') . ' LIMIT 1');
        $args = [$modelId,$key]; if ($fieldId) $args[] = $fieldId;
        $stmt->execute($args);
        if ($stmt->fetchColumn()) $errors[] = 'That field key is already used in this content model.';
        if ($existing && (int)($settings['unique'] ?? 0) === 1) {
            $oldSettings=self::decodeSettings($existing['settings_json']??null);
            if ((int)($oldSettings['unique']??0)!==1 && self::fieldHasDuplicateScalarValues($modelId,$key)) {
                $errors[] = 'Unique cannot be enabled yet because existing entries contain duplicate values in this field.';
            }
        }
        $errors = array_merge($errors, self::validateFieldSettings($type, $settings, $modelId));

        return ['data' => [
            'field_key' => $key,'label' => $label,'field_type' => $type,'help_text' => $help,'placeholder' => $placeholder,
            'is_required' => $required,'sort_order' => $sort,'settings' => $settings,
            'settings_json' => json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ], 'errors' => array_values(array_unique($errors))];
    }

    public static function saveField(int $modelId, array $data, ?int $fieldId = null): int
    {
        return (int)self::schemaMutation(static function (PDO $db) use ($modelId,$data,$fieldId): int {
            if ($fieldId) {
                $stmt = $db->prepare(
                    'UPDATE content_fields SET label=?,field_type=?,help_text=?,placeholder=?,is_required=?,settings_json=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND model_id=?'
                );
                $stmt->execute([$data['label'],$data['field_type'],self::nullable($data['help_text']),self::nullable($data['placeholder']),$data['is_required'],$data['settings_json'],$data['sort_order'],$fieldId,$modelId]);
                if ($stmt->rowCount() < 1 && !self::field($modelId,$fieldId)) throw new RuntimeException('Content model field not found.');
                CustomContent::reindexModel($modelId);
                return $fieldId;
            }
            $stmt = $db->prepare(
                'INSERT INTO content_fields (model_id,field_key,label,field_type,help_text,placeholder,is_required,settings_json,sort_order,created_at,updated_at) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $stmt->execute([$modelId,$data['field_key'],$data['label'],$data['field_type'],self::nullable($data['help_text']),self::nullable($data['placeholder']),$data['is_required'],$data['settings_json'],$data['sort_order']]);
            $id=(int)$db->lastInsertId();
            CustomContent::reindexModel($modelId);
            return $id;
        });
    }

    public static function deleteField(int $modelId, int $fieldId): void
    {
        self::schemaMutation(static function (PDO $db) use ($modelId,$fieldId): void {
            $count = self::fieldDataCount($modelId, $fieldId);
            if ($count > 0) {
                $db->prepare('UPDATE content_fields SET archived_at=COALESCE(archived_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=? AND model_id=?')->execute([$fieldId,$modelId]);
            } else {
                $db->prepare('DELETE FROM content_fields WHERE id=? AND model_id=?')->execute([$fieldId,$modelId]);
            }
            CustomContent::reindexModel($modelId);
        });
    }

    public static function fieldDataCount(int $modelId, int $fieldId): int
    {
        $field = self::field($modelId, $fieldId);
        if (!$field) return 0;
        $key = (string)$field['field_key'];
        $path = '$."' . $key . '"';
        $stmt = Database::connection()->prepare(
            "SELECT COUNT(*) FROM content_entries WHERE model_id=?
             AND JSON_CONTAINS_PATH(field_values_json,'one',?)=1
             AND JSON_TYPE(JSON_EXTRACT(field_values_json,?)) <> 'NULL'
             AND NOT (JSON_TYPE(JSON_EXTRACT(field_values_json,?))='STRING' AND TRIM(JSON_UNQUOTE(JSON_EXTRACT(field_values_json,?)))='')
             AND NOT (JSON_TYPE(JSON_EXTRACT(field_values_json,?)) IN ('ARRAY','OBJECT') AND JSON_LENGTH(JSON_EXTRACT(field_values_json,?))=0)"
        );
        $stmt->execute([$modelId,$path,$path,$path,$path,$path,$path]);
        return (int)$stmt->fetchColumn();
    }

    public static function restoreField(int $modelId, int $fieldId): void
    {
        self::schemaMutation(static function (PDO $db) use ($modelId,$fieldId): void {
            $db->prepare('UPDATE content_fields SET archived_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND model_id=?')->execute([$fieldId,$modelId]);
            CustomContent::reindexModel($modelId);
        });
    }

    /** @param list<int> $fieldIds */
    public static function reorderFields(int $modelId, array $fieldIds): void
    {
        self::reorderSchemaRows('content_fields', 'model_id', $modelId, $fieldIds);
    }

    public static function components(bool $activeOnly = false): array
    {
        $sql = 'SELECT c.*,u.display_name created_by_name,(SELECT COUNT(*) FROM component_fields f WHERE f.component_id=c.id AND f.archived_at IS NULL) field_count FROM content_components c LEFT JOIN users u ON u.id=c.created_by';
        if ($activeOnly) $sql .= " WHERE c.status='active'";
        $sql .= ' ORDER BY c.name ASC,c.id ASC';
        try { return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
        catch (\Throwable) { return []; }
    }

    public static function component(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM content_components WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function componentFields(int $componentId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM component_fields WHERE component_id=?' . ($includeArchived ? '' : ' AND archived_at IS NULL') . ' ORDER BY sort_order ASC,id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$componentId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) $row['settings'] = self::decodeSettings($row['settings_json'] ?? null);
        unset($row);
        return $rows;
    }

    public static function componentField(int $componentId, int $fieldId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM component_fields WHERE id=? AND component_id=? LIMIT 1');
        $stmt->execute([$fieldId,$componentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['settings'] = self::decodeSettings($row['settings_json'] ?? null);
        return $row;
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validateComponent(array $input, ?int $id = null): array
    {
        $name = self::clip($input['name'] ?? '', 120);
        $slug = self::slug((string)($input['slug'] ?? $name));
        $description = self::clip($input['description'] ?? '', 500);
        $statusInput = (string)($input['status'] ?? 'active');
        $status = in_array($statusInput, ['active','disabled'], true) ? $statusInput : 'active';
        $errors = [];
        if (mb_strlen($name) < 2) $errors[] = 'Component name must be at least 2 characters.';
        if ($slug === '') $errors[] = 'Component slug is required.';
        if (strlen($slug) > 100) $errors[] = 'Component slug must be 100 characters or fewer.';
        $sql = 'SELECT id FROM content_components WHERE slug=?' . ($id ? ' AND id<>?' : '') . ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql); $args = [$slug]; if ($id) $args[] = $id; $stmt->execute($args);
        if ($stmt->fetchColumn()) $errors[] = 'That component slug is already in use.';
        return ['data' => ['name'=>$name,'slug'=>$slug,'description'=>$description,'status'=>$status], 'errors'=>$errors];
    }

    public static function saveComponent(array $data, int $userId, ?int $id = null): int
    {
        if ($id) {
            $stmt = Database::connection()->prepare('UPDATE content_components SET name=?,slug=?,description=?,status=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
            $stmt->execute([$data['name'],$data['slug'],self::nullable($data['description']),$data['status'],$id]);
            return $id;
        }
        $stmt = Database::connection()->prepare('INSERT INTO content_components (name,slug,description,status,created_by,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$data['name'],$data['slug'],self::nullable($data['description']),$data['status'],$userId]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function deleteComponent(int $id): void
    {
        if (self::componentInUse($id)) throw new RuntimeException('This component is used by a content model and cannot be deleted.');
        Database::connection()->prepare('DELETE FROM content_components WHERE id=?')->execute([$id]);
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validateComponentField(array $input, int $componentId, ?int $fieldId = null): array
    {
        $existing = $fieldId ? self::componentField($componentId, $fieldId) : null;
        $label = self::clip($input['label'] ?? '', 120);
        $key = $existing ? (string)$existing['field_key'] : self::fieldKey((string)($input['field_key'] ?? $label));
        $type = trim((string)($input['field_type'] ?? 'text'));
        // Components cannot recursively contain components/repeaters. This prevents cycles and keeps schemas predictable.
        $componentAllowed = array_diff_key(self::fieldTypes(), ['component'=>true,'repeater'=>true,'relation'=>true]);
        $help = self::clip($input['help_text'] ?? '', 500);
        $placeholder = self::clip($input['placeholder'] ?? '', 255);
        $required = isset($input['is_required']) ? 1 : 0;
        $sort = max(0, min(10000, (int)($input['sort_order'] ?? 100)));
        $settings = self::settingsFromInput($type, $input);
        // Component values live inside their owning entry. Uniqueness and global
        // search indexing therefore belong to model fields, not nested fields.
        $settings['unique'] = 0;
        $settings['searchable'] = 0;
        $errors = [];
        if ($label === '') $errors[] = 'Field label is required.';
        if ($key === '') $errors[] = 'Field key could not be generated.';
        if ($key !== '' && !preg_match('/^[a-z][a-z0-9_]{0,99}$/', $key)) $errors[] = 'The field key must start with a letter and use only lowercase letters, numbers and underscores.';
        if (in_array($key, self::RESERVED_KEYS, true)) $errors[] = 'That field key is reserved by Talvoro.';
        if (!isset($componentAllowed[$type])) $errors[] = 'Choose a supported component field type.';
        if ($existing && (string)$existing['field_type'] !== $type && self::componentInUse($componentId)) {
            $errors[] = 'Field type cannot be changed while this component is used by a content model. Create a new field instead to protect saved content.';
            $type = (string)$existing['field_type'];
            $settings = self::decodeSettings($existing['settings_json'] ?? null);
        }
        $sql = 'SELECT id FROM component_fields WHERE component_id=? AND field_key=?' . ($fieldId ? ' AND id<>?' : '') . ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql); $args = [$componentId,$key]; if ($fieldId) $args[] = $fieldId; $stmt->execute($args);
        if ($stmt->fetchColumn()) $errors[] = 'That field key is already used in this component.';
        $errors = array_merge($errors, self::validateFieldSettings($type, $settings, null));
        return ['data'=>[
            'field_key'=>$key,'label'=>$label,'field_type'=>$type,'help_text'=>$help,'placeholder'=>$placeholder,
            'is_required'=>$required,'sort_order'=>$sort,'settings'=>$settings,
            'settings_json'=>json_encode($settings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}',
        ], 'errors'=>array_values(array_unique($errors))];
    }

    public static function saveComponentField(int $componentId, array $data, ?int $fieldId = null): int
    {
        if ($fieldId) {
            $stmt = Database::connection()->prepare('UPDATE component_fields SET label=?,field_type=?,help_text=?,placeholder=?,is_required=?,settings_json=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND component_id=?');
            $stmt->execute([$data['label'],$data['field_type'],self::nullable($data['help_text']),self::nullable($data['placeholder']),$data['is_required'],$data['settings_json'],$data['sort_order'],$fieldId,$componentId]);
            return $fieldId;
        }
        $stmt = Database::connection()->prepare('INSERT INTO component_fields (component_id,field_key,label,field_type,help_text,placeholder,is_required,settings_json,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$componentId,$data['field_key'],$data['label'],$data['field_type'],self::nullable($data['help_text']),self::nullable($data['placeholder']),$data['is_required'],$data['settings_json'],$data['sort_order']]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function deleteComponentField(int $componentId, int $fieldId): void
    {
        if (self::componentInUse($componentId)) {
            Database::connection()->prepare('UPDATE component_fields SET archived_at=COALESCE(archived_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=? AND component_id=?')->execute([$fieldId,$componentId]);
            return;
        }
        Database::connection()->prepare('DELETE FROM component_fields WHERE id=? AND component_id=?')->execute([$fieldId,$componentId]);
    }

    public static function restoreComponentField(int $componentId, int $fieldId): void
    {
        Database::connection()->prepare('UPDATE component_fields SET archived_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND component_id=?')->execute([$fieldId,$componentId]);
    }

    /** @param list<int> $fieldIds */
    public static function reorderComponentFields(int $componentId, array $fieldIds): void
    {
        self::reorderSchemaRows('component_fields', 'component_id', $componentId, $fieldIds);
    }

    public static function componentInUse(int $componentId): bool
    {
        if ($componentId < 1) return false;
        try {
            $stmt = Database::connection()->prepare(
                "SELECT 1 FROM content_fields WHERE field_type IN ('component','repeater') "
                . "AND JSON_UNQUOTE(JSON_EXTRACT(settings_json,'$.component_id'))=? LIMIT 1"
            );
            $stmt->execute([(string)$componentId]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) { return false; }
    }

    private static function modelReferencedByRelation(int $modelId): bool
    {
        if ($modelId < 1) return false;
        try {
            $stmt = Database::connection()->prepare(
                "SELECT 1 FROM content_fields WHERE field_type='relation' "
                . "AND JSON_UNQUOTE(JSON_EXTRACT(settings_json,'$.target_model_id'))=? LIMIT 1"
            );
            $stmt->execute([(string)$modelId]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) { return false; }
    }

    public static function publicPathReserved(string $path): bool
    {
        $path = '/' . ltrim(mb_strtolower(trim($path)), '/');
        $segment = explode('/', trim($path, '/'))[0] ?? '';
        if ($segment === '') return false;
        try {
            $stmt = Database::connection()->prepare("SELECT 1 FROM content_models WHERE is_public=1 AND (has_urls=1 OR has_archive=1) AND slug=? LIMIT 1");
            $stmt->execute([$segment]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) { return false; }
    }

    /** @return array<string,mixed> */
    public static function decodeSettings(mixed $json): array
    {
        if (is_array($json)) return $json;
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function fieldKey(string $value): string
    {
        $value = self::slug($value);
        $value = str_replace('-', '_', $value);
        return mb_substr($value, 0, 100);
    }

    public static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, ['č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'d','ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss']);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
            if (is_string($ascii) && $ascii !== '') $value = $ascii;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private static function slugIsReserved(string $slug, ?int $existingId): bool
    {
        if (in_array($slug, self::RESERVED_SLUGS, true)) return true;
        if (AdminPath::isProtectedPublicPath('/' . $slug)) return true;
        $sql = 'SELECT id FROM content_models WHERE slug=?' . ($existingId ? ' AND id<>?' : '') . ' LIMIT 1';
        $stmt = Database::connection()->prepare($sql); $args=[$slug]; if ($existingId) $args[]=$existingId; $stmt->execute($args);
        if ($stmt->fetchColumn()) return true;
        try {
            $stmt = Database::connection()->prepare("SELECT 1 FROM pages WHERE path=? OR path LIKE ? LIMIT 1");
            $stmt->execute(['/' . $slug, '/' . $slug . '/%']);
            if ($stmt->fetchColumn()) return true;
        } catch (\Throwable) {}
        try {
            $stmt = Database::connection()->prepare('SELECT 1 FROM redirects WHERE source_path=? LIMIT 1');
            $stmt->execute(['/' . $slug]);
            if ($stmt->fetchColumn()) return true;
        } catch (\Throwable) {}
        return false;
    }

    /** @return array<string,mixed> */
    private static function settingsFromInput(string $type, array $input): array
    {
        $settings = [];
        $settings['unique'] = isset($input['unique']) ? 1 : 0;
        $settings['searchable'] = isset($input['searchable']) ? 1 : 0;
        $default = self::clip($input['default_value'] ?? '', 1000);
        if ($default !== '') $settings['default_value'] = $default;
        if (in_array($type, ['select','multiselect'], true)) {
            $options = preg_split('/\R+/', (string)($input['options'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $clean = [];
            foreach ($options as $option) {
                $option = self::clip($option, 120);
                if ($option !== '' && !in_array($option, $clean, true)) $clean[] = $option;
            }
            $settings['options'] = array_slice($clean, 0, 100);
        }
        if ($type === 'relation') {
            $settings['target_model_id'] = max(0, (int)($input['target_model_id'] ?? 0));
            $relationType=(string)($input['relation_type']??'');
            if (!in_array($relationType,['one_to_one','many_to_one','one_to_many','many_to_many'],true)) {
                $relationType=isset($input['multiple']) ? 'many_to_many' : 'many_to_one';
            }
            $settings['relation_type']=$relationType;
            $settings['multiple']=in_array($relationType,['one_to_many','many_to_many'],true) ? 1 : 0;
        }
        if (in_array($type, ['component','repeater'], true)) {
            $settings['component_id'] = max(0, (int)($input['component_id'] ?? 0));
        }
        if ($type === 'number') {
            foreach (['min','max','step'] as $key) {
                $raw = trim((string)($input[$key] ?? ''));
                if ($raw !== '' && is_numeric($raw)) $settings[$key] = (float)$raw;
            }
        }
        if (in_array($type, ['text','textarea'], true)) {
            $max = max(0, min(100000, (int)($input['max_length'] ?? 0)));
            if ($max > 0) $settings['max_length'] = $max;
        }
        return $settings;
    }

    /** @return list<string> */
    private static function validateFieldSettings(string $type, array $settings, ?int $modelId): array
    {
        $errors = [];
        if (in_array($type, ['select','multiselect'], true) && count((array)($settings['options'] ?? [])) < 1) {
            $errors[] = 'Add at least one option for this field.';
        }
        if ($type === 'relation') {
            $target = (int)($settings['target_model_id'] ?? 0);
            if ($target < 1 || !self::find($target)) $errors[] = 'Choose a valid target content model for the relation.';
        }
        if (in_array($type, ['component','repeater'], true)) {
            $component = (int)($settings['component_id'] ?? 0);
            if ($component < 1 || !self::component($component)) $errors[] = 'Choose a valid reusable component.';
        }
        if ($type === 'number' && isset($settings['min'],$settings['max']) && (float)$settings['min'] > (float)$settings['max']) {
            $errors[] = 'Minimum number cannot be greater than maximum number.';
        }
        if ((int)($settings['unique'] ?? 0) === 1 && in_array($type, ['boolean','rich_text','media','gallery','relation','component','repeater'], true)) {
            $errors[] = 'Unique values are only available for simple text, number, date, select, email, and URL fields.';
        }
        if (array_key_exists('default_value', $settings)) {
            $default = (string)$settings['default_value'];
            if (in_array($type, ['media','gallery','relation','component','repeater','rich_text'], true)) {
                $errors[] = 'Default values are not available for this field type.';
            } elseif ($type === 'select' && $default !== '' && !in_array($default, (array)($settings['options'] ?? []), true)) {
                $errors[] = 'Default value must match one of the allowed options.';
            } elseif ($type === 'number' && $default !== '' && !is_numeric($default)) {
                $errors[] = 'Default value must be a number.';
            } elseif ($type === 'email' && $default !== '' && filter_var($default, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'Default value must be a valid email address.';
            }
        }
        if ((int)($settings['searchable'] ?? 0) === 1 && in_array($type, ['boolean','media','gallery','relation','component','repeater'], true)) {
            $errors[] = 'Search indexing is available for text-like scalar fields only.';
        }
        return $errors;
    }

    private static function fieldHasDuplicateScalarValues(int $modelId, string $fieldKey): bool
    {
        if ($modelId<1 || $fieldKey==='') return false;
        $path='$.' . chr(34) . $fieldKey . chr(34);
        $sql="SELECT 1 FROM (
                SELECT LOWER(TRIM(JSON_UNQUOTE(JSON_EXTRACT(field_values_json,?)))) normalized_value,COUNT(*) total
                FROM content_entries
                WHERE model_id=? AND JSON_CONTAINS_PATH(field_values_json,'one',?)=1
                  AND JSON_TYPE(JSON_EXTRACT(field_values_json,?)) <> 'NULL'
                GROUP BY normalized_value
                HAVING normalized_value<>'' AND total>1
                LIMIT 1
              ) duplicate_values LIMIT 1";
        $stmt=Database::connection()->prepare($sql);
        $stmt->execute([$path,$modelId,$path,$path]);
        return (bool)$stmt->fetchColumn();
    }

    /** @param list<int> $fieldIds */
    private static function reorderSchemaRows(string $table, string $ownerColumn, int $ownerId, array $fieldIds): void
    {
        if (!in_array($table, ['content_fields','component_fields'], true) || !in_array($ownerColumn, ['model_id','component_id'], true)) {
            throw new RuntimeException('Unsupported schema reorder target.');
        }
        $fieldIds = array_values(array_unique(array_filter(array_map('intval', $fieldIds), static fn(int $id): bool => $id > 0)));
        $db = Database::connection();
        $stmt = $db->prepare("SELECT id FROM {$table} WHERE {$ownerColumn}=? AND archived_at IS NULL ORDER BY sort_order ASC,id ASC");
        $stmt->execute([$ownerId]);
        $existing = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $expected = $existing; $submitted = $fieldIds;
        sort($expected, SORT_NUMERIC); sort($submitted, SORT_NUMERIC);
        if ($expected !== $submitted) throw new RuntimeException('The field order is stale. Refresh the page and try again.');
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $update = $db->prepare("UPDATE {$table} SET sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND {$ownerColumn}=?");
            $order = 10;
            foreach ($fieldIds as $fieldId) { $update->execute([$order,$fieldId,$ownerId]); $order += 10; }
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function modelRolePermissions(int $modelId): array
    {
        if ($modelId<1) return [];
        $sql="SELECT r.id,r.name,r.label,
                     COALESCE(mp.can_view,1) can_view,COALESCE(mp.can_create,1) can_create,
                     COALESCE(mp.can_edit,1) can_edit,COALESCE(mp.can_publish,1) can_publish,
                     COALESCE(mp.can_delete,CASE WHEN r.name='editor' THEN 0 ELSE 1 END) can_delete
              FROM roles r
              JOIN role_permissions rp ON rp.role_id=r.id
              JOIN permissions p ON p.id=rp.permission_id AND p.name='custom_content.view'
              LEFT JOIN content_model_role_permissions mp ON mp.role_id=r.id AND mp.model_id=?
              WHERE r.name<>'super_administrator'
              ORDER BY CASE r.name WHEN 'administrator' THEN 0 WHEN 'editor' THEN 1 ELSE 2 END,r.label ASC";
        try { $stmt=Database::connection()->prepare($sql); $stmt->execute([$modelId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
        catch (\Throwable) { return []; }
    }

    public static function saveModelRolePermissions(int $modelId, array $submitted): void
    {
        if ($modelId<1) throw new RuntimeException('Content model not found.');
        $rows=self::modelRolePermissions($modelId);
        $db=Database::connection();
        $stmt=$db->prepare(
            'INSERT INTO content_model_role_permissions (model_id,role_id,can_view,can_create,can_edit,can_publish,can_delete,created_at,updated_at) '
            . 'VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) '
            . 'ON DUPLICATE KEY UPDATE can_view=VALUES(can_view),can_create=VALUES(can_create),can_edit=VALUES(can_edit),can_publish=VALUES(can_publish),can_delete=VALUES(can_delete),updated_at=UTC_TIMESTAMP()'
        );
        foreach ($rows as $role) {
            $roleId=(int)$role['id'];
            $values=is_array($submitted[(string)$roleId]??null)?$submitted[(string)$roleId]:[];
            $view=isset($values['view'])?1:0;
            $create=$view&&isset($values['create'])?1:0;
            $edit=$view&&isset($values['edit'])?1:0;
            $publish=$edit&&isset($values['publish'])?1:0;
            $delete=$view&&isset($values['delete'])?1:0;
            $stmt->execute([$modelId,$roleId,$view,$create,$edit,$publish,$delete]);
        }
        Gate::clearCache();
    }

    private static function seedModelPermissions(int $modelId): void
    {
        if ($modelId < 1) return;
        $db = Database::connection();
        try {
            $roles = $db->query("SELECT id,name FROM roles WHERE name IN ('administrator','editor')")->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $stmt = $db->prepare(
                'INSERT IGNORE INTO content_model_role_permissions (model_id,role_id,can_view,can_create,can_edit,can_publish,can_delete,created_at,updated_at) '
                . 'VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            foreach ($roles as $role) {
                $isEditor = (string)$role['name'] === 'editor';
                $stmt->execute([$modelId,(int)$role['id'],1,1,1,1,$isEditor ? 0 : 1]);
            }
        } catch (\Throwable) {
            // Keep model creation compatible while migrations are being applied.
        }
    }

    private static function schemaMutation(callable $operation): mixed
    {
        $db=Database::connection();
        $ownsTransaction=!$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $result=$operation($db);
            if ($ownsTransaction) $db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private static function clip(mixed $value, int $max): string
    {
        return mb_substr(trim((string)$value), 0, $max);
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function __construct() {}
}
