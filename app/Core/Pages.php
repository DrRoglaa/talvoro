<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Pages
{
    private const RESERVED = [
        '/health','/blog','/assets','/uploads','/install',
        '/robots.txt','/sitemap.xml','/theme.css','/account','/_talvoro'
    ];

    public static function adminList(string $search = '', string $status = '', bool $trashed = false): array
    {
        self::ensureHomePage();
        $sql = 'SELECT p.id,p.title,p.path,p.page_template,p.status,p.show_in_navigation,p.show_in_footer,p.updated_at,p.published_at,p.deleted_at,
                       u.display_name author_name
                FROM pages p
                JOIN users u ON u.id=p.author_id
                WHERE ' . ($trashed ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL');
        $args = [];
        if ($search !== '') {
            $sql .= ' AND (p.title LIKE ? OR p.path LIKE ?)';
            $needle = '%' . $search . '%';
            $args[] = $needle;
            $args[] = $needle;
        }
        if (in_array($status, ['draft','published'], true)) {
            $sql .= ' AND p.status=?';
            $args[] = $status;
        }
        $sql .= " ORDER BY CASE WHEN p.path='/' THEN 0 ELSE 1 END,p.updated_at DESC,p.id DESC";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM pages WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row = self::hydrateEditorData($row);
        return $row;
    }

    public static function frontPage(): ?array
    {
        self::ensureHomePage();
        return self::findPublishedByPath('/');
    }

    public static function frontPageId(): int
    {
        try {
            return (int)Database::connection()->query("SELECT id FROM pages WHERE path='/' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function ensureHomePage(?int $authorId = null): int
    {
        try {
            $db = Database::connection();
            $existing = (int)$db->query("SELECT id FROM pages WHERE path='/' AND deleted_at IS NULL LIMIT 1")->fetchColumn();
            if ($existing > 0) {
                $db->prepare("UPDATE pages SET page_template='home' WHERE id=? AND page_template<>'home'")->execute([$existing]);
                self::seedHomeBlocksIfUninitialized($existing);
                return $existing;
            }
            if ($authorId === null || $authorId < 1) {
                $authorId = (int)$db->query(
                    "SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id
                     WHERE u.status='active'
                     ORDER BY CASE r.name WHEN 'super_administrator' THEN 0 WHEN 'administrator' THEN 1 ELSE 2 END,u.id ASC LIMIT 1"
                )->fetchColumn();
            }
            if ($authorId < 1) return 0;
            $stmt = $db->prepare(
                "INSERT INTO pages
                 (author_id,title,path,page_template,excerpt,eyebrow,body,body_html,status,show_in_navigation,navigation_label,navigation_order,show_in_footer,footer_label,footer_order,published_at,created_at,updated_at)
                 VALUES (?,'Home','/','home','Website home page','HOME','Homepage layout managed in Talvoro.','<p>Homepage layout managed in Talvoro.</p>','published',0,NULL,0,0,NULL,100,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
            );
            $stmt->execute([$authorId]);
            $id = (int)$db->lastInsertId();
            self::seedHomeBlocksIfUninitialized($id);
            return $id;
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function findPublishedByPath(string $path): ?array
    {
        $path = self::normalizePath($path);
        $stmt = Database::connection()->prepare(
            "SELECT p.*,u.display_name author_name
             FROM pages p JOIN users u ON u.id=p.author_id
             WHERE p.path=? AND p.status='published' AND p.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$path]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row = self::hydrateEditorData($row);
        return $row;
    }

    public static function navigation(): array
    {
        try {
            return Database::connection()->query(
                "SELECT title,path,navigation_label
                 FROM pages
                 WHERE status='published' AND deleted_at IS NULL AND show_in_navigation=1 AND path<>'/'
                 ORDER BY navigation_order ASC,title ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function footerNavigation(): array
    {
        try {
            return Database::connection()->query(
                "SELECT title,path,footer_label
                 FROM pages
                 WHERE status='published' AND deleted_at IS NULL AND show_in_footer=1 AND path<>'/'
                 ORDER BY footer_order ASC,title ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function publishedForSeo(): array
    {
        try {
            return Database::connection()->query("SELECT title,path FROM pages WHERE status='published' AND deleted_at IS NULL AND path<>'/' ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    /** @return array{data:array,errors:list<string>} */
    public static function validate(array $input, ?int $existingId, bool $canPublish): array
    {
        $existing = $existingId !== null ? self::find($existingId) : null;
        $isHome = ($existing['path'] ?? '') === '/' || ($input['page_template'] ?? '') === 'home';
        $title = $isHome ? 'Home' : trim((string)($input['title'] ?? ''));
        $rawPath = trim((string)($input['path'] ?? $input['slug'] ?? ''));
        $pathMode = strtolower(trim((string)($input['path_mode'] ?? ($existingId === null ? 'auto' : 'manual'))));
        if (!in_array($pathMode, ['auto','manual'], true)) $pathMode = $existingId === null ? 'auto' : 'manual';
        if ($isHome) {
            $path = '/';
        } elseif ($existingId === null && ($pathMode === 'auto' || $rawPath === '')) {
            $path = self::uniquePathFromTitle($title, null);
        } elseif ($existingId !== null && $rawPath === '') {
            $path = (string)($existing['path'] ?? '/');
        } else {
            $path = self::slugifyPath($rawPath);
        }
        $pageTemplate = $isHome ? 'home' : 'standard';
        $excerpt = trim((string)($input['excerpt'] ?? ''));
        $eyebrow = trim((string)($input['eyebrow'] ?? ''));
        $bodyHtml = RichText::sanitize((string)($input['body_html'] ?? ''));
        $body = RichText::plainText($bodyHtml);
        $status = $isHome ? 'published' : trim((string)($input['status'] ?? 'draft'));
        $blockValidation = PageBlocks::validateSubmitted((string)($input['page_blocks_json'] ?? '[]'));

        $placement = (string)($input['navigation_placement'] ?? '');
        if (!in_array($placement, ['hidden','main','footer','both'], true)) {
            $showMainLegacy = isset($input['show_in_navigation']);
            $showFooterLegacy = isset($input['show_in_footer']);
            $placement = $showMainLegacy && $showFooterLegacy ? 'both' : ($showMainLegacy ? 'main' : ($showFooterLegacy ? 'footer' : 'hidden'));
        }
        $showInNavigation = !$isHome && in_array($placement, ['main','both'], true) ? 1 : 0;
        $showInFooter = !$isHome && in_array($placement, ['footer','both'], true) ? 1 : 0;
        $navigationLabel = trim((string)($input['navigation_label'] ?? ''));
        $navigationOrder = max(0, min(10000, (int)($input['navigation_order'] ?? 100)));
        $footerLabel = trim((string)($input['footer_label'] ?? ''));
        $footerOrder = max(0, min(10000, (int)($input['footer_order'] ?? 100)));

        $errors = [];
        if (mb_strlen($title) < 2 || mb_strlen($title) > 255) $errors[] = 'Page title must be between 2 and 255 characters.';
        if (!$isHome) {
            if ($path === '/' || strlen($path) > 191) $errors[] = 'Choose a public page slug/path such as about or company/contact.';
            if (!preg_match('#^/[a-z0-9][a-z0-9/_-]*$#', $path)) $errors[] = 'Page slug/path can contain lowercase letters, numbers, /, - and _.';
            foreach (self::RESERVED as $reserved) {
                if ($path === $reserved || str_starts_with($path, $reserved . '/')) {
                    $errors[] = 'That path is reserved by Talvoro.';
                    break;
                }
            }
            if (AdminPath::isProtectedPublicPath($path) || ContentModels::publicPathReserved($path)) $errors[] = 'That path is reserved by Talvoro or a structured content model.';
            if (self::pathExists($path, $existingId)) $errors[] = 'That page path is already in use.';
        }
        if (mb_strlen($excerpt) > 1000) $errors[] = 'Excerpt must be 1,000 characters or fewer.';
        if (mb_strlen($eyebrow) > 120) $errors[] = 'Eyebrow must be 120 characters or fewer.';
        if (!$isHome && ($bodyHtml === '' || $body === '') && !$blockValidation['blocks']) $errors[] = 'Add page content or at least one structured block.';
        $errors = array_merge($errors, $blockValidation['errors']);

        if (!in_array($status, ['draft','published'], true)) $status = 'draft';
        if ($status === 'published' && !$canPublish && !$isHome) {
            $errors[] = 'You do not have permission to publish pages.';
            $status = 'draft';
        }
        if (mb_strlen($navigationLabel) > 120) $errors[] = 'Navigation label must be 120 characters or fewer.';
        if (mb_strlen($footerLabel) > 120) $errors[] = 'Footer label must be 120 characters or fewer.';
        if ($showInNavigation && $navigationLabel === '') $navigationLabel = $title;
        if ($showInFooter && $footerLabel === '') $footerLabel = $title;

        return [
            'data' => [
                'title' => $title,
                'path' => $path,
                'path_mode' => $pathMode,
                'page_template' => $pageTemplate,
                'excerpt' => $excerpt,
                'eyebrow' => $eyebrow,
                'body' => $body,
                'body_html' => $bodyHtml,
                'blocks_json' => $blockValidation['json'],
                'blocks' => $blockValidation['blocks'],
                'status' => $status,
                'show_in_navigation' => $showInNavigation,
                'navigation_label' => $navigationLabel,
                'navigation_order' => $navigationOrder,
                'show_in_footer' => $showInFooter,
                'footer_label' => $footerLabel,
                'footer_order' => $footerOrder,
                'navigation_placement' => $placement,
            ],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public static function create(array $data, int $authorId): int
    {
        $publishedAt = $data['status'] === 'published' ? gmdate('Y-m-d H:i:s') : null;
        $stmt = Database::connection()->prepare(
            'INSERT INTO pages
             (author_id,title,path,page_template,excerpt,eyebrow,body,body_html,blocks_json,status,show_in_navigation,navigation_label,navigation_order,show_in_footer,footer_label,footer_order,published_at,created_at,updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $authorId,$data['title'],$data['path'],$data['page_template'],$data['excerpt'] !== '' ? $data['excerpt'] : null,
            $data['eyebrow'] !== '' ? $data['eyebrow'] : null,$data['body'],$data['body_html'],$data['blocks_json'],$data['status'],$data['show_in_navigation'],
            $data['navigation_label'] !== '' ? $data['navigation_label'] : null,$data['navigation_order'],$data['show_in_footer'],
            $data['footer_label'] !== '' ? $data['footer_label'] : null,$data['footer_order'],$publishedAt,
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function update(int $id, array $data, ?string $existingPublishedAt): void
    {
        $db = Database::connection();
        $oldStmt = $db->prepare('SELECT path FROM pages WHERE id=? AND deleted_at IS NULL LIMIT 1');
        $oldStmt->execute([$id]);
        $oldPath = (string)($oldStmt->fetchColumn() ?: '');
        $publishedAt = $data['status'] === 'published' ? ($existingPublishedAt ?: gmdate('Y-m-d H:i:s')) : null;
        $stmt = $db->prepare(
            'UPDATE pages SET title=?,path=?,page_template=?,excerpt=?,eyebrow=?,body=?,body_html=?,blocks_json=?,status=?,show_in_navigation=?,
                 navigation_label=?,navigation_order=?,show_in_footer=?,footer_label=?,footer_order=?,published_at=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $data['title'],$data['path'],$data['page_template'],$data['excerpt'] !== '' ? $data['excerpt'] : null,
            $data['eyebrow'] !== '' ? $data['eyebrow'] : null,$data['body'],$data['body_html'],$data['blocks_json'],$data['status'],$data['show_in_navigation'],
            $data['navigation_label'] !== '' ? $data['navigation_label'] : null,$data['navigation_order'],$data['show_in_footer'],
            $data['footer_label'] !== '' ? $data['footer_label'] : null,$data['footer_order'],$publishedAt,$id,
        ]);
        if ($oldPath !== '' && $oldPath !== $data['path']) {
            try {
                $db->prepare('DELETE FROM seo_pages WHERE path=?')->execute([$data['path']]);
                $db->prepare('UPDATE seo_pages SET path=? WHERE path=?')->execute([$data['path'], $oldPath]);
            } catch (\Throwable) {}
        }
    }

    /** @deprecated Use ContentLifecycle::moveToTrash() or permanentlyDelete(). */
    public static function delete(int $id): void
    {
        throw new RuntimeException('Direct page deletion is disabled. Move the page to Trash first.');
    }

    public static function counts(): array
    {
        $rows = Database::connection()->query('SELECT status,COUNT(*) total FROM pages WHERE deleted_at IS NULL GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
        return ['total' => array_sum(array_map('intval', $rows)), 'draft' => (int)($rows['draft'] ?? 0), 'published' => (int)($rows['published'] ?? 0)];
    }

    public static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '/';
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : $path;
        $path = '/' . ltrim(mb_strtolower($path), '/');
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return $path !== '/' ? rtrim($path, '/') : '/';
    }

    public static function slugifyPath(string $path): string
    {
        $path = trim($path);
        if ($path === '') return '/';
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : $path;
        $segments = array_values(array_filter(explode('/', trim($path, '/')), static fn(string $segment): bool => trim($segment) !== ''));
        $slugs = [];
        foreach ($segments as $segment) {
            $slug = self::slugifySegment($segment);
            if ($slug !== '') $slugs[] = $slug;
        }
        return $slugs ? '/' . implode('/', $slugs) : '/';
    }

    public static function uniquePathFromTitle(string $title, ?int $existingId = null): string
    {
        $base = self::slugifySegment($title);
        if ($base === '') $base = 'page';
        for ($suffix = 1; $suffix <= 9999; $suffix++) {
            $candidate = '/' . $base . ($suffix === 1 ? '' : '-' . $suffix);
            if (!self::pathUnavailable($candidate, $existingId)) return $candidate;
        }
        throw new RuntimeException('Talvoro could not create a unique URL for this page.');
    }

    private static function slugifySegment(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd',
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
        ]);
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii) && $ascii !== '') $value = $ascii;
        }
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private static function pathUnavailable(string $path, ?int $existingId): bool
    {
        foreach (self::RESERVED as $reserved) {
            if ($path === $reserved || str_starts_with($path, $reserved . '/')) return true;
        }
        if (AdminPath::isProtectedPublicPath($path) || ContentModels::publicPathReserved($path)) return true;
        return self::pathExists($path, $existingId);
    }

    private static function hydrateEditorData(array $row): array
    {
        $row['body_html'] = self::editorHtml($row);
        $blocksJson = trim((string)($row['blocks_json'] ?? ''));
        // NULL/empty-string means this Home page predates the block builder and has never
        // been initialized. An explicit JSON [] means the editor intentionally removed all
        // blocks and MUST remain empty. Never resurrect deleted blocks.
        if (($row['path'] ?? '') === '/' && $blocksJson === '') {
            self::seedHomeBlocksIfUninitialized((int)$row['id']);
            try {
                $stmt = Database::connection()->prepare('SELECT blocks_json FROM pages WHERE id=? AND deleted_at IS NULL LIMIT 1');
                $stmt->execute([(int)$row['id']]);
                $blocksJson = trim((string)($stmt->fetchColumn() ?: ''));
            } catch (\Throwable) {
                $blocksJson = '';
            }
        }
        $row['blocks_json'] = $blocksJson !== '' ? $blocksJson : '[]';
        $row['blocks'] = PageBlocks::decode($row['blocks_json']);
        return $row;
    }

    private static function seedHomeBlocksIfUninitialized(int $pageId): void
    {
        if ($pageId < 1) return;
        try {
            $db = Database::connection();
            $stmt = $db->prepare('SELECT blocks_json FROM pages WHERE id=? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$pageId]);
            $stored = $stmt->fetchColumn();
            // Important: JSON [] is an explicit user choice. Only NULL/empty string is legacy/uninitialized.
            if ($stored !== null && trim((string)$stored) !== '') return;
            $blocks = PageBlocks::legacyHome(HomePage::current());
            $encoded = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded)) return;
            $db->prepare("UPDATE pages SET blocks_json=? WHERE id=? AND deleted_at IS NULL AND (blocks_json IS NULL OR blocks_json='')")
                ->execute([$encoded, $pageId]);
        } catch (\Throwable) {
            // Safe during staged upgrades before the page-builder migration exists.
        }
    }

    private static function editorHtml(array $row): string
    {
        $html = trim((string)($row['body_html'] ?? ''));
        if ($html === '<p>Homepage layout managed in Talvoro.</p>') return '';
        return $html !== '' ? $html : RichText::fromPlain((string)($row['body'] ?? ''));
    }

    private static function pathExists(string $path, ?int $existingId): bool
    {
        if ($existingId === null) {
            $stmt = Database::connection()->prepare('SELECT 1 FROM pages WHERE path=? LIMIT 1');
            $stmt->execute([$path]);
        } else {
            $stmt = Database::connection()->prepare('SELECT 1 FROM pages WHERE path=? AND id<>? LIMIT 1');
            $stmt->execute([$path, $existingId]);
        }
        return (bool)$stmt->fetchColumn();
    }

    private function __construct() {}
}
