<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Categories
{
    private const STATUSES = ['active', 'inactive'];

    public static function adminList(): array
    {
        $stmt = Database::connection()->query(
            'SELECT c.id,c.name,c.slug,c.description,c.seo_title,c.meta_description,c.status,c.sort_order,c.is_default,c.created_at,c.updated_at,
                    COUNT(cp.id) post_count
             FROM blog_categories c
             LEFT JOIN blog_post_categories pc ON pc.category_id=c.id
             LEFT JOIN posts cp ON cp.id=pc.post_id AND cp.deleted_at IS NULL
             GROUP BY c.id,c.name,c.slug,c.description,c.seo_title,c.meta_description,c.status,c.sort_order,c.is_default,c.created_at,c.updated_at
             ORDER BY c.is_default DESC,c.sort_order ASC,c.name ASC,c.id ASC'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT id,name,slug,description,seo_title,meta_description,status,sort_order,is_default
             FROM blog_categories
             ORDER BY is_default DESC,sort_order ASC,name ASC,id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function active(): array
    {
        return Database::connection()->query(
            "SELECT id,name,slug,description,seo_title,meta_description,status,sort_order,is_default
             FROM blog_categories
             WHERE status='active'
             ORDER BY is_default DESC,sort_order ASC,name ASC,id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM blog_categories WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findPublicBySlug(string $slug): ?array
    {
        $stmt = Database::connection()->prepare(
            "SELECT * FROM blog_categories WHERE slug=? AND status='active' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function defaultCategory(): ?array
    {
        $row = Database::connection()->query(
            'SELECT * FROM blog_categories WHERE is_default=1 ORDER BY id ASC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function forPost(int $postId, bool $publicOnly = false): array
    {
        $sql = 'SELECT c.id,c.name,c.slug,c.description,c.status,c.sort_order,c.is_default,pc.is_primary
                FROM blog_post_categories pc
                JOIN blog_categories c ON c.id=pc.category_id
                WHERE pc.post_id=?';
        if ($publicOnly) {
            $sql .= " AND c.status='active'";
        }
        $sql .= ' ORDER BY pc.is_primary DESC,c.is_default DESC,c.sort_order ASC,c.name ASC,c.id ASC';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validate(array $input, ?int $existingId = null): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $manualSlug = trim((string)($input['slug'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $seoTitle = trim((string)($input['seo_title'] ?? ''));
        $metaDescription = trim((string)($input['meta_description'] ?? ''));
        $status = trim((string)($input['status'] ?? 'active'));
        $sortOrder = max(0, min(10000, (int)($input['sort_order'] ?? 100)));
        $errors = [];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            $errors[] = 'Category name must be between 2 and 120 characters.';
        }
        if (mb_strlen($description) > 4000) {
            $errors[] = 'Category description must be 4,000 characters or fewer.';
        }
        if (mb_strlen($seoTitle) > 255) {
            $errors[] = 'SEO title must be 255 characters or fewer.';
        }
        if (mb_strlen($metaDescription) > 500) {
            $errors[] = 'Meta description must be 500 characters or fewer.';
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $slug = self::slugify($manualSlug !== '' ? $manualSlug : $name);
        if ($slug === '' || strlen($slug) > 191) {
            $errors[] = 'Choose a valid category slug.';
        } elseif (self::slugExists($slug, $existingId)) {
            $errors[] = 'That category slug is already in use.';
        }

        return [
            'data' => [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'seo_title' => $seoTitle,
                'meta_description' => $metaDescription,
                'status' => $status,
                'sort_order' => $sortOrder,
            ],
            'errors' => $errors,
        ];
    }

    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO blog_categories
             (name,slug,description,seo_title,meta_description,status,sort_order,is_default,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            self::nullable($data['description']),
            self::nullable($data['seo_title']),
            self::nullable($data['meta_description']),
            $data['status'],
            $data['sort_order'],
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        $existing = self::find($id);
        if (!$existing) {
            throw new RuntimeException('Category not found.');
        }

        $status = $existing['is_default'] ? 'active' : $data['status'];
        $stmt = Database::connection()->prepare(
            'UPDATE blog_categories
             SET name=?,slug=?,description=?,seo_title=?,meta_description=?,status=?,sort_order=?,updated_at=UTC_TIMESTAMP()
             WHERE id=?'
        );
        $stmt->execute([
            $data['name'],
            $data['slug'],
            self::nullable($data['description']),
            self::nullable($data['seo_title']),
            self::nullable($data['meta_description']),
            $status,
            $data['sort_order'],
            $id,
        ]);
    }

    public static function delete(int $id): void
    {
        $category = self::find($id);
        if (!$category) {
            throw new RuntimeException('Category not found.');
        }
        if ((int)$category['is_default'] === 1) {
            throw new RuntimeException('The default category cannot be deleted.');
        }

        $db = Database::connection();
        $affected = $db->prepare('SELECT post_id FROM blog_post_categories WHERE category_id=?');
        $affected->execute([$id]);
        $postIds = array_map('intval', $affected->fetchAll(PDO::FETCH_COLUMN));

        $default = self::defaultCategory();
        if (!$default) {
            throw new RuntimeException('A default blog category is required.');
        }
        $defaultId = (int)$default['id'];

        $db->beginTransaction();
        try {
            $stmt = $db->prepare('DELETE FROM blog_categories WHERE id=? AND is_default=0');
            $stmt->execute([$id]);

            foreach ($postIds as $postId) {
                $rows = self::forPostWithConnection($db, $postId);
                if ($rows === []) {
                    $insert = $db->prepare(
                        'INSERT INTO blog_post_categories (post_id,category_id,is_primary,created_at) VALUES (?,?,1,UTC_TIMESTAMP())'
                    );
                    $insert->execute([$postId, $defaultId]);
                    continue;
                }

                $hasPrimary = false;
                foreach ($rows as $row) {
                    if ((int)$row['is_primary'] === 1) {
                        $hasPrimary = true;
                        break;
                    }
                }
                if (!$hasPrimary) {
                    $promote = $db->prepare(
                        'UPDATE blog_post_categories SET is_primary=1 WHERE post_id=? AND category_id=?'
                    );
                    $promote->execute([$postId, (int)$rows[0]['id']]);
                }
            }

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{category_ids:list<int>,primary_category_id:int,errors:list<string>} */
    public static function validateSelection(array $input): array
    {
        $raw = $input['category_ids'] ?? [];
        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        $hasMalformed = false;
        foreach ($raw as $value) {
            $text = is_int($value) ? (string)$value : trim((string)$value);
            if ($text === '' || !ctype_digit($text) || (int)$text < 1) {
                if ($text !== '') {
                    $hasMalformed = true;
                }
                continue;
            }
            $ids[] = (int)$text;
        }
        $ids = array_values(array_unique($ids));
        $errors = $hasMalformed ? ['One or more selected categories are invalid.'] : [];

        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = Database::connection()->prepare(
                'SELECT id FROM blog_categories WHERE id IN (' . $placeholders . ') ORDER BY id'
            );
            $stmt->execute($ids);
            $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            sort($validIds);
            $compareIds = $ids;
            sort($compareIds);
            if ($validIds !== $compareIds) {
                $errors[] = 'One or more selected categories no longer exist.';
                $ids = $validIds;
            }
        }

        $default = self::defaultCategory();
        if (!$default) {
            $errors[] = 'The default blog category is missing.';
            return ['category_ids' => $ids, 'primary_category_id' => 0, 'errors' => $errors];
        }
        $defaultId = (int)$default['id'];

        if ($ids === []) {
            $ids = [$defaultId];
        }

        $primaryRaw = trim((string)($input['primary_category_id'] ?? ''));
        $primaryId = ctype_digit($primaryRaw) ? (int)$primaryRaw : 0;
        if ($primaryId > 0 && !in_array($primaryId, $ids, true)) {
            $primaryId = 0;
        }
        if ($primaryId < 1) {
            $primaryId = in_array($defaultId, $ids, true) ? $defaultId : $ids[0];
        }

        return [
            'category_ids' => $ids,
            'primary_category_id' => $primaryId,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public static function syncPostCategories(PDO $db, int $postId, array $categoryIds, int $primaryCategoryId): void
    {
        if ($categoryIds === []) {
            throw new RuntimeException('A post must have at least one category.');
        }
        if (!in_array($primaryCategoryId, $categoryIds, true)) {
            throw new RuntimeException('Primary category must be one of the selected categories.');
        }

        $delete = $db->prepare('DELETE FROM blog_post_categories WHERE post_id=?');
        $delete->execute([$postId]);
        $insert = $db->prepare(
            'INSERT INTO blog_post_categories (post_id,category_id,is_primary,created_at) VALUES (?,?,?,UTC_TIMESTAMP())'
        );
        foreach ($categoryIds as $categoryId) {
            $insert->execute([$postId, (int)$categoryId, (int)$categoryId === $primaryCategoryId ? 1 : 0]);
        }
    }

    public static function publicArchiveCategories(): array
    {
        return Database::connection()->query(
            "SELECT c.id,c.name,c.slug,c.description,c.seo_title,c.meta_description,c.sort_order,c.is_default,COUNT(DISTINCT p.id) post_count
             FROM blog_categories c
             LEFT JOIN blog_post_categories pc ON pc.category_id=c.id
             LEFT JOIN posts p ON p.id=pc.post_id
                AND p.deleted_at IS NULL
                AND p.status='published'
                AND p.published_at IS NOT NULL
                AND p.published_at <= UTC_TIMESTAMP()
             WHERE c.status='active'
             GROUP BY c.id,c.name,c.slug,c.description,c.seo_title,c.meta_description,c.sort_order,c.is_default
             ORDER BY c.is_default DESC,c.sort_order ASC,c.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function slugExists(string $slug, ?int $existingId): bool
    {
        $sql = 'SELECT COUNT(*) FROM blog_categories WHERE slug=?';
        $args = [$slug];
        if ($existingId !== null) {
            $sql .= ' AND id<>?';
            $args[] = $existingId;
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($args);
        return (int)$stmt->fetchColumn() > 0;
    }

    private static function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value));
        if (class_exists(\Transliterator::class)) {
            $trans = \Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
            if ($trans) {
                $value = (string)$trans->transliterate($value);
            }
        }
        $value = preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '';
        return trim($value, '-');
    }

    private static function nullable(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private static function forPostWithConnection(PDO $db, int $postId): array
    {
        $stmt = $db->prepare(
            'SELECT c.id,c.is_default,c.sort_order,c.name,pc.is_primary
             FROM blog_post_categories pc
             JOIN blog_categories c ON c.id=pc.category_id
             WHERE pc.post_id=?
             ORDER BY pc.is_primary DESC,c.is_default DESC,c.sort_order ASC,c.name ASC,c.id ASC'
        );
        $stmt->execute([$postId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function __construct()
    {
    }
}
