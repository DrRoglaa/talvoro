<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class InternalLinks
{
    /** @return list<array{type:string,title:string,url:string,status:string,meta:string}> */
    public static function search(string $query, int $limit = 18): array
    {
        $query = trim($query);
        if ($query === '') return [];
        $limit = max(3, min(30, $limit));
        $like = '%' . $query . '%';
        $results = [];
        $db = Database::connection();

        $stmt = $db->prepare("SELECT title,path,status FROM pages WHERE deleted_at IS NULL AND (title LIKE ? OR path LIKE ?) ORDER BY CASE status WHEN 'published' THEN 0 ELSE 1 END,title ASC LIMIT 8");
        $stmt->execute([$like,$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $results[] = ['type' => 'Page','title' => (string)$row['title'],'url' => (string)$row['path'],'status' => (string)$row['status'],'meta' => (string)$row['path']];
        }

        $stmt = $db->prepare("SELECT title,slug,status FROM posts WHERE deleted_at IS NULL AND (title LIKE ? OR slug LIKE ?) ORDER BY CASE status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END,title ASC LIMIT 8");
        $stmt->execute([$like,$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $results[] = ['type' => 'Post','title' => (string)$row['title'],'url' => '/blog/' . (string)$row['slug'],'status' => (string)$row['status'],'meta' => '/blog/' . (string)$row['slug']];
        }

        $stmt = $db->prepare("SELECT name,slug,status FROM blog_categories WHERE name LIKE ? OR slug LIKE ? ORDER BY CASE status WHEN 'active' THEN 0 ELSE 1 END,sort_order ASC,name ASC LIMIT 8");
        $stmt->execute([$like,$like]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $results[] = ['type' => 'Category','title' => (string)$row['name'],'url' => '/blog/category/' . (string)$row['slug'],'status' => (string)$row['status'],'meta' => '/blog/category/' . (string)$row['slug']];
        }

        if (Gate::allows('custom_content.view')) {
            try {
                $stmt = $db->prepare(
                    "SELECT e.id,e.model_id,e.title,e.slug,e.status,m.singular_name,m.slug model_slug
                     FROM content_entries e
                     JOIN content_models m ON m.id=e.model_id
                     WHERE e.deleted_at IS NULL
                       AND m.status='active' AND m.is_public=1 AND m.has_urls=1 AND m.searchable=1
                       AND (e.title LIKE ? OR e.slug LIKE ? OR EXISTS (
                           SELECT 1 FROM content_search_values sv WHERE sv.entry_id=e.id AND sv.value_text LIKE ?
                       ))
                     ORDER BY CASE e.status WHEN 'published' THEN 0 WHEN 'scheduled' THEN 1 ELSE 2 END,e.title ASC
                     LIMIT 24"
                );
                $stmt->execute([$like,$like,$like]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                    if (!Gate::allowsModel((int)$row['model_id'],'view')) continue;
                    $url = '/' . (string)$row['model_slug'] . '/' . (string)$row['slug'];
                    $results[] = ['type' => (string)$row['singular_name'],'title' => (string)$row['title'],'url' => $url,'status' => (string)$row['status'],'meta' => $url];
                }
            } catch (\Throwable) {
            }
        }

        usort($results, static function (array $a, array $b) use ($query): int {
            $qa = mb_strtolower($query);
            $at = mb_strtolower($a['title']);
            $bt = mb_strtolower($b['title']);
            $as = $at === $qa ? 0 : (str_starts_with($at, $qa) ? 1 : 2);
            $bs = $bt === $qa ? 0 : (str_starts_with($bt, $qa) ? 1 : 2);
            return $as <=> $bs ?: strcmp($a['title'], $b['title']);
        });
        return array_slice($results, 0, $limit);
    }

    private function __construct() {}
}
