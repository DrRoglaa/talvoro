<?php
declare(strict_types=1);

namespace CMS\Core;

interface StarterResourceAdapter
{
    /** @return list<string> */
    public function types(): array;

    /** @return array{action:string,message?:string,existing_id?:int,existing_locator?:string,ownership_mode?:string} */
    public function preflight(array $resource,array $context): array;

    /** @return array{record_id:?int,record_locator:?string,ownership_mode:string,baseline_sha256:string,previous_state:?array,created_files?:list<string>} */
    public function create(array $resource,array $context): array;

    /** @return array<string,mixed>|null */
    public function snapshot(array $ownership,array $context): ?array;

    /** @return array{removed:bool,files?:list<string>,reason?:string} */
    public function remove(array $ownership,array $context): array;

    /** @return array{restored:bool,reason?:string} */
    public function restore(array $ownership,array $context): array;
}
