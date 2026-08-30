<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\ContentLifecycle;
use CMS\Core\ContentModels;
use CMS\Core\CustomContent;
use CMS\Core\Database;
use CMS\Core\StarterReferences;
use CMS\Core\StarterResourceAdapter;
use PDO;
use RuntimeException;

final class StructuredContentStarterResource extends Support implements StarterResourceAdapter
{
    public function types(): array { return ['content_component','component_field','content_model','content_field','content_entry']; }

    public function preflight(array $resource,array $context): array
    {
        $type=(string)($resource['type']??''); $data=self::data($resource); $db=Database::connection();
        if($type==='content_component'){
            $s=$db->prepare('SELECT id FROM content_components WHERE slug=? LIMIT 1'); $s->execute([(string)$data['slug']]);
            if($s->fetchColumn()) return ['action'=>'conflict','message'=>'A Structured Content component already uses slug '.(string)$data['slug'].'.'];
        }
        if($type==='content_model'){
            $s=$db->prepare('SELECT id,model_key,slug FROM content_models WHERE LOWER(model_key)=LOWER(?) OR slug=? LIMIT 1'); $s->execute([(string)$data['model_key'],(string)$data['slug']]);
            if($s->fetch()) return ['action'=>'conflict','message'=>'A Structured Content model already uses key or URL base '.(string)$data['model_key'].'.'];
        }
        if($type==='component_field'){
            $parent=StarterReferences::key($data['component']??null); $resolved=self::resolved($context);
            if($parent!==null && isset($resolved[$parent])){
                $componentId=(int)$resolved[$parent]['record_id'];
                $s=$db->prepare('SELECT id FROM component_fields WHERE component_id=? AND field_key=? AND archived_at IS NULL LIMIT 1'); $s->execute([$componentId,(string)$data['field_key']]);
                if($s->fetchColumn()) return ['action'=>'conflict','message'=>'The component already contains field '.(string)$data['field_key'].'.'];
            }
        }
        if($type==='content_field'){
            $parent=StarterReferences::key($data['model']??null); $resolved=self::resolved($context);
            if($parent!==null && isset($resolved[$parent])){
                $modelId=(int)$resolved[$parent]['record_id'];
                if(ContentModels::fieldByKey($modelId,(string)$data['field_key'])) return ['action'=>'conflict','message'=>'The content model already contains field '.(string)$data['field_key'].'.'];
            }
        }
        if($type==='content_entry'){
            $parent=StarterReferences::key($data['model']??null); $resolved=self::resolved($context);
            if($parent!==null && isset($resolved[$parent])){
                $modelId=(int)$resolved[$parent]['record_id'];
                $s=$db->prepare('SELECT id FROM content_entries WHERE model_id=? AND slug=? AND deleted_at IS NULL LIMIT 1'); $s->execute([$modelId,(string)$data['slug']]);
                if($s->fetchColumn()) return ['action'=>'conflict','message'=>'Structured Content already contains slug '.(string)$data['slug'].'.'];
            }
        }
        return ['action'=>'create','ownership_mode'=>'created'];
    }

