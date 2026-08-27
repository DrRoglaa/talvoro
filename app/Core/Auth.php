<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class Auth
{
    /** Used when an account is missing/disabled so password verification still pays a real bcrypt cost. */
    private const DUMMY_PASSWORD_HASH = '$2y$12$SjYjo5jHCSTimh0.RBl5QORHryKxxStSFsuBrc74Hmxdc0Bc/w31y';

    private static ?array $cached = null;

    public static function user(): ?array
    {
        if (self::$cached !== null) {
            return self::$cached ?: null;
        }

        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            self::$cached = [];
            return null;
        }

        $stmt = Database::connection()->prepare(
            'SELECT u.id,u.email,u.display_name,u.status,u.must_change_password,u.mfa_enabled,
                    r.name AS role_name,r.label AS role_label
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE u.id=? LIMIT 1'
        );
        $stmt->execute([(int)$id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$user || $user['status'] !== 'active') {
            self::logout();
            self::$cached = [];
            return null;
        }

        if ((int)$user['mfa_enabled'] === 1 && empty($_SESSION['mfa_verified'])) {
            self::logout();
            self::$cached = [];
            return null;
        }

        if (!self::touchSession((int)$user['id'], !empty($_SESSION['mfa_verified']))) {
            self::logout();
            self::$cached = [];
            return null;
        }

        self::$cached = $user;
        return $user;
    }

    /** @return 'ok'|'mfa_required'|'invalid' */
    public static function attempt(string $email, string $password): string
    {
        $stmt = Database::connection()->prepare(
            'SELECT id,email,password_hash,status,mfa_enabled
             FROM users WHERE email=? LIMIT 1'
        );
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $active = is_array($user) && (string)($user['status'] ?? '') === 'active';
        $hash = $active ? (string)($user['password_hash'] ?? '') : self::DUMMY_PASSWORD_HASH;
        $passwordValid = password_verify($password, $hash !== '' ? $hash : self::DUMMY_PASSWORD_HASH);

        if (!$active || !$passwordValid) {
            return 'invalid';
        }

        session_regenerate_id(true);
        self::clearPendingMfa();
        self::$cached = null;

        if ((int)$user['mfa_enabled'] === 1) {
            $_SESSION['_mfa_user_id'] = (int)$user['id'];
            $_SESSION['_mfa_email'] = (string)$user['email'];
            $_SESSION['_mfa_expires'] = time() + 300;
            $_SESSION['last_activity'] = time();
            return 'mfa_required';
        }

        self::completeLogin((int)$user['id'], false);
        return 'ok';
    }

    public static function pendingMfaEmail(): ?string
    {
        if (!self::hasPendingMfa()) {
            return null;
        }
        return (string)($_SESSION['_mfa_email'] ?? '');
    }

    public static function hasPendingMfa(): bool
    {
        return isset($_SESSION['_mfa_user_id'])
            && (int)($_SESSION['_mfa_expires'] ?? 0) >= time();
    }

    public static function verifyPendingMfa(string $candidate): bool
    {
        if (!self::hasPendingMfa()) {
            self::clearPendingMfa();
            return false;
        }

        $userId = (int)$_SESSION['_mfa_user_id'];
        $stmt = Database::connection()->prepare(
            'SELECT email,mfa_enabled,mfa_secret_encrypted,mfa_recovery_hashes
             FROM users WHERE id=? AND status="active" LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || (int)$user['mfa_enabled'] !== 1) {
            self::clearPendingMfa();
            return false;
        }

        $secret = Crypto::decrypt((string)$user['mfa_secret_encrypted'], 'mfa');
        $verified = $secret !== '' && TwoFactor::verify($secret, $candidate);

        if (!$verified) {
            $candidateHash = TwoFactor::recoveryHash($candidate);
            $hashes = json_decode((string)($user['mfa_recovery_hashes'] ?? '[]'), true);
            $hashes = is_array($hashes) ? array_values(array_filter($hashes, 'is_string')) : [];

            foreach ($hashes as $index => $hash) {
                if (hash_equals($hash, $candidateHash)) {
                    $verified = true;
                    unset($hashes[$index]);
                    Database::connection()->prepare(
                        'UPDATE users SET mfa_recovery_hashes=?,updated_at=UTC_TIMESTAMP() WHERE id=?'
                    )->execute([json_encode(array_values($hashes), JSON_UNESCAPED_SLASHES), $userId]);
                    break;
                }
            }
        }

        if (!$verified) {
            return false;
        }

        self::clearPendingMfa();
        self::completeLogin($userId, true);
        return true;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function requiresPasswordChange(): bool
    {
        $user = self::user();
        return $user !== null && (int)($user['must_change_password'] ?? 0) === 1;
    }

    public static function verifyCurrentPassword(string $password): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        $stmt = Database::connection()->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->execute([(int)$user['id']]);
        $hash = (string)($stmt->fetchColumn() ?: '');
        return $hash !== '' && password_verify($password, $hash);
    }

    public static function verifyCurrentSecondFactor(string $candidate): bool
    {
        $user = self::user();
        if (!$user || (int)($user['mfa_enabled'] ?? 0) !== 1) {
            return false;
        }

        $stmt = Database::connection()->prepare(
            'SELECT mfa_secret_encrypted FROM users WHERE id=? AND status="active" LIMIT 1'
        );
        $stmt->execute([(int)$user['id']]);
        $encrypted = (string)($stmt->fetchColumn() ?: '');
        $secret = Crypto::decrypt($encrypted, 'mfa');
        return $secret !== '' && TwoFactor::verify($secret, trim($candidate));
    }

    public static function logout(): void
    {
        self::revokeCurrentSession();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::$cached = null;
    }

    public static function enforceLifetime(): void
    {
        $minutes = max(15, (int)(Env::get('SESSION_LIFETIME', '120') ?? '120'));
        $last = (int)($_SESSION['last_activity'] ?? time());

        if ((isset($_SESSION['user_id']) || isset($_SESSION['_mfa_user_id'])) && (time() - $last) > $minutes * 60) {
            self::logout();
            return;
        }

        $_SESSION['last_activity'] = time();
    }

    public static function sessionsForUser(int $userId): array
    {
        try {
            $stmt = Database::connection()->prepare(
                'SELECT id,session_hash,user_agent,created_at,last_seen_at,mfa_verified_at,revoked_at
                 FROM user_sessions
                 WHERE user_id=?
                 ORDER BY COALESCE(revoked_at,last_seen_at) DESC,id DESC
                 LIMIT 30'
            );
            $stmt->execute([$userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $currentHash = self::currentSessionHash();

            foreach ($rows as &$row) {
                $row['is_current'] = hash_equals((string)$row['session_hash'], $currentHash);
            }
            unset($row);
            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }

    public static function revokeSessionsForUser(int $userId): void
    {
        try {
            Database::connection()->prepare(
                'UPDATE user_sessions
                 SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP())
                 WHERE user_id=?'
            )->execute([$userId]);
        } catch (\Throwable) {
        }
    }

    public static function markCurrentSessionMfaVerified(): void
    {
        $_SESSION['mfa_verified'] = 1;
        $id = (int)($_SESSION['user_id'] ?? 0);
        if ($id > 0) {
            self::touchSession($id, true);
        }
    }

    /**
     * Revoke every recorded session for the user, then immediately establish a fresh
     * session for the current browser. Used after sensitive MFA state changes so the
     * current administrator stays signed in without leaving older sessions trusted.
     */
    public static function rotateCurrentSessionAfterSecurityChange(int $userId, bool $mfaVerified): void
    {
        self::revokeSessionsForUser($userId);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        self::clearPendingMfa();
        $_SESSION['user_id'] = $userId;
        $_SESSION['mfa_verified'] = $mfaVerified ? 1 : 0;
        $_SESSION['last_activity'] = time();
        self::$cached = null;
        self::touchSession($userId, $mfaVerified);
    }

    private static function completeLogin(int $userId, bool $mfaVerified): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['mfa_verified'] = $mfaVerified ? 1 : 0;
        $_SESSION['last_activity'] = time();
        self::$cached = null;

        Database::connection()->prepare(
            'UPDATE users SET last_login_at=UTC_TIMESTAMP() WHERE id=?'
        )->execute([$userId]);
        self::touchSession($userId, $mfaVerified);
    }

    private static function clearPendingMfa(): void
    {
        unset($_SESSION['_mfa_user_id'], $_SESSION['_mfa_email'], $_SESSION['_mfa_expires']);
    }

    private static function touchSession(int $userId, bool $mfaVerified): bool
    {
        try {
            $hash = self::currentSessionHash();
            $stmt = Database::connection()->prepare(
                'SELECT revoked_at FROM user_sessions WHERE session_hash=? LIMIT 1'
            );
            $stmt->execute([$hash]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing && $existing['revoked_at'] !== null) {
                return false;
            }

            if (!$existing) {
                $ua = mb_substr(trim((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown client')), 0, 255);
                Database::connection()->prepare(
                    'INSERT INTO user_sessions
                     (user_id,session_hash,user_agent,created_at,last_seen_at,mfa_verified_at)
                     VALUES (?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),?)'
                )->execute([$userId, $hash, $ua, $mfaVerified ? gmdate('Y-m-d H:i:s') : null]);
            } else {
                Database::connection()->prepare(
                    'UPDATE user_sessions
                     SET last_seen_at=UTC_TIMESTAMP(),mfa_verified_at=CASE WHEN ?=1 THEN COALESCE(mfa_verified_at,UTC_TIMESTAMP()) ELSE mfa_verified_at END
                     WHERE session_hash=?'
                )->execute([$mfaVerified ? 1 : 0, $hash]);
            }

            return true;
        } catch (\Throwable) {
            return true;
        }
    }

    private static function revokeCurrentSession(): void
    {
        try {
            Database::connection()->prepare(
                'UPDATE user_sessions SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE session_hash=?'
            )->execute([self::currentSessionHash()]);
        } catch (\Throwable) {
        }
    }

    private static function currentSessionHash(): string
    {
        $key = Env::get('APP_KEY', '') ?: 'local-session-key';
        return hash_hmac('sha256', session_id(), $key);
    }

    private function __construct()
    {
    }
}
