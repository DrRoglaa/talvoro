<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class UserManager
{
    private const ROLE_RANK = [
        'analyst' => 10,
        'editor' => 20,
        'administrator' => 30,
        'super_administrator' => 40,
    ];

    public static function all(): array
    {
        return Database::connection()->query(
            'SELECT u.id,u.email,u.display_name,u.status,u.last_login_at,u.created_at,u.mfa_enabled,
                    r.id role_id,r.name role_name,r.label role_label
             FROM users u
             JOIN roles r ON r.id=u.role_id
             ORDER BY FIELD(r.name,"super_administrator","administrator","editor","analyst"),u.id'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT u.id,u.email,u.display_name,u.status,u.must_change_password,u.mfa_enabled,
                    u.mfa_secret_encrypted,u.mfa_pending_secret_encrypted,u.mfa_recovery_hashes,
                    u.last_login_at,u.created_at,u.updated_at,
                    r.id role_id,r.name role_name,r.label role_label
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.id=? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function availableRoles(array $actor): array
    {
        $actorRole = (string)$actor['role_name'];

        if ($actorRole === 'super_administrator') {
            return Database::connection()->query(
                'SELECT id,name,label FROM roles
                 WHERE name IN ("super_administrator","administrator","editor","analyst")
                 ORDER BY FIELD(name,"super_administrator","administrator","editor","analyst")'
            )->fetchAll(PDO::FETCH_ASSOC);
        }

        return Database::connection()->query(
            'SELECT id,name,label FROM roles
             WHERE name IN ("editor","analyst")
             ORDER BY FIELD(name,"editor","analyst")'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function canManage(array $actor, array $target): bool
    {
        if ((int)$actor['id'] === (int)$target['id']) {
            return true;
        }

        if ((string)$actor['role_name'] === 'super_administrator') {
            return true;
        }

        if ((string)$actor['role_name'] === 'administrator') {
            return in_array((string)$target['role_name'], ['editor','analyst'], true);
        }

        return false;
    }

    public static function canDelete(array $actor, array $target): bool
    {
        return (string)$actor['role_name'] === 'super_administrator'
            && (int)$actor['id'] !== (int)$target['id']
            && in_array((string)$target['role_name'], ['administrator','editor','analyst'], true);
    }

    public static function create(
        array $actor,
        string $email,
        string $displayName,
        string $password,
        int $roleId
    ): int {
        $role = self::roleById($roleId);
        if (!$role || !self::actorCanAssignRole($actor, (string)$role['name'])) {
            throw new RuntimeException('You cannot assign that role.');
        }

        $stmt = Database::connection()->prepare(
            "INSERT INTO users
             (email,password_hash,display_name,role_id,status,must_change_password,created_at,updated_at)
             VALUES (?,?,?,?,'active',1,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
        $stmt->execute([
            mb_strtolower(trim($email)),
            password_hash($password, PASSWORD_DEFAULT),
            trim($displayName),
            $roleId,
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    public static function updateSecurity(
        array $actor,
        array $target,
        string $displayName,
        int $roleId,
        string $status
    ): void {
        if (!self::canManage($actor, $target)) {
            throw new RuntimeException('You cannot manage this user.');
        }

        $displayName = trim($displayName);
        if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 120) {
            throw new RuntimeException('Display name must be between 2 and 120 characters.');
        }

        if ((int)$actor['id'] === (int)$target['id'] && $roleId !== (int)$target['role_id']) {
            throw new RuntimeException('You cannot change your own role.');
        }

        $role = self::roleById($roleId);
        if (!$role || !self::actorCanAssignRole($actor, (string)$role['name'])) {
            if ((int)$actor['id'] !== (int)$target['id'] || $roleId !== (int)$target['role_id']) {
                throw new RuntimeException('You cannot assign that role.');
            }
        }

        if (!in_array($status, ['active','disabled'], true)) {
            throw new RuntimeException('Invalid account status.');
        }
        if ((int)$actor['id'] === (int)$target['id'] && $status !== 'active') {
            throw new RuntimeException('You cannot disable your own account.');
        }

        if (
            (string)$target['role_name'] === 'super_administrator'
            && ((string)$role['name'] !== 'super_administrator' || $status !== 'active')
            && self::activeSuperAdministratorCount() <= 1
        ) {
            throw new RuntimeException('At least one active Super Administrator must remain.');
        }

        Database::connection()->prepare(
            'UPDATE users SET display_name=?,role_id=?,status=?,updated_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([$displayName, $roleId, $status, (int)$target['id']]);
    }

    public static function resetPassword(array $actor, array $target, string $password): void
    {
        if (!self::canManage($actor, $target)) {
            throw new RuntimeException('You cannot manage this user.');
        }
        $errors = PasswordPolicy::validate($password, (string)($target['email'] ?? ''), (string)($target['display_name'] ?? ''));
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        Database::connection()->prepare(
            'UPDATE users SET password_hash=?,must_change_password=1,updated_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([password_hash($password, PASSWORD_DEFAULT), (int)$target['id']]);
        Auth::revokeSessionsForUser((int)$target['id']);
    }

    public static function setOwnPassword(int $userId, string $password): void
    {
        $stmt = Database::connection()->prepare('SELECT email,display_name FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $identity = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        $errors = PasswordPolicy::validate($password, (string)($identity['email'] ?? ''), (string)($identity['display_name'] ?? ''));
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }

        Database::connection()->prepare(
            'UPDATE users SET password_hash=?,must_change_password=0,updated_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([password_hash($password, PASSWORD_DEFAULT), $userId]);
    }

    public static function delete(array $actor, array $target): void
    {
        if (!self::canDelete($actor, $target)) {
            throw new RuntimeException('Only a Super Administrator can delete Administrator, Editor or Analyst accounts.');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE posts SET author_id=? WHERE author_id=?')
                ->execute([(int)$actor['id'], (int)$target['id']]);
            $db->prepare('UPDATE pages SET author_id=? WHERE author_id=?')
                ->execute([(int)$actor['id'], (int)$target['id']]);
            $db->prepare('DELETE FROM users WHERE id=?')->execute([(int)$target['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function auditPage(int $userId, int $page = 1, int $perPage = 10): array
    {
        $perPage = max(1, min(50, $perPage));
        $page = max(1, $page);

        $count = Database::connection()->prepare(
            'SELECT COUNT(*) FROM audit_log
             WHERE user_id=? OR (target_type="user" AND target_id=?)'
        );
        $count->execute([$userId, $userId]);
        $total = (int)$count->fetchColumn();
        $pages = max(1, (int)ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = Database::connection()->prepare(
            'SELECT id,user_id,action,target_type,target_id,meta_json,created_at
             FROM audit_log
             WHERE user_id=? OR (target_type="user" AND target_id=?)
             ORDER BY id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset
        );
        $stmt->execute([$userId, $userId]);

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'per_page' => $perPage,
        ];
    }

    public static function purgeAudit(array $actor, array $target): int
    {
        if ((string)$actor['role_name'] !== 'super_administrator') {
            throw new RuntimeException('Only a Super Administrator can delete user audit logs.');
        }

        $stmt = Database::connection()->prepare(
            'DELETE FROM audit_log WHERE user_id=? OR (target_type="user" AND target_id=?)'
        );
        $stmt->execute([(int)$target['id'], (int)$target['id']]);
        return $stmt->rowCount();
    }

    public static function startMfa(array $actor, array $target): string
    {
        if ((int)$actor['id'] !== (int)$target['id']) {
            throw new RuntimeException('Each user must enroll their own authenticator.');
        }

        $secret = TwoFactor::generateSecret();
        Database::connection()->prepare(
            'UPDATE users SET mfa_pending_secret_encrypted=?,updated_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([Crypto::encrypt($secret, 'mfa'), (int)$target['id']]);
        return $secret;
    }

    public static function pendingMfaSecret(array $target): string
    {
        return Crypto::decrypt((string)($target['mfa_pending_secret_encrypted'] ?? ''), 'mfa');
    }

    public static function enableMfa(array $actor, array $target, string $code): array
    {
        if ((int)$actor['id'] !== (int)$target['id']) {
            throw new RuntimeException('Each user must enroll their own authenticator.');
        }

        $secret = self::pendingMfaSecret($target);
        if ($secret === '' || !TwoFactor::verify($secret, $code)) {
            throw new RuntimeException('Authenticator code was not accepted.');
        }

        $recovery = TwoFactor::generateRecoveryCodes(8);
        $hashes = array_map(static fn(string $item): string => TwoFactor::recoveryHash($item), $recovery);

        Database::connection()->prepare(
            'UPDATE users
             SET mfa_enabled=1,mfa_secret_encrypted=?,mfa_pending_secret_encrypted=NULL,
                 mfa_recovery_hashes=?,updated_at=UTC_TIMESTAMP()
             WHERE id=?'
        )->execute([
            Crypto::encrypt($secret, 'mfa'),
            json_encode($hashes, JSON_UNESCAPED_SLASHES),
            (int)$target['id'],
        ]);

        Auth::markCurrentSessionMfaVerified();
        return $recovery;
    }

    public static function regenerateRecoveryCodes(array $actor, array $target): array
    {
        if ((int)$actor['id'] !== (int)$target['id']) {
            throw new RuntimeException('Each user must regenerate their own recovery codes.');
        }
        if ((int)($target['mfa_enabled'] ?? 0) !== 1) {
            throw new RuntimeException('Two-factor authentication is not enabled.');
        }

        $recovery = TwoFactor::generateRecoveryCodes(8);
        $hashes = array_map(static fn(string $item): string => TwoFactor::recoveryHash($item), $recovery);
        Database::connection()->prepare(
            'UPDATE users SET mfa_recovery_hashes=?,updated_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([json_encode($hashes, JSON_UNESCAPED_SLASHES), (int)$target['id']]);
        return $recovery;
    }

    public static function resetMfa(array $actor, array $target): void
    {
        if ((int)$actor['id'] !== (int)$target['id'] && !self::canManage($actor, $target)) {
            throw new RuntimeException('You cannot manage this user.');
        }

        Database::connection()->prepare(
            'UPDATE users
             SET mfa_enabled=0,mfa_secret_encrypted=NULL,mfa_pending_secret_encrypted=NULL,
                 mfa_recovery_hashes=NULL,updated_at=UTC_TIMESTAMP()
             WHERE id=?'
        )->execute([(int)$target['id']]);
    }

    private static function roleById(int $id): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id,name,label FROM roles WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function actorCanAssignRole(array $actor, string $targetRole): bool
    {
        if ((string)$actor['role_name'] === 'super_administrator') {
            return array_key_exists($targetRole, self::ROLE_RANK);
        }
        return (string)$actor['role_name'] === 'administrator'
            && in_array($targetRole, ['editor','analyst'], true);
    }

    private static function activeSuperAdministratorCount(): int
    {
        return (int)Database::connection()->query(
            "SELECT COUNT(*) FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE r.name='super_administrator' AND u.status='active'"
        )->fetchColumn();
    }

    private function __construct()
    {
    }
}
