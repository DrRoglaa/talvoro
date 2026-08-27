<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class ContentLifecycle
{
    private const TYPES = ['page' => 'pages', 'post' => 'posts', 'entry' => 'content_entries'];

    public static function moveToTrash(string $type, int $id, int $userId): void
    {
        $table = self::table($type);
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            // Lock the entity first. All lifecycle/save operations use the same
            // entity -> revision lock order, avoiding revision/page deadlocks.
            $select = $db->prepare("SELECT * FROM {$table} WHERE id=? AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
            $select->execute([$id]);
            $row = $select->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException(ucfirst($type) . ' is already in Trash or no longer exists.');
            if ($type === 'page' && (string)($row['path'] ?? '') === '/') {
                throw new RuntimeException('The Home page cannot be moved to Trash.');
            }

            if (self::historyEnabled($type,$row)) ContentHistory::capture($type, $id, $userId, 'trash');
            $stmt = $db->prepare("UPDATE {$table} SET deleted_at=UTC_TIMESTAMP(),deleted_by=?,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NULL");
            $stmt->execute([$userId, $id]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException(ucfirst($type) . ' could not be moved to Trash.');
            if (self::autosaveEnabled($type,$row)) ContentHistory::clearAutosave($type, $id, $userId);
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function restore(string $type, int $id, int $userId): void
    {
        $table = self::table($type);
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            $select = $db->prepare("SELECT * FROM {$table} WHERE id=? AND deleted_at IS NOT NULL LIMIT 1 FOR UPDATE");
            $select->execute([$id]);
            $row=$select->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException(ucfirst($type) . ' is not in Trash.');

            $stmt = $db->prepare("UPDATE {$table} SET deleted_at=NULL,deleted_by=NULL,updated_at=UTC_TIMESTAMP() WHERE id=? AND deleted_at IS NOT NULL");
            $stmt->execute([$id]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException(ucfirst($type) . ' could not be restored.');
            if (self::historyEnabled($type,$row)) ContentHistory::capture($type, $id, $userId, 'trash_restore');
            if ($ownsTransaction) $db->commit();
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function permanentlyDelete(string $type, int $id): array
    {
        $table = self::table($type);
        $db = Database::connection();
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) $db->beginTransaction();
        try {
            // Re-read under a row lock. A concurrent Restore must never race a
            // permanent delete and leave restored content without its history.
            $stmt = $db->prepare("SELECT * FROM {$table} WHERE id=? AND deleted_at IS NOT NULL LIMIT 1 FOR UPDATE");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new RuntimeException(ucfirst($type) . ' must be in Trash before permanent deletion.');

            if ($type === 'page' && !empty($row['path'])) {
                try { $db->prepare('DELETE FROM seo_pages WHERE path=?')->execute([(string)$row['path']]); } catch (\Throwable) {}
            }
            if ($type === 'entry') {
                $references=CustomContent::relationReferencesCount($id);
                if ($references>0) {
                    throw new RuntimeException($references . ($references===1 ? ' content entry still references this item. Remove or change that relation before permanent deletion, including references from content in Trash.' : ' content entries still reference this item. Remove or change those relations before permanent deletion, including references from content in Trash.'));
                }
            }
            ContentHistory::purgeForContent($type, $id);
            $delete = $db->prepare("DELETE FROM {$table} WHERE id=? AND deleted_at IS NOT NULL");
            $delete->execute([$id]);
            if ($delete->rowCount() !== 1) throw new RuntimeException(ucfirst($type) . ' could not be permanently deleted.');
            if ($ownsTransaction) $db->commit();
            return $row;
        } catch (\Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function trashedItem(string $type, int $id): ?array
    {
        $table = self::table($type);
        $stmt = Database::connection()->prepare("SELECT * FROM {$table} WHERE id=? AND deleted_at IS NOT NULL LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Opportunistic daily maintenance for self-hosted installs that may not
     * have a cron scheduler. It is lock-protected and bounded so opening the
     * content area cannot turn into an unbounded cleanup request.
     *
     * @return array{page:int,post:int,entry:int,ran:bool}
     */
    public static function maybePurgeExpired(): array
    {
        $last = trim((string)(Settings::get('content.trash_last_prune_at', '') ?? ''));
        $lastTime = $last !== '' ? strtotime($last . ' UTC') : false;
        if ($lastTime !== false && $lastTime > time() - 86400) return ['page' => 0, 'post' => 0, 'entry' => 0, 'ran' => false];

        $db = Database::connection();
        $lockName = 'talvoro_content_trash_prune';
        $locked = false;
        try {
            $lockStmt = $db->prepare('SELECT GET_LOCK(?,0)');
            $lockStmt->execute([$lockName]);
            $locked = (int)$lockStmt->fetchColumn() === 1;
            if (!$locked) return ['page' => 0, 'post' => 0, 'entry' => 0, 'ran' => false];

            // Another request may have completed maintenance while this request
            // waited to acquire the lock, so re-read the setting directly.
            $freshStmt = $db->prepare('SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1');
            $freshStmt->execute(['content.trash_last_prune_at']);
            $fresh = trim((string)($freshStmt->fetchColumn() ?: ''));
            $freshTime = $fresh !== '' ? strtotime($fresh . ' UTC') : false;
            if ($freshTime !== false && $freshTime > time() - 86400) return ['page' => 0, 'post' => 0, 'entry' => 0, 'ran' => false];

            $pages = self::purgeExpired('page', 25);
            $posts = self::purgeExpired('post', 25);
            $entries = self::purgeExpired('entry', 25);
            Settings::set('content.trash_last_prune_at', gmdate('Y-m-d H:i:s'));
            return ['page' => $pages, 'post' => $posts, 'entry' => $entries, 'ran' => true];
        } finally {
            if ($locked) {
                try {
                    $release = $db->prepare('SELECT RELEASE_LOCK(?)');
                    $release->execute([$lockName]);
                } catch (\Throwable) {}
            }
        }
    }

    /**
     * Purge expired Trash entries in small batches. Revision assets are removed
     * only after the database deletion succeeds.
     *
     * @return int number of permanently removed items
     */
    public static function purgeExpired(string $type, int $limit = 25): int
    {
        $table = self::table($type);
        $limit = max(1, min(100, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::retentionDays() * 86400);
        $stmt = Database::connection()->prepare(
            "SELECT id FROM {$table} WHERE deleted_at IS NOT NULL AND deleted_at < ? ORDER BY deleted_at ASC,id ASC LIMIT {$limit}"
        );
        $stmt->execute([$cutoff]);
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $removed = 0;

        foreach ($ids as $id) {
            $revisionAssets = ContentHistory::assetPathsForContent($type, $id);
            try {
                $row = self::permanentlyDelete($type, $id);
            } catch (\Throwable) {
                continue;
            }
            if ($type === 'post') {
                SiteAssets::remove((string)($row['featured_image_path'] ?? ''));
            } elseif ($type === 'page') {
                foreach (PageBlocks::assetPaths(PageBlocks::decode((string)($row['blocks_json'] ?? '[]'))) as $path) {
                    SiteAssets::remove($path);
                }
            }
            foreach ($revisionAssets as $path) SiteAssets::remove($path);
            $removed++;
        }
        return $removed;
    }

    public static function trashCount(string $type): int
    {
        $table = self::table($type);
        return (int)Database::connection()->query("SELECT COUNT(*) FROM {$table} WHERE deleted_at IS NOT NULL")->fetchColumn();
    }

    public static function retentionDays(): int
    {
        return max(1, min(3650, (int)(Settings::get('content.trash_retention_days', '30') ?? '30')));
    }

    private static function historyEnabled(string $type, array $row): bool
    {
        if ($type!=='entry') return true;
        $model=ContentModels::find((int)($row['model_id']??0));
        return $model && (int)($model['enable_revisions']??1)===1;
    }

    private static function autosaveEnabled(string $type, array $row): bool
    {
        if ($type!=='entry') return true;
        $model=ContentModels::find((int)($row['model_id']??0));
        return $model && (int)($model['enable_autosave']??1)===1;
    }

    private static function table(string $type): string
    {
        if (!isset(self::TYPES[$type])) throw new RuntimeException('Unsupported content type.');
        return self::TYPES[$type];
    }

    private function __construct() {}
}
