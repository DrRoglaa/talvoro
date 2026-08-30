<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class StarterSite
{
    private const ALLOWED_SETTINGS=['branding.site_name'=>true,'branding.tagline'=>true,'branding.footer_text'=>true,'branding.footer_note'=>true,'blog.enabled'=>true,'blog.archive_title'=>true,'blog.archive_intro'=>true];

    public static function starterSettingAllowed(string $key): bool { return isset(self::ALLOWED_SETTINGS[$key]); }

    public static function removalDecision(array $ownership,?string $currentSha256,bool $exists): string
    {
        if((string)($ownership['state']??'owned')!=='owned') return 'skip';
        if(!$exists) return 'mark_removed';
        $baseline=(string)($ownership['baseline_sha256']??'');
        if($baseline==='' || $currentSha256===null || !hash_equals($baseline,$currentSha256)) return 'detach';
        return (string)($ownership['ownership_mode']??'created')==='mutated' ? 'restore' : 'remove';
    }

    /** @return array<string,mixed> */
    public static function review(int $themeId): array
    {
        $theme=self::theme($themeId);
        $definition=StarterSiteRepository::definitionForTheme($themeId);
        if(!$definition) throw new RuntimeException('This theme does not include a Starter Site.');
        $installation=StarterSiteRepository::activeInstallationForTheme($themeId);
        if($installation){
            return [
                'theme'=>$theme,'definition'=>$definition,'installation'=>$installation,
                'summary'=>StarterSiteEngine::summarizeDefinition($definition),
                'state'=>self::status($themeId),'items'=>[],
                'decision'=>['allowed'=>false,'conflicts'=>[],'mutations'=>[]],
            ];
        }
        $items=self::preflight($theme,$definition,[]);
        return [
            'theme'=>$theme,'definition'=>$definition,'installation'=>null,
            'summary'=>StarterSiteEngine::summarizeDefinition($definition),
            'state'=>['code'=>'not_installed','missing'=>[],'modified'=>[],'detached'=>[]],
            'items'=>$items,'decision'=>StarterSiteEngine::preflightDecision($items,false),
        ];
    }

    /** @return array{code:string,missing:list<string>,modified:list<string>,detached:list<string>} */
    public static function status(int $themeId): array
    {
        $definition=StarterSiteRepository::definitionForTheme($themeId);
        if(!$definition) return ['code'=>'not_installed','missing'=>[],'modified'=>[],'detached'=>[]];
        $installation=StarterSiteRepository::activeInstallationForTheme($themeId);
        if(!$installation) return ['code'=>'not_installed','missing'=>[],'modified'=>[],'detached'=>[]];
        $theme=self::theme($themeId);
        $ownership=StarterSiteRepository::resourcesForInstallation((int)$installation['id']);
        $snapshot=self::ownershipSnapshot($ownership,$theme,(int)($installation['installed_by']??1));
        return StarterSiteEngine::stateFromSnapshot($definition,$installation,$snapshot);
    }

    /** @return array<string,mixed> */
    public static function install(int $themeId,int $userId,bool $confirmMutations=false): array
    {
        self::requireActor($userId);
        $theme=self::requireActiveTheme($themeId);
        $definition=StarterSiteRepository::definitionForTheme($themeId);
        if(!$definition) throw new RuntimeException('This theme does not include a Starter Site.');
        $existing=StarterSiteRepository::activeInstallationForTheme($themeId);
        if($existing) return ['installed'=>false,'idempotent'=>true,'state'=>self::status($themeId),'installation_id'=>(int)$existing['id']];

        $items=self::preflight($theme,$definition,[]);
        $decision=StarterSiteEngine::preflightDecision($items,$confirmMutations);
        if($decision['conflicts']) throw new RuntimeException('Starter Site conflicts must be resolved before installation: '.implode(', ',$decision['conflicts']).'.');
        if(!$decision['allowed']) throw new RuntimeException('Confirm the listed changes to existing site content before installing this Starter Site.');

        $db=Database::connection();
        $token=bin2hex(random_bytes(16));
        $journal=new StarterFilesystemJournal($token);
        try{
            $db->beginTransaction();
            $lock=$db->prepare('SELECT id FROM themes WHERE id=? FOR UPDATE');
            $lock->execute([$themeId]);
            if(!(int)$lock->fetchColumn()) throw new RuntimeException('Theme not found.');
            self::requireActiveTheme($themeId);
            $existing=StarterSiteRepository::activeInstallationForTheme($themeId);
            if($existing){
                $db->rollBack();
                $journal->complete();
                return ['installed'=>false,'idempotent'=>true,'state'=>self::status($themeId),'installation_id'=>(int)$existing['id']];
            }

            $installationId=StarterSiteRepository::createInstallation(
                $themeId,(int)$definition['id'],(string)$definition['starter_version'],(string)$definition['manifest_sha256'],$userId,$token
            );
            $resolved=[];
            foreach(($definition['resources']??[]) as $resource){
                if(!is_array($resource)) continue;
                $type=(string)$resource['type'];
                $adapter=StarterResourceRegistry::adapter($type);
                $context=self::context($theme,$userId,$resolved,$journal,$installationId);
                $preflight=$adapter->preflight($resource,$context);
                $action=(string)($preflight['action']??'create');
                if($action==='conflict') throw new RuntimeException((string)($preflight['message']??('Starter conflict at '.(string)$resource['key'].'.')));
                if($action==='controlled_mutation' && !$confirmMutations) throw new RuntimeException('Starter mutation confirmation is required.');
                $created=$adapter->create($resource,$context);
                self::recordCreated($installationId,$resource,$created);
                $resolved[(string)$resource['key']]=self::resolvedRow($resource,$created);
            }
            $db->commit();
            $journal->complete();
            return ['installed'=>true,'idempotent'=>false,'installation_id'=>$installationId,'state'=>self::status($themeId)];
        }catch(\Throwable $e){
            if($db->inTransaction()) $db->rollBack();
            $journal->rollback();
            throw $e;
        }
    }

    /** @return array<string,mixed> */
    public static function repair(int $themeId,int $userId): array
    {
        self::requireActor($userId);
        $theme=self::requireActiveTheme($themeId);
        $definition=StarterSiteRepository::definitionForTheme($themeId);
        $installation=StarterSiteRepository::activeInstallationForTheme($themeId);
        if(!$definition || !$installation) throw new RuntimeException('No installed Starter Site is available to repair.');
        if(!hash_equals((string)$installation['manifest_sha256'],(string)$definition['manifest_sha256'])){
            throw new RuntimeException('A newer Starter Site definition is available. Talvoro 0.17.0 does not apply starter updates automatically.');
        }

        $db=Database::connection();$token=bin2hex(random_bytes(16));$journal=new StarterFilesystemJournal($token);
        $repaired=[];$preserved=[];$conflicts=[];
        try{
            $db->beginTransaction();
            $lock=$db->prepare('SELECT id FROM starter_site_installations WHERE id=? AND status=\'installed\' FOR UPDATE');
            $lock->execute([(int)$installation['id']]);
            if(!(int)$lock->fetchColumn()) throw new RuntimeException('Starter Site installation changed while repair was starting.');
            self::requireActiveTheme($themeId);

            $ownership=StarterSiteRepository::resourcesForInstallation((int)$installation['id']);
            $resolved=[];
            foreach($ownership as $key=>$row){
                if((string)($row['state']??'')!=='owned') continue;
                $adapter=StarterResourceRegistry::adapter((string)$row['resource_type']);
                $snapshot=$adapter->snapshot($row,self::context($theme,$userId,$resolved,$journal,(int)$installation['id']));
                if($snapshot!==null) $resolved[$key]=self::resolvedRow($row,$row);
            }

            foreach(($definition['resources']??[]) as $resource){
                if(!is_array($resource)) continue;
                $key=(string)$resource['key'];$type=(string)$resource['type'];
                $row=$ownership[$key]??null;
                if(is_array($row) && (string)($row['state']??'')!=='owned'){ $preserved[]=$key; continue; }
                $adapter=StarterResourceRegistry::adapter($type);
                $context=self::context($theme,$userId,$resolved,$journal,(int)$installation['id']);
                $snapshot=is_array($row)?$adapter->snapshot($row,$context):null;
                if($snapshot!==null){
                    $hash=StarterSiteEngine::snapshotHash($snapshot);
                    if(is_array($row) && (string)($row['baseline_sha256']??'')!=='' && $hash!==null && !hash_equals((string)$row['baseline_sha256'],$hash)) $preserved[]=$key;
                    $resolved[$key]=self::resolvedRow($resource,$row??[]);
                    continue;
                }
                $preflight=$adapter->preflight($resource,$context);
                if((string)($preflight['action']??'')==='conflict'){ $conflicts[]=$key; continue; }
                $created=$adapter->create($resource,$context);
                if(is_array($row)){
                    StarterSiteRepository::updateResourceBaseline((int)$installation['id'],$key,$created['record_id']??null,$created['record_locator']??null,(string)$resource['definition_sha256'],(string)$created['baseline_sha256']);
                }else self::recordCreated((int)$installation['id'],$resource,$created);
                $resolved[$key]=self::resolvedRow($resource,$created);
                $repaired[]=$key;
            }
            $db->commit();$journal->complete();
            return ['repaired'=>$repaired,'preserved'=>$preserved,'conflicts'=>$conflicts,'state'=>self::status($themeId)];
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            $journal->rollback();
            throw $e;
        }
    }

    /** Ownership-safe user-facing removal action for starter/demo content. */
    public static function deleteDemoData(int $themeId,int $userId): array
    {
        self::requireActor($userId);
        $theme=self::theme($themeId);
        $definition=StarterSiteRepository::definitionForTheme($themeId);
        $installation=StarterSiteRepository::activeInstallationForTheme($themeId);
        if(!$installation) throw new RuntimeException('No installed Starter Site demo data was found for this theme.');
        if(!$definition) throw new RuntimeException('The Starter Site definition is unavailable; demo data cannot be removed safely.');

        $db=Database::connection();$files=[];$removed=[];$restored=[];$detached=[];$missing=[];
        try{
            $db->beginTransaction();
            $lock=$db->prepare('SELECT id FROM starter_site_installations WHERE id=? AND status=\'installed\' FOR UPDATE');
            $lock->execute([(int)$installation['id']]);
            if(!(int)$lock->fetchColumn()) throw new RuntimeException('Starter Site installation changed while removal was starting.');
            $ownership=StarterSiteRepository::resourcesForInstallation((int)$installation['id']);
            $resolved=[];
            foreach($ownership as $key=>$row) if((string)($row['state']??'')==='owned') $resolved[$key]=self::resolvedRow($row,$row);
            $context=self::context($theme,$userId,$resolved,null,(int)$installation['id']);

            foreach(StarterSiteEngine::removalOrder($definition,$ownership) as $key){
                $row=$ownership[$key]??null;
                if(!is_array($row) || (string)($row['state']??'')!=='owned') continue;
                $adapter=StarterResourceRegistry::adapter((string)$row['resource_type']);
                $snapshot=$adapter->snapshot($row,$context);
                $hash=StarterSiteEngine::snapshotHash($snapshot);
                $decision=self::removalDecision($row,$hash,$snapshot!==null);
                if($decision==='mark_removed'){
                    StarterSiteRepository::markResourceState((int)$installation['id'],$key,'removed');$missing[]=$key;continue;
                }
                if($decision==='detach'){
                    StarterSiteRepository::markResourceState((int)$installation['id'],$key,'detached');$detached[]=$key;continue;
                }
                if($decision==='restore'){
                    $result=$adapter->restore($row,$context);
                    if(!empty($result['restored'])){
                        StarterSiteRepository::markResourceState((int)$installation['id'],$key,'removed');$restored[]=$key;
                        foreach(($result['files']??[]) as $path) if(is_string($path)&&$path!=='')$files[]=$path;
                    }else{StarterSiteRepository::markResourceState((int)$installation['id'],$key,'detached');$detached[]=$key;}
                    continue;
                }
                if($decision==='remove'){
                    $result=$adapter->remove($row,$context);
                    if(!empty($result['removed'])){
                        StarterSiteRepository::markResourceState((int)$installation['id'],$key,'removed');$removed[]=$key;
                        foreach(($result['files']??[]) as $path) if(is_string($path)&&$path!=='')$files[]=$path;
                    }else{StarterSiteRepository::markResourceState((int)$installation['id'],$key,'detached');$detached[]=$key;}
                }
            }
            StarterSiteRepository::markInstallationRemoved((int)$installation['id'],$userId);
            $db->commit();
        }catch(\Throwable $e){
            if($db->inTransaction())$db->rollBack();
            throw $e;
        }
        foreach(array_values(array_unique($files)) as $path) SiteAssets::remove($path);
        return ['removed'=>$removed,'restored'=>$restored,'detached'=>$detached,'already_missing'=>$missing,'installation_removed'=>true];
    }

    /** @return array<string,mixed> */
    public static function theme(int $themeId): array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM themes WHERE id=? LIMIT 1');
        $stmt->execute([$themeId]);
        $theme=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$theme) throw new RuntimeException('Theme not found.');
        return $theme;
    }

    public static function assetRootIfPresent(array $theme): ?string
    {
        $slug=trim((string)($theme['slug']??''));
        if(preg_match('/^[a-z0-9][a-z0-9-]{1,119}$/D',$slug)!==1) throw new RuntimeException('Theme asset slug is invalid.');
        $root=realpath(base_path('public/uploads/themes/'.$slug));
        return $root!==false && is_dir($root) ? $root : null;
    }

    public static function assetRoot(array $theme): string
    {
        $root=self::assetRootIfPresent($theme);
        if($root===null) throw new RuntimeException('Installed theme assets are missing.');
        return $root;
    }

    /** @return list<array<string,mixed>> */
    private static function preflight(array $theme,array $definition,array $resolved): array
    {
        $items=[];
        foreach(($definition['resources']??[]) as $resource){
            if(!is_array($resource))continue;
            $adapter=StarterResourceRegistry::adapter((string)$resource['type']);
            $result=$adapter->preflight($resource,self::context($theme,1,$resolved,null,0));
            $items[]=array_merge(['key'=>(string)$resource['key'],'type'=>(string)$resource['type'],'label'=>StarterResourceRegistry::label((string)$resource['type'])],$result);
        }
        return $items;
    }

    /** @param array<string,array<string,mixed>> $ownership @return array<string,array<string,mixed>> */
    private static function ownershipSnapshot(array $ownership,array $theme,int $userId): array
    {
        $out=[];$resolved=[];
        foreach($ownership as $key=>$row){
            if((string)($row['state']??'')==='owned')$resolved[$key]=self::resolvedRow($row,$row);
        }
        foreach($ownership as $key=>$row){
            $adapter=StarterResourceRegistry::adapter((string)$row['resource_type']);
            $snapshot=$adapter->snapshot($row,self::context($theme,max(1,$userId),$resolved,null,(int)$row['installation_id']));
            $out[$key]=array_merge($row,['exists'=>$snapshot!==null,'current_sha256'=>StarterSiteEngine::snapshotHash($snapshot)]);
        }
        return $out;
    }

    private static function requireActiveTheme(int $themeId): array
    {
        $theme=self::theme($themeId);
        $active=ThemeManager::active();
        if((int)($active['id']??0)!==$themeId) throw new RuntimeException('Activate this theme before installing or repairing its Starter Site.');
        return $theme;
    }

    private static function requireActor(int $userId): void { if($userId<1)throw new RuntimeException('Starter operation actor is missing.'); }

    /** @param array<string,array<string,mixed>> $resolved */
    private static function context(array $theme,int $userId,array $resolved,?StarterFilesystemJournal $journal,int $installationId): array
    {
        return ['theme'=>$theme,'theme_asset_root'=>self::assetRootIfPresent($theme),'user_id'=>$userId,'resolved'=>$resolved,'journal'=>$journal,'installation_id'=>$installationId];
    }

    private static function recordCreated(int $installationId,array $resource,array $created): void
    {
        StarterSiteRepository::recordResource(
            $installationId,(string)$resource['key'],(string)$resource['type'],$created['record_id']??null,$created['record_locator']??null,
            (string)($created['ownership_mode']??'created'),(string)$resource['definition_sha256'],(string)$created['baseline_sha256'],
            is_array($created['previous_state']??null)?$created['previous_state']:null
        );
    }

    private static function resolvedRow(array $resource,array $created): array
    {
        return [
            'resource_type'=>(string)($resource['type']??$resource['resource_type']??''),
            'record_id'=>$created['record_id']??null,
            'record_locator'=>$created['record_locator']??null,
        ];
    }

    private function __construct(){}
}
