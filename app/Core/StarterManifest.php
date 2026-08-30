<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class StarterManifest
{
    public const SCHEMA_VERSION = 1;
    public const MAX_BYTES = 524288;
    public const MAX_RESOURCES = 500;
    public const MAX_REFERENCES = 2000;
    public const MAX_KEY_LENGTH = 160;
    public const MAX_STRING_BYTES = 262144;

    /** @var array<string,list<string>> */
    private const DATA_FIELDS = [
        'media' => ['source','title','alt','caption','focal_x','focal_y'],
        'content_component' => ['name','slug','description','is_active'],
        'component_field' => ['component','field_key','label','field_type','help_text','placeholder','is_required','sort_order','settings'],
        'content_model' => ['singular_name','plural_name','model_key','slug','description','icon','is_active','is_public','has_archive','has_urls','enable_revisions','enable_trash','enable_seo','enable_featured_image','enable_scheduling'],
        'content_field' => ['model','field_key','label','field_type','help_text','placeholder','is_required','sort_order','settings'],
        'content_entry' => ['model','title','slug','excerpt','status','is_featured','featured_image','published_at','values','seo'],
        'blog_category' => ['name','slug','description'],
        'post' => ['title','slug','excerpt','body_html','status','featured_image','categories','published_at'],
        'page' => ['title','path','excerpt','eyebrow','body_html','status','navigation_placement','navigation_label','navigation_order','footer_label','footer_order','blocks','replace_existing'],
        'menu' => ['name','menu_key','location'],
        'menu_item' => ['menu','label','target','url','parent','sort_order','new_tab'],
        'seo' => ['path','title','description','robots','canonical_url','social_image'],
        'setting' => ['key','value'],
        'theme_design' => ['values'],
    ];

    private const STARTER_MEDIA_EXTENSIONS = ['jpg','jpeg','png','webp'];

    /**
     * @param array<string,array{sha256?:string,extension?:string,size?:int}> $availableAssets
     * @return array<string,mixed>
     */
    public static function decodeAndValidate(string $json, array $availableAssets): array
    {
        if ($json === '' || strlen($json) > self::MAX_BYTES) {
            throw new RuntimeException('starter/starter.json must be 512 KiB or smaller.');
        }

        try {
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('starter/starter.json contains invalid JSON.');
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Starter manifest must be a JSON object.');
        }

        self::rejectUnknownKeys($decoded, ['schema_version','starter_version','name','description','resources'], 'Unsupported starter field');

        if (($decoded['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new RuntimeException('Unsupported starter schema_version. Talvoro 0.17.0 supports schema_version 1.');
        }

        $starterVersion = trim((string)($decoded['starter_version'] ?? ''));
        if ($starterVersion === '' || strlen($starterVersion) > 40 || preg_match('/^[0-9]+(?:\.[0-9]+){1,2}(?:[-+][A-Za-z0-9.-]+)?$/', $starterVersion) !== 1) {
            throw new RuntimeException('starter_version must be a safe semantic-style version string.');
        }

        $name = trim((string)($decoded['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('Starter name must be between 2 and 120 characters.');
        }
        $description = trim((string)($decoded['description'] ?? ''));
        if (mb_strlen($description) > 1000) {
            throw new RuntimeException('Starter description must be 1,000 characters or fewer.');
        }

        $resources = $decoded['resources'] ?? null;
        if (!is_array($resources) || !array_is_list($resources)) {
            throw new RuntimeException('Starter resources must be a JSON array.');
        }
        if (count($resources) < 1 || count($resources) > self::MAX_RESOURCES) {
            throw new RuntimeException('Starter manifest must define between 1 and 500 resources.');
        }

        $normalized = [];
        $byKey = [];
        foreach ($resources as $index => $resource) {
            if (!is_array($resource) || array_is_list($resource)) {
                throw new RuntimeException('Starter resource #' . ($index + 1) . ' must be an object.');
            }
            self::rejectUnknownKeys($resource, ['key','type','data'], 'Unsupported resource field');

            $key = trim((string)($resource['key'] ?? ''));
            if (!self::validResourceKey($key)) {
                throw new RuntimeException('Starter resource key is invalid: ' . ($key !== '' ? $key : '(empty)') . '.');
            }
            if (isset($byKey[$key])) {
                throw new RuntimeException('Duplicate starter resource key: ' . $key . '.');
            }

            $type = trim((string)($resource['type'] ?? ''));
            if (!isset(self::DATA_FIELDS[$type])) {
                throw new RuntimeException('Unsupported starter resource type: ' . ($type !== '' ? $type : '(empty)') . '.');
            }
            $data = $resource['data'] ?? null;
            if (!is_array($data) || array_is_list($data)) {
                throw new RuntimeException('Starter resource data must be an object for ' . $key . '.');
            }
            self::rejectUnknownKeys($data, self::DATA_FIELDS[$type], 'Unsupported ' . $type . ' field');
            self::validateStrings($data, $key);
            self::validateRequiredData($type, $data, $key, $availableAssets);
            if ($type === 'media') {
                $source=(string)$data['source'];
                $sha=strtolower((string)($availableAssets[$source]['sha256'] ?? ''));
                if (preg_match('/^[a-f0-9]{64}$/D',$sha)!==1) throw new RuntimeException('Starter media package checksum is unavailable: '.$source.'.');
                $data['_asset_sha256']=$sha;
            }

            $normalizedResource = ['key'=>$key, 'type'=>$type, 'data'=>$data];
            $normalized[] = $normalizedResource;
            $byKey[$key] = $normalizedResource;
        }

        $dependencies = [];
        $referenceCount = 0;
        foreach ($normalized as $resource) {
            $key = (string)$resource['key'];
            $refs = [];
            self::collectRefs($resource['data'], ['data'], $refs);
            foreach ($refs as $ref) {
                $referenceCount++;
                if ($referenceCount > self::MAX_REFERENCES) {
                    throw new RuntimeException('Starter manifest may contain no more than 2,000 logical references.');
                }
                $targetKey = $ref['key'];
                if (!isset($byKey[$targetKey])) {
                    throw new RuntimeException('Starter resource ' . $key . ' references missing resource ' . $targetKey . '.');
                }
                self::validateReferenceTarget((string)$resource['type'], $ref['path'], (string)$byKey[$targetKey]['type'], $key, $targetKey);
                if ($targetKey === $key) {
                    throw new RuntimeException('Starter reference cycle detected at ' . $key . '.');
                }
                $dependencies[$key][$targetKey] = true;
            }
            $dependencies[$key] ??= [];
        }

        $orderedKeys = self::topologicalOrder(array_keys($byKey), $dependencies);
        $ordered = [];
        foreach ($orderedKeys as $key) {
            $item = $byKey[$key];
            $item['definition_sha256'] = self::hashCanonical(['type'=>$item['type'],'data'=>$item['data']]);
            $ordered[] = $item;
        }

        $canonicalResources = array_values($byKey);
        usort($canonicalResources, static fn(array $a, array $b): int => strcmp((string)$a['key'], (string)$b['key']));
        $canonical = [
            'schema_version' => self::SCHEMA_VERSION,
            'starter_version' => $starterVersion,
            'name' => $name,
            'description' => $description,
            'resources' => $canonicalResources,
        ];

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'starter_version' => $starterVersion,
            'name' => $name,
            'description' => $description,
            'resources' => $ordered,
            'manifest_sha256' => self::hashCanonical($canonical),
            'canonical_json' => self::encodeCanonical($canonical),
            'reference_count' => $referenceCount,
        ];
    }

    /** @param list<array<string,mixed>> $resources @return list<array<string,mixed>> */
    public static function orderStoredResources(array $resources): array
    {
        $byKey = [];
        $dependencies = [];
        foreach ($resources as $resource) {
            if (!is_array($resource)) throw new RuntimeException('Stored starter definition contains an invalid resource.');
            $key = trim((string)($resource['key'] ?? ''));
            if (!self::validResourceKey($key) || isset($byKey[$key])) throw new RuntimeException('Stored starter definition contains an invalid or duplicate resource key.');
            $byKey[$key] = $resource;
        }
        foreach ($byKey as $key => $resource) {
            $refs = [];
            self::collectRefs($resource['data'] ?? [], ['data'], $refs);
            foreach ($refs as $ref) {
                $target = (string)$ref['key'];
                if (!isset($byKey[$target])) throw new RuntimeException('Stored starter definition references a missing resource.');
                $dependencies[$key][$target] = true;
            }
            $dependencies[$key] ??= [];
        }
        $ordered = [];
        foreach (self::topologicalOrder(array_keys($byKey), $dependencies) as $key) $ordered[] = $byKey[$key];
        return $ordered;
    }

    /** @param array<string,mixed> $data */
    public static function hashResourceDefinition(string $type, array $data): string
    {
        if (!isset(self::DATA_FIELDS[$type])) {
            throw new RuntimeException('Unsupported starter resource type: ' . $type . '.');
        }
        return self::hashCanonical(['type'=>$type,'data'=>$data]);
    }

    /** @param array<string,mixed> $data */
    private static function validateRequiredData(string $type, array $data, string $key, array $availableAssets): void
    {
        $requireString = static function (string $field) use ($data, $key): string {
            $value = trim((string)($data[$field] ?? ''));
            if ($value === '') throw new RuntimeException('Starter resource ' . $key . ' requires ' . $field . '.');
            return $value;
        };

        switch ($type) {
            case 'media':
                $source = $requireString('source');
                if (!self::safeAssetPath($source)) {
                    throw new RuntimeException('Starter media asset path is unsafe: ' . $source . '.');
                }
                $extension = strtolower((string)pathinfo($source, PATHINFO_EXTENSION));
                if (!in_array($extension, self::STARTER_MEDIA_EXTENSIONS, true)) {
                    throw new RuntimeException('Starter Media supports JPEG, PNG or WebP images only.');
                }
                if (!isset($availableAssets[$source])) {
                    throw new RuntimeException('Starter media references missing packaged asset: ' . $source . '.');
                }
                $declaredExt = strtolower((string)($availableAssets[$source]['extension'] ?? $extension));
                if ($declaredExt !== $extension) {
                    throw new RuntimeException('Starter media asset metadata does not match its extension: ' . $source . '.');
                }
                foreach (['focal_x','focal_y'] as $focal) {
                    if (array_key_exists($focal, $data) && (!is_numeric($data[$focal]) || (float)$data[$focal] < 0 || (float)$data[$focal] > 100)) {
                        throw new RuntimeException('Starter media ' . $focal . ' must be between 0 and 100.');
                    }
                }
                break;
            case 'content_component':
                $requireString('name');
                $requireString('slug');
                break;
            case 'component_field':
            case 'content_field':
                $fieldKey = $requireString('field_key');
                if (ContentModels::keyIsReserved($fieldKey)) {
                    throw new RuntimeException('Starter Structured Content field key is reserved by Talvoro: ' . $fieldKey . '.');
                }
                $requireString('label');
                $requireString('field_type');
                break;
            case 'content_model':
                $requireString('singular_name');
                $requireString('plural_name');
                $modelKey = $requireString('model_key');
                if (ContentModels::keyIsReserved($modelKey)) {
                    throw new RuntimeException('Starter Structured Content model key is reserved by Talvoro: ' . $modelKey . '.');
                }
                $requireString('slug');
                break;
            case 'content_entry':
                $requireString('title');
                $requireString('slug');
                break;
            case 'blog_category':
                $requireString('name');
                $requireString('slug');
                break;
            case 'post':
                $requireString('title');
                $requireString('slug');
                break;
            case 'page':
                $requireString('title');
                $path = $requireString('path');
                if (!str_starts_with($path, '/') || strlen($path) > 191) throw new RuntimeException('Starter page path is invalid for ' . $key . '.');
                if (array_key_exists('replace_existing', $data) && !is_bool($data['replace_existing'])) {
                    throw new RuntimeException('Starter page replace_existing must be boolean for ' . $key . '.');
                }
                break;
            case 'menu':
                $requireString('name');
                $requireString('menu_key');
                break;
            case 'menu_item':
                $requireString('label');
                break;
            case 'seo':
                $requireString('path');
                break;
            case 'setting':
                $requireString('key');
                if (!array_key_exists('value', $data)) throw new RuntimeException('Starter setting ' . $key . ' requires value.');
                break;
            case 'theme_design':
                if (!isset($data['values']) || !is_array($data['values']) || array_is_list($data['values'])) {
                    throw new RuntimeException('Starter theme_design ' . $key . ' requires a values object.');
                }
                break;
        }
    }

    /** @param mixed $value @param list<string> $path @param list<array{key:string,path:list<string>}> $refs */
    private static function collectRefs(mixed $value, array $path, array &$refs): void
    {
        if (!is_array($value)) return;
        if (!array_is_list($value) && array_key_exists('$ref', $value)) {
            if (count($value) !== 1 || !is_string($value['$ref']) || !self::validResourceKey($value['$ref'])) {
                throw new RuntimeException('Starter contains an invalid logical reference object.');
            }
            $refs[] = ['key'=>$value['$ref'], 'path'=>$path];
            return;
        }
        foreach ($value as $key => $child) {
            $next = $path;
            $next[] = is_int($key) ? '*' : (string)$key;
            self::collectRefs($child, $next, $refs);
        }
    }

    /** @param list<string> $path */
    private static function validateReferenceTarget(string $sourceType, array $path, string $targetType, string $sourceKey, string $targetKey): void
    {
        $pathString = implode('.', $path);
        $allowed = null;

        if ($pathString === 'data.model') $allowed = ['content_model'];
        elseif ($pathString === 'data.component') $allowed = ['content_component'];
        elseif ($pathString === 'data.featured_image' || $pathString === 'data.social_image') $allowed = ['media'];
        elseif ($pathString === 'data.menu') $allowed = ['menu'];
        elseif ($pathString === 'data.parent') $allowed = ['menu_item'];
        elseif ($pathString === 'data.target') $allowed = ['page','content_entry','post'];
        elseif (str_starts_with($pathString, 'data.categories.')) $allowed = ['blog_category'];
        elseif (str_starts_with($pathString, 'data.settings.')) $allowed = ['content_model','content_component'];
        elseif (str_starts_with($pathString, 'data.values.')) $allowed = ['media','content_entry'];
        elseif (str_starts_with($pathString, 'data.blocks.')) $allowed = ['media','content_model','page','content_entry','post'];
        elseif (str_starts_with($pathString, 'data.seo.')) $allowed = ['media'];

        if ($allowed === null || !in_array($targetType, $allowed, true)) {
            throw new RuntimeException('Invalid starter reference from ' . $sourceKey . ' (' . $sourceType . ') at ' . $pathString . ' to ' . $targetKey . ' (' . $targetType . ').');
        }
    }

    /** @param list<string> $keys @param array<string,array<string,bool>> $dependencies @return list<string> */
    private static function topologicalOrder(array $keys, array $dependencies): array
    {
        $visiting = [];
        $visited = [];
        $ordered = [];
        $visit = function (string $key) use (&$visit, &$visiting, &$visited, &$ordered, $dependencies): void {
            if (isset($visited[$key])) return;
            if (isset($visiting[$key])) throw new RuntimeException('Starter reference cycle detected involving ' . $key . '.');
            $visiting[$key] = true;
            $deps = array_keys($dependencies[$key] ?? []);
            sort($deps, SORT_STRING);
            foreach ($deps as $dependency) $visit($dependency);
            unset($visiting[$key]);
            $visited[$key] = true;
            $ordered[] = $key;
        };
        sort($keys, SORT_STRING);
        foreach ($keys as $key) $visit($key);
        return $ordered;
    }

    /** @param array<string,mixed> $array @param list<string> $allowed */
    private static function rejectUnknownKeys(array $array, array $allowed, string $prefix): void
    {
        $allowedMap = array_fill_keys($allowed, true);
        foreach (array_keys($array) as $key) {
            if (!is_string($key) || !isset($allowedMap[$key])) {
                throw new RuntimeException($prefix . ': ' . (string)$key . '.');
            }
        }
    }

    private static function validResourceKey(string $key): bool
    {
        return $key !== ''
            && strlen($key) <= self::MAX_KEY_LENGTH
            && preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z0-9][a-z0-9_-]*)+$/', $key) === 1;
    }

    private static function safeAssetPath(string $path): bool
    {
        if (!str_starts_with($path, 'assets/') || str_contains($path, "\0") || str_contains($path, '\\') || str_ends_with($path, '/')) return false;
        foreach (explode('/', $path) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('/^[A-Za-z0-9._-]+$/', $part) !== 1) return false;
        }
        return true;
    }

    private static function validateStrings(mixed $value, string $resourceKey): void
    {
        if (is_string($value)) {
            if (strlen($value) > self::MAX_STRING_BYTES) throw new RuntimeException('Starter resource ' . $resourceKey . ' contains a string that is too large.');
            if (str_contains($value, "\0")) throw new RuntimeException('Starter resource ' . $resourceKey . ' contains an invalid null byte.');
            return;
        }
        if (is_array($value)) foreach ($value as $child) self::validateStrings($child, $resourceKey);
    }

    private static function hashCanonical(mixed $value): string
    {
        return hash('sha256', self::encodeCanonical($value));
    }

    private static function encodeCanonical(mixed $value): string
    {
        $normalized = self::canonicalize($value);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        return $json;
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) return $value;
        if (array_is_list($value)) return array_map([self::class, 'canonicalize'], $value);
        ksort($value, SORT_STRING);
        foreach ($value as $key => $child) $value[$key] = self::canonicalize($child);
        return $value;
    }

    private function __construct() {}
}
