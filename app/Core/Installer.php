<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Installer
{
    /**
     * @return array<int,array{label:string,ok:bool,detail:string}>
     */
    public static function preflight(): array
    {
        $documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $publicRoot = realpath(base_path('public')) ?: base_path('public');
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $httpsOk = Security::isHttps() || Security::isLocalHost($host);

        $directoryChecks = self::prepareWritableDirectories();
        $storageOk = $directoryChecks['storage'] ?? false;
        $uploadsOk = $directoryChecks['uploads'] ?? false;
        $runtimeOk = !in_array(false, $directoryChecks, true);

        $gdOk = extension_loaded('gd') && function_exists('gd_info');
        $documentRootOk = $documentRoot !== '' && realpath($publicRoot) !== false && $documentRoot === realpath($publicRoot);

        return [
            self::item('PHP 8.5+', version_compare(PHP_VERSION, '8.5.0', '>='), PHP_VERSION),
            self::item('PDO MySQL', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'Available' : 'Missing'),
            self::item('mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Available' : 'Missing'),
            self::item('intl', extension_loaded('intl'), extension_loaded('intl') ? 'Available' : 'Missing'),
            self::item('GD image support', $gdOk, $gdOk ? 'Available' : 'Required for Media Library image processing'),
            self::item('OpenSSL', extension_loaded('openssl'), extension_loaded('openssl') ? 'Available' : 'Missing'),
            self::item('DOM', class_exists(\DOMDocument::class), class_exists(\DOMDocument::class) ? 'Available' : 'Missing'),
            self::item('ZipArchive', class_exists(\ZipArchive::class), class_exists(\ZipArchive::class) ? 'Available' : 'Missing'),
            self::item('Stream sockets', function_exists('stream_socket_client'), function_exists('stream_socket_client') ? 'Available' : 'Missing'),
            self::item('Secure random', function_exists('random_bytes'), function_exists('random_bytes') ? 'Available' : 'Missing'),
            self::item('File uploads', filter_var((string)ini_get('file_uploads'), FILTER_VALIDATE_BOOL), filter_var((string)ini_get('file_uploads'), FILTER_VALIDATE_BOOL) ? 'Enabled' : 'Enable file_uploads in PHP'),
            self::item('PHP sessions', PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE, PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE ? 'Available' : 'Session storage is unavailable'),
            self::item('Private storage writable', $storageOk, $storageOk ? base_path('storage') : 'Talvoro must be able to create and write the storage/ directory'),
            self::item('Public uploads writable', $uploadsOk, $uploadsOk ? base_path('public/uploads') : 'Talvoro must be able to create and write public/uploads/'),
            self::item('Runtime directories ready', $runtimeOk, $runtimeOk ? 'Cache, logs, sessions, backups, updates, themes and media directories are writable' : 'One or more Talvoro runtime directories could not be prepared'),
            self::item('HTTPS', $httpsOk, $httpsOk ? 'Secure or local development host' : 'HTTPS is required for production installation'),
            self::item(
                'Document root',
                $documentRootOk,
                $documentRootOk
                    ? (realpath($publicRoot) ?: $publicRoot)
                    : 'Point this domain to ' . $publicRoot . ' (the Talvoro public/ directory)'
            ),
        ];
    }

    public static function canContinue(): bool
    {
        foreach (self::preflight() as $item) {
            if (!$item['ok']) {
                return false;
            }
        }
        return true;
    }

    /** @return array{db_host:string,db_port:string,db_database:string,db_username:string} */
    public static function databaseDefaults(): array
    {
        return [
            'db_host' => trim((string)Env::get('DB_HOST', 'localhost')) ?: 'localhost',
            'db_port' => trim((string)Env::get('DB_PORT', '3306')) ?: '3306',
            'db_database' => trim((string)Env::get('DB_DATABASE', '')),
            'db_username' => trim((string)Env::get('DB_USERNAME', '')),
        ];
    }

    public static function testDatabase(array $input): array
    {
        $config = self::databaseConfig($input);
        $pdo = self::connect($config);

        // Verify the server responds before checking for an existing Talvoro installation.
        $pdo->query('SELECT 1')->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema=? AND table_name IN
             ('users','schema_migrations','cms_settings','posts','pages')"
        );
        $stmt->execute([$config['DB_DATABASE']]);
        if ((int)$stmt->fetchColumn() > 0) {
            throw new RuntimeException('This database already contains Talvoro tables. Use the updater/recovery flow instead of the installer.');
        }
        return $config;
    }

    public static function storeDatabaseStep(array $config): void
    {
        $_SESSION['_install_db'] = $config;
        $_SESSION['_install_db_expires'] = time() + 1800;
    }

    public static function databaseStep(): array
    {
        if ((int)($_SESSION['_install_db_expires'] ?? 0) < time()) {
            unset($_SESSION['_install_db'], $_SESSION['_install_db_expires']);
            return [];
        }
        $value = $_SESSION['_install_db'] ?? [];
        return is_array($value) ? $value : [];
    }

    public static function install(array $input): int
    {
        if (InstallState::isInstalled()) {
            throw new RuntimeException('Talvoro is already installed.');
        }
        if (!self::canContinue()) {
            throw new RuntimeException('Server requirements changed or are no longer satisfied. Return to Server readiness and correct the failed checks.');
        }

        $dbConfig = self::databaseStep();
        if (!$dbConfig && InstallState::isPending()) {
            $pending = BootstrapConfig::read(BootstrapConfig::pendingPath());
            foreach (['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $key) {
                if (isset($pending[$key])) {
                    $dbConfig[$key] = $pending[$key];
                }
            }
        }
        if (!$dbConfig) {
            throw new RuntimeException('Database setup expired. Test the database connection again.');
        }

        $name = trim((string)($input['site_name'] ?? 'My Website'));
        $url = rtrim(trim((string)($input['app_url'] ?? '')), '/');
        $timezone = trim((string)($input['timezone'] ?? 'Europe/Ljubljana'));
        $adminName = trim((string)($input['admin_name'] ?? ''));
        $adminEmail = mb_strtolower(trim((string)($input['admin_email'] ?? '')));
        $adminPath = AdminPath::normalize((string)($input['admin_path'] ?? AdminPath::DEFAULT));
        $password = (string)($input['admin_password'] ?? '');
        $confirm = (string)($input['admin_password_confirm'] ?? '');

        $errors = [];
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $errors[] = 'Site name must be 2–120 characters.';
        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) $errors[] = 'Enter a valid http/https website URL.';
        $urlParts = parse_url($url);
        $host = (string)($urlParts['host'] ?? '');
        $pathPart = (string)($urlParts['path'] ?? '');
        if ($pathPart !== '' && $pathPart !== '/') $errors[] = 'Install Talvoro at the domain root; the website URL must not contain a path.';
        if (!str_starts_with($url, 'https://') && !Security::isLocalHost($host)) $errors[] = 'Production installations require HTTPS.';
        try { new \DateTimeZone($timezone); } catch (\Throwable) { $errors[] = 'Choose a valid timezone.'; }
        if (mb_strlen($adminName) < 2 || mb_strlen($adminName) > 120) $errors[] = 'Super Administrator name must be 2–120 characters.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid Super Administrator email.';
        if ($password !== $confirm) $errors[] = 'Super Administrator passwords do not match.';
        $errors = array_merge($errors, AdminPath::validate($adminPath, false));
        $errors = array_merge($errors, PasswordPolicy::validate($password, $adminEmail, $adminName));
        if ($errors) {
            throw new RuntimeException(implode(' ', array_values(array_unique($errors))));
        }

        // Existing deployments may intentionally inject APP_KEY from the process
        // environment. Preserve a strong injected key; otherwise the browser installer
        // owns generation and stores it in protected storage/config.php.
        $runtimeKey = trim((string)Env::get('APP_KEY', ''));
        $appKey = strlen($runtimeKey) >= 32
            ? $runtimeKey
            : 'base64:' . base64_encode(random_bytes(48));

        $config = array_merge($dbConfig, [
            'INSTALL_STATE' => 'pending',
            'APP_NAME' => $name,
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_KEY' => $appKey,
            'APP_URL' => $url,
            'APP_TIMEZONE' => $timezone,
            'SESSION_LIFETIME' => '120',
            'ANALYTICS_ENABLED' => 'true',
        ]);

        BootstrapConfig::write($config, true);
        Env::loadConfig(BootstrapConfig::pendingPath(), true);
        Database::reset();
        $pdo = Database::connection();
        Migrator::run($pdo);

        $existing = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $adminId = 0;

        if ($existing > 0) {
            // Recovery path: configuration/migrations completed but final installer
            // locking was interrupted. Only the exact first Super Administrator may resume.
            $stmt = $pdo->prepare(
                "SELECT u.id,u.email,r.name role_name
                 FROM users u JOIN roles r ON r.id=u.role_id
                 ORDER BY u.id"
            );
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (
                count($users) !== 1
                || ($users[0]['role_name'] ?? '') !== 'super_administrator'
                || !hash_equals(mb_strtolower((string)$users[0]['email']), $adminEmail)
            ) {
                throw new RuntimeException('Installation stopped because unexpected user accounts already exist.');
            }
            $adminId = (int)$users[0]['id'];
        } else {
            $roleId = (int)$pdo->query("SELECT id FROM roles WHERE name='super_administrator' LIMIT 1")->fetchColumn();
            if ($roleId < 1) {
                throw new RuntimeException('Super Administrator role was not created by migrations.');
            }

            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO users
                     (email,password_hash,display_name,role_id,status,must_change_password,mfa_enabled,created_at,updated_at)
                     VALUES (?,?,?,?,'active',0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP())"
                );
                $stmt->execute([
                    $adminEmail,
                    password_hash($password, PASSWORD_DEFAULT),
                    $adminName,
                    $roleId,
                ]);
                $adminId = (int)$pdo->lastInsertId();

                Settings::set('site.mode', 'live', $adminId);
                Settings::set('frontend.theme', 'trenlume-light', $adminId);
                Settings::set('blog.enabled', '1', $adminId);
                Settings::set('mail.from_name', $name, $adminId);

                $audit = $pdo->prepare(
                    'INSERT INTO audit_log (user_id,action,target_type,target_id,meta_json,created_at)
                     VALUES (?,?,?,?,?,UTC_TIMESTAMP())'
                );
                $audit->execute([$adminId, 'system.install.completed', 'system', null, json_encode(['version' => app_version()], JSON_UNESCAPED_SLASHES)]);
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }

        if (Pages::ensureHomePage($adminId) < 1) {
            throw new RuntimeException('Talvoro could not create the default Home page.');
        }
        AdminPath::set($adminPath, $adminId);

        // Finalization remains recoverable: pending config stays in place until both
        // the final config and installer lock have been written successfully.
        BootstrapConfig::write(array_merge($config, ['INSTALL_STATE' => 'installed']), false);
        try {
            InstallState::lock();
        } catch (\Throwable $e) {
            @unlink(BootstrapConfig::path());
            throw $e;
        }
        @unlink(BootstrapConfig::pendingPath());

        unset($_SESSION['_install_db'], $_SESSION['_install_db_expires']);
        session_regenerate_id(true);
        return $adminId;
    }

    public static function defaults(): array
    {
        $origin = Security::currentOrigin();
        if (empty($_SESSION['_install_admin_path'])) {
            $_SESSION['_install_admin_path'] = AdminPath::generate();
        }
        return [
            'site_name' => 'My Website',
            'app_url' => $origin,
            'timezone' => 'Europe/Ljubljana',
            'admin_name' => '',
            'admin_email' => '',
            'admin_path' => (string)$_SESSION['_install_admin_path'],
        ];
    }

    /** @return array<int,mixed> */
    public static function databasePdoOptions(): array
    {
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ];
        if (PHP_VERSION_ID >= 80500 && class_exists('Pdo\\Mysql') && defined('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')) {
            $options[constant('Pdo\\Mysql::ATTR_MULTI_STATEMENTS')] = false;
        } elseif (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
            // Compatibility for supported runtimes where the namespaced PDO MySQL
            // constants are not yet available.
            $options[constant('PDO::MYSQL_ATTR_MULTI_STATEMENTS')] = false;
        }
        return $options;
    }

    /** @return array<string,string> */
    public static function requiredWritableDirectories(): array
    {
        return [
            'storage' => base_path('storage'),
            'storage_cache' => base_path('storage/cache'),
            'storage_logs' => base_path('storage/logs'),
            'storage_sessions' => base_path('storage/sessions'),
            'storage_backups' => base_path('storage/backups'),
            'storage_theme_imports' => base_path('storage/theme-imports'),
            'storage_update' => base_path('storage/update'),
            'uploads' => base_path('public/uploads'),
            'uploads_site' => base_path('public/uploads/site'),
            'uploads_themes' => base_path('public/uploads/themes'),
        ];
    }

    private static function databaseConfig(array $input): array
    {
        $host = trim((string)($input['db_host'] ?? 'localhost'));
        $port = trim((string)($input['db_port'] ?? '3306'));
        $database = trim((string)($input['db_database'] ?? ''));
        $username = trim((string)($input['db_username'] ?? ''));
        $password = (string)($input['db_password'] ?? '');

        if ($host === '' || strlen($host) > 255 || preg_match('/[;\x00-\x1F\x7F]/', $host)) {
            throw new RuntimeException('Database host is invalid.');
        }
        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
            throw new RuntimeException('Database port is invalid.');
        }
        if (!preg_match('/^[A-Za-z0-9_$-]{1,64}$/', $database)) {
            throw new RuntimeException('Database name contains unsupported characters.');
        }
        if ($username === '' || strlen($username) > 128 || preg_match('/[\x00-\x1F\x7F]/', $username)) {
            throw new RuntimeException('Database username is invalid.');
        }
        if ($password === '' || strlen($password) > 512 || str_contains($password, "\0")) {
            throw new RuntimeException('Database password is required.');
        }

        return [
            'DB_HOST' => $host,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ];
    }

    private static function connect(array $config): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            (int)$config['DB_PORT'],
            $config['DB_DATABASE']
        );
        return new PDO($dsn, $config['DB_USERNAME'], $config['DB_PASSWORD'], self::databasePdoOptions());
    }

    /** @return array<string,bool> */
    private static function prepareWritableDirectories(): array
    {
        $checks = [];
        foreach (self::requiredWritableDirectories() as $key => $path) {
            $mode = str_starts_with($key, 'uploads') ? 0755 : 0750;
            $checks[$key] = self::prepareWritableDirectory($path, $mode);
        }
        return $checks;
    }

    private static function prepareWritableDirectory(string $path, int $mode): bool
    {
        if (!is_dir($path) && !@mkdir($path, $mode, true) && !is_dir($path)) {
            return false;
        }
        if (!is_writable($path)) {
            return false;
        }

        $probe = rtrim($path, '/\\') . '/.talvoro-write-test-' . bin2hex(random_bytes(6));
        $written = @file_put_contents($probe, 'ok', LOCK_EX);
        if ($written === false) {
            return false;
        }
        @unlink($probe);
        return true;
    }

    private static function item(string $label, bool $ok, string $detail): array
    {
        return compact('label','ok','detail');
    }

    private function __construct() {}
}
