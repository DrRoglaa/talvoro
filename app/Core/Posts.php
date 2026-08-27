<?php
declare(strict_types=1);

namespace CMS\Core;

use DateTimeImmutable;
use DateTimeZone;
use PDO;

final class Posts
{
    private const STATUSES = ['draft', 'scheduled', 'published'];

    public static function publishDue(): void
    {
        $db = Database::connection();
        $due = $db->query(
            "SELECT id,title,slug FROM posts WHERE deleted_at IS NULL AND status='scheduled' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP() ORDER BY id"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (!$due) {
            return;
        }

        $stmt = $db->prepare("UPDATE posts SET status='published', updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL AND status='scheduled' AND published_at <= UTC_TIMESTAMP()");

        foreach ($due as $post) {
            $postId = (int)$post['id'];
            try {
                $db->beginTransaction();
                $stmt->execute([$postId]);
                if ($stmt->rowCount() === 1) {
                    // Publication and its revision belong to one transaction. If
                    // revision history is unavailable, leave the post scheduled
                    // rather than publishing content without a recoverable state.
                    ContentHistory::capture('post', $postId, null, 'scheduled_publish');
                }
                $published = $stmt->rowCount() === 1;
                $db->commit();
                if ($published) {
                    Audit::log('post.publish', 'post', $postId, [
                        'mode' => 'scheduled',
                        'title' => $post['title'],
                        'slug' => $post['slug'],
                    ]);
                }
            } catch (\Throwable) {
                if ($db->inTransaction()) $db->rollBack();
                // Scheduled publication is best-effort from public requests. A
                // history/database problem must not take the public site down.
            }
        }
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function adminList(string $search = '', string $status = '', int $page = 1, int $perPage = 10, bool $trashed = false): array
    {
        self::publishDue();

        $db = Database::connection();
        $where = [$trashed ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL'];
        $params = [];
        $search = trim($search);

        if ($search !== '') {
            $where[] = '(p.title LIKE ? OR p.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }

        if (in_array($status, self::STATUSES, true)) {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $count = $db->prepare('SELECT COUNT(*) FROM posts p' . $whereSql);
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $perPage = max(5, min(50, $perPage));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $sql = 'SELECT p.id,p.title,p.slug,p.excerpt,p.status,p.published_at,p.created_at,p.updated_at,p.deleted_at,u.display_name author_name '
            . 'FROM posts p JOIN users u ON u.id=p.author_id'
            . $whereSql
            . ' ORDER BY p.updated_at DESC,p.id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::attachCategories($items, false);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function publicList(int $page = 1, int $perPage = 8): array
    {
        self::publishDue();

        $db = Database::connection();
        $total = (int)$db->query(
            "SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status='published' AND published_at IS NOT NULL AND published_at <= UTC_TIMESTAMP()"
        )->fetchColumn();

        $perPage = max(1, min(24, $perPage));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $db->query(
            "SELECT p.id,p.title,p.slug,p.excerpt,p.featured_image_path,p.body,p.published_at,u.display_name author_name "
            . "FROM posts p JOIN users u ON u.id=p.author_id "
            . "WHERE p.deleted_at IS NULL AND p.status='published' AND p.published_at IS NOT NULL AND p.published_at <= UTC_TIMESTAMP() "
            . "ORDER BY p.published_at DESC,p.id DESC LIMIT {$perPage} OFFSET {$offset}"
        );
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::attachCategories($items, true);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    /** @return array{items:array,total:int,page:int,pages:int,per_page:int} */
    public static function publicListByCategory(int $categoryId, int $page = 1, int $perPage = 8): array
    {
        self::publishDue();
        $db = Database::connection();
        $count = $db->prepare(
            "SELECT COUNT(DISTINCT p.id)
             FROM posts p
             JOIN blog_post_categories pc ON pc.post_id=p.id
             JOIN blog_categories c ON c.id=pc.category_id
             WHERE pc.category_id=?
               AND c.status='active'
               AND p.deleted_at IS NULL
               AND p.status='published'
               AND p.published_at IS NOT NULL
               AND p.published_at <= UTC_TIMESTAMP()"
        );
        $count->execute([$categoryId]);
        $total = (int)$count->fetchColumn();

        $perPage = max(1, min(24, $perPage));
        $pages = max(1, (int)ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;

        $stmt = $db->prepare(
            "SELECT DISTINCT p.id,p.title,p.slug,p.excerpt,p.featured_image_path,p.body,p.published_at,u.display_name author_name
             FROM posts p
             JOIN users u ON u.id=p.author_id
             JOIN blog_post_categories pc ON pc.post_id=p.id
             JOIN blog_categories c ON c.id=pc.category_id
             WHERE pc.category_id=?
               AND c.status='active'
               AND p.deleted_at IS NULL
               AND p.status='published'
               AND p.published_at IS NOT NULL
               AND p.published_at <= UTC_TIMESTAMP()
             ORDER BY p.published_at DESC,p.id DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$categoryId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        self::attachCategories($items, true);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public static function find(int $id): ?array
    {
        self::publishDue();

        $stmt = Database::connection()->prepare(
            'SELECT p.*,u.display_name author_name FROM posts p JOIN users u ON u.id=p.author_id WHERE p.id=? AND p.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) {
            return null;
        }
        self::attachCategoryData($post, false);
        return $post;
    }

    public static function findPublishedBySlug(string $slug): ?array
    {
        self::publishDue();

        $stmt = Database::connection()->prepare(
            "SELECT p.*,u.display_name author_name FROM posts p JOIN users u ON u.id=p.author_id "
            . "WHERE p.slug=? AND p.deleted_at IS NULL AND p.status='published' AND p.published_at IS NOT NULL AND p.published_at <= UTC_TIMESTAMP() LIMIT 1"
        );
        $stmt->execute([$slug]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$post) {
            return null;
        }
        self::attachCategoryData($post, true);
        return $post;
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validate(array $input, ?int $existingId, bool $canPublish): array
    {
        $title = trim((string)($input['title'] ?? ''));
        $manualSlug = trim((string)($input['slug'] ?? ''));
        $excerpt = trim((string)($input['excerpt'] ?? ''));
        $bodyHtml = RichText::sanitize((string)($input['body_html'] ?? ''));
        if ($bodyHtml === '') {
            $legacyBody = trim((string)($input['body'] ?? ''));
            $bodyHtml = $legacyBody !== '' ? RichText::fromPlain($legacyBody) : '';
        }
        $body = RichText::plainText($bodyHtml);
        $status = (string)($input['status'] ?? 'draft');
        $publishLocal = trim((string)($input['published_at_local'] ?? ''));
        $errors = [];

        if (mb_strlen($title) < 2 || mb_strlen($title) > 255) {
            $errors[] = 'Title must be between 2 and 255 characters.';
        }
        if (mb_strlen($excerpt) > 1000) {
            $errors[] = 'Excerpt must be 1,000 characters or fewer.';
        }
        if ($body === '') {
            $errors[] = 'Post body cannot be empty.';
        }

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'draft';
        }
        if (!$canPublish && $status !== 'draft') {
            $errors[] = 'You do not have permission to schedule or publish content.';
            $status = 'draft';
        }

        $publishedAt = null;
        if ($status === 'scheduled') {
            if ($publishLocal === '') {
                $errors[] = 'Scheduled posts require a publish date and time.';
            } else {
                $publishedAt = self::localInputToUtc($publishLocal, $errors);
                if ($publishedAt !== null && strtotime($publishedAt . ' UTC') <= time()) {
                    $errors[] = 'Scheduled publish time must be in the future.';
                }
            }
        } elseif ($status === 'published') {
            if ($publishLocal !== '') {
                $publishedAt = self::localInputToUtc($publishLocal, $errors);
                if ($publishedAt !== null && strtotime($publishedAt . ' UTC') > time()) {
                    $errors[] = 'Use Scheduled for a future publish date.';
                }
            } else {
                $publishedAt = gmdate('Y-m-d H:i:s');
            }
        }

        $slugSource = $manualSlug !== '' ? $manualSlug : $title;
        $slug = self::slugify($slugSource);
        if ($slug === '') {
            $errors[] = 'A URL slug could not be generated from this title.';
        } else {
            $slug = self::uniqueSlug($slug, $existingId);
        }

        $categorySelection = Categories::validateSelection($input);
        $errors = array_merge($errors, $categorySelection['errors']);

        return [
            'data' => [
                'title' => $title,
                'slug' => $slug,
                'excerpt' => $excerpt,
                'featured_image_path' => trim((string)($input['featured_image_path'] ?? '')),
                'body' => $body,
                'body_html' => $bodyHtml,
                'status' => $status,
                'published_at' => $publishedAt,
                'published_at_local' => $publishLocal,
                'category_ids' => $categorySelection['category_ids'],
                'primary_category_id' => $categorySelection['primary_category_id'],
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public static function create(array $data, int $authorId): int
    {
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'INSERT INTO posts (author_id,title,slug,excerpt,featured_image_path,body,body_html,status,published_at,created_at,updated_at) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $stmt->execute([
                $authorId,
                $data['title'],
                $data['slug'],
                $data['excerpt'] !== '' ? $data['excerpt'] : null,
                $data['featured_image_path'] !== '' ? $data['featured_image_path'] : null,
                $data['body'],
                $data['body_html'],
                $data['status'],
                $data['published_at'],
            ]);
            $id = (int)$db->lastInsertId();
            Categories::syncPostCategories($db, $id, $data['category_ids'], (int)$data['primary_category_id']);
            if ($ownsTransaction) $db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $id, array $data): void
    {
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                'UPDATE posts SET title=?,slug=?,excerpt=?,featured_image_path=?,body=?,body_html=?,status=?,published_at=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL'
            );
            $stmt->execute([
                $data['title'],
                $data['slug'],
                $data['excerpt'] !== '' ? $data['excerpt'] : null,
                $data['featured_image_path'] !== '' ? $data['featured_image_path'] : null,
                $data['body'],
                $data['body_html'],
                $data['status'],
                $data['published_at'],
                $id,
            ]);
            Categories::syncPostCategories($db, $id, $data['category_ids'], (int)$data['primary_category_id']);
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** @deprecated Use ContentLifecycle::moveToTrash() or permanentlyDelete(). */
    public static function delete(int $id): void
    {
        throw new \RuntimeException('Direct post deletion is disabled. Move the post to Trash first.');
    }

    public static function editorHtml(array $post): string
    {
        $html = trim((string)($post['body_html'] ?? ''));
        return $html !== '' ? $html : RichText::fromPlain((string)($post['body'] ?? ''));
    }

    public static function publicHtml(array $post): string
    {
        return self::editorHtml($post);
    }

    /** @return array{total:int,draft:int,scheduled:int,published:int} */
    public static function counts(): array
    {
        self::publishDue();
        $rows = Database::connection()->query(
            'SELECT status,COUNT(*) total FROM posts WHERE deleted_at IS NULL GROUP BY status'
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'total' => array_sum(array_map('intval', $rows)),
            'draft' => (int)($rows['draft'] ?? 0),
            'scheduled' => (int)($rows['scheduled'] ?? 0),
            'published' => (int)($rows['published'] ?? 0),
        ];
    }

    /** @return array<int,array> */
    public static function recent(int $limit = 5): array
    {
        self::publishDue();
        $limit = max(1, min(10, $limit));
        return Database::connection()->query(
            'SELECT id,title,slug,status,updated_at,published_at FROM posts WHERE deleted_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT ' . $limit
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function localDateTime(?string $utc): string
    {
        if ($utc === null || $utc === '') {
            return '';
        }
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(self::appTimezone())->format('Y-m-d\TH:i');
    }

    public static function displayDate(?string $utc, string $format = 'j M Y, H:i'): string
    {
        if ($utc === null || $utc === '') {
            return '—';
        }
        $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
        return $date->setTimezone(self::appTimezone())->format($format);
    }

    private static function attachCategories(array &$items, bool $publicOnly): void
    {
        foreach ($items as &$item) {
            self::attachCategoryData($item, $publicOnly);
        }
        unset($item);
    }

    private static function attachCategoryData(array &$post, bool $publicOnly): void
    {
        $categories = Categories::forPost((int)$post['id'], $publicOnly);
        $post['categories'] = $categories;
        $post['category_ids'] = array_map(static fn(array $row): int => (int)$row['id'], $categories);
        $post['primary_category_id'] = 0;
        $post['primary_category'] = null;
        foreach ($categories as $category) {
            if ((int)$category['is_primary'] === 1) {
                $post['primary_category_id'] = (int)$category['id'];
                $post['primary_category'] = $category;
                break;
            }
        }
        if ($post['primary_category'] === null && $categories !== []) {
            $post['primary_category_id'] = (int)$categories[0]['id'];
            $post['primary_category'] = $categories[0];
        }
    }

    private static function localInputToUtc(string $value, array &$errors): ?string
    {
        $timezone = self::appTimezone();
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, $timezone);
        $lastErrors = DateTimeImmutable::getLastErrors();
        $hasErrors = is_array($lastErrors)
            && (($lastErrors['warning_count'] ?? 0) > 0 || ($lastErrors['error_count'] ?? 0) > 0);

        if (!$date || $hasErrors || $date->format('Y-m-d\TH:i') !== $value) {
            $errors[] = 'Publish date/time is invalid in ' . $timezone->getName() . '.';
            return null;
        }
        return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private static function appTimezone(): DateTimeZone
    {
        try {
            return new DateTimeZone((string)Env::get('APP_TIMEZONE', 'Europe/Ljubljana'));
        } catch (\Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    private static function slugify(string $value): string
    {
        $value = trim($value);
        if (function_exists('transliterator_transliterate')) {
            $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
            if (is_string($transliterated)) {
                $value = $transliterated;
            }
        }
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? '';
        $value = trim($value, '-');
        return substr($value, 0, 180);
    }

    private static function uniqueSlug(string $base, ?int $existingId): string
    {
        $db = Database::connection();
        $candidate = $base;
        $suffix = 2;
        while (true) {
            if ($existingId === null) {
                $stmt = $db->prepare('SELECT 1 FROM posts WHERE slug=? LIMIT 1');
                $stmt->execute([$candidate]);
            } else {
                $stmt = $db->prepare('SELECT 1 FROM posts WHERE slug=? AND id<>? LIMIT 1');
                $stmt->execute([$candidate, $existingId]);
            }
            if (!$stmt->fetchColumn()) {
                return $candidate;
            }
            $suffixText = '-' . $suffix++;
            $candidate = substr($base, 0, 180 - strlen($suffixText)) . $suffixText;
        }
    }

    private function __construct()
    {
    }
}
