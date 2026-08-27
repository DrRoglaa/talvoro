<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\ContentModels;
use CMS\Core\ContentPresentation;
use CMS\Core\Csrf;
use CMS\Core\CustomContent;
use CMS\Core\Database;
use CMS\Core\Gate;
use CMS\Core\MediaLibrary;
use CMS\Core\Response;
use CMS\Core\Redirects;
use CMS\Core\View;
use PDO;

final class CustomContentController
{
    public static function index(string $modelSlug): Response
    {
        if ($r=self::requirePermission('custom_content.view')) return $r;
        $model=ContentModels::findBySlug($modelSlug);
        if (!$model) return self::notFound('Content model not found');
        if ($r=self::requireModelPermission($model,'view')) return $r;
        $search=trim((string)($_GET['q']??'')); $status=trim((string)($_GET['status']??'')); $trashed=(string)($_GET['view']??'')==='trash';
        if ($trashed && ((int)$model['enable_trash'] !== 1 || !Gate::allowsModel((int)$model['id'],'delete'))) return new Response(View::render('errors/403',['title'=>'Forbidden']),403);
        $page=max(1,(int)($_GET['page']??1));
        try { ContentLifecycle::maybePurgeExpired(); } catch (\Throwable) {}
        $listing=CustomContent::adminPage((int)$model['id'],$search,$status,$trashed,$page,50);
        return new Response(View::render('admin/custom-content/index',[
            'title'=>(string)$model['plural_name'],'model'=>$model,'entries'=>$listing['items'],'pagination'=>$listing,
            'search'=>$search,'status'=>$status,'trashed'=>$trashed,'trashCount'=>((int)$model['enable_trash']===1 && Gate::allowsModel((int)$model['id'],'delete'))?CustomContent::trashCount((int)$model['id']):0,
            'trashedNow'=>isset($_GET['trashed']),'restored'=>isset($_GET['restored']),'purged'=>isset($_GET['purged']),
            'referenceWarningCount'=>max(0,(int)($_GET['refs']??0)),
            'canCreate'=>Gate::allowsModel((int)$model['id'],'create'),'canEdit'=>Gate::allowsModel((int)$model['id'],'edit'),
            'canPublish'=>Gate::allowsModel((int)$model['id'],'publish'),'canDelete'=>Gate::allowsModel((int)$model['id'],'delete'),
        ]));
    }

    public static function newEntry(string $modelSlug): Response
    {
        if ($r=self::requirePermission('custom_content.create')) return $r;
        $model=ContentModels::findBySlug($modelSlug); if (!$model) return self::notFound('Content model not found');
        if ($r=self::requireModelPermission($model,'create')) return $r;
        return self::entryForm($model,null,[],[],200);
    }

    public static function createEntry(string $modelSlug): Response
    {
        if ($r=self::requirePermission('custom_content.create')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $model=ContentModels::findBySlug($modelSlug); if (!$model) return self::notFound('Content model not found');
        if ($r=self::requireModelPermission($model,'create')) return $r;
        $v=CustomContent::validateEntry($_POST,$model,null,Gate::allowsModel((int)$model['id'],'publish'));
        $v['errors']=array_values(array_unique(array_merge($v['errors'],self::relationPermissionErrors($model,$v['data'],null))));
        if ($v['errors']) return self::entryForm($model,null,$v['data'],$v['errors'],422);
        $db=Database::connection();
        try {
            $db->beginTransaction(); $user=Auth::user();
            $id=CustomContent::create($v['data'],(int)$user['id']);
            if ((int)$model['enable_revisions'] === 1) ContentHistory::capture('entry',$id,(int)$user['id'],'create');
            $db->commit();
            Audit::log('structured_content.create','entry',$id,['model_id'=>(int)$model['id'],'model_slug'=>$model['slug'],'title'=>$v['data']['title']]);
            return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$id.'/edit?created=1');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return self::entryForm($model,null,$v['data'],[$e->getMessage()],422);
        }
    }

