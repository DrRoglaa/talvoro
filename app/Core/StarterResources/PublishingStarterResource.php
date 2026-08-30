<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\Categories;
use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\Database;
use CMS\Core\MediaLibrary;
use CMS\Core\PageBlocks;
use CMS\Core\Pages;
use CMS\Core\Posts;
use CMS\Core\SEO;
use CMS\Core\SiteAssets;
use CMS\Core\StarterReferences;
use CMS\Core\StarterResourceAdapter;
use PDO;
use RuntimeException;

final class PublishingStarterResource extends Support implements StarterResourceAdapter
{
    public function types(): array { return ['blog_category','post','page','seo']; }

    public function preflight(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);
        $db=Database::connection();

        if($type==='blog_category'){
            $stmt=$db->prepare('SELECT id FROM blog_categories WHERE slug=? LIMIT 1');
            $stmt->execute([(string)$data['slug']]);
            $id=(int)($stmt->fetchColumn()?:0);
            return $id>0
                ? ['action'=>'conflict','message'=>'A Journal category already uses slug '.(string)$data['slug'].'.','existing_id'=>$id]
                : ['action'=>'create','ownership_mode'=>'created'];
        }

        if($type==='post'){
            $stmt=$db->prepare('SELECT id FROM posts WHERE slug=? LIMIT 1');
            $stmt->execute([(string)$data['slug']]);
            $id=(int)($stmt->fetchColumn()?:0);
            return $id>0
                ? ['action'=>'conflict','message'=>'A Journal post already uses slug '.(string)$data['slug'].'.','existing_id'=>$id]
                : ['action'=>'create','ownership_mode'=>'created'];
        }

        if($type==='page'){
            $path=Pages::normalizePath((string)$data['path']);
            $stmt=$db->prepare('SELECT id,title,path FROM pages WHERE path=? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$path]);
            $existing=$stmt->fetch(PDO::FETCH_ASSOC)?:null;
            if(!$existing) return ['action'=>'create','ownership_mode'=>'created'];
            $replaceExisting=$path==='/' || (($data['replace_existing']??false)===true);
            if($replaceExisting){
                $title=$path==='/'?'Home page':'Page '.$path;
                return [
                    'action'=>'controlled_mutation',
                    'message'=>'The existing page will be replaced only after explicit Starter Site confirmation. Its current state is retained for ownership-safe removal.',
                    'existing_id'=>(int)$existing['id'],
                    'existing_locator'=>$path,
                    'ownership_mode'=>'mutated',
                    'change_title'=>$title,
                    'change_before'=>(string)$existing['title'],
                    'change_after'=>(string)$data['title'],
                    'change_note'=>'Previous page content will be restored when Demo Data is deleted, unless the starter version has been modified afterwards.',
                ];
            }
            return ['action'=>'conflict','message'=>'A page already uses path '.$path.'.','existing_id'=>(int)$existing['id'],'existing_locator'=>$path];
        }

        if($type==='seo'){
            $path=$this->seoPath($data,$context);
            $existing=SEO::get($path);
            if($existing){
                return [
                    'action'=>'controlled_mutation',
                    'message'=>'Existing SEO settings will be replaced for '.$path.' and preserved for ownership-safe removal.',
                    'existing_id'=>(int)($existing['id']??0),
                    'existing_locator'=>$path,
                    'ownership_mode'=>'mutated',
                    'change_title'=>'SEO · '.$path,
                    'change_before'=>(string)($existing['meta_title']??'Existing SEO'),
                    'change_after'=>(string)($data['title']??'Starter SEO'),
                    'change_note'=>'Current SEO metadata will be restored when Demo Data is deleted unless it is modified after installation.',
                ];
            }
            return ['action'=>'create','ownership_mode'=>'created','existing_locator'=>$path];
        }

        throw new RuntimeException('Unsupported publishing starter resource.');
    }

    public function create(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);