    public function create(array $resource,array $context): array
    {
        $type=(string)$resource['type']; $data=self::data($resource); $userId=self::userId($context); $id=0; $locator=null;
        if($type==='content_component'){
            $input=['name'=>(string)$data['name'],'slug'=>(string)$data['slug'],'description'=>(string)($data['description']??''),'status'=>!array_key_exists('is_active',$data)||!empty($data['is_active'])?'active':'disabled'];
            $clean=self::validation(ContentModels::validateComponent($input),'Starter component');
            $id=ContentModels::saveComponent($clean,$userId); $locator=(string)$clean['slug'];
        } elseif($type==='component_field'){
            $componentId=self::refId($data['component']??null,$context,'content_component');
            $input=$this->fieldInput($data,$context,false);
            $clean=self::validation(ContentModels::validateComponentField($input,$componentId),'Starter component field');
            $id=ContentModels::saveComponentField($componentId,$clean); $locator=$componentId.':'.(string)$clean['field_key'];
        } elseif($type==='content_model'){
            $input=['singular_name'=>(string)$data['singular_name'],'plural_name'=>(string)$data['plural_name'],'model_key'=>(string)$data['model_key'],'slug'=>(string)$data['slug'],'description'=>(string)($data['description']??''),'icon'=>(string)($data['icon']??'collection'),'status'=>!array_key_exists('is_active',$data)||!empty($data['is_active'])?'active':'disabled'];
            $input=self::boolInput($data,['is_public','has_archive','has_urls','searchable','sitemap_enabled','enable_revisions','enable_autosave','enable_trash','enable_seo','enable_featured_image','enable_scheduling'],$input);
            $clean=self::validation(ContentModels::validateModel($input),'Starter content model');
            $id=ContentModels::createModel($clean,$userId); $locator=(string)$clean['model_key'];
        } elseif($type==='content_field'){
            $modelId=self::refId($data['model']??null,$context,'content_model');
            $input=$this->fieldInput($data,$context,true);
            $clean=self::validation(ContentModels::validateField($input,$modelId),'Starter content field');
            $id=ContentModels::saveField($modelId,$clean); $locator=$modelId.':'.(string)$clean['field_key'];
        } elseif($type==='content_entry'){
            $modelId=self::refId($data['model']??null,$context,'content_model'); $model=ContentModels::find($modelId);
            if(!$model) throw new RuntimeException('Starter content entry model is missing.');
            $values=StarterReferences::resolveValueTree((array)($data['values']??[]),self::resolved($context),['media','content_entry']);
            $seo=is_array($data['seo']??null)?$data['seo']:[];
            $input=['title'=>(string)$data['title'],'slug'=>(string)$data['slug'],'status'=>(string)($data['status']??'draft'),'fields'=>$values,
                'seo_title'=>(string)($seo['title']??''),'seo_description'=>(string)($seo['description']??''),'canonical_url'=>(string)($seo['canonical_url']??''),'robots'=>(string)($seo['robots']??'index,follow'),
                'social_title'=>(string)($seo['social_title']??''),'social_description'=>(string)($seo['social_description']??'')];
            if(isset($data['featured_image'])) $input['featured_media_id']=self::refId($data['featured_image'],$context,'media');
            if(isset($seo['social_image'])) $input['social_media_id']=self::refId($seo['social_image'],$context,'media');
            $clean=self::validation(CustomContent::validateEntry($input,$model,null,true),'Starter content entry');
            $id=CustomContent::create($clean,$userId); $entry=CustomContent::find($id)??[]; $locator=CustomContent::publicUrl($model,$entry);
        } else throw new RuntimeException('Unsupported Structured Content starter resource.');
        $snapshot=$this->snapshot(['record_id'=>$id,'resource_type'=>$type,'record_locator'=>$locator],$context)??[];
        return self::created($id,$locator,$snapshot);
    }

