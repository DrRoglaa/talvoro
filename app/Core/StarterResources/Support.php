<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\StarterFilesystemJournal;
use CMS\Core\StarterReferences;
use RuntimeException;

abstract class Support
{
    protected static function data(array $resource): array
    {
        $data=$resource['data']??null;
        if(!is_array($data)) throw new RuntimeException('Starter resource data is missing.');
        return $data;
    }

    /** @return array<string,array<string,mixed>> */
    protected static function resolved(array $context): array { return is_array($context['resolved']??null)?$context['resolved']:[]; }
    protected static function userId(array $context): int { $id=(int)($context['user_id']??0); if($id<1) throw new RuntimeException('Starter operation actor is missing.'); return $id; }
    protected static function journal(array $context): ?StarterFilesystemJournal { return ($context['journal']??null) instanceof StarterFilesystemJournal ? $context['journal'] : null; }

    protected static function refId(mixed $value,array $context,string|array|null $type=null): int { return StarterReferences::recordId($value,self::resolved($context),$type); }
    protected static function refLocator(mixed $value,array $context,string|array|null $type=null): string { return StarterReferences::locator($value,self::resolved($context),$type); }

    protected static function hash(mixed $value): string
    {
        return hash('sha256',self::canonicalJson($value));
    }

    protected static function canonicalJson(mixed $value): string
    {
        $normalize=function(mixed $item) use (&$normalize): mixed {
            if(!is_array($item)) return $item;
            if(array_is_list($item)) return array_map($normalize,$item);
            ksort($item,SORT_STRING); foreach($item as $k=>$v)$item[$k]=$normalize($v); return $item;
        };
        return json_encode($normalize($value),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
    }

    protected static function created(?int $id,?string $locator,array $snapshot,?array $previous=null,array $files=[]): array
    {
        return ['record_id'=>$id,'record_locator'=>$locator,'ownership_mode'=>$previous===null?'created':'mutated','baseline_sha256'=>self::hash($snapshot),'previous_state'=>$previous,'created_files'=>array_values($files)];
    }

    protected static function boolInput(array $data,array $keys,array $base=[]): array
    {
        foreach($keys as $key) if(!empty($data[$key])) $base[$key]='1';
        return $base;
    }

    protected static function validation(array $result,string $context): array
    {
        $errors=is_array($result['errors']??null)?$result['errors']:[];
        if($errors) throw new RuntimeException($context.': '.implode(' ',array_map('strval',$errors)));
        $data=$result['data']??null;
        if(!is_array($data)) throw new RuntimeException($context.' validation returned no data.');
        return $data;
    }
}
