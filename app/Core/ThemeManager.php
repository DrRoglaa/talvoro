<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;
use ZipArchive;

final class ThemeManager
{
    private const MAX_PACKAGE_BYTES = 20 * 1024 * 1024;
    private const MAX_FILES = 100;
    private const MAX_UNCOMPRESSED_BYTES = 50 * 1024 * 1024;
    private const MAX_CSS_BYTES = 1_000_000;
    private const MAX_ASSET_BYTES = 12 * 1024 * 1024;
    private const MAX_IMAGE_PIXELS = 60_000_000;
    private const MAX_IMAGE_DIMENSION = 16_000;

    private const ALLOWED_IMAGE_MIMES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'avif' => ['image/avif'],
    ];

    public static function all(): array
    {
        try {
            return Database::connection()->query(
                "SELECT id,name,slug,version,author,description,is_builtin,is_active,created_at,updated_at
                 FROM themes
                 WHERE slug<>'trenlume-light'
                 ORDER BY is_active DESC,is_builtin DESC,name ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function active(): array
    {
        try {
            $row = Database::connection()->query(
                "SELECT * FROM themes WHERE is_active=1 AND slug<>'trenlume-light' ORDER BY id LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\Throwable) {
        }

        return [
            'id' => 0,
            'name' => 'Talvoro Editorial',
            'slug' => 'talvoro-editorial',
            'version' => '1.0.0',
            'author' => 'Talvoro',
            'description' => 'Warm, light editorial publishing with calm typography and self-hosted ownership.',
            'css_text' => '',
            'is_builtin' => 1,
            'is_active' => 1,
        ];
    }

    public static function css(): string
    {
        $active = self::active();
        return (string)($active['css_text'] ?? '');
    }

    public static function importLimits(): array
    {
        return [
            'package_mb' => intdiv(self::MAX_PACKAGE_BYTES, 1024 * 1024),
            'files' => self::MAX_FILES,
            'expanded_mb' => intdiv(self::MAX_UNCOMPRESSED_BYTES, 1024 * 1024),
            'asset_mb' => intdiv(self::MAX_ASSET_BYTES, 1024 * 1024),
            'extensions' => array_keys(self::ALLOWED_IMAGE_MIMES),
            'starter_kib' => intdiv(StarterManifest::MAX_BYTES, 1024),
            'starter_resources' => StarterManifest::MAX_RESOURCES,
        ];
    }

    public static function create(array $input, int $userId): int
    {
        $name = trim((string)($input['name'] ?? ''));
        $slug = self::slug((string)($input['slug'] ?? $name));
        $version = trim((string)($input['version'] ?? '1.0.0')) ?: '1.0.0';
        $author = trim((string)($input['author'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $css = trim((string)($input['css_text'] ?? ''));

        self::validate($name, $slug, $version, $css);

        $stmt = Database::connection()->prepare(
            'INSERT INTO themes
             (name,slug,version,author,description,css_text,is_builtin,is_active,created_by,created_at,updated_at)
             VALUES (?,?,?,?,?,?,0,0,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $name,
            $slug,
            $version,
            $author !== '' ? $author : null,
            $description !== '' ? $description : null,
            $css,
            $userId,
        ]);

        return (int)Database::connection()->lastInsertId();
    }

    public static function importZip(array $file, int $userId): int
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP Zip extension is required to import theme ZIP files.');
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException('Theme package exceeds the server upload limit.');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Theme upload failed.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_PACKAGE_BYTES) {
            throw new RuntimeException('Theme package must be 20 MB or smaller.');
        }

        $originalName = trim((string)($file['name'] ?? ''));
        if ($originalName !== '' && strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION)) !== 'zip') {
            throw new RuntimeException('Theme package must be a ZIP file.');
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ((PHP_SAPI === 'cli' && !is_file($tmp)) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            throw new RuntimeException('Theme upload could not be verified.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            throw new RuntimeException('Theme ZIP could not be opened.');
        }

        $staging = null;
        $createdThemeId = 0;

        try {
            $package = self::inspectPackage($zip);
            $prefix = $package['prefix'];
            $assetEntries = $package['assets'];
            $starterEntry = $package['starter'];

            $manifestRaw = $zip->getFromName($prefix . 'theme.json');
            $css = $zip->getFromName($prefix . 'style.css');
            if (!is_string($manifestRaw) || !is_string($css)) {
                throw new RuntimeException('Theme ZIP must contain theme.json and style.css.');
            }
            if (strlen($manifestRaw) > 64 * 1024) {
                throw new RuntimeException('Theme manifest is too large.');
            }
            if (strlen($css) > self::MAX_CSS_BYTES) {
                throw new RuntimeException('Theme CSS is too large.');
            }

            $manifest = json_decode($manifestRaw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($manifest)) {
                throw new RuntimeException('Theme manifest is invalid.');
            }

            $name = trim((string)($manifest['name'] ?? ''));
            $slug = self::slug((string)($manifest['slug'] ?? $name));
            $version = trim((string)($manifest['version'] ?? '1.0.0')) ?: '1.0.0';
            $author = trim((string)($manifest['author'] ?? ''));
            $description = trim((string)($manifest['description'] ?? ''));

            $availableAssets = array_fill_keys(array_keys($assetEntries), true);
            $css = self::rewriteImportedCss($css, $slug, $availableAssets);
            self::validate($name, $slug, $version, $css);

            $starterDefinition = null;
            $availableStarterAssets = [];

            if ($assetEntries !== []) {
                $finalRoot = self::themeAssetRoot($slug);
                if (file_exists($finalRoot)) {
                    throw new RuntimeException('Theme assets already exist for this slug. Delete the old theme assets before importing again.');
                }

                $staging = self::newStagingDirectory();
                $availableStarterAssets = self::extractAssets($zip, $prefix, $assetEntries, $staging);
            }

            if (is_array($starterEntry)) {
                $starterRaw = $zip->getFromIndex((int)$starterEntry['index']);
                if (!is_string($starterRaw) || strlen($starterRaw) !== (int)$starterEntry['size']) {
                    throw new RuntimeException('Theme starter manifest could not be read safely.');
                }
                $starterDefinition = StarterManifest::decodeAndValidate($starterRaw, $availableStarterAssets);
            }

            $createdThemeId = self::create([
                'name' => $name,
                'slug' => $slug,
                'version' => $version,
                'author' => $author,
                'description' => $description,
                'css_text' => $css,
            ], $userId);

            if (is_array($starterDefinition)) {
                self::persistStarterDefinition($createdThemeId, $starterDefinition);
            }

            if ($staging !== null) {
                self::installStagedAssets($staging, $slug);
                $staging = null;
            }

            return $createdThemeId;
        } catch (\JsonException) {
            throw new RuntimeException('theme.json contains invalid JSON.');
        } catch (\Throwable $e) {
            if ($createdThemeId > 0) {
                try {
                    Database::connection()->prepare('DELETE FROM themes WHERE id=?')->execute([$createdThemeId]);
                } catch (\Throwable) {
                }
            }
            if (is_string($staging) && $staging !== '') {
                self::removeDirectory($staging);
            }
            throw $e;
        } finally {
            $zip->close();
        }
    }

    public static function activate(int $id): void
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT id,slug FROM themes WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (string)$row['slug'] === 'trenlume-light') {
            throw new RuntimeException('Theme not found.');
        }

        $db->beginTransaction();
        try {
            $db->exec('UPDATE themes SET is_active=0,updated_at=UTC_TIMESTAMP() WHERE is_active=1');
            $db->prepare('UPDATE themes SET is_active=1,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deactivate(int $id): void
    {
        $stmt = Database::connection()->prepare('SELECT is_active,is_builtin FROM themes WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Theme not found.');
        }
        if ((int)$row['is_active'] !== 1) {
            return;
        }

        $builtin = Database::connection()->query(
            "SELECT id FROM themes WHERE is_builtin=1 AND slug<>'trenlume-light' ORDER BY CASE WHEN slug='talvoro-editorial' THEN 0 ELSE 1 END,id LIMIT 1"
        )->fetchColumn();
        if (!$builtin) {
            throw new RuntimeException('Default Talvoro Editorial theme is missing.');
        }

        self::activate((int)$builtin);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::connection()->prepare('SELECT slug,is_builtin,is_active FROM themes WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('Theme not found.');
        }
        if ((int)$row['is_builtin'] === 1) {
            throw new RuntimeException('Built-in Talvoro themes are protected and cannot be deleted.');
        }
        if ((int)$row['is_active'] === 1) {
            throw new RuntimeException('Deactivate the theme before deleting it.');
        }

        try {
            $starterStmt = Database::connection()->prepare("SELECT 1 FROM starter_site_installations WHERE theme_id=? AND status='installed' LIMIT 1");
            $starterStmt->execute([$id]);
            if ($starterStmt->fetchColumn()) {
                throw new RuntimeException('Remove the Starter Site before deleting this theme. Starter-owned content must be handled explicitly.');
            }
        } catch (RuntimeException $e) {
            throw $e;
        } catch (\Throwable) {
            // Upgrade compatibility: the starter tables may not exist until migration 027 is applied.
        }

        Database::connection()->prepare('DELETE FROM themes WHERE id=?')->execute([$id]);
        self::removeDirectory(self::themeAssetRoot((string)$row['slug']));
    }

    private static function inspectPackage(ZipArchive $zip): array
    {
        if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_FILES) {
            throw new RuntimeException('Theme package must contain no more than 100 files.');
        }

        $uncompressed = 0;
        $manifestCandidates = [];
        $stats = [];
        $seenZipPaths = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!is_array($stat)) {
                throw new RuntimeException('Theme package contains an unreadable ZIP entry.');
            }

            $name = (string)($stat['name'] ?? '');
            if (!self::safeZipPath($name)) {
                throw new RuntimeException('Theme package contains an unsafe path.');
            }
            $pathKey = strtolower(rtrim($name, '/'));
            if (isset($seenZipPaths[$pathKey])) {
                throw new RuntimeException('Theme package contains duplicate or case-conflicting ZIP paths.');
            }
            $seenZipPaths[$pathKey] = true;
            self::rejectSymlink($zip, $i);

            $entrySize = (int)($stat['size'] ?? 0);
            if ($entrySize < 0) {
                throw new RuntimeException('Theme package contains an invalid file size.');
            }
            $uncompressed += $entrySize;
            if ($uncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                throw new RuntimeException('Theme package expands beyond the 50 MB safety limit.');
            }

            $stats[$name] = ['index' => $i, 'size' => $entrySize];
            if (!self::ignorableMetadataPath($name) && ($name === 'theme.json' || preg_match('#^[^/]+/theme\.json$#', $name) === 1)) {
                $manifestCandidates[] = $name;
            }
        }

        if (count($manifestCandidates) !== 1) {
            throw new RuntimeException('Theme ZIP must contain exactly one theme.json at its root or inside one top-level folder.');
        }

        $manifestName = $manifestCandidates[0];
        $prefix = $manifestName === 'theme.json' ? '' : substr($manifestName, 0, -strlen('theme.json'));
        $styleName = $prefix . 'style.css';
        if (!isset($stats[$styleName])) {
            throw new RuntimeException('Theme ZIP must contain style.css next to theme.json.');
        }

        $assets = [];
        $starter = null;
        $seenRelative = [];
        foreach ($stats as $name => $stat) {
            if (self::ignorableMetadataPath($name)) {
                continue;
            }
            if (str_ends_with($name, '/')) {
                if ($prefix !== '' && $name === $prefix) {
                    continue;
                }
                if (!str_starts_with($name, $prefix)) {
                    throw new RuntimeException('Theme package contains files outside its theme folder.');
                }
                $relativeDirectory = substr($name, strlen($prefix));
                if ($relativeDirectory === 'starter/') {
                    continue;
                }
                if (!str_starts_with($relativeDirectory, 'assets/')) {
                    throw new RuntimeException('Theme packages may contain directories inside assets/ and the optional starter/ directory only.');
                }
                continue;
            }
            if (!str_starts_with($name, $prefix)) {
                throw new RuntimeException('Theme package contains files outside its theme folder.');
            }

            $relative = substr($name, strlen($prefix));
            $lower = strtolower($relative);
            if (isset($seenRelative[$lower])) {
                throw new RuntimeException('Theme package contains duplicate or case-conflicting file paths.');
            }
            $seenRelative[$lower] = true;

            if ($relative === 'theme.json' || $relative === 'style.css') {
                continue;
            }
            if ($relative === 'starter/starter.json') {
                if ($starter !== null) {
                    throw new RuntimeException('Theme package may contain only one starter/starter.json file.');
                }
                if ((int)$stat['size'] < 2 || (int)$stat['size'] > StarterManifest::MAX_BYTES) {
                    throw new RuntimeException('Theme starter manifest must be 512 KiB or smaller.');
                }
                $starter = $stat;
                continue;
            }

            if (!str_starts_with($relative, 'assets/')) {
                throw new RuntimeException('Theme packages may contain theme.json, style.css, files inside assets/, and optional starter/starter.json only.');
            }
            if (!self::safeAssetPath($relative)) {
                throw new RuntimeException('Theme package contains an unsafe asset path. Use simple letters, numbers, dots, hyphens and underscores.');
            }

            $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
            if (!isset(self::ALLOWED_IMAGE_MIMES[$extension])) {
                throw new RuntimeException('Unsupported theme asset type: .' . ($extension !== '' ? $extension : '(none)') . '. Only safe image assets are allowed.');
            }
            if ((int)$stat['size'] < 1 || (int)$stat['size'] > self::MAX_ASSET_BYTES) {
                throw new RuntimeException('Each theme image must be between 1 byte and 12 MB.');
            }

            $assets[$relative] = $stat;
        }

        return ['prefix' => $prefix, 'assets' => $assets, 'starter' => $starter];
    }

    private static function extractAssets(ZipArchive $zip, string $prefix, array $assets, string $staging): array
    {
        $metadata = [];
        foreach ($assets as $relative => $stat) {
            $contents = $zip->getFromIndex((int)$stat['index']);
            if (!is_string($contents) || strlen($contents) !== (int)$stat['size']) {
                throw new RuntimeException('Theme asset could not be read safely: ' . $relative);
            }

            $target = $staging . '/' . $relative;
            $directory = dirname($target);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException('Could not create theme asset staging directory.');
            }
            if (file_put_contents($target, $contents, LOCK_EX) !== strlen($contents)) {
                throw new RuntimeException('Could not stage theme asset: ' . $relative);
            }
            @chmod($target, 0644);
            self::validateImageAsset($target, $relative);
            $metadata[$relative] = [
                'sha256' => hash('sha256', $contents),
                'extension' => strtolower((string)pathinfo($relative, PATHINFO_EXTENSION)),
                'size' => strlen($contents),
            ];
        }
        return $metadata;
    }

    /** @param array<string,mixed> $definition */
    private static function persistStarterDefinition(int $themeId, array $definition): void
    {
        $stmt = Database::connection()->prepare(
            'INSERT INTO theme_starter_definitions '
            . '(theme_id,schema_version,starter_version,name,description,manifest_json,manifest_sha256,created_at,updated_at) '
            . 'VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'
        );
        $stmt->execute([
            $themeId,
            (int)$definition['schema_version'],
            (string)$definition['starter_version'],
            (string)$definition['name'],
            trim((string)($definition['description'] ?? '')) !== '' ? (string)$definition['description'] : null,
            (string)$definition['canonical_json'],
            (string)$definition['manifest_sha256'],
        ]);
    }

    private static function validateImageAsset(string $path, string $relative): void
    {
        $extension = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
        $image = @getimagesize($path);
        if (!is_array($image)) {
            throw new RuntimeException('Theme asset is not a valid image: ' . $relative);
        }

        $mime = strtolower((string)($image['mime'] ?? ''));
        if (!in_array($mime, self::ALLOWED_IMAGE_MIMES[$extension] ?? [], true)) {
            throw new RuntimeException('Theme asset content does not match its file extension: ' . $relative);
        }

        $width = (int)($image[0] ?? 0);
        $height = (int)($image[1] ?? 0);
        if ($width < 1 || $height < 1
            || $width > self::MAX_IMAGE_DIMENSION
            || $height > self::MAX_IMAGE_DIMENSION
            || ($width * $height) > self::MAX_IMAGE_PIXELS) {
            throw new RuntimeException('Theme image dimensions exceed the safety limit: ' . $relative);
        }
    }

    private static function rewriteImportedCss(string $css, string $slug, array $availableAssets): string
    {
        $rewritten = preg_replace_callback(
            '/url\(\s*(["\']?)(.*?)\1\s*\)/is',
            static function (array $match) use ($slug, $availableAssets): string {
                $url = trim((string)$match[2]);
                if ($url === '') {
                    throw new RuntimeException('Theme CSS contains an empty url() reference.');
                }

                if (preg_match('#^data:image/(?:png|jpeg|gif|webp|avif);base64,[a-z0-9+/=\r\n]+$#i', $url) === 1) {
                    return $match[0];
                }
                if (str_starts_with($url, '#')) {
                    return $match[0];
                }
                if (preg_match('#^(?:https?:|//|javascript:|vbscript:|file:|blob:)#i', $url) === 1) {
                    throw new RuntimeException('Theme CSS cannot load remote or executable resources.');
                }
                if (str_contains($url, '?') || str_contains($url, '#')) {
                    throw new RuntimeException('Theme asset URLs may not contain query strings or fragments.');
                }
                if (str_starts_with($url, '/')) {
                    if (preg_match('#^/(?:assets|uploads)/[A-Za-z0-9._/-]+$#', $url) === 1
                        && !str_contains($url, '/./')
                        && !str_contains($url, '/../')) {
                        return $match[0];
                    }
                    throw new RuntimeException('Theme CSS contains an unsupported local asset URL: ' . $url);
                }

                while (str_starts_with($url, './')) {
                    $url = substr($url, 2);
                }
                if (!self::safeAssetPath($url) || !isset($availableAssets[$url])) {
                    throw new RuntimeException('Theme CSS references a missing or unsafe asset: ' . $url);
                }

                $encoded = implode('/', array_map('rawurlencode', explode('/', $url)));
                return 'url("/uploads/themes/' . rawurlencode($slug) . '/' . $encoded . '")';
            },
            $css
        );

        if (!is_string($rewritten)) {
            throw new RuntimeException('Theme CSS could not be validated.');
        }

        return $rewritten;
    }

    private static function validate(string $name, string $slug, string $version, string $css): void
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw new RuntimeException('Theme name must be between 2 and 120 characters.');
        }
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,119}$/', $slug)) {
            throw new RuntimeException('Theme slug can contain lowercase letters, numbers and hyphens.');
        }
        if ($slug === 'trenlume-light') {
            throw new RuntimeException('The legacy Trenlume Light slug is reserved and cannot be used for custom themes.');
        }
        if (mb_strlen($version) > 40) {
            throw new RuntimeException('Theme version is too long.');
        }
        if ($css === '') {
            throw new RuntimeException('Custom theme CSS cannot be empty.');
        }
        if (strlen($css) > self::MAX_CSS_BYTES) {
            throw new RuntimeException('Theme CSS is too large.');
        }
        if (preg_match('/<\/?(?:script|style)|javascript\s*:|vbscript\s*:|expression\s*\(|@import\b|-moz-binding/i', $css)) {
            throw new RuntimeException('Theme CSS contains disallowed executable or imported content.');
        }
        if (preg_match('/url\s*\(\s*["\']?\s*(?:https?:)?\/\//i', $css)) {
            throw new RuntimeException('Theme CSS cannot load remote resources. Use local theme assets or self-contained CSS.');
        }
    }

    private static function ignorableMetadataPath(string $name): bool
    {
        $parts = explode('/', rtrim($name, '/'));
        $base = (string)end($parts);
        return in_array('__MACOSX', $parts, true)
            || $base === '.DS_Store'
            || str_starts_with($base, '._');
    }

    private static function safeZipPath(string $name): bool
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || str_starts_with($name, '/')) {
            return false;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            return false;
        }

        $trimmed = rtrim($name, '/');
        if ($trimmed === '') {
            return false;
        }
        foreach (explode('/', $trimmed) as $part) {
            if ($part === '' || $part === '.' || $part === '..') {
                return false;
            }
        }
        return true;
    }

    private static function safeAssetPath(string $relative): bool
    {
        if (!str_starts_with($relative, 'assets/') || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return false;
        }
        if (str_ends_with($relative, '/')) {
            return false;
        }
        foreach (explode('/', $relative) as $part) {
            if ($part === '' || $part === '.' || $part === '..' || preg_match('/^[A-Za-z0-9._-]+$/', $part) !== 1) {
                return false;
            }
        }
        return true;
    }

    private static function rejectSymlink(ZipArchive $zip, int $index): void
    {
        if (!method_exists($zip, 'getExternalAttributesIndex')) {
            return;
        }

        $opsys = 0;
        $attributes = 0;
        if (!$zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return;
        }
        $mode = ($attributes >> 16) & 0170000;
        if ($mode === 0120000) {
            throw new RuntimeException('Theme packages may not contain symbolic links.');
        }
    }

    private static function newStagingDirectory(): string
    {
        $root = base_path('storage/theme-imports');
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Theme staging storage is not writable.');
        }

        $path = $root . '/staged-' . bin2hex(random_bytes(12));
        if (!mkdir($path, 0750, true)) {
            throw new RuntimeException('Could not create theme staging directory.');
        }
        return $path;
    }

    private static function installStagedAssets(string $staging, string $slug): void
    {
        $root = base_path('public/uploads/themes');
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException('Theme asset directory is not writable.');
        }

        $target = self::themeAssetRoot($slug);
        if (file_exists($target)) {
            throw new RuntimeException('Theme asset directory already exists for this slug.');
        }

        if (@rename($staging, $target)) {
            @chmod($target, 0755);
            return;
        }

        self::copyDirectory($staging, $target);
        self::removeDirectory($staging);
    }

    private static function copyDirectory(string $source, string $target): void
    {
        if (!mkdir($target, 0755, true) && !is_dir($target)) {
            throw new RuntimeException('Could not create the theme asset directory.');
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($iterator as $item) {
                $relative = substr($item->getPathname(), strlen($source) + 1);
                $destination = $target . '/' . $relative;
                if ($item->isDir()) {
                    if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
                        throw new RuntimeException('Could not create a theme asset subdirectory.');
                    }
                } elseif (!$item->isLink()) {
                    if (!copy($item->getPathname(), $destination)) {
                        throw new RuntimeException('Could not install a theme asset.');
                    }
                    @chmod($destination, 0644);
                }
            }
        } catch (\Throwable $e) {
            self::removeDirectory($target);
            throw $e;
        }
    }

    private static function themeAssetRoot(string $slug): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,119}$/', $slug)) {
            throw new RuntimeException('Theme asset slug is invalid.');
        }
        return base_path('public/uploads/themes/' . $slug);
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    private static function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }

    private function __construct()
    {
    }
}
