<?php
declare(strict_types=1);

namespace CMS\Core;

final class Gate
{
    private static array $cache = [];

    public static function allows(string $permission): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $role = (string)$user['role_name'];

        if ($role === 'super_administrator') {
            return true;
        }

        $key = $role . ':' . $permission;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $stmt = Database::connection()->prepare(
            'SELECT 1
             FROM permissions p
             JOIN role_permissions rp ON rp.permission_id=p.id
             JOIN roles r ON r.id=rp.role_id
             WHERE r.name=? AND p.name=?
             LIMIT 1'
        );
        $stmt->execute([$role, $permission]);

        return self::$cache[$key] = (bool)$stmt->fetchColumn();
    }

    public static function allowsModel(int $modelId, string $action): bool
    {
        $user = Auth::user();
        if (!$user || $modelId < 1) {
            return false;
        }

        $role = (string)$user['role_name'];
        if ($role === 'super_administrator') {
            return true;
        }

        $permissionMap = [
            'view' => 'custom_content.view',
            'create' => 'custom_content.create',
            'edit' => 'custom_content.edit',
            'publish' => 'custom_content.publish',
            'delete' => 'custom_content.delete',
        ];
        $columnMap = [
            'view' => 'can_view',
            'create' => 'can_create',
            'edit' => 'can_edit',
            'publish' => 'can_publish',
            'delete' => 'can_delete',
        ];
        if (!isset($permissionMap[$action], $columnMap[$action])) {
            return false;
        }

        // A model-specific grant never broadens the user's global role. Both
        // layers must allow the action. This prevents privilege escalation if
        // a model permission row is edited incorrectly.
        if (!self::allows($permissionMap[$action])) {
            return false;
        }

        $key = $role . ':model:' . $modelId . ':' . $action;
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $column = $columnMap[$action];
            $stmt = Database::connection()->prepare(
                "SELECT mp.{$column}
                 FROM content_model_role_permissions mp
                 JOIN roles r ON r.id=mp.role_id
                 WHERE mp.model_id=? AND r.name=?
                 LIMIT 1"
            );
            $stmt->execute([$modelId, $role]);
            $value = $stmt->fetchColumn();
            // Existing installations are upgraded incrementally. Until a row
            // exists for a legacy model, preserve the established global role
            // behavior rather than unexpectedly locking editors out.
            return self::$cache[$key] = ($value === false ? true : (int)$value === 1);
        } catch (\Throwable) {
            // During a numbered migration window the table may not exist yet.
            // The global permission check above remains the secure fallback.
            return self::$cache[$key] = true;
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private function __construct()
    {
    }
}
