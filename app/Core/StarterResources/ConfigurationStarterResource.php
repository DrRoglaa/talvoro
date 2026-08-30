<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\Database;
use CMS\Core\DesignSystem;
use CMS\Core\Settings;
use CMS\Core\StarterResourceAdapter;
use CMS\Core\StarterSite;
use PDO;
use RuntimeException;

final class ConfigurationStarterResource extends Support implements StarterResourceAdapter
{
    public function types(): array { return ['setting','theme_design']; }

    public function preflight(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);
        if($type==='setting'){
            $key=(string)$data['key'];
            if(!StarterSite::starterSettingAllowed($key)) throw new RuntimeException('Starter setting is not allowlisted: '.$key.'.');
            $current=$this->settingSnapshot($key);
            $next=$this->settingValue($key,$data['value']??null);
            return [
                'action'=>'controlled_mutation',
                'message'=>'This starter will change an allowlisted site setting and preserve its previous value.',
                'existing_locator'=>$key,
                'ownership_mode'=>'mutated',
                'change_title'=>$this->settingLabel($key),
                'change_before'=>!empty($current['exists'])?(string)($current['value']??''):'Not set',
                'change_after'=>$next===null?'Not set':$next,
                'change_note'=>'The previous site setting will be restored when Demo Data is deleted unless it is changed after installation.',
            ];
        }
        if($type==='theme_design'){
            return [
                'action'=>'controlled_mutation',
                'message'=>'This starter will apply design tokens to this theme only and preserve the previous theme design.',
                'existing_locator'=>$this->themeSlug($context),
                'ownership_mode'=>'mutated',
                'change_title'=>'Theme design',
                'change_before'=>'Current theme design',
                'change_after'=>'Starter theme design',
                "change_note"=>"Only this theme's design tokens change; the previous design is preserved for Demo Data removal.",
            ];
        }
        throw new RuntimeException('Unsupported configuration starter resource.');
    }

    public function create(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);
        if($type==='setting'){
            $key=(string)$data['key'];
            if(!StarterSite::starterSettingAllowed($key)) throw new RuntimeException('Starter setting is not allowlisted: '.$key.'.');
            $previous=$this->settingSnapshot($key);
            $value=$this->settingValue($key,$data['value']??null);
            Settings::set($key,$value,self::userId($context));
            $snapshot=$this->settingSnapshot($key);
            return self::created(null,$key,$snapshot,$previous);
        }
        if($type==='theme_design'){
            $slug=$this->themeSlug($context);
            $previous=['slug'=>$slug,'values'=>DesignSystem::valuesForTheme($slug)];
            $values=is_array($data['values']??null)?$data['values']:[];
            DesignSystem::saveForTheme($slug,$values,self::userId($context));
            $snapshot=['slug'=>$slug,'values'=>DesignSystem::valuesForTheme($slug)];
            return self::created(null,$slug,$snapshot,$previous);
        }
        throw new RuntimeException('Unsupported configuration starter resource.');
    }

    public function snapshot(array $ownership,array $context): ?array
    {
        $type=(string)($ownership['resource_type']??'');
        if($type==='setting'){
            $key=(string)($ownership['record_locator']??'');
            return $key!==''?$this->settingSnapshot($key):null;
        }
        if($type==='theme_design'){
            $slug=(string)($ownership['record_locator']??$this->themeSlug($context));
            return ['slug'=>$slug,'values'=>DesignSystem::valuesForTheme($slug)];
        }
        return null;
    }

    public function remove(array $ownership,array $context): array
    {
        return ['removed'=>false,'reason'=>'Configuration starter resources are controlled mutations and must be restored or detached.'];
    }

    public function restore(array $ownership,array $context): array
    {
        $type=(string)($ownership['resource_type']??'');
        $previous=$ownership['previous_state']??null;
        if(!is_array($previous)) return ['restored'=>false,'reason'=>'The pre-starter configuration snapshot is unavailable.'];
        try{
            if($type==='setting'){
                $key=(string)($ownership['record_locator']??'');
                if($key==='' || !StarterSite::starterSettingAllowed($key)) return ['restored'=>false,'reason'=>'The starter setting key is invalid.'];
                if(!empty($previous['exists'])) Settings::set($key,$previous['value']===null?null:(string)$previous['value'],self::userId($context));
                else Settings::forget($key);
                return ['restored'=>true];
            }
            if($type==='theme_design'){
                $slug=(string)($previous['slug']??$ownership['record_locator']??'');
                $values=$previous['values']??null;
                if($slug==='' || !is_array($values)) return ['restored'=>false,'reason'=>'The pre-starter theme design snapshot is invalid.'];
                DesignSystem::saveForTheme($slug,$values,self::userId($context));
                return ['restored'=>true];
            }
        }catch(\Throwable $e){ return ['restored'=>false,'reason'=>$e->getMessage()]; }
        return ['restored'=>false,'reason'=>'Unsupported configuration starter resource.'];
    }

    private function settingSnapshot(string $key): array
    {
        $stmt=Database::connection()->prepare('SELECT setting_value FROM cms_settings WHERE setting_key=? LIMIT 1');
        $stmt->execute([$key]);
        $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return ['key'=>$key,'exists'=>$row!==false,'value'=>$row===false?null:$row['setting_value']];
    }

    private function settingLabel(string $key): string
    {
        return match($key){
            'branding.site_name'=>'Site name',
            'branding.tagline'=>'Site tagline',
            'branding.footer_text'=>'Footer text',
            'branding.footer_note'=>'Footer note',
            'blog.enabled'=>'Journal visibility',
            'blog.archive_title'=>'Journal title',
            'blog.archive_intro'=>'Journal introduction',
            default=>$key,
        };
    }

    private function settingValue(string $key,mixed $value): ?string
    {
        if($key==='blog.enabled') return !empty($value)&&$value!=='0'?'1':'0';
        $text=trim((string)$value);
        $limit=$key==='blog.archive_intro'?500:255;
        if(mb_strlen($text)>$limit) throw new RuntimeException('Starter site text setting exceeds its supported length.');
        return $text;
    }

    private function themeSlug(array $context): string
    {
        $theme=is_array($context['theme']??null)?$context['theme']:[];
        $slug=trim((string)($theme['slug']??''));
        if($slug==='') throw new RuntimeException('Starter theme context is missing.');
        return $slug;
    }
}
