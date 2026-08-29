<?php
declare(strict_types=1);

namespace CMS\Core;

final class ContactFormState
{
    public static function put(int $pageId, string $ownerId, string $blockId, array $state): void
    {
        if ($pageId < 1 || !self::validId($ownerId) || !self::validId($blockId)) return;
        if (!isset($_SESSION) || !is_array($_SESSION)) return;
        if (!isset($_SESSION['_contact_forms']) || !is_array($_SESSION['_contact_forms'])) {
            $_SESSION['_contact_forms'] = [];
        }
        $_SESSION['_contact_forms'][self::key($pageId, $ownerId, $blockId)] = $state;
        if (count($_SESSION['_contact_forms']) > 12) {
            $_SESSION['_contact_forms'] = array_slice($_SESSION['_contact_forms'], -12, null, true);
        }
    }

    public static function pull(int $pageId, string $ownerId, string $blockId): array
    {
        if ($pageId < 1 || !self::validId($ownerId) || !self::validId($blockId)) return [];
        if (!isset($_SESSION['_contact_forms']) || !is_array($_SESSION['_contact_forms'])) return [];
        $key = self::key($pageId, $ownerId, $blockId);
        $state = $_SESSION['_contact_forms'][$key] ?? [];
        unset($_SESSION['_contact_forms'][$key]);
        if ($_SESSION['_contact_forms'] === []) unset($_SESSION['_contact_forms']);
        return is_array($state) ? $state : [];
    }

    private static function key(int $pageId, string $ownerId, string $blockId): string
    {
        return $pageId . ':' . $ownerId . ':' . $blockId;
    }

    private static function validId(string $value): bool
    {
        return preg_match('/^[a-z0-9]{8,32}$/D', $value) === 1;
    }

    private function __construct() {}
}
