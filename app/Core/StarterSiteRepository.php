<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class StarterSiteRepository
{
    public static function definitionForTheme(int $themeId): ?array
    {
        if ($themeId < 1) return null;
        $stmt = Database::connection()->prepare('SELECT * FROM theme_starter_definitions WHERE theme_id=? LIMIT 1');
        $stmt->execute([$themeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return self::hydrateDefinition($row);
    }

    public static function activeInstallationForTheme(int $themeId): ?array
    {
        if ($themeId < 1) return null;
        $stmt = Database::connection()->prepare("SELECT * FROM starter_site_installations WHERE theme_id=? AND status='installed' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$themeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string,array<string,mixed>> keyed by resource_key */
    public static function resourcesForInstallation(int $installationId): array
    {
        if ($installationId < 1) return [];
        $stmt = Database::connection()->prepare('SELECT * FROM starter_site_resources WHERE installation_id=? ORDER BY id');
        $stmt->execute([$installationId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(string)$row['resource_key']] = self::hydrateResource($row);
        }
        return $out;
    }

    public static function createInstallation(int $themeId, int $definitionId, string $starterVersion, string $manifestSha256, int $userId, string $token): int
    {
        if ($themeId < 1 || $definitionId < 1 || $userId < 1 || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new RuntimeException('Starter installation metadata is invalid.');
        }
        $stmt = Database::connection()->prepare(
            "INSERT INTO starter_site_installations (theme_id,definition_id,starter_version,manifest_sha256,installation_token,status,installed_by,installed_at,created_at,updated_at) VALUES (?,?,?,?,?,'installed',?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
        $stmt->execute([$themeId,$definitionId,$starterVersion,$manifestSha256,$token,$userId]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function recordResource(
        int $installationId,
        string $resourceKey,
        string $resourceType,
        ?int $recordId,
        ?string $recordLocator,
        string $ownershipMode,
        string $definitionSha256,
        string $baselineSha256,
        ?array $previousState = null
    ): void {
        if ($installationId < 1 || !StarterResourceRegistry::supports($resourceType)) {
            throw new RuntimeException('Starter ownership metadata is invalid.');
        }
        if (!in_array($ownershipMode, ['created','mutated'], true)) {
            throw new RuntimeException('Starter ownership mode is invalid.');
        }
        $previousJson = $previousState === null ? null : json_encode($previousState, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $stmt = Database::connection()->prepare(
            "INSERT INTO starter_site_resources (installation_id,resource_key,resource_type,record_id,record_locator,ownership_mode,definition_sha256,baseline_sha256,previous_state_json,state,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,'owned',UTC_TIMESTAMP(),UTC_TIMESTAMP())"
        );
        $stmt->execute([$installationId,$resourceKey,$resourceType,$recordId,$recordLocator,$ownershipMode,$definitionSha256,$baselineSha256,$previousJson]);
    }

    public static function updateResourceBaseline(int $installationId, string $resourceKey, ?int $recordId, ?string $recordLocator, string $definitionSha256, string $baselineSha256): void
    {
        $stmt = Database::connection()->prepare(
            "UPDATE starter_site_resources SET record_id=?,record_locator=?,definition_sha256=?,baseline_sha256=?,state='owned',updated_at=UTC_TIMESTAMP() WHERE installation_id=? AND resource_key=?"
        );
        $stmt->execute([$recordId,$recordLocator,$definitionSha256,$baselineSha256,$installationId,$resourceKey]);
    }

    public static function markResourceState(int $installationId, string $resourceKey, string $state): void
    {
        if (!in_array($state, ['owned','detached','removed'], true)) {
            throw new RuntimeException('Starter resource ownership state is invalid.');
        }
        $stmt = Database::connection()->prepare('UPDATE starter_site_resources SET state=?,updated_at=UTC_TIMESTAMP() WHERE installation_id=? AND resource_key=?');
        $stmt->execute([$state,$installationId,$resourceKey]);
    }

    public static function markInstallationRemoved(int $installationId, int $userId): void
    {
        $stmt = Database::connection()->prepare("UPDATE starter_site_installations SET status='removed',removed_by=?,removed_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=? AND status='installed'");
        $stmt->execute([$userId,$installationId]);
    }

    public static function resourceByKey(int $installationId, string $resourceKey): ?array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM starter_site_resources WHERE installation_id=? AND resource_key=? LIMIT 1');
        $stmt->execute([$installationId,$resourceKey]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrateResource($row) : null;
    }

    private static function hydrateResource(array $row): array
    {
        $previous=null;
        $json=$row['previous_state_json'] ?? null;
        if(is_string($json) && trim($json)!==''){
            try { $decoded=json_decode($json,true,64,JSON_THROW_ON_ERROR); $previous=is_array($decoded)?$decoded:null; }
            catch (\JsonException) { throw new RuntimeException('Stored starter ownership snapshot is corrupted.'); }
        }
        $row['record_id']=$row['record_id']===null?null:(int)$row['record_id'];
        $row['previous_state']=$previous;
        return $row;
    }

    private static function hydrateDefinition(array $row): array
    {
        try {
            $manifest = json_decode((string)$row['manifest_json'], true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Stored starter definition is corrupted.');
        }
        if (!is_array($manifest)) throw new RuntimeException('Stored starter definition is corrupted.');
        $resources = $manifest['resources'] ?? [];
        if (is_array($resources)) {
            $resources = StarterManifest::orderStoredResources(array_values($resources));
            foreach ($resources as &$resource) {
                if (!is_array($resource)) continue;
                $resource['definition_sha256'] = StarterManifest::hashResourceDefinition((string)($resource['type'] ?? ''), (array)($resource['data'] ?? []));
            }
            unset($resource);
        }
        return array_merge($row, [
            'schema_version'=>(int)$row['schema_version'],
            'resources'=>$resources,
            'manifest'=>$manifest,
            'manifest_sha256'=>(string)$row['manifest_sha256'],
            'starter_version'=>(string)$row['starter_version'],
        ]);
    }

    private function __construct() {}
}