    public static function editEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.edit')) return $r;
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'edit')) return $r;
        return self::entryForm($model,$entry,[],[],200);
    }

    public static function updateEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $existing=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$existing||(int)$existing['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'edit')) return $r;
        $v=CustomContent::validateEntry($_POST,$model,$entryId,Gate::allowsModel((int)$model['id'],'publish'));
        $v['errors']=array_values(array_unique(array_merge($v['errors'],self::relationPermissionErrors($model,$v['data'],$entryId))));
        if ($v['errors']) return self::entryForm($model,array_merge($existing,$v['data']),$v['data'],$v['errors'],422);
        $db=Database::connection();
        try {
            $db->beginTransaction(); $user=Auth::user();
            CustomContent::update($entryId,$v['data'],$existing['published_at']);
            if (
                ($_POST['keep_old_url'] ?? '') === '1'
                && (int)$model['is_public'] === 1
                && (int)$model['has_urls'] === 1
                && (string)$existing['status'] === 'published'
                && (string)$v['data']['status'] === 'published'
                && (string)$existing['slug'] !== (string)$v['data']['slug']
            ) {
                $oldPath = CustomContent::publicUrl($model, $existing);
                $newEntryForPath = ['slug' => (string)$v['data']['slug']];
                $newPath = CustomContent::publicUrl($model, $newEntryForPath);
                Redirects::upsertPermanentLocal($oldPath, $newPath, (int)$user['id']);
            }
            if ((int)$model['enable_revisions'] === 1) {
                $action = $v['data']['status']==='scheduled' ? 'schedule' : ($v['data']['status']==='published' ? 'save' : 'draft_save');
                ContentHistory::capture('entry',$entryId,(int)$user['id'],$action);
            }
            if ((int)$model['enable_autosave'] === 1) ContentHistory::clearAutosave('entry',$entryId,(int)$user['id']);
            $db->commit();
            Audit::log('structured_content.update','entry',$entryId,['model_id'=>(int)$model['id'],'title'=>$v['data']['title'],'status'=>$v['data']['status']]);
            return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$entryId.'/edit?saved=1');
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            return self::entryForm($model,array_merge($existing,$v['data']),$v['data'],[$e->getMessage()],422);
        }
    }

    public static function trashEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.delete')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if (($_POST['confirm_delete']??'')!=='1') return self::message('Move to Trash not confirmed','Confirm before moving this content to Trash.',422);
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'delete')) return $r;
        if ((int)$model['enable_trash'] !== 1) return self::message('Trash is disabled','Trash is disabled for this content model.',422);
        $references=CustomContent::relationReferencesCount($entryId);
        try { $uid=(int)Auth::user()['id']; ContentLifecycle::moveToTrash('entry',$entryId,$uid); Audit::log('structured_content.trash','entry',$entryId,['model_id'=>(int)$model['id'],'relation_references'=>$references]); }
        catch (\Throwable $e) { return self::message('Could not move content to Trash',$e->getMessage(),422); }
        $suffix='?trashed=1'.($references>0?'&refs='.$references:'');
        return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).$suffix);
    }

    public static function restoreEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.delete')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?ContentLifecycle::trashedItem('entry',$entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'delete')) return $r;
        if ((int)$model['enable_trash'] !== 1) return self::message('Trash is disabled','Trash is disabled for this content model.',422);
        if ((string)$entry['status']==='published'&&!Gate::allowsModel((int)$model['id'],'publish')) return new Response(View::render('errors/403',['title'=>'Publishing permission required']),403);
        try { $uid=(int)Auth::user()['id']; ContentLifecycle::restore('entry',$entryId,$uid); Audit::log('structured_content.restore','entry',$entryId); }
        catch (\Throwable $e) { return self::message('Could not restore content',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'?restored=1');
    }

    public static function permanentDeleteEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.delete')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if (($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm permanent deletion first.',422);
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?ContentLifecycle::trashedItem('entry',$entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'delete')) return $r;
        if ((int)$model['enable_trash'] !== 1) return self::message('Trash is disabled','Trash is disabled for this content model.',422);
        try { ContentLifecycle::permanentlyDelete('entry',$entryId); Audit::log('structured_content.permanent_delete','entry',$entryId); }
        catch (\Throwable $e) { return self::message('Could not permanently delete content',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'?view=trash&purged=1');
    }

    public static function revisions(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.edit')) return $r;
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'edit')) return $r;
        if ((int)$model['enable_revisions'] !== 1) return self::notFound('Revision history is disabled for this content model');
        if (ContentHistory::count('entry',$entryId)===0) ContentHistory::capture('entry',$entryId,null,'baseline');
        $selected=max(0,(int)($_GET['revision']??0)); $revision=$selected?ContentHistory::revision('entry',$entryId,$selected):null;
        $history=AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$entryId.'/revisions';
        return new Response(View::render('admin/revisions/index',[
            'title'=>'Revision history','contentType'=>'entry','contentId'=>$entryId,'contentTitle'=>(string)$entry['title'],
            'revisions'=>ContentHistory::list('entry',$entryId),'selectedRevision'=>$revision,
            'changes'=>$revision?ContentHistory::compareToCurrent('entry',$entryId,$revision['snapshot']):[],
            'editUrl'=>AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$entryId.'/edit',
            'historyBaseUrl'=>$history,'restoreBaseUrl'=>$history,'restored'=>isset($_GET['restored']),
        ]));
    }

    public static function restoreRevision(string $modelSlug,string $id,string $revision): Response
    {
        if ($r=self::requirePermission('custom_content.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if (($_POST['confirm_restore']??'')!=='1') return self::message('Restore not confirmed','Confirm the revision before restoring it.',422);
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $revisionId=self::id($revision); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||!$revisionId||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Revision not found');
        if ($r=self::requireModelPermission($model,'edit')) return $r;
        if ((int)$model['enable_revisions'] !== 1) return self::notFound('Revision history is disabled for this content model');
        $rev=ContentHistory::revision('entry',$entryId,$revisionId); if (!$rev) return self::notFound('Revision not found');
        if (in_array((string)($rev['snapshot']['fields']['status']??'draft'),['published','scheduled'],true)&&!Gate::allowsModel((int)$model['id'],'publish')) return new Response(View::render('errors/403',['title'=>'Publishing permission required']),403);
        try { $uid=(int)Auth::user()['id']; ContentHistory::restore('entry',$entryId,$revisionId,$uid); Audit::log('structured_content.revision.restore','entry',$entryId,['revision_id'=>$revisionId]); }
        catch (\Throwable $e) { return self::message('Could not restore revision',$e->getMessage(),422); }
        return Response::redirect(AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$entryId.'/revisions?restored=1',303);
    }

    public static function autosave(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf']??null)) return new Response('{"ok":false,"error":"csrf"}',419,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return new Response('{"ok":false,"error":"not_found"}',404,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
        if (!Gate::allowsModel((int)$model['id'],'edit')) return new Response('{"ok":false,"error":"forbidden"}',403,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
        if ((int)$model['enable_autosave'] !== 1) return new Response('{"ok":false,"error":"autosave_disabled"}',409,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
        $_POST['model_id']=(string)$model['id'];
        try { $saved=ContentHistory::saveAutosave('entry',$entryId,(int)Auth::user()['id'],$_POST); return new Response(json_encode(['ok'=>true,'saved_at'=>$saved['saved_at']],JSON_UNESCAPED_SLASHES)?:'{"ok":true}',200,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']); }
        catch (\Throwable) { return new Response('{"ok":false,"error":"autosave_failed"}',422,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']); }
    }

    public static function previewEntry(string $modelSlug,string $id): Response
    {
        if ($r=self::requirePermission('custom_content.view')) return $r;
        $model=ContentModels::findBySlug($modelSlug); $entryId=self::id($id); $entry=$entryId?CustomContent::find($entryId):null;
        if (!$model||!$entry||(int)$entry['model_id']!==(int)$model['id']) return self::notFound('Content entry not found');
        if ($r=self::requireModelPermission($model,'view')) return $r;
        return new Response(View::render('content/show',[
            'title'=>(string)$entry['title'],'model'=>$model,'entry'=>$entry,'fields'=>ContentModels::fields((int)$model['id']),
            'relatedTo'=>CustomContent::relatedTo((int)$entry['id']),'publicPreview'=>true,
        ]),200,['Cache-Control'=>'no-store, private','X-Robots-Tag'=>'noindex, nofollow']);
    }

    public static function relationSearch(string $modelSlug): Response
    {
        if ($r=self::requirePermission('custom_content.view')) return $r;
        $model=ContentModels::findBySlug($modelSlug);
        if (!$model) return self::json(['ok'=>false,'error'=>'not_found'],404);
        if (!Gate::allowsModel((int)$model['id'],'edit')) return self::json(['ok'=>false,'error'=>'forbidden'],403);
        $fieldKey=(string)($_GET['field_key']??'');
        $field=ContentModels::fieldByKey((int)$model['id'],$fieldKey);
        if (!$field || (string)$field['field_type']!=='relation') return self::json(['ok'=>false,'error'=>'invalid_field'],422);
        $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null);
        $targetModelId=(int)($settings['target_model_id']??0);
        if ($targetModelId<1 || !Gate::allowsModel($targetModelId,'view')) return self::json(['ok'=>false,'error'=>'forbidden_relation'],403);
        $q=mb_substr(trim((string)($_GET['q']??'')),0,120);
        $current=max(0,(int)($_GET['current_entry_id']??0));
        $items=CustomContent::searchRelationOptions($targetModelId,$q,30);
        if ($current>0) $items=array_values(array_filter($items,static fn(array $row): bool => (int)$row['id']!==$current));
        return self::json(['ok'=>true,'items'=>array_map(static fn(array $row): array => [
            'id'=>(int)$row['id'],'title'=>(string)$row['title'],'status'=>(string)$row['status'],'slug'=>(string)$row['slug'],
        ],$items)]);
    }

    public static function publicArchive(string $modelSlug): Response
    {
        $model=ContentModels::findBySlug($modelSlug,true);
        if (!$model||(int)$model['is_public']!==1||(int)$model['has_archive']!==1) return self::notFound('Page not found');
        $page=max(1,(int)($_GET['page']??1));
        $listing=CustomContent::publicPage((int)$model['id'],$page,12);
        $presentation=ContentPresentation::recommendedPresentation((string)$model['model_key']);
        $entries=ContentPresentation::presentEntries($model,$listing['items'],$presentation);
        return new Response(View::render('content/archive',['title'=>(string)$model['plural_name'],'model'=>$model,'entries'=>$entries,'pagination'=>$listing,'presentation'=>$presentation]));
    }

    public static function publicEntry(string $modelSlug,string $slug): Response
    {
        $model=ContentModels::findBySlug($modelSlug,true);
        if (!$model||(int)$model['is_public']!==1||(int)$model['has_urls']!==1) return self::notFound('Page not found');
        $entry=CustomContent::findPublishedBySlug((int)$model['id'],$slug); if (!$entry) return self::notFound('Page not found');
        return new Response(View::render('content/show',[
            'title'=>(string)$entry['title'],'model'=>$model,'entry'=>$entry,'fields'=>ContentModels::fields((int)$model['id']),
            'relatedTo'=>CustomContent::relatedTo((int)$entry['id']),
        ]));
    }

    private static function entryForm(array $model, ?array $entry, array $submitted, array $errors, int $status): Response
    {
        $isEdit=is_array($entry)&&isset($entry['id']);
        $values=$submitted['field_values']??($entry['values']??[]); if (!is_array($values)) $values=[];
        $entryData=array_merge([
            'id'=>null,'title'=>'','slug'=>'','status'=>'draft','seo_title'=>'','seo_description'=>'','canonical_url'=>'','robots'=>'index,follow',
            'social_title'=>'','social_description'=>'','social_media_id'=>null,'featured_media_id'=>null,'published_at'=>null,'published_at_local'=>'','updated_at'=>null,'values'=>$values,
        ],$entry?:[], $submitted);
        if (($entryData['published_at_local'] ?? '') === '' && !empty($entryData['published_at'])) {
            $entryData['published_at_local']=CustomContent::utcToLocalInput((string)$entryData['published_at']);
        }
        $entryData['values']=$values;
        $entryId=(int)($entryData['id']??0);
        $fields=ContentModels::fields((int)$model['id']); $relationOptions=[]; $relationAccess=[];
        foreach ($fields as $field) if ((string)$field['field_type']==='relation') {
            $key=(string)$field['field_key'];
            $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null);
            $targetModelId=(int)($settings['target_model_id']??0);
            $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null);
            $rawSelected=$values[$key]??null;
            $selectedIds=ContentModels::relationAllowsMultiple($settings)?array_map('intval',(array)$rawSelected):[(int)$rawSelected];
            $selected=[];
            foreach (array_values(array_unique(array_filter($selectedIds,static fn(int $id): bool => $id>0))) as $selectedId) {
                $target=CustomContent::rawFind($selectedId,true);
                if ($target && (int)$target['model_id']===$targetModelId) {
                    $targetStatus=!empty($target['deleted_at']) ? 'in Trash' : (string)$target['status'];
                    $selected[]=['id'=>$selectedId,'title'=>(string)$target['title'],'status'=>$targetStatus];
                }
            }
            $relationAccess[$key]=Gate::allowsModel($targetModelId,'view');
            if (!$relationAccess[$key]) {
                $selected=array_map(static fn(array $row): array => ['id'=>(int)$row['id'],'title'=>'Restricted related item','status'=>'restricted'], $selected);
            }
            $relationOptions[$key]=$selected;
        }
        $user=Auth::user();
        $canBrowseMedia=Gate::allows('media.view');
        return new Response(View::render('admin/custom-content/form',[
            'title'=>$isEdit?'Edit '.(string)$model['singular_name']:'New '.(string)$model['singular_name'],'model'=>$model,'entry'=>$entryData,'fields'=>$fields,
            'errors'=>$errors,'isEdit'=>$isEdit,'canPublish'=>Gate::allowsModel((int)$model['id'],'publish'),'canDelete'=>Gate::allowsModel((int)$model['id'],'delete'),'canBrowseMedia'=>$canBrowseMedia,'mediaAssets'=>$canBrowseMedia?MediaLibrary::pickerAssets():[],
            'relationOptions'=>$relationOptions,'relationAccess'=>$relationAccess,'relationSearchUrl'=>AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/relation-search',
            'components'=>ContentModels::components(true),'revisionCount'=>$entryId && (int)$model['enable_revisions']===1?ContentHistory::count('entry',$entryId):0,
            'autosave'=>$entryId && (int)$model['enable_autosave']===1?ContentHistory::latestAutosave('entry',$entryId,(int)$user['id'],(string)($entryData['updated_at']??'')):null,
            'autosaveUrl'=>$entryId && (int)$model['enable_autosave']===1?AdminPath::baseUrl().'/content/'.rawurlencode((string)$model['slug']).'/'.$entryId.'/autosave':'',
            'created'=>isset($_GET['created']),'saved'=>isset($_GET['saved']),
        ]),$status);
    }

    private static function relationPermissionErrors(array $model, array $data, ?int $existingId): array
    {
        $errors=[];
        foreach (ContentModels::fields((int)$model['id']) as $field) {
            if ((string)$field['field_type']!=='relation') continue;
            $key=(string)$field['field_key'];
            $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null);
            $targetModelId=(int)($settings['target_model_id']??0);
            if (Gate::allowsModel($targetModelId,'view')) continue;
            $submitted=array_values(array_unique(array_filter(array_map('intval',(array)($data['relations'][$key]??[])),static fn(int $id): bool => $id>0)));
            sort($submitted,SORT_NUMERIC);
            $existing=[];
            if ($existingId) {
                $existing=array_map(static fn(array $row): int => (int)$row['id'],CustomContent::relationTargets($existingId,$key));
                sort($existing,SORT_NUMERIC);
            }
            if ($submitted!==$existing) $errors[]=(string)$field['label'].': you do not have permission to select entries from the related content model.';
        }
        return $errors;
    }

    private static function json(array $payload,int $status=200): Response
    {
        return new Response(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{}',$status,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
    }

    private static function requireModelPermission(array $model, string $action): ?Response
    {
        if (!Gate::allowsModel((int)$model['id'],$action)) {
            return new Response(View::render('errors/403',['title'=>'Forbidden']),403);
        }
        return null;
    }

    private static function requirePermission(string $permission): ?Response
    {
        if (!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if (!Gate::allows($permission)) return new Response(View::render('errors/403',['title'=>'Forbidden']),403);
        return null;
    }
    private static function id(string $v): int { return ctype_digit($v)&&(int)$v>0?(int)$v:0; }
    private static function csrf(): Response { return new Response('Invalid CSRF token',419,['Content-Type'=>'text/plain; charset=UTF-8']); }
    private static function notFound(string $title): Response { return new Response(View::render('errors/404',['title'=>$title]),404); }
    private static function message(string $title,string $message,int $status): Response { return new Response(View::render('errors/message',['title'=>$title,'message'=>$message]),$status); }
    private function __construct() {}
}
