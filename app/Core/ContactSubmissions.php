<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class ContactSubmissions
{
    public static function create(array $data): int
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO contact_submissions
             (page_id,form_owner_id,block_id,source_label,source_path,sender_name,sender_email,subject,message,status,delivery_status,created_at)
             VALUES (?,?,?,?,?,?,?,?,?,\'new\',\'pending\',UTC_TIMESTAMP())'
        );
        $stmt->execute([
            ($data['page_id'] ?? 0) > 0 ? (int)$data['page_id'] : null,
            (string)$data['form_owner_id'],
            (string)$data['block_id'],
            (string)$data['source_label'],
            (string)$data['source_path'],
            (string)$data['sender_name'],
            (string)$data['sender_email'],
            (string)($data['subject'] ?? '') !== '' ? (string)$data['subject'] : null,
            (string)$data['message'],
        ]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function markDelivery(int $id, string $status): void
    {
        if ($id < 1 || !in_array($status, ['sent','failed'], true)) return;
        Database::connection()->prepare('UPDATE contact_submissions SET delivery_status=? WHERE id=?')->execute([$status, $id]);
    }

    /** @return array{items:array<int,array<string,mixed>>,page:int,pages:int,total:int,status:string} */
    public static function adminPage(int $page = 1, string $status = '', int $perPage = 25): array
    {
        $page = max(1, $page);
        $perPage = max(10, min(100, $perPage));
        $status = in_array($status, ['new','read'], true) ? $status : '';
        $where = $status !== '' ? ' WHERE status=?' : '';
        $args = $status !== '' ? [$status] : [];

        $count = Database::connection()->prepare('SELECT COUNT(*) FROM contact_submissions' . $where);
        $count->execute($args);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            'SELECT id,source_label,source_path,sender_name,sender_email,subject,status,delivery_status,read_at,created_at
             FROM contact_submissions' . $where . ' ORDER BY created_at DESC,id DESC LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute($args);
        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'page' => $page, 'pages' => $pages, 'total' => $total, 'status' => $status];
    }

    public static function find(int $id): ?array
    {
        if ($id < 1) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM contact_submissions WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function setRead(int $id, bool $read): void
    {
        if ($id < 1) return;
        if ($read) {
            Database::connection()->prepare("UPDATE contact_submissions SET status='read',read_at=COALESCE(read_at,UTC_TIMESTAMP()) WHERE id=?")->execute([$id]);
        } else {
            Database::connection()->prepare("UPDATE contact_submissions SET status='new',read_at=NULL WHERE id=?")->execute([$id]);
        }
    }

    public static function delete(int $id): bool
    {
        if ($id < 1) return false;
        $stmt = Database::connection()->prepare('DELETE FROM contact_submissions WHERE id=?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    /** @param list<int> $ids */
    public static function deleteMany(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
        $ids = array_slice($ids, 0, 100);
        if ($ids === []) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare('DELETE FROM contact_submissions WHERE id IN (' . $placeholders . ')');
        $stmt->execute($ids);
        return $stmt->rowCount();
    }

    public static function purgeExpired(int $retentionDays): int
    {
        if ($retentionDays < 1) return 0;
        $retentionDays = min(3650, $retentionDays);
        $stmt = Database::connection()->prepare('DELETE FROM contact_submissions WHERE created_at < (UTC_TIMESTAMP() - INTERVAL ' . $retentionDays . ' DAY)');
        $stmt->execute();
        return $stmt->rowCount();
    }

    public static function unreadCount(): int
    {
        try {
            return (int)Database::connection()->query("SELECT COUNT(*) FROM contact_submissions WHERE status='new'")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function __construct() {}
}
