<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class SiteAssets
{
    private const MAX_BYTES = 12 * 1024 * 1024;
    private const MAX_PIXELS = 60_000_000;
    private const MAX_DIMENSION = 16_000;
    private const KINDS = ['logo','hero','home-card','post','page-block','pattern','media'];
    private const MIMES = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public static function storeImage(array $file, string $kind): string
    {
        if (!in_array($kind, self::KINDS, true)) throw new RuntimeException('Unsupported image asset type.');

        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return '';
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            throw new RuntimeException('Uploaded image exceeds the server upload limit.');
        }
        if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');

        $size = (int)($file['size'] ?? 0);
        if ($size < 1 || $size > self::MAX_BYTES) throw new RuntimeException('Images must be 12 MB or smaller.');

        $tmp = (string)($file['tmp_name'] ?? '');
        if ((PHP_SAPI === 'cli' && !is_file($tmp)) || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            throw new RuntimeException('Image upload could not be verified.');
        }

        $original = trim((string)($file['name'] ?? ''));
        $ext = strtolower((string)pathinfo($original, PATHINFO_EXTENSION));
        if (!isset(self::MIMES[$ext])) throw new RuntimeException('Use a JPEG, PNG or WebP image.');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($tmp));
        if (!in_array($mime, self::MIMES[$ext], true)) throw new RuntimeException('Image content does not match its file extension.');

        $imageInfo = @getimagesize($tmp);
        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) throw new RuntimeException('Uploaded file is not a readable image.');
        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('Image dimensions are too large.');
        }

        $root = base_path('public/uploads/site');
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) throw new RuntimeException('Could not create the site upload directory.');

        $normalizedExt = $ext === 'jpeg' ? 'jpg' : $ext;
        $name = $kind . '-' . bin2hex(random_bytes(12)) . '.' . $normalizedExt;
        $target = $root . '/' . $name;
        $moved = PHP_SAPI === 'cli' ? copy($tmp, $target) : move_uploaded_file($tmp, $target);
        if (!$moved) throw new RuntimeException('Could not store the uploaded image.');
        @chmod($target, 0644);
        return '/uploads/site/' . $name;
    }


    public static function duplicateStoredImage(string $publicPath, string $kind): string
    {
        if (!in_array($kind, self::KINDS, true) || $kind === 'media') throw new RuntimeException('Unsupported image asset type.');
        $safe = HomePage::safeStoredAssetPath($publicPath);
        $isUpload = str_starts_with($safe, '/uploads/site/');
        $isBundledDemo = str_starts_with($safe, '/assets/demo/');
        if ($safe === '' || (!$isUpload && !$isBundledDemo)) throw new RuntimeException('Selected media path is invalid.');
        $source = base_path('public' . $safe);
        if (!is_file($source)) throw new RuntimeException('Selected media file is missing.');

        $size = (int)filesize($source);
        if ($size < 1 || $size > self::MAX_BYTES) throw new RuntimeException('Selected image exceeds the allowed size.');
        $ext = strtolower((string)pathinfo($source, PATHINFO_EXTENSION));
        if (!isset(self::MIMES[$ext])) throw new RuntimeException('Selected media type is not supported.');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$finfo->file($source));
        if (!in_array($mime, self::MIMES[$ext], true)) throw new RuntimeException('Selected media content is invalid.');
        $imageInfo = @getimagesize($source);
        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) throw new RuntimeException('Selected media image could not be read.');
        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];
        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException('Selected image dimensions are too large.');
        }

        $root = base_path('public/uploads/site');
        if (!is_dir($root) && !mkdir($root, 0755, true) && !is_dir($root)) throw new RuntimeException('Could not create the site upload directory.');
        $normalizedExt = $ext === 'jpeg' ? 'jpg' : $ext;
        $name = $kind . '-' . bin2hex(random_bytes(12)) . '.' . $normalizedExt;
        $target = $root . '/' . $name;
        if (!copy($source, $target)) throw new RuntimeException('Could not copy the selected media image.');
        @chmod($target, 0644);
        return '/uploads/site/' . $name;
    }

    public static function managedUploadPath(string $publicPath): string
    {
        $value = trim($publicPath);
        return preg_match('#^/uploads/site/[a-z0-9][a-z0-9._-]*\.(?:jpe?g|png|webp|avif)$#Di', $value) === 1 ? $value : '';
    }

    public static function remove(string $publicPath): void
    {
        $safe = self::managedUploadPath($publicPath);
        if ($safe === '') return;
        $target = base_path('public/uploads/site/' . basename($safe));
        if (is_file($target)) @unlink($target);
    }

    public static function maxUploadMb(): int
    {
        return intdiv(self::MAX_BYTES, 1024 * 1024);
    }

    private function __construct() {}
}
