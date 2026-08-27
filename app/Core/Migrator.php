<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Migrator
{
    public static function run(?PDO $db = null): array
    {
        $db ??= Database::connection();
        $db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
        $applied = $db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(base_path('database/migrations/*.sql')) ?: [];
        sort($files, SORT_STRING);
        $result = [];

        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $applied, true)) {
                $result[] = ['migration' => $name, 'status' => 'skip'];
                continue;
            }

            $sql = file_get_contents($file);
            if (!is_string($sql) || trim($sql) === '') {
                throw new RuntimeException('Migration is empty: ' . $name);
            }

            $statements = self::splitStatements($sql);
            if ($statements === []) {
                throw new RuntimeException('Migration has no executable statements: ' . $name);
            }

            foreach ($statements as $index => $statement) {
                try {
                    $db->exec($statement);
                } catch (\Throwable $e) {
                    throw new RuntimeException(
                        sprintf('Migration %s failed at statement %d: %s', $name, $index + 1, $e->getMessage()),
                        0,
                        $e
                    );
                }
            }

            $stmt = $db->prepare('INSERT INTO schema_migrations (migration,applied_at) VALUES (?,UTC_TIMESTAMP())');
            $stmt->execute([$name]);
            $result[] = ['migration' => $name, 'status' => 'apply'];
        }

        return $result;
    }

    public static function pending(): array
    {
        $files = array_map('basename', glob(base_path('database/migrations/*.sql')) ?: []);
        sort($files, SORT_STRING);
        try {
            $applied = Database::connection()->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return $files;
        }
        return array_values(array_diff($files, $applied));
    }

    /**
     * Split trusted migration files into individual SQL statements while keeping
     * MYSQL_ATTR_MULTI_STATEMENTS disabled on the connection. This deliberately
     * supports ordinary DDL/DML migrations and rejects DELIMITER directives/
     * stored-program style migration files.
     *
     * @return list<string>
     */
    private static function splitStatements(string $sql): array
    {
        if (preg_match('/^\s*DELIMITER\b/im', $sql)) {
            throw new RuntimeException('DELIMITER directives are not supported in migration files.');
        }

        $statements = [];
        $buffer = '';
        $length = strlen($sql);
        $quote = null;
        $lineComment = false;
        $blockComment = false;

        for ($i = 0; $i < $length; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($lineComment) {
                if ($ch === "\n" || $ch === "\r") {
                    $lineComment = false;
                    $buffer .= ' ';
                }
                continue;
            }

            if ($blockComment) {
                if ($ch === '*' && $next === '/') {
                    $blockComment = false;
                    $i++;
                    $buffer .= ' ';
                }
                continue;
            }

            if ($quote !== null) {
                $buffer .= $ch;

                if ($ch === '\\' && $quote !== '`' && $i + 1 < $length) {
                    $buffer .= $sql[++$i];
                    continue;
                }

                if ($ch === $quote) {
                    if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                        $buffer .= $sql[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }

            if (($ch === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $ch === '#') {
                $lineComment = true;
                if ($ch === '-') $i++;
                continue;
            }

            if ($ch === '/' && $next === '*') {
                $blockComment = true;
                $i++;
                continue;
            }

            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buffer .= $ch;
                continue;
            }

            if ($ch === ';') {
                $statement = trim($buffer);
                if ($statement !== '') $statements[] = $statement;
                $buffer = '';
                continue;
            }

            $buffer .= $ch;
        }

        if ($quote !== null || $blockComment) {
            throw new RuntimeException('Migration contains an unterminated quoted string or comment.');
        }

        $statement = trim($buffer);
        if ($statement !== '') $statements[] = $statement;

        return $statements;
    }

    private function __construct() {}
}
