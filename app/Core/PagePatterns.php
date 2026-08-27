<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class PagePatterns
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $rows = Database::connection()->query(
            "SELECT p.id,p.name,p.mode,p.blocks_json,p.created_at,p.updated_at,
                    cu.display_name created_by_name,uu.display_name updated_by_name
             FROM page_patterns p
             LEFT JOIN users cu ON cu.id=p.created_by
             LEFT JOIN users uu ON uu.id=p.updated_by
             ORDER BY p.name ASC,p.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $usage = self::usageCounts();
        foreach ($rows as &$row) {
            $row['blocks'] = PageBlocks::decode((string)$row['blocks_json']);
            $row['preview_types'] = self::previewTypes($row['blocks']);
            $row['usage_count'] = (int)($usage[(int)$row['id']] ?? 0);
        }
        unset($row);
        return $rows;
    }

    /** @return list<array{id:int,name:string,mode:string,blocks:list<array<string,mixed>>,usage_count:int}> */
    public static function picker(): array
    {
        $items = [];
        foreach (self::all() as $row) {
            $items[] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'mode' => (string)$row['mode'],
                'blocks' => $row['blocks'],
                'preview_types' => $row['preview_types'] ?? self::previewTypes($row['blocks']),
                'usage_count' => (int)$row['usage_count'],
            ];
        }
        return $items;
    }

    public static function find(int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = Database::connection()->prepare(
            "SELECT p.id,p.name,p.mode,p.blocks_json,p.created_by,p.updated_by,p.created_at,p.updated_at,
                    cu.display_name created_by_name,uu.display_name updated_by_name
             FROM page_patterns p
             LEFT JOIN users cu ON cu.id=p.created_by
             LEFT JOIN users uu ON uu.id=p.updated_by
             WHERE p.id=? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['blocks'] = PageBlocks::decode((string)$row['blocks_json']);
        $row['usage_count'] = self::usageCount($id);
        return $row;
    }

    /** @return array{data:array{name:string,mode:string,blocks:list<array<string,mixed>>,blocks_json:string},errors:list<string>} */
    public static function validate(array $input): array
    {
        $name = trim((string)($input['name'] ?? ''));
        $mode = strtolower(trim((string)($input['mode'] ?? 'regular')));
        if (!in_array($mode, ['regular','synced'], true)) $mode = 'regular';
        $validated = PageBlocks::validateSubmitted((string)($input['page_blocks_json'] ?? '[]'), false);
        $errors = $validated['errors'];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160) $errors[] = 'Pattern name must be between 2 and 160 characters.';
        if (!$validated['blocks']) $errors[] = 'Add at least one block to the pattern.';
        return [
            'data' => ['name' => $name, 'mode' => $mode, 'blocks' => $validated['blocks'], 'blocks_json' => $validated['json']],
            'errors' => array_values(array_unique($errors)),
        ];
    }

    public static function create(array $data, int $userId, bool $duplicateExistingAssets = false, array $alreadyOwnedAssets = []): int
    {
        $blocks = $data['blocks'];
        $ownedBefore = PageBlocks::assetPaths($blocks);
        if ($duplicateExistingAssets) $blocks = self::duplicateAssets($blocks, $alreadyOwnedAssets);
        $ownedAfter = PageBlocks::assetPaths($blocks);
        $createdCopies = array_values(array_diff($ownedAfter, $ownedBefore));
        try {
            $json = self::encode($blocks);
            $stmt = Database::connection()->prepare(
                'INSERT INTO page_patterns (name,mode,blocks_json,created_by,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
            );
            $stmt->execute([$data['name'],$data['mode'],$json,$userId > 0 ? $userId : null,$userId > 0 ? $userId : null]);
            return (int)Database::connection()->lastInsertId();
        } catch (\Throwable $e) {
            foreach ($createdCopies as $path) SiteAssets::remove($path);
            throw $e;
        }
    }

    public static function update(int $id, array $data, int $userId): void
    {
        if (!self::find($id)) throw new RuntimeException('Pattern not found.');
        $stmt = Database::connection()->prepare('UPDATE page_patterns SET name=?,mode=?,blocks_json=?,updated_by=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$data['name'],$data['mode'],self::encode($data['blocks']),$userId > 0 ? $userId : null,$id]);
    }

    public static function delete(int $id): void
    {
        $row = self::find($id);
        if (!$row) return;
        if ((int)$row['usage_count'] > 0) {
            throw new RuntimeException('This synced pattern is still used by ' . (int)$row['usage_count'] . ' page' . ((int)$row['usage_count'] === 1 ? '' : 's') . '. Detach those instances before deleting it.');
        }
        $assets = PageBlocks::assetPaths($row['blocks']);
        $stmt = Database::connection()->prepare('DELETE FROM page_patterns WHERE id=?');
        $stmt->execute([$id]);
        foreach ($assets as $path) SiteAssets::remove($path);
    }

    public static function usageCount(int $id): int
    {
        if ($id < 1) return 0;
        return (int)(self::usageCounts()[$id] ?? 0);
    }

    /** @return array<int,int> pattern id => number of pages using it */
    private static function usageCounts(): array
    {
        $stmt = Database::connection()->query("SELECT blocks_json FROM pages WHERE deleted_at IS NULL AND blocks_json LIKE '%\"pattern_id\":%'");
        $counts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $json) {
            $seenOnPage = [];
            foreach (PageBlocks::decode((string)$json) as $block) {
                if (!is_array($block) || ($block['type'] ?? '') !== 'pattern') continue;
                $patternId = (int)($block['pattern_id'] ?? 0);
                if ($patternId > 0) $seenOnPage[$patternId] = true;
            }
            foreach (array_keys($seenOnPage) as $patternId) {
                $counts[(int)$patternId] = (int)($counts[(int)$patternId] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /** @param list<array<string,mixed>> $blocks @return list<array<string,mixed>> */
    public static function duplicateAssets(array $blocks, array $skipPaths = []): array
    {
        foreach ($blocks as &$block) {
            if (!is_array($block)) continue;
            if (($block['type'] ?? '') === 'hero') {
                $path = HomePage::safeStoredAssetPath((string)($block['image_path'] ?? ''));
                if ($path !== '' && !in_array($path, $skipPaths, true)) $block['image_path'] = SiteAssets::duplicateStoredImage($path, 'pattern');
            }
            if (($block['type'] ?? '') === 'cards') {
                foreach (($block['items'] ?? []) as &$item) {
                    if (!is_array($item)) continue;
                    $path = HomePage::safeStoredAssetPath((string)($item['image_path'] ?? ''));
                    if ($path !== '' && !in_array($path, $skipPaths, true)) $item['image_path'] = SiteAssets::duplicateStoredImage($path, 'pattern');
                }
                unset($item);
            }
        }
        unset($block);
        return $blocks;
    }

    /** @param list<array<string,mixed>> $blocks @return list<string> */
    private static function previewTypes(array $blocks): array
    {
        $types = [];
        foreach ($blocks as $block) {
            if (!is_array($block) || ($block['enabled'] ?? true) === false) continue;
            $type = (string)($block['type'] ?? 'custom');
            if ($type === 'pattern') $type = 'pattern';
            if (!preg_match('/^[a-z_]{2,40}$/D', $type)) $type = 'custom';
            $types[] = $type;
            if (count($types) >= 4) break;
        }
        return $types ?: ['custom'];
    }

    /** @param list<array<string,mixed>> $blocks */
    private static function encode(array $blocks): string
    {
        $json = json_encode($blocks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) throw new RuntimeException('Pattern blocks could not be encoded.');
        return $json;
    }

    private function __construct() {}
}
