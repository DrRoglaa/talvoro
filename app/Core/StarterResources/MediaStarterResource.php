<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\MediaLibrary;
use CMS\Core\StarterResourceAdapter;
use CMS\Core\StarterSite;

final class MediaStarterResource extends Support implements StarterResourceAdapter
{
    public function types(): array { return ['media']; }

    public function preflight(array $resource,array $context): array
    {
        return ['action'=>'create','message'=>'A new Media Library item will be imported.','ownership_mode'=>'created'];
    }

    public function create(array $resource,array $context): array
    {
        $data=self::data($resource);
        $theme=is_array($context['theme']??null)?$context['theme']:[];
        $root=(string)($context['theme_asset_root']??'');
        if($root==='') $root=StarterSite::assetRoot($theme);
        $id=MediaLibrary::importVerifiedThemeAsset(
            $root,
            (string)$data['source'],
            (string)($data['_asset_sha256']??''),
            self::userId($context),
            (string)($data['alt']??''),
            (string)($data['title']??''),
            (string)($data['caption']??''),
            (float)($data['focal_x']??50),
            (float)($data['focal_y']??50)
        );
        $asset=MediaLibrary::find($id) ?? [];
        $files=[];
        if(!empty($asset['storage_path'])) $files[]=(string)$asset['storage_path'];
        foreach(($asset['variants']??[]) as $variant) if(is_array($variant)&&!empty($variant['storage_path'])) $files[]=(string)$variant['storage_path'];
        self::journal($context)?->recordMany($files);
        $snapshot=$this->snapshot(['record_id'=>$id],$context) ?? [];
        return self::created($id,(string)($asset['storage_path']??''),$snapshot,null,$files);
    }

    public function snapshot(array $ownership,array $context): ?array
    {
        $id=(int)($ownership['record_id']??0); if($id<1) return null;
        $asset=MediaLibrary::find($id); if(!$asset) return null;
        $path=(string)($asset['storage_path']??'');
        $file=$path!==''?base_path('public'.$path):'';
        $fileHash=$file!==''&&is_file($file)?hash_file('sha256',$file):null;
        return [
            'storage_path'=>$path,
            'original_name'=>(string)($asset['original_name']??''),
            'title'=>(string)($asset['title']??''),
            'alt_text'=>(string)($asset['alt_text']??''),
            'caption'=>(string)($asset['caption']??''),
            'mime_type'=>(string)($asset['mime_type']??''),
            'width'=>(int)($asset['width']??0),'height'=>(int)($asset['height']??0),
            'focal_x'=>(float)($asset['focal_x']??50),'focal_y'=>(float)($asset['focal_y']??50),
            'file_sha256'=>is_string($fileHash)?$fileHash:'',
        ];
    }

    public function remove(array $ownership,array $context): array
    {
        $id=(int)($ownership['record_id']??0); if($id<1) return ['removed'=>true,'files'=>[]];
        $asset=MediaLibrary::find($id); if(!$asset) return ['removed'=>true,'files'=>[]];
        $files=[]; if(!empty($asset['storage_path']))$files[]=(string)$asset['storage_path'];
        foreach(($asset['variants']??[]) as $variant)if(is_array($variant)&&!empty($variant['storage_path']))$files[]=(string)$variant['storage_path'];
        try { MediaLibrary::delete($id); return ['removed'=>true,'files'=>$files]; }
        catch(\Throwable $e){ return ['removed'=>false,'files'=>[],'reason'=>$e->getMessage()]; }
    }

    public function restore(array $ownership,array $context): array { return ['restored'=>false,'reason'=>'Media starter resources are created records and have no pre-starter state.']; }
}