        if($type==='blog_category'){
            $validated=self::validation(Categories::validate([
                'name'=>(string)$data['name'],
                'slug'=>(string)$data['slug'],
                'description'=>(string)($data['description']??''),
                'status'=>'active',
                'sort_order'=>100,
            ]), 'Starter Journal category');
            $id=Categories::create($validated);
            return self::created($id,(string)$validated['slug'],$this->snapshot(['resource_type'=>$type,'record_id'=>$id],$context)??[]);
        }

        if($type==='post') return $this->createPost($resource,$context);
        if($type==='page') return $this->createPage($resource,$context);
        if($type==='seo') return $this->createSeo($resource,$context);

        throw new RuntimeException('Unsupported publishing starter resource.');
    }

    public function snapshot(array $ownership,array $context): ?array
    {
        $type=(string)($ownership['resource_type']??'');
        $id=(int)($ownership['record_id']??0);

        if($type==='blog_category'){
            $row=$id>0?Categories::find($id):null;
            if(!$row) return null;
            return [
                'name'=>(string)$row['name'],'slug'=>(string)$row['slug'],'description'=>(string)($row['description']??''),
                'seo_title'=>(string)($row['seo_title']??''),'meta_description'=>(string)($row['meta_description']??''),
                'status'=>(string)$row['status'],'sort_order'=>(int)$row['sort_order'],'is_default'=>(int)$row['is_default'],
            ];
        }

        if($type==='post'){
            $row=$id>0?Posts::find($id):null;
            if(!$row) return null;
            $categoryIds=[];
            foreach(($row['categories']??[]) as $category) if(is_array($category)&&isset($category['id'])) $categoryIds[]=(int)$category['id'];
            sort($categoryIds);
            return [
                'title'=>(string)$row['title'],'slug'=>(string)$row['slug'],'excerpt'=>(string)($row['excerpt']??''),
                'body_html'=>Posts::editorHtml($row),'status'=>(string)$row['status'],'published_at'=>(string)($row['published_at']??''),
                'featured_image_path'=>(string)($row['featured_image_path']??''),'category_ids'=>$categoryIds,
                'primary_category_id'=>(int)($row['primary_category_id']??0),
            ];
        }

        if($type==='page'){
            $row=$id>0?Pages::find($id):null;
            if(!$row) return null;
            return [
                'title'=>(string)$row['title'],'path'=>(string)$row['path'],'page_template'=>(string)($row['page_template']??'standard'),
                'excerpt'=>(string)($row['excerpt']??''),'eyebrow'=>(string)($row['eyebrow']??''),'body_html'=>(string)($row['body_html']??''),
                'blocks_json'=>(string)($row['blocks_json']??'[]'),'status'=>(string)$row['status'],
                'show_in_navigation'=>(int)($row['show_in_navigation']??0),'navigation_label'=>(string)($row['navigation_label']??''),
                'navigation_order'=>(int)($row['navigation_order']??100),'show_in_footer'=>(int)($row['show_in_footer']??0),
                'footer_label'=>(string)($row['footer_label']??''),'footer_order'=>(int)($row['footer_order']??100),
                'published_at'=>(string)($row['published_at']??''),
            ];
        }

        if($type==='seo'){
            $path=(string)($ownership['record_locator']??'');
            if($path==='') return null;
            $row=SEO::get($path);
            if(!$row) return null;
            return [
                'path'=>(string)$row['path'],'search_phrase'=>(string)($row['search_phrase']??''),'meta_title'=>(string)($row['meta_title']??''),
                'meta_description'=>(string)($row['meta_description']??''),'social_title'=>(string)($row['social_title']??''),
                'social_description'=>(string)($row['social_description']??''),'social_media_id'=>$row['social_media_id']===null?null:(int)$row['social_media_id'],
                'canonical_url'=>(string)($row['canonical_url']??''),'robots'=>(string)($row['robots']??'index,follow'),
                'sitemap_enabled'=>(int)($row['sitemap_enabled']??1),'schema_type'=>(string)($row['schema_type']??'WebPage'),
            ];
        }

        return null;
    }

    public function remove(array $ownership,array $context): array
    {
        $type=(string)($ownership['resource_type']??'');
        $id=(int)($ownership['record_id']??0);
        try{
            if($type==='blog_category'){
                if($id<1 || !Categories::find($id)) return ['removed'=>true];
                $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM blog_post_categories WHERE category_id=?');
                $stmt->execute([$id]);
                if((int)$stmt->fetchColumn()>0) return ['removed'=>false,'reason'=>'The starter category is still used by Journal posts and was preserved.'];
                Categories::delete($id);
                return ['removed'=>true];
            }
            if($type==='post'){
                if($id<1 || !Posts::find($id)) return ['removed'=>true];
                $row=Posts::find($id)??[];
                $files=ContentHistory::assetPathsForContent('post',$id);
                $featured=(string)($row['featured_image_path']??'');
                if($featured!=='') $files[]=$featured;
                ContentLifecycle::moveToTrash('post',$id,self::userId($context));
                ContentLifecycle::permanentlyDelete('post',$id);
                return ['removed'=>true,'files'=>array_values(array_unique($files))];
            }
            if($type==='page'){
                if($id<1 || !Pages::find($id)) return ['removed'=>true];
                $row=Pages::find($id)??[];
                if((string)($row['path']??'')==='/') return ['removed'=>false,'reason'=>'Home is a controlled mutation and must be restored, not deleted.'];
                $files=ContentHistory::assetPathsForContent('page',$id);
                foreach(PageBlocks::assetPaths(PageBlocks::decode((string)($row['blocks_json']??'[]'))) as $path) $files[]=$path;
                ContentLifecycle::moveToTrash('page',$id,self::userId($context));
                ContentLifecycle::permanentlyDelete('page',$id);
                return ['removed'=>true,'files'=>array_values(array_unique($files))];
            }
            if($type==='seo'){
                $path=(string)($ownership['record_locator']??'');
                if($path!=='') Database::connection()->prepare('DELETE FROM seo_pages WHERE path=?')->execute([$path]);
                return ['removed'=>true];
            }
        }catch(\Throwable $e){ return ['removed'=>false,'reason'=>$e->getMessage()]; }
        return ['removed'=>false,'reason'=>'Unsupported publishing starter resource.'];
    }

    public function restore(array $ownership,array $context): array
    {
        $type=(string)($ownership['resource_type']??'');
        $previous=$ownership['previous_state']??null;
        if(!is_array($previous)) return ['restored'=>false,'reason'=>'The pre-starter publishing snapshot is unavailable.'];
        try{
            if($type==='page'){
                $id=(int)($ownership['record_id']??0);
                $existing=$id>0?Pages::find($id):null;
                if(!$existing) return ['restored'=>false,'reason'=>'The replaced page no longer exists.'];
                $starterFiles=PageBlocks::assetPaths(PageBlocks::decode((string)($existing['blocks_json']??'[]')));
                $previousFiles=PageBlocks::assetPaths(PageBlocks::decode((string)($previous['blocks_json']??'[]')));
                $restore=[
                    'title'=>(string)$previous['title'],'path'=>(string)$previous['path'],'page_template'=>(string)$previous['page_template'],
                    'excerpt'=>(string)($previous['excerpt']??''),'eyebrow'=>(string)($previous['eyebrow']??''),
                    'body'=>(string)strip_tags((string)($previous['body_html']??'')),'body_html'=>(string)($previous['body_html']??''),
                    'blocks_json'=>(string)($previous['blocks_json']??'[]'),'status'=>(string)$previous['status'],
                    'show_in_navigation'=>(int)($previous['show_in_navigation']??0),'navigation_label'=>(string)($previous['navigation_label']??''),
                    'navigation_order'=>(int)($previous['navigation_order']??100),'show_in_footer'=>(int)($previous['show_in_footer']??0),
                    'footer_label'=>(string)($previous['footer_label']??''),'footer_order'=>(int)($previous['footer_order']??100),
                ];
                Pages::update($id,$restore,(string)($previous['published_at']??''));
                $files=array_values(array_unique(array_diff($starterFiles,$previousFiles)));
                return ['restored'=>true,'files'=>$files];
            }
            if($type==='seo'){
                $restore=$previous;
                $restore['path']=(string)($restore['path']??$ownership['record_locator']??'/');
                if((int)($restore['sitemap_enabled']??0)!==1) unset($restore['sitemap_enabled']);
                SEO::save($restore,self::userId($context));
                return ['restored'=>true];
            }
        }catch(\Throwable $e){ return ['restored'=>false,'reason'=>$e->getMessage()]; }
        return ['restored'=>false,'reason'=>'This publishing resource has no restorable pre-starter state.'];
    }

    private function createPost(array $resource,array $context): array
    {
        $data=self::data($resource);
        $categoryIds=[];
        foreach(($data['categories']??[]) as $ref) $categoryIds[]=self::refId($ref,$context,'blog_category');
        $input=[
            'title'=>(string)$data['title'],'slug'=>(string)$data['slug'],'excerpt'=>(string)($data['excerpt']??''),
            'body_html'=>(string)($data['body_html']??''),'status'=>(string)($data['status']??'published'),
            'category_ids'=>$categoryIds,
        ];
        if($categoryIds!==[]) $input['primary_category_id']=(string)$categoryIds[0];
        if(!empty($data['published_at'])) $input['published_at_local']=$this->publishedLocal((string)$data['published_at']);
        $validated=self::validation(Posts::validate($input,null,true),'Starter Journal post');

        $createdFiles=[];
        if(isset($data['featured_image'])){
            $mediaId=self::refId($data['featured_image'],$context,'media');
            $path=MediaLibrary::duplicateForUsage($mediaId,'post');
            $validated['featured_image_path']=$path;
            $createdFiles[]=$path;
            self::journal($context)?->record($path);
        }
        try{
            $id=Posts::create($validated,self::userId($context));
        }catch(\Throwable $e){
            foreach($createdFiles as $path) SiteAssets::remove($path);
            throw $e;
        }
        $snapshot=$this->snapshot(['resource_type'=>'post','record_id'=>$id],$context)??[];
        return self::created($id,'/blog/'.(string)$validated['slug'],$snapshot,null,$createdFiles);
    }

    private function createPage(array $resource,array $context): array
    {
        $data=self::data($resource);
        $path=Pages::normalizePath((string)$data['path']);
        $replaceExisting=$path==='/' || (($data['replace_existing']??false)===true);
        $existingId=0;
        if($replaceExisting){
            $stmt=Database::connection()->prepare('SELECT id FROM pages WHERE path=? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$path]);
            $existingId=(int)($stmt->fetchColumn()?:0);
        }
        $previous=$existingId>0?$this->snapshot(['resource_type'=>'page','record_id'=>$existingId],$context):null;

        $blocks=is_array($data['blocks']??null)?$data['blocks']:[];
        $blocks=$this->preparePageBlocks($blocks,$context);
        $validatedBlocks=PageBlocks::validateSubmitted(json_encode($blocks,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        if($validatedBlocks['errors']) throw new RuntimeException('Starter page blocks: '.implode(' ',$validatedBlocks['errors']));
        $uploaded=PageBlocks::applyUploads($validatedBlocks['blocks'],[],[],'page-block');
        foreach($uploaded['new_assets'] as $asset) self::journal($context)?->record($asset);

        $pageInput=[
            'title'=>(string)$data['title'],'path'=>$path,'path_mode'=>'manual','page_template'=>$path==='/'?'home':'standard',
            'excerpt'=>(string)($data['excerpt']??''),'eyebrow'=>(string)($data['eyebrow']??''),'body_html'=>(string)($data['body_html']??''),
            'status'=>(string)($data['status']??'published'),'navigation_placement'=>(string)($data['navigation_placement']??'hidden'),
            'navigation_label'=>(string)($data['navigation_label']??''),'navigation_order'=>(int)($data['navigation_order']??100),
            'footer_label'=>(string)($data['footer_label']??''),'footer_order'=>(int)($data['footer_order']??100),
            'page_blocks_json'=>json_encode($uploaded['blocks'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
        ];
        $validated=self::validation(Pages::validate($pageInput,$existingId>0?$existingId:null,true),'Starter page');
        try{
            if($existingId>0){
                $existing=Pages::find($existingId)??[];
                Pages::update($existingId,$validated,(string)($existing['published_at']??''));
                $id=$existingId;
            }else $id=Pages::create($validated,self::userId($context));
        }catch(\Throwable $e){
            foreach($uploaded['new_assets'] as $asset) SiteAssets::remove($asset);
            throw $e;
        }
        $snapshot=$this->snapshot(['resource_type'=>'page','record_id'=>$id],$context)??[];
        return self::created($id,$path,$snapshot,$previous,$uploaded['new_assets']);
    }

    private function createSeo(array $resource,array $context): array
    {
        $data=self::data($resource);
        $path=$this->seoPath($data,$context);
        $previous=$this->snapshot(['resource_type'=>'seo','record_locator'=>$path],$context);
        $input=[
            'path'=>$path,'meta_title'=>(string)($data['title']??''),'meta_description'=>(string)($data['description']??''),
            'social_title'=>(string)($data['title']??''),'social_description'=>(string)($data['description']??''),
            'canonical_url'=>(string)($data['canonical_url']??''),'robots'=>(string)($data['robots']??'index,follow'),
            'sitemap_enabled'=>'1','schema_type'=>$path==='/contact'?'ContactPage':($path==='/our-story'?'AboutPage':'WebPage'),
        ];
        if(isset($data['social_image'])) $input['social_media_id']=self::refId($data['social_image'],$context,'media');
        $errors=SEO::validate($input);
        if($errors) throw new RuntimeException('Starter SEO: '.implode(' ',$errors));
        SEO::save($input,self::userId($context));
        $row=SEO::get($path)??[];
        $id=(int)($row['id']??0);
        return self::created($id>0?$id:null,$path,$this->snapshot(['resource_type'=>'seo','record_id'=>$id,'record_locator'=>$path],$context)??[],$previous);
    }

    private function seoPath(array $data,array $context): string
    {
        $raw=$data['path']??'/';
        if(StarterReferences::key($raw)!==null) return self::refLocator($raw,$context,['page','post','content_entry']);
        return Pages::normalizePath((string)$raw);
    }

    /** @param list<array<string,mixed>> $blocks @return list<array<string,mixed>> */
    private function preparePageBlocks(array $blocks,array $context): array
    {
        $walk=function(mixed $value,?string $field=null) use (&$walk,$context): mixed {
            $ref=StarterReferences::key($value);
            if($ref!==null){
                $resolved=StarterReferences::resolved($value,self::resolved($context));
                $type=(string)($resolved['resource_type']??'');
                if($type==='content_model' && $field==='model_key') return (string)($resolved['record_locator']??'');
                if(in_array($type,['page','post','content_entry'],true)) return (string)($resolved['record_locator']??'');
                if($type==='media') return (int)($resolved['record_id']??0);
                throw new RuntimeException('Unsupported Page Builder starter reference '.$ref.'.');
            }
            if(!is_array($value)) return $value;
            $out=[];
            foreach($value as $key=>$child){
                if($key==='image_path' && StarterReferences::key($child)!==null){
                    $resolved=StarterReferences::resolved($child,self::resolved($context),'media');
                    $out['image_path']='';
                    $out['_media_id']=(int)$resolved['record_id'];
                    continue;
                }
                $out[$key]=$walk($child,is_string($key)?$key:null);
            }
            return $out;
        };
        $resolved=$walk($blocks);
        return is_array($resolved)?array_values($resolved):[];
    }

    private function publishedLocal(string $value): string
    {
        try{
            $dt=new \DateTimeImmutable($value);
            return $dt->setTimezone(new \DateTimeZone((string)(\CMS\Core\Env::get('APP_TIMEZONE','Europe/Ljubljana')?:'Europe/Ljubljana')))->format('Y-m-d\\TH:i');
        }catch(\Throwable){ return ''; }
    }
}
