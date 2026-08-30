<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class StarterReferences
{
    public static function key(mixed $value): ?string
    {
        if (!is_array($value) || array_is_list($value) || count($value) !== 1 || !isset($value['$ref']) || !is_string($value['$ref'])) return null;
        $key=trim($value['$ref']);
        return preg_match('/^[a-z][a-z0-9_-]*(?:\.[a-z0-9][a-z0-9_-]*)+$/D',$key)===1 ? $key : null;
    }

    /** @param array<string,array<string,mixed>> $resolved */
    public static function recordId(mixed $value,array $resolved,string|array|null $expectedType=null): int
    {
        $row=self::resolved($value,$resolved,$expectedType);
        $id=(int)($row['record_id']??0);
        if($id<1) throw new RuntimeException('Starter reference does not resolve to a database record.');
        return $id;
    }

    /** @param array<string,array<string,mixed>> $resolved */
    public static function locator(mixed $value,array $resolved,string|array|null $expectedType=null): string
    {
        $row=self::resolved($value,$resolved,$expectedType);
        $locator=trim((string)($row['record_locator']??''));
        if($locator==='') throw new RuntimeException('Starter reference does not resolve to a public/internal locator.');
        return $locator;
    }

    /** @param array<string,array<string,mixed>> $resolved @param list<string> $allowedTypes */
    public static function resolveValueTree(mixed $value,array $resolved,array $allowedTypes): mixed
    {
        $key=self::key($value);
        if($key!==null){
            $row=self::resolved($value,$resolved,$allowedTypes);
            $id=(int)($row['record_id']??0);
            if($id<1) throw new RuntimeException('Starter value reference '.$key.' does not resolve to a database record.');
            return $id;
        }
        if(!is_array($value)) return $value;
        $out=[];
        foreach($value as $k=>$child) $out[$k]=self::resolveValueTree($child,$resolved,$allowedTypes);
        return $out;
    }

    /** @param array<string,array<string,mixed>> $resolved */
    public static function resolved(mixed $value,array $resolved,string|array|null $expectedType=null): array
    {
        $key=self::key($value);
        if($key===null) throw new RuntimeException('Expected a valid starter logical reference.');
        $row=$resolved[$key]??null;
        if(!is_array($row)) throw new RuntimeException('Starter reference '.$key.' has not been resolved yet.');
        if($expectedType!==null){
            $types=is_array($expectedType)?array_values($expectedType):[$expectedType];
            if(!in_array((string)($row['resource_type']??''),$types,true)) throw new RuntimeException('Starter reference '.$key.' resolves to an incompatible resource type.');
        }
        return $row;
    }

    private function __construct(){}
}
