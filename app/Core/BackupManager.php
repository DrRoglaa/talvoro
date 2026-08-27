<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class BackupManager
{
    public static function create(string $label): array
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $label) ?: 'backup';
        $dir = base_path('storage/backups/' . gmdate('Ymd-His') . '-' . trim($safe, '-'));
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Could not create backup directory.');
        @chmod($dir, 0750);

        $database = $dir . '/database.sql';
        self::dumpDatabase($database);
        $databaseSize = filesize($database);
        $databaseHash = hash_file('sha256', $database);
        if ($databaseSize === false || !is_string($databaseHash) || $databaseHash === '') {
            throw new RuntimeException('Could not verify the database backup.');
        }
        $meta = [
            'created_at' => gmdate(DATE_ATOM),
            'version' => app_version(),
            'database' => basename($database),
            'database_size' => $databaseSize,
            'database_sha256' => $databaseHash,
        ];
        file_put_contents($dir . '/backup.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX);
        return ['path' => $dir, 'database' => $database];
    }

    public static function backupFiles(array $relativeFiles, string $backupDir): void
    {
        $base = realpath(base_path()) ?: base_path();
        foreach ($relativeFiles as $relative) {
            $relative = ltrim(str_replace('\\', '/', (string)$relative), '/');
            if ($relative === '' || str_contains($relative, '..')) continue;
            $source = base_path($relative);
            if (!is_file($source)) continue;
            $target = rtrim($backupDir, '/') . '/files/' . $relative;
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Could not create file backup directory.');
            }
            if (!copy($source, $target)) throw new RuntimeException('Could not back up ' . $relative);
        }
    }

    public static function restoreFiles(string $backupDir): int
    {
        $filesDir = rtrim($backupDir, '/') . '/files';
        if (!is_dir($filesDir)) throw new RuntimeException('File backup is unavailable.');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filesDir, \FilesystemIterator::SKIP_DOTS));
        $count = 0;
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $relative = substr($file->getPathname(), strlen($filesDir) + 1);
            $target = base_path($relative);
            if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0750, true) && !is_dir(dirname($target))) {
                throw new RuntimeException('Could not restore directory for ' . $relative);
            }
            if (!copy($file->getPathname(), $target)) throw new RuntimeException('Could not restore ' . $relative);
            $count++;
        }
        return $count;
    }


    /**
     * Restore the complete database snapshot created by Talvoro.
     * Current tables are removed first so tables introduced by a failed migration
     * cannot survive a rollback and leave code/schema versions mismatched.
     */
    public static function restoreDatabase(string $backupDir): int
    {
        $sqlFile = rtrim($backupDir, '/') . '/database.sql';
        $metaFile = rtrim($backupDir, '/') . '/backup.json';
        if (!is_file($sqlFile) || !is_readable($sqlFile)) throw new RuntimeException('Database backup is unavailable.');
        if (!is_file($metaFile)) throw new RuntimeException('Backup metadata is unavailable.');
        $meta = json_decode((string)file_get_contents($metaFile), true);
        if (!is_array($meta) || (string)($meta['database'] ?? '') !== 'database.sql') {
            throw new RuntimeException('Backup metadata is invalid.');
        }
        $size = filesize($sqlFile);
        if ($size === false || $size < 20 || $size > 1_000_000_000) throw new RuntimeException('Database backup size is invalid.');
        if (isset($meta['database_size']) && (int)$meta['database_size'] !== $size) {
            throw new RuntimeException('Database backup size does not match its metadata.');
        }
        $expectedHash = strtolower(trim((string)($meta['database_sha256'] ?? '')));
        if ($expectedHash !== '') {
            if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) throw new RuntimeException('Database backup checksum metadata is invalid.');
            $actualHash = hash_file('sha256', $sqlFile);
            if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException('Database backup checksum verification failed.');
            }
        }
        $sql = file_get_contents($sqlFile);
        if (!is_string($sql) || !str_starts_with($sql, 'SET NAMES utf8mb4;')) throw new RuntimeException('Database backup format is invalid.');
        $statements = self::splitSqlStatements($sql);
        if (count($statements) < 3) throw new RuntimeException('Database backup appears incomplete.');
        $normalized = array_map(static fn(string $statement): string => strtoupper(trim($statement)), $statements);
        if (!str_starts_with($normalized[0] ?? '', 'SET NAMES UTF8MB4')
            || !in_array('SET FOREIGN_KEY_CHECKS=0', $normalized, true)
            || !in_array('SET FOREIGN_KEY_CHECKS=1', $normalized, true)
            || count(array_filter($normalized, static fn(string $statement): bool => str_starts_with($statement, 'CREATE TABLE '))) < 1) {
            throw new RuntimeException('Database backup structure is incomplete.');
        }

        $db = Database::connection();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $table = (string)$table;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
                $db->exec('DROP TABLE IF EXISTS `' . $table . '`');
            }
            $count = 0;
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement === '') continue;
                $db->exec($statement);
                $count++;
            }
            return $count;
        } finally {
            try { $db->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (\Throwable) {}
            Database::reset();
        }
    }

    /** @return list<string> */
    private static function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $quote = null;
        $escaped = false;
        $len = strlen($sql);
        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $buffer .= $ch;
            if ($quote !== null) {
                if ($escaped) { $escaped = false; continue; }
                if ($ch === '\\') { $escaped = true; continue; }
                if ($ch === $quote) {
                    // SQL represents an embedded quote by doubling it.
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') { $quote = $ch; continue; }
            if ($ch === ';') {
                $statements[] = trim(substr($buffer, 0, -1));
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') $statements[] = trim($buffer);
        return array_values(array_filter($statements, static fn(string $s): bool => $s !== ''));
    }

    private static function dumpDatabase(string $target): void
    {
        $db = Database::connection();
        $fh = fopen($target, 'wb');
        if (!$fh) throw new RuntimeException('Could not create database backup.');
        try {
            fwrite($fh, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $table = (string)$table;
                if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) continue;
                $create = $db->query('SHOW CREATE TABLE `' . $table . '`')->fetch(PDO::FETCH_NUM);
                if (!$create || !isset($create[1])) continue;
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n" . $create[1] . ";\n\n");
                $stmt = $db->query('SELECT * FROM `' . $table . '`', PDO::FETCH_ASSOC);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $columns = array_map(static fn(string $c): string => '`' . str_replace('`', '``', $c) . '`', array_keys($row));
                    $values = [];
                    foreach ($row as $value) $values[] = $value === null ? 'NULL' : $db->quote((string)$value);
                    fwrite($fh, 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n");
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        } finally {
            fclose($fh);
        }
        @chmod($target, 0600);
    }

    private function __construct() {}
}
