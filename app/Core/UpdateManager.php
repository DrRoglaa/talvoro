<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;
use ZipArchive;

final class UpdateManager
{
    private const MAX_ZIP = 15_000_000;
    private const MAX_FILES = 1200;
    private const MAX_UNCOMPRESSED = 120_000_000;
    private const PACKAGE_ROOTS = ['talvoro', 'privacy-cms'];
    private const PRODUCT_IDS = ['talvoro', 'privacy-cms'];

    public static function updateLockPath(): string { return base_path('storage/update/update.lock'); }

    public static function isLocked(): bool { return is_file(self::updateLockPath()); }

    public static function lockData(): array
    {
        if (!self::isLocked()) return [];
        $data = json_decode((string)file_get_contents(self::updateLockPath()), true);
        return is_array($data) ? $data : [];
    }

    public static function validateUpload(array $file): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('PHP Zip extension is required for updates.');
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Update upload failed.');
        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_ZIP) throw new RuntimeException('Update ZIP is too large.');
        $tmp = (string)($file['tmp_name'] ?? '');
        if ((PHP_SAPI === 'cli' && !is_file($tmp)) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) throw new RuntimeException('Update upload could not be verified.');
        return self::validatePackage($tmp);
    }

    public static function stage(array $file): array
    {
        $manifest = self::validateUpload($file);
        $stage = base_path('storage/update/staged-' . bin2hex(random_bytes(8)));
        if (!mkdir($stage, 0750, true) && !is_dir($stage)) throw new RuntimeException('Could not create update staging directory.');
        $zip = new ZipArchive();
        if ($zip->open((string)$file['tmp_name']) !== true) throw new RuntimeException('Could not open update ZIP.');
        try {
            if (!$zip->extractTo($stage)) throw new RuntimeException('Could not extract staged update.');
        } finally { $zip->close(); }
        $_SESSION['_update_stage'] = $stage;
        $_SESSION['_update_manifest'] = $manifest;
        $_SESSION['_update_expires'] = time() + 1800;
        return $manifest;
    }

    public static function staged(): array
    {
        if ((int)($_SESSION['_update_expires'] ?? 0) < time()) return [];
        $stage = (string)($_SESSION['_update_stage'] ?? '');
        $manifest = $_SESSION['_update_manifest'] ?? [];
        if ($stage === '' || !is_dir($stage) || !is_array($manifest)) return [];
        return ['path' => $stage, 'manifest' => $manifest];
    }

    public static function apply(int $userId): array
    {
        if (self::isLocked()) throw new RuntimeException('Another update or recovery operation is already active.');
        $staged = self::staged();
        if (!$staged) throw new RuntimeException('Staged update expired. Upload the package again.');
        $manifest = $staged['manifest'];
        $stage = $staged['path'];
        $root = self::manifestRoot($manifest);

        foreach (($manifest['files'] ?? []) as $relative => $expectedHash) {
            $source = $stage . '/' . $root . '/' . $relative;
            if (!is_file($source) || !hash_equals((string)$expectedHash, hash_file('sha256', $source))) {
                throw new RuntimeException('Staged update integrity check failed for ' . $relative);
            }
        }

        $from = app_version();
        $to = (string)$manifest['version'];
        $backup = BackupManager::create('pre-update-' . $from . '-to-' . $to);
        $files = array_keys($manifest['files']);
        $managedFiles = array_values(array_unique(array_merge($files, ['release.json'])));
        $newFiles = array_values(array_filter($managedFiles, static fn(string $relative): bool => !is_file(base_path($relative))));
        BackupManager::backupFiles($managedFiles, $backup['path']);

        $lock = [
            'from' => $from,
            'to' => $to,
            'backup' => $backup['path'],
            'stage' => $stage,
            'started_at' => gmdate(DATE_ATOM),
            'started_by' => $userId,
            'new_files' => $newFiles,
            'status' => 'applying',
        ];
        self::writeLock($lock);

        $db = Database::connection();
        $stmt = $db->prepare(
            "INSERT INTO system_updates
             (from_version,to_version,status,backup_path,started_by,started_at)
             VALUES (?,?, 'applying', ?, ?, UTC_TIMESTAMP())"
        );
        $stmt->execute([$from, $to, $backup['path'], $userId]);
        $updateId = (int)$db->lastInsertId();

        try {
            foreach ($files as $relative) {
                $source = $stage . '/' . $root . '/' . $relative;
                if (!is_file($source)) throw new RuntimeException('Staged update file is missing: ' . $relative);
                $target = base_path($relative);
                if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) throw new RuntimeException('Could not create target directory.');
                $tmp = $target . '.update-' . bin2hex(random_bytes(4));
                if (!copy($source, $tmp)) throw new RuntimeException('Could not stage replacement for ' . $relative);
                @chmod($tmp, file_exists($target) ? (fileperms($target) & 0777) : 0644);
                if (!rename($tmp, $target)) { @unlink($tmp); throw new RuntimeException('Could not replace ' . $relative); }
            }

            $releaseSource = $stage . '/' . $root . '/release.json';
            if (!is_file($releaseSource)) throw new RuntimeException('Staged release manifest is missing.');
            $releaseTarget = base_path('release.json');
            $releaseTmp = $releaseTarget . '.update-' . bin2hex(random_bytes(4));
            if (!copy($releaseSource, $releaseTmp) || !rename($releaseTmp, $releaseTarget)) {
                @unlink($releaseTmp);
                throw new RuntimeException('Could not activate release manifest.');
            }

            Database::reset();
            $migrations = Migrator::run();
            $db = Database::connection();
            $db->prepare("UPDATE system_updates SET status='completed',completed_at=UTC_TIMESTAMP(),details_json=? WHERE id=?")
                ->execute([json_encode(['migrations' => $migrations], JSON_UNESCAPED_SLASHES), $updateId]);
            @unlink(self::updateLockPath());
            self::clearStage();
            return ['from' => $from, 'to' => $to, 'migrations' => $migrations, 'backup' => $backup['path']];
        } catch (\Throwable $e) {
            $lock['status'] = 'failed';
            $lock['error'] = mb_substr($e->getMessage(), 0, 1000);
            self::writeLock($lock);
            try {
                Database::connection()->prepare("UPDATE system_updates SET status='failed',details_json=? WHERE id=?")
                    ->execute([json_encode(['error' => $lock['error']], JSON_UNESCAPED_SLASHES), $updateId]);
            } catch (\Throwable) {}

            // From Talvoro 0.11 onward a failed update attempts a complete rollback.
            // Database and application code return to the same pre-update snapshot.
            try {
                $rollback = self::restoreRecovery();
                throw new RuntimeException(
                    'Update failed and Talvoro automatically restored the pre-update database and application files. Original error: ' . $e->getMessage(),
                    0,
                    $e
                );
            } catch (RuntimeException $rollbackError) {
                if ($rollbackError->getPrevious() === $e) throw $rollbackError;
                $lock['status'] = 'rollback_failed';
                $lock['rollback_error'] = mb_substr($rollbackError->getMessage(), 0, 1000);
                self::writeLock($lock);
                throw new RuntimeException(
                    'Update failed and automatic rollback could not complete. Use System recovery. Update error: ' . $e->getMessage() . ' Rollback error: ' . $rollbackError->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /** @return array{files:int,database_statements:int,backup:string} */
    public static function restoreRecovery(): array
    {
        $lock = self::lockData();
        $backup = (string)($lock['backup'] ?? '');
        if ($backup === '') throw new RuntimeException('Recovery backup is unavailable.');

        // Restore the database first. The currently loaded PHP request can then
        // finish restoring files even after the on-disk code returns to the old release.
        $databaseStatements = BackupManager::restoreDatabase($backup);
        $fileCount = BackupManager::restoreFiles($backup);
        foreach (($lock['new_files'] ?? []) as $relative) {
            if (!is_string($relative) || !self::safeRelativeFile($relative)) continue;
            $target = base_path($relative);
            if (is_file($target)) @unlink($target);
        }
        @unlink(self::updateLockPath());
        self::clearStage();
        return ['files' => $fileCount, 'database_statements' => $databaseStatements, 'backup' => $backup];
    }

    /** @deprecated Use restoreRecovery() so database and code are never recovered independently. */
    public static function restoreFiles(): int
    {
        return self::restoreRecovery()['files'];
    }

    public static function validatePackage(string $zipPath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new RuntimeException('Update ZIP could not be opened.');
        try {
            if ($zip->numFiles < 1 || $zip->numFiles > self::MAX_FILES) throw new RuntimeException('Update package contains an invalid number of files.');
            $uncompressed = 0;
            for ($i=0; $i<$zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = (string)($stat['name'] ?? '');
                $uncompressed += (int)($stat['size'] ?? 0);
                if ($uncompressed > self::MAX_UNCOMPRESSED) throw new RuntimeException('Update package expands beyond the allowed size.');
                if (!self::safeZipPath($name)) throw new RuntimeException('Update package contains an unsafe path.');
                $attrs = (int)($stat['external_attributes'] ?? 0);
                $mode = ($attrs >> 16) & 0170000;
                if ($mode === 0120000) throw new RuntimeException('Update package may not contain symbolic links.');
            }

            [$root, $raw] = self::locateManifest($zip);
            $manifest = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            $product = is_array($manifest) ? (string)($manifest['product'] ?? '') : '';
            if (!is_array($manifest) || !in_array($product, self::PRODUCT_IDS, true)) throw new RuntimeException('This is not a Talvoro update package.');
            if ((int)($manifest['package_format'] ?? 0) !== 1) throw new RuntimeException('Unsupported Talvoro update package format.');
            $version = (string)($manifest['version'] ?? '');
            $minimum = (string)($manifest['minimum_version'] ?? '0.0.0');
            if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) throw new RuntimeException('Update version is invalid.');
            if (version_compare($version, app_version(), '<=')) throw new RuntimeException('Update version must be newer than the installed version.');
            if (version_compare(app_version(), $minimum, '<')) throw new RuntimeException('This update requires Talvoro ' . $minimum . ' or newer.');
            $files = $manifest['files'] ?? null;
            if (!is_array($files) || !$files) throw new RuntimeException('Update manifest has no files.');

            foreach ($files as $relative => $expectedHash) {
                if (!is_string($relative) || !self::safeRelativeFile($relative)) throw new RuntimeException('Manifest contains an unsafe file path.');
                if (!is_string($expectedHash) || !preg_match('/^[a-f0-9]{64}$/', $expectedHash)) throw new RuntimeException('Manifest contains an invalid file hash.');
                $contents = $zip->getFromName($root . '/' . $relative);
                if (!is_string($contents)) throw new RuntimeException('Manifest file missing from ZIP: ' . $relative);
                if (!hash_equals($expectedHash, hash('sha256', $contents))) throw new RuntimeException('Checksum mismatch for ' . $relative);
            }

            for ($i=0; $i<$zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if ($name === '' || str_ends_with($name, '/')) continue;
                if (!str_starts_with($name, $root . '/')) throw new RuntimeException('Update ZIP mixes multiple package roots.');
                if ($name === $root . '/release.json') continue;
                $relative = substr($name, strlen($root . '/'));
                if (str_starts_with($relative, 'storage/') || str_starts_with($relative, 'public/uploads/')) continue;
                if (!array_key_exists($relative, $files)) throw new RuntimeException('Update ZIP contains an unmanifested file: ' . $relative);
            }

            return [
                'product' => $product,
                'version' => $version,
                'minimum_version' => $minimum,
                'files' => $files,
                'package_root' => $root,
            ];
        } catch (\JsonException) {
            throw new RuntimeException('release.json is invalid JSON.');
        } finally { $zip->close(); }
    }

    /** @return array{0:string,1:string} */
    private static function locateManifest(ZipArchive $zip): array
    {
        foreach (self::PACKAGE_ROOTS as $root) {
            $raw = $zip->getFromName($root . '/release.json');
            if (is_string($raw)) return [$root, $raw];
        }
        throw new RuntimeException('Update package is missing release.json.');
    }

    private static function manifestRoot(array $manifest): string
    {
        $root = (string)($manifest['package_root'] ?? '');
        return in_array($root, self::PACKAGE_ROOTS, true) ? $root : 'privacy-cms';
    }

    private static function safeZipPath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '\\')) return false;
        $validRoot = false;
        foreach (self::PACKAGE_ROOTS as $root) {
            if (str_starts_with($name, $root . '/')) {
                $validRoot = true;
                break;
            }
        }
        if (!$validRoot) return false;
        foreach (explode('/', $name) as $part) if ($part === '..') return false;
        return true;
    }

    private static function safeRelativeFile(string $relative): bool
    {
        $relative = str_replace('\\','/',$relative);
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, "\0")) return false;
        foreach (explode('/', $relative) as $part) if ($part === '..') return false;
        $protected = ['.env'];
        if (in_array($relative, $protected, true)
            || str_starts_with($relative, 'storage/')
            || str_starts_with($relative, 'public/uploads/')) return false;
        return true;
    }

    private static function writeLock(array $data): void
    {
        $dir = dirname(self::updateLockPath());
        if (!is_dir($dir)) @mkdir($dir, 0750, true);
        file_put_contents(self::updateLockPath(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
        @chmod(self::updateLockPath(), 0600);
    }

    private static function clearStage(): void
    {
        unset($_SESSION['_update_stage'], $_SESSION['_update_manifest'], $_SESSION['_update_expires']);
    }

    private function __construct() {}
}
