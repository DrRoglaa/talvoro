<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) return self::$pdo;

        $host = (string)Env::get('DB_HOST', 'db');
        $port = (string)Env::get('DB_PORT', '3306');
        $name = (string)Env::get('DB_DATABASE', 'privacy_cms');
        $user = (string)Env::get('DB_USERNAME', 'privacy_cms');
        $pass = (string)Env::get('DB_PASSWORD', '');

        if ($pass === '') throw new RuntimeException('Database password is not configured.');
        if ($host === '' || strlen($host) > 255 || preg_match('/[;\x00-\x1F\x7F]/', $host)) throw new RuntimeException('Database host is invalid.');
        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) throw new RuntimeException('Database port is invalid.');
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $name)) throw new RuntimeException('Database name is invalid.');
        if ($user === '' || strlen($user) > 128 || preg_match('/[\x00-\x1F\x7F]/', $user)) throw new RuntimeException('Database username is invalid.');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, (int)$port, $name);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if (PHP_VERSION_ID >= 80500 && class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')) {
            $options[constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            $options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] = false;
        }
        self::$pdo = new PDO($dsn, $user, $pass, $options);
        return self::$pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    private function __construct() {}
}
