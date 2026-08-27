<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\PageBlocks;
use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\ContentModels;
use CMS\Core\ContentModelStarters;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\Response;
use CMS\Core\View;

final class ContentModelController
{
    public static function index(): Response
    {
        if ($r=self::requirePermission()) return $r;
        $models=ContentModels::all();
        return new Response(View::render('admin/content-models/index', [
            'title'=>'Content models','models'=>$models,'components'=>ContentModels::components(),'starterModels'=>ContentModelStarters::catalog($models),
            'created'=>isset($_GET['created']),'deleted'=>isset($_GET['deleted']),'componentCreated'=>isset($_GET['component_created']),
        ]));
    }

    public static function newModel(): Response
    {
        if ($r=self::requirePermission()) return $r;
        return self::modelForm(null, [], []);
    }

    public static function installStarterModel(): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $key=trim((string)($_POST['starter_key']??''));
        try {
            $user=Auth::user();
            $id=ContentModelStarters::install($key,(int)$user['id']);
            Audit::log('content_model.starter_install','content_model',$id,['starter'=>$key]);
            return Response::redirect(AdminPath::baseUrl().'/content-models/'.$id.'/edit?starter_installed=1');
        } catch (\Throwable $e) {
            return self::message('Could not install starter model',$e->getMessage(),422);
        }
    }

    public static function createModel(): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $validated=ContentModels::validateModel($_POST,null);
        if ($validated['errors']) return self::modelForm(null,$validated['data'],$validated['errors'],422);
        try {
            $user=Auth::user(); $id=ContentModels::createModel($validated['data'],(int)$user['id']);
            Audit::log('content_model.create','content_model',$id,['slug'=>$validated['data']['slug']]);
            return Response::redirect(AdminPath::baseUrl().'/content-models/'.$id.'/edit?created=1');
        } catch (\Throwable $e) { return self::modelForm(null,$validated['data'],[$e->getMessage()],422); }
    }

    public static function editModel(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        $modelId=self::id($id); $model=$modelId?ContentModels::find($modelId):null;
        if (!$model) return self::notFound('Content model not found');
        return self::modelForm($modelId,$model,[],200);
    }

    public static function updateModel(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $modelId=self::id($id); $model=$modelId?ContentModels::find($modelId):null;
        if (!$model) return self::notFound('Content model not found');
        $validated=ContentModels::validateModel($_POST,$modelId);
        if ($validated['errors']) return self::modelForm($modelId,array_merge($model,$validated['data']),$validated['errors'],422);
        try {
            ContentModels::updateModel($modelId,$validated['data']);
            if (is_array($_POST['model_permissions']??null)) ContentModels::saveModelRolePermissions($modelId,$_POST['model_permissions']);
            Audit::log('content_model.update','content_model',$modelId,['slug'=>$validated['data']['slug']]);
            return Response::redirect(AdminPath::baseUrl().'/content-models/'.$modelId.'/edit?saved=1');
        } catch (\Throwable $e) { return self::modelForm($modelId,array_merge($model,$validated['data']),[$e->getMessage()],422); }
    }

    public static function deleteModel(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if (($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm permanent deletion of this empty content model.',422);
        $modelId=self::id($id); $model=$modelId?ContentModels::find($modelId):null;
        if (!$model) return self::notFound('Content model not found');
        try { ContentModels::deleteModel($modelId); Audit::log('content_model.delete','content_model',$modelId,['slug'=>$model['slug']]); }
        catch (\Throwable $e) { return self::message('Could not delete content model',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models?deleted=1');
    }

    public static function newField(string $id): Response { return self::fieldForm($id,null,[],[]); }
    public static function editField(string $id,string $field): Response { return self::fieldForm($id,$field,[],[]); }

    public static function createField(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $modelId=self::id($id); $model=$modelId?ContentModels::find($modelId):null;
        if (!$model) return self::notFound('Content model not found');
        $v=ContentModels::validateField($_POST,$modelId,null);
        if ($v['errors']) return self::fieldForm($id,null,$v['data'],$v['errors'],422);
        try { $fid=ContentModels::saveField($modelId,$v['data']); Audit::log('content_field.create','content_field',$fid,['model_id'=>$modelId]); }
        catch (\Throwable $e) { return self::fieldForm($id,null,$v['data'],[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models/'.$modelId.'/edit?field_created=1');
    }

    public static function updateField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $modelId=self::id($id); $fieldId=self::id($field);
        if (!$modelId||!$fieldId||!ContentModels::field($modelId,$fieldId)) return self::notFound('Field not found');
        $v=ContentModels::validateField($_POST,$modelId,$fieldId);
        if ($v['errors']) return self::fieldForm($id,$field,$v['data'],$v['errors'],422);
        try { ContentModels::saveField($modelId,$v['data'],$fieldId); Audit::log('content_field.update','content_field',$fieldId,['model_id'=>$modelId]); }
        catch (\Throwable $e) { return self::fieldForm($id,$field,$v['data'],[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models/'.$modelId.'/edit?field_saved=1');
    }

    public static function deleteField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $modelId=self::id($id); $fieldId=self::id($field);
        $existing=$modelId&&$fieldId?ContentModels::field($modelId,$fieldId):null;
        if (!$existing) return self::notFound('Field not found');
        $count=ContentModels::fieldDataCount($modelId,$fieldId);
        if ($count>0 && ($_POST['confirm_archive']??'')!=='1') {
            return self::message('Archive not confirmed',$count . ($count===1 ? ' record currently contains data in this field. Confirm that you want to archive it. Existing data will be preserved.' : ' records currently contain data in this field. Confirm that you want to archive it. Existing data will be preserved.'),422);
        }
        if ($count===0 && ($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm deletion of this unused field.',422);
        try { ContentModels::deleteField($modelId,$fieldId); Audit::log($count>0?'content_field.archive':'content_field.delete','content_field',$fieldId,['model_id'=>$modelId,'records_with_data'=>$count]); }
        catch (\Throwable $e) { return self::message('Could not remove field',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models/'.$modelId.'/edit?'.($count>0?'field_archived=1':'field_deleted=1'));
    }

    public static function restoreField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $modelId=self::id($id); $fieldId=self::id($field);
        $existing=$modelId&&$fieldId?ContentModels::field($modelId,$fieldId):null;
        if (!$existing || empty($existing['archived_at'])) return self::notFound('Archived field not found');
        try { ContentModels::restoreField($modelId,$fieldId); Audit::log('content_field.restore','content_field',$fieldId,['model_id'=>$modelId]); }
        catch (\Throwable $e) { return self::message('Could not restore field',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models/'.$modelId.'/edit?field_restored=1');
    }

    public static function reorderFields(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::json(['ok'=>false,'error'=>'csrf'],419);
        $modelId=self::id($id); if (!$modelId||!ContentModels::find($modelId)) return self::json(['ok'=>false,'error'=>'not_found'],404);
        $order=is_array($_POST['order']??null)?array_map('intval',$_POST['order']):[];
        try { ContentModels::reorderFields($modelId,$order); Audit::log('content_field.reorder','content_model',$modelId,['order'=>$order]); return self::json(['ok'=>true]); }
        catch (\Throwable $e) { return self::json(['ok'=>false,'error'=>$e->getMessage()],422); }
    }

    public static function newComponent(): Response { return self::componentForm(null,[],[]); }
    public static function editComponent(string $id): Response { return self::componentForm($id,[],[]); }

    public static function createComponent(): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $v=ContentModels::validateComponent($_POST,null);
        if ($v['errors']) return self::componentForm(null,$v['data'],$v['errors'],422);
        try { $uid=(int)Auth::user()['id']; $id=ContentModels::saveComponent($v['data'],$uid); Audit::log('content_component.create','content_component',$id); }
        catch (\Throwable $e) { return self::componentForm(null,$v['data'],[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$id.'/edit?created=1');
    }

    public static function updateComponent(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $cid=self::id($id); $component=$cid?ContentModels::component($cid):null;
        if (!$component) return self::notFound('Component not found');
        $v=ContentModels::validateComponent($_POST,$cid);
        if ($v['errors']) return self::componentForm($id,array_merge($component,$v['data']),$v['errors'],422);
        try { ContentModels::saveComponent($v['data'],(int)Auth::user()['id'],$cid); Audit::log('content_component.update','content_component',$cid); }
        catch (\Throwable $e) { return self::componentForm($id,array_merge($component,$v['data']),[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$cid.'/edit?saved=1');
    }

    public static function deleteComponent(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if (($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm permanent deletion of this unused component.',422);
        $cid=self::id($id); $component=$cid?ContentModels::component($cid):null;
        if (!$component) return self::notFound('Component not found');
        try { ContentModels::deleteComponent($cid); Audit::log('content_component.delete','content_component',$cid); }
        catch (\Throwable $e) { return self::message('Could not delete component',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content-models?component_deleted=1');
    }

    public static function newComponentField(string $id): Response { return self::componentFieldForm($id,null,[],[]); }
    public static function editComponentField(string $id,string $field): Response { return self::componentFieldForm($id,$field,[],[]); }

    public static function createComponentField(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $cid=self::id($id); if (!$cid||!ContentModels::component($cid)) return self::notFound('Component not found');
        $v=ContentModels::validateComponentField($_POST,$cid,null);
        if ($v['errors']) return self::componentFieldForm($id,null,$v['data'],$v['errors'],422);
        try { $fid=ContentModels::saveComponentField($cid,$v['data']); Audit::log('component_field.create','component_field',$fid,['component_id'=>$cid]); }
        catch (\Throwable $e) { return self::componentFieldForm($id,null,$v['data'],[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$cid.'/edit?field_created=1');
    }

    public static function updateComponentField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $cid=self::id($id); $fid=self::id($field); if (!$cid||!$fid||!ContentModels::componentField($cid,$fid)) return self::notFound('Component field not found');
        $v=ContentModels::validateComponentField($_POST,$cid,$fid);
        if ($v['errors']) return self::componentFieldForm($id,$field,$v['data'],$v['errors'],422);
        try { ContentModels::saveComponentField($cid,$v['data'],$fid); Audit::log('component_field.update','component_field',$fid,['component_id'=>$cid]); }
        catch (\Throwable $e) { return self::componentFieldForm($id,$field,$v['data'],[$e->getMessage()],422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$cid.'/edit?field_saved=1');
    }

    public static function deleteComponentField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $cid=self::id($id); $fid=self::id($field);
        $component=$cid?ContentModels::component($cid):null; $existing=$component&&$fid?ContentModels::componentField($cid,$fid):null;
        if (!$component||!$existing) return self::notFound('Component field not found');
        $archive=ContentModels::componentInUse($cid);
        if ($archive && ($_POST['confirm_archive']??'')!=='1') return self::message('Archive not confirmed','This component is already used by a content model. Confirm that you want to archive the field while preserving existing structured values.',422);
        if (!$archive && ($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm deletion of this unused component field.',422);
        try { ContentModels::deleteComponentField($cid,$fid); Audit::log($archive?'component_field.archive':'component_field.delete','component_field',$fid,['component_id'=>$cid]); }
        catch (\Throwable $e) { return self::message('Could not remove component field',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$cid.'/edit?'.($archive?'field_archived=1':'field_deleted=1'));
    }

    public static function restoreComponentField(string $id,string $field): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $cid=self::id($id); $fid=self::id($field); $existing=$cid&&$fid?ContentModels::componentField($cid,$fid):null;
        if (!$existing||empty($existing['archived_at'])) return self::notFound('Archived component field not found');
        try { ContentModels::restoreComponentField($cid,$fid); Audit::log('component_field.restore','component_field',$fid,['component_id'=>$cid]); }
        catch (\Throwable $e) { return self::message('Could not restore component field',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/components/'.$cid.'/edit?field_restored=1');
    }

    public static function reorderComponentFields(string $id): Response
    {
        if ($r=self::requirePermission()) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::json(['ok'=>false,'error'=>'csrf'],419);
        $cid=self::id($id); if (!$cid||!ContentModels::component($cid)) return self::json(['ok'=>false,'error'=>'not_found'],404);
        $order=is_array($_POST['order']??null)?array_map('intval',$_POST['order']):[];
        try { ContentModels::reorderComponentFields($cid,$order); Audit::log('component_field.reorder','content_component',$cid,['order'=>$order]); return self::json(['ok'=>true]); }
        catch (\Throwable $e) { return self::json(['ok'=>false,'error'=>$e->getMessage()],422); }
    }

    private static function modelForm(?int $id,array $model,array $errors,int $status=200): Response
    {
        $defaults=['singular_name'=>'','plural_name'=>'','model_key'=>'','slug'=>'','icon'=>'collection','description'=>'','status'=>'active','is_public'=>1,'has_archive'=>1,'has_urls'=>1,'searchable'=>1,'sitemap_enabled'=>1,
            'enable_revisions'=>1,'enable_autosave'=>1,'enable_trash'=>1,'enable_seo'=>1,'enable_featured_image'=>0,'enable_scheduling'=>1];
        $data=array_merge($defaults,$model);
        $allFields=$id?ContentModels::fields($id,true):[];
        $activeFields=array_values(array_filter($allFields,static fn(array $field): bool => empty($field['archived_at'])));
        $archivedFields=array_values(array_filter($allFields,static fn(array $field): bool => !empty($field['archived_at'])));
        return new Response(View::render('admin/content-models/form',[
            'title'=>$id?'Edit content model':'New content model','model'=>$data,'modelId'=>$id,'errors'=>$errors,'icons'=>ContentModels::icons(),
            'fields'=>$activeFields,'archivedFields'=>$archivedFields,'modelPermissions'=>$id?ContentModels::modelRolePermissions($id):[],'entryCount'=>$id?ContentModels::entryCount($id,false):0,
            'dynamicUsage'=>$id?PageBlocks::modelUsage((string)($data['model_key']??'')):['pages'=>0,'patterns'=>0,'total'=>0],
            'created'=>isset($_GET['created']),'saved'=>isset($_GET['saved']),'fieldCreated'=>isset($_GET['field_created']),'fieldSaved'=>isset($_GET['field_saved']),'fieldDeleted'=>isset($_GET['field_deleted']),
            'fieldArchived'=>isset($_GET['field_archived']),'fieldRestored'=>isset($_GET['field_restored']),'starterInstalled'=>isset($_GET['starter_installed']),
        ]),$status);
    }

    private static function fieldForm(string $id, string|int|null $field, array $old, array $errors, int $status=200): Response
    {
        if ($r=self::requirePermission()) return $r;
        $modelId=self::id((string)$id); $model=$modelId?ContentModels::find($modelId):null;
        if (!$model) return self::notFound('Content model not found');
        $fieldId=$field!==null?self::id((string)$field):0; $existing=$fieldId?ContentModels::field($modelId,$fieldId):null;
        if ($fieldId&&!$existing) return self::notFound('Field not found');
        $settings=$existing['settings']??[];
        $data=array_merge([
            'field_key'=>'','label'=>'','field_type'=>'text','help_text'=>'','placeholder'=>'','is_required'=>0,'sort_order'=>100,
            'options'=>implode("\n",(array)($settings['options']??[])),'target_model_id'=>(int)($settings['target_model_id']??0),'multiple'=>(int)($settings['multiple']??0),
            'component_id'=>(int)($settings['component_id']??0),'relation_type'=>ContentModels::relationType($settings),'min'=>$settings['min']??'','max'=>$settings['max']??'','step'=>$settings['step']??'','max_length'=>$settings['max_length']??'',
            'default_value'=>$settings['default_value']??'','unique'=>(int)($settings['unique']??0),'searchable'=>(int)($settings['searchable']??0),
        ],$existing?:[],$old);
        if (isset($old['settings'])&&is_array($old['settings'])) {
            $st=$old['settings']; $data['options']=implode("\n",(array)($st['options']??[])); $data['target_model_id']=$st['target_model_id']??0; $data['multiple']=$st['multiple']??0; $data['relation_type']=ContentModels::relationType($st); $data['component_id']=$st['component_id']??0; $data['min']=$st['min']??''; $data['max']=$st['max']??''; $data['step']=$st['step']??''; $data['max_length']=$st['max_length']??''; $data['default_value']=$st['default_value']??''; $data['unique']=$st['unique']??0; $data['searchable']=$st['searchable']??0;
        }
        return new Response(View::render('admin/content-models/field-form',[
            'title'=>$fieldId?'Edit field':'Add field','model'=>$model,'field'=>$data,'fieldId'=>$fieldId?:null,'errors'=>$errors,
            'types'=>ContentModels::fieldTypes(),'models'=>ContentModels::all(),'components'=>ContentModels::components(),'dataCount'=>$fieldId?ContentModels::fieldDataCount($modelId,$fieldId):0,
        ]),$status);
    }

    private static function componentForm(string|int|null $id,array $old,array $errors,int $status=200): Response
    {
        if ($r=self::requirePermission()) return $r;
        $cid=$id!==null?self::id((string)$id):0; $existing=$cid?ContentModels::component($cid):null;
        if ($cid&&!$existing) return self::notFound('Component not found');
        $data=array_merge(['name'=>'','slug'=>'','description'=>'','status'=>'active'],$existing?:[],$old);
        $allFields=$cid?ContentModels::componentFields($cid,true):[];
        $activeFields=array_values(array_filter($allFields,static fn(array $field): bool => empty($field['archived_at'])));
        $archivedFields=array_values(array_filter($allFields,static fn(array $field): bool => !empty($field['archived_at'])));
        return new Response(View::render('admin/components/form',[
            'title'=>$cid?'Edit component':'New component','component'=>$data,'componentId'=>$cid?:null,'errors'=>$errors,
            'fields'=>$activeFields,'archivedFields'=>$archivedFields,'inUse'=>$cid?ContentModels::componentInUse($cid):false,
            'created'=>isset($_GET['created']),'saved'=>isset($_GET['saved']),'fieldCreated'=>isset($_GET['field_created']),'fieldSaved'=>isset($_GET['field_saved']),'fieldDeleted'=>isset($_GET['field_deleted']),
            'fieldArchived'=>isset($_GET['field_archived']),'fieldRestored'=>isset($_GET['field_restored']),
        ]),$status);
    }

    private static function componentFieldForm(string $id,string|int|null $field,array $old,array $errors,int $status=200): Response
    {
        if ($r=self::requirePermission()) return $r;
        $cid=self::id($id); $component=$cid?ContentModels::component($cid):null; if (!$component) return self::notFound('Component not found');
        $fid=$field!==null?self::id((string)$field):0; $existing=$fid?ContentModels::componentField($cid,$fid):null; if ($fid&&!$existing) return self::notFound('Component field not found');
        $settings=$existing['settings']??[];
        $data=array_merge([
            'field_key'=>'','label'=>'','field_type'=>'text','help_text'=>'','placeholder'=>'','is_required'=>0,'sort_order'=>100,
            'options'=>implode("\n",(array)($settings['options']??[])),'min'=>$settings['min']??'','max'=>$settings['max']??'','step'=>$settings['step']??'','max_length'=>$settings['max_length']??'','default_value'=>$settings['default_value']??'',
        ],$existing?:[],$old);
        if (isset($old['settings'])&&is_array($old['settings'])) { $st=$old['settings']; $data['options']=implode("\n",(array)($st['options']??[])); $data['min']=$st['min']??''; $data['max']=$st['max']??''; $data['step']=$st['step']??''; $data['max_length']=$st['max_length']??''; $data['default_value']=$st['default_value']??''; }
        $types=array_diff_key(ContentModels::fieldTypes(),['component'=>true,'repeater'=>true,'relation'=>true]);
        return new Response(View::render('admin/components/field-form',['title'=>$fid?'Edit component field':'Add component field','component'=>$component,'field'=>$data,'fieldId'=>$fid?:null,'errors'=>$errors,'types'=>$types]),$status);
    }

    private static function json(array $payload,int $status=200): Response
    {
        return new Response(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}',$status,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
    }

    private static function requirePermission(): ?Response
    {
        if (!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if (!Gate::allows('content_models.manage')) return new Response(View::render('errors/403',['title'=>'Forbidden']),403);
        return null;
    }
    private static function id(string $v): int { return ctype_digit($v)&&(int)$v>0?(int)$v:0; }
    private static function csrf(): Response { return new Response('Invalid CSRF token',419,['Content-Type'=>'text/plain; charset=UTF-8']); }
    private static function notFound(string $title): Response { return new Response(View::render('errors/404',['title'=>$title]),404); }
    private static function message(string $title,string $message,int $status): Response { return new Response(View::render('errors/message',['title'=>$title,'message'=>$message]),$status); }
    private function __construct() {}
}