    public function snapshot(array $ownership,array $context): ?array
    {
        $type=(string)($ownership['resource_type']??''); $id=(int)($ownership['record_id']??0); if($id<1)return null;
        if($type==='content_component'){
            $row=ContentModels::component($id); if(!$row)return null; return $this->pick($row,['name','slug','description','status']);
        }
        if($type==='component_field'){
            $s=Database::connection()->prepare('SELECT * FROM component_fields WHERE id=? LIMIT 1');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
            return ['component_id'=>(int)$row['component_id'],'field_key'=>(string)$row['field_key'],'label'=>(string)$row['label'],'field_type'=>(string)$row['field_type'],'help_text'=>(string)($row['help_text']??''),'placeholder'=>(string)($row['placeholder']??''),'is_required'=>(int)$row['is_required'],'settings'=>ContentModels::decodeSettings($row['settings_json']??null),'sort_order'=>(int)$row['sort_order']];
        }
        if($type==='content_model'){
            $row=ContentModels::find($id);if(!$row)return null;return $this->pick($row,['singular_name','plural_name','model_key','slug','icon','description','status','is_public','has_archive','has_urls','searchable','sitemap_enabled','enable_revisions','enable_autosave','enable_trash','enable_seo','enable_featured_image','enable_scheduling']);
        }
        if($type==='content_field'){
            $s=Database::connection()->prepare('SELECT * FROM content_fields WHERE id=? LIMIT 1');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)return null;
            return ['model_id'=>(int)$row['model_id'],'field_key'=>(string)$row['field_key'],'label'=>(string)$row['label'],'field_type'=>(string)$row['field_type'],'help_text'=>(string)($row['help_text']??''),'placeholder'=>(string)($row['placeholder']??''),'is_required'=>(int)$row['is_required'],'settings'=>ContentModels::decodeSettings($row['settings_json']??null),'sort_order'=>(int)$row['sort_order']];
        }
        if($type==='content_entry'){
            $row=CustomContent::rawFind($id,false);if(!$row)return null;
            return ['model_id'=>(int)$row['model_id'],'title'=>(string)$row['title'],'slug'=>(string)$row['slug'],'status'=>(string)$row['status'],'field_values'=>CustomContent::decodedValues((string)$row['field_values_json']),'featured_media_id'=>$row['featured_media_id']===null?null:(int)$row['featured_media_id'],'seo_title'=>(string)($row['seo_title']??''),'seo_description'=>(string)($row['seo_description']??''),'canonical_url'=>(string)($row['canonical_url']??''),'robots'=>(string)($row['robots']??''),'social_title'=>(string)($row['social_title']??''),'social_description'=>(string)($row['social_description']??''),'social_media_id'=>$row['social_media_id']===null?null:(int)$row['social_media_id']];
        }
        return null;
    }

    public function remove(array $ownership,array $context): array
    {
        $type=(string)($ownership['resource_type']??''); $id=(int)($ownership['record_id']??0); if($id<1)return ['removed'=>true];
        try{
            if($type==='content_entry'){ if(!CustomContent::rawFind($id,false))return ['removed'=>true]; ContentLifecycle::moveToTrash('entry',$id,self::userId($context)); ContentLifecycle::permanentlyDelete('entry',$id); }
            elseif($type==='content_field'){ $s=Database::connection()->prepare('SELECT model_id FROM content_fields WHERE id=? LIMIT 1');$s->execute([$id]);$mid=(int)($s->fetchColumn()?:0);if($mid>0)ContentModels::deleteField($mid,$id); }
            elseif($type==='component_field'){ $s=Database::connection()->prepare('SELECT component_id FROM component_fields WHERE id=? LIMIT 1');$s->execute([$id]);$cid=(int)($s->fetchColumn()?:0);if($cid>0)ContentModels::deleteComponentField($cid,$id); }
            elseif($type==='content_model'){ if(ContentModels::find($id))ContentModels::deleteModel($id); }
            elseif($type==='content_component'){ if(ContentModels::component($id))ContentModels::deleteComponent($id); }
            return ['removed'=>true];
        }catch(\Throwable $e){return ['removed'=>false,'reason'=>$e->getMessage()];}
    }

    public function restore(array $ownership,array $context): array { return ['restored'=>false,'reason'=>'Structured starter resources are created records and have no pre-starter state.']; }

    private function fieldInput(array $data,array $context,bool $allowStructural): array
    {
        $input=['field_key'=>(string)$data['field_key'],'label'=>(string)$data['label'],'field_type'=>(string)$data['field_type'],'help_text'=>(string)($data['help_text']??''),'placeholder'=>(string)($data['placeholder']??''),'sort_order'=>(int)($data['sort_order']??100)];
        if(!empty($data['is_required']))$input['is_required']='1';
        $settings=is_array($data['settings']??null)?$data['settings']:[];
        foreach(['unique','searchable'] as $flag)if(!empty($settings[$flag]))$input[$flag]='1';
        foreach(['default_value','relation_type','min','max','step','max_length'] as $key)if(array_key_exists($key,$settings))$input[$key]=$settings[$key];
        if(isset($settings['options'])&&is_array($settings['options']))$input['options']=implode("\n",array_map('strval',$settings['options']));
        if($allowStructural && isset($settings['target_model_id']))$input['target_model_id']=StarterReferences::key($settings['target_model_id'])!==null?self::refId($settings['target_model_id'],$context,'content_model'):(int)$settings['target_model_id'];
        if($allowStructural && isset($settings['target_model']))$input['target_model_id']=self::refId($settings['target_model'],$context,'content_model');
        if($allowStructural && isset($settings['component_id']))$input['component_id']=StarterReferences::key($settings['component_id'])!==null?self::refId($settings['component_id'],$context,'content_component'):(int)$settings['component_id'];
        if($allowStructural && isset($settings['component']))$input['component_id']=self::refId($settings['component'],$context,'content_component');
        return $input;
    }

    private function pick(array $row,array $keys): array { $out=[];foreach($keys as $k)$out[$k]=is_numeric($row[$k]??null)?(int)$row[$k]:(string)($row[$k]??'');return $out; }
}
