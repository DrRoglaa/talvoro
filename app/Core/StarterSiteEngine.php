<?php
declare(strict_types=1);

namespace CMS\Core;

final class StarterSiteEngine
{
    /**
     * Pure state reducer used by the UI and runtime scanner.
     * Snapshot items may add exists/current_sha256 to persisted ownership rows.
     * @return array{code:string,missing:list<string>,modified:list<string>,detached:list<string>}
     */
    public static function stateFromSnapshot(array $definition, ?array $installation, array $snapshot): array
    {
        $result = ['code'=>'not_installed','missing'=>[],'modified'=>[],'detached'=>[]];
        if (!$installation || (string)($installation['status'] ?? '') !== 'installed') return $result;

        $expected = [];
        foreach (($definition['resources'] ?? []) as $resource) {
            if (is_array($resource) && isset($resource['key'])) $expected[] = (string)$resource['key'];
        }

        foreach ($expected as $key) {
            $row = $snapshot[$key] ?? null;
            if (!is_array($row)) {
                $result['missing'][] = $key;
                continue;
            }
            $ownershipState = (string)($row['state'] ?? 'owned');
            if ($ownershipState !== 'owned') {
                $result['detached'][] = $key;
                continue;
            }
            if (($row['exists'] ?? true) !== true) {
                $result['missing'][] = $key;
                continue;
            }
            $baseline = (string)($row['baseline_sha256'] ?? '');
            $current = $row['current_sha256'] ?? $baseline;
            if ($baseline !== '' && is_string($current) && $current !== '' && !hash_equals($baseline, $current)) {
                $result['modified'][] = $key;
            }
        }

        sort($result['missing']); sort($result['modified']); sort($result['detached']);
        if ($result['detached'] !== []) $result['code'] = 'needs_attention';
        elseif ($result['missing'] !== []) $result['code'] = 'repair_available';
        elseif ($result['modified'] !== []) $result['code'] = 'modified';
        elseif (!hash_equals((string)($installation['manifest_sha256'] ?? ''), (string)($definition['manifest_sha256'] ?? ''))) $result['code'] = 'starter_update_available';
        else $result['code'] = 'installed';
        return $result;
    }

    /** @return array{total:int,counts:array<string,int>} */
    public static function summarizeDefinition(array $definition): array
    {
        $counts = [];
        foreach (($definition['resources'] ?? []) as $resource) {
            if (!is_array($resource)) continue;
            $type = (string)($resource['type'] ?? '');
            if (!StarterResourceRegistry::supports($type)) continue;
            $label = StarterResourceRegistry::label($type);
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }
        return ['total'=>array_sum($counts),'counts'=>$counts];
    }

    public static function snapshotHash(?array $snapshot): ?string
    {
        if ($snapshot === null) return null;
        $normalize = function (mixed $value) use (&$normalize): mixed {
            if (!is_array($value)) return $value;
            if (array_is_list($value)) return array_map($normalize, $value);
            ksort($value, SORT_STRING);
            foreach ($value as $key => $child) $value[$key] = $normalize($child);
            return $value;
        };
        return hash('sha256', json_encode($normalize($snapshot), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR));
    }

    /** @param array<string,array<string,mixed>> $snapshot @return list<string> */
    public static function repairCandidates(array $snapshot): array
    {
        $keys=[];
        foreach($snapshot as $key=>$row){
            if(!is_array($row) || (string)($row['state']??'owned')!=='owned') continue;
            if(($row['exists']??true)===false) $keys[]=(string)$key;
        }
        sort($keys,SORT_STRING);
        return $keys;
    }

    /** @param array<string,array<string,mixed>> $ownership @return list<string> */
    public static function removalOrder(array $definition,array $ownership): array
    {
        $order=[];
        foreach(($definition['resources']??[]) as $resource){
            if(is_array($resource) && isset($resource['key'])) $order[]=(string)$resource['key'];
        }
        $order=array_reverse($order);
        $known=array_fill_keys($order,true);
        $orphans=[];
        foreach($ownership as $key=>$row){
            $resourceKey=is_array($row)?(string)($row['resource_key']??$key):(string)$key;
            if(!isset($known[$resourceKey])) $orphans[]=['key'=>$resourceKey,'id'=>(int)(is_array($row)?($row['id']??0):0)];
        }
        usort($orphans,static fn(array $a,array $b):int=>$b['id']<=>$a['id'] ?: strcmp($a['key'],$b['key']));
        foreach($orphans as $row)$order[]=$row['key'];
        return $order;
    }

    /** @param list<array<string,mixed>> $items @return array{allowed:bool,conflicts:list<string>,mutations:list<string>} */
    public static function preflightDecision(array $items,bool $mutationsConfirmed): array
    {
        $conflicts=[];$mutations=[];
        foreach($items as $item){
            if(!is_array($item))continue;
            $key=(string)($item['key']??'');$action=(string)($item['action']??'');
            if($action==='conflict' && $key!=='')$conflicts[]=$key;
            if($action==='controlled_mutation' && $key!=='')$mutations[]=$key;
        }
        sort($conflicts,SORT_STRING);sort($mutations,SORT_STRING);
        return ['allowed'=>$conflicts===[] && ($mutationsConfirmed || $mutations===[]),'conflicts'=>$conflicts,'mutations'=>$mutations];
    }

    private function __construct() {}
}
