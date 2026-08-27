<?php
declare(strict_types=1);

namespace CMS\Core;

final class Audit
{
    public static function log(string $action, ?string $targetType = null, ?int $targetId = null, array $meta = []): void
    {
        try {
            $user = Auth::user();
            $stmt = Database::connection()->prepare('INSERT INTO audit_log (user_id,action,target_type,target_id,meta_json,created_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP())');
            $stmt->execute([$user['id'] ?? null, $action, $targetType, $targetId, $meta ? json_encode($meta, JSON_UNESCAPED_SLASHES) : null]);
        } catch (\Throwable) {
        }
    }
}
