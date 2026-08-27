<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\MediaLibrary;
use CMS\Core\Response;
use CMS\Core\SiteAssets;
use CMS\Core\View;

final class MediaController
{
    public static function index(): Response
    {
        if ($r=self::guard('media.view')) return $r; $search=trim((string)($_GET['q']??'')); $folderRaw=(string)($_GET['folder']??''); $folder=$folderRaw===''?null:max(0,(int)$folderRaw); $page=max(1,(int)($_GET['page']??1)); $per=60; $total=MediaLibrary::countAll($search,$folder);
        $assets=MediaLibrary::all($search,$per,$folder,($page-1)*$per);
        return new Response(View::render('admin/media/index',[
            'title'=>'Media','assets'=>$assets,'usageMap'=>MediaLibrary::usageReferencesForAssets(array_map(static fn(array $asset): int=>(int)$asset['id'],$assets)),'totalCount'=>$total,'search'=>$search,'folderFilter'=>$folder,'folders'=>MediaLibrary::folders(),'page'=>$page,'pages'=>max(1,(int)ceil($total/$per)),'canManage'=>Gate::allows('media.manage'),'uploaded'=>isset($_GET['uploaded']),'saved'=>isset($_GET['saved']),'deleted'=>isset($_GET['deleted']),'replaced'=>isset($_GET['replaced']),'transformed'=>isset($_GET['transformed']),'folderCreated'=>isset($_GET['folder_created']),'folderDeleted'=>isset($_GET['folder_deleted']),'maxUploadMb'=>SiteAssets::maxUploadMb(),
        ]));
    }
    public static function upload(): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); try { $u=Auth::user(); $folder=self::nullableId($_POST['folder_id']??null); $id=MediaLibrary::upload($_FILES['image']??[],(string)($_POST['alt_text']??''),(int)($u['id']??0),$folder,(string)($_POST['title']??''),(string)($_POST['caption']??'')); Audit::log('media.create','media',$id); return Response::redirect(AdminPath::baseUrl().'/media?uploaded=1'); } catch (\Throwable $e) { return self::message('Could not upload media',$e->getMessage(),422); }
    }
    public static function update(string $id): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); if (!$mid||!MediaLibrary::find($mid)) return self::notFound(); try { MediaLibrary::updateDetails($mid,$_POST); Audit::log('media.update','media',$mid); return Response::redirect(AdminPath::baseUrl().'/media?saved=1#media-'.$mid); } catch (\Throwable $e) { return self::message('Could not update media',$e->getMessage(),422); }
    }
    public static function replace(string $id): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); if (!$mid||!MediaLibrary::find($mid)) return self::notFound(); try { MediaLibrary::replace($mid,$_FILES['replacement']??[]); Audit::log('media.replace','media',$mid); return Response::redirect(AdminPath::baseUrl().'/media?replaced=1#media-'.$mid); } catch (\Throwable $e) { return self::message('Could not replace media',$e->getMessage(),422); }
    }
    public static function transform(string $id): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); if (!$mid||!MediaLibrary::find($mid)) return self::notFound();
        try { $operation=(string)($_POST['operation']??''); MediaLibrary::transform($mid,$operation); Audit::log('media.transform','media',$mid,['operation'=>$operation]); return Response::redirect(AdminPath::baseUrl().'/media?transformed=1#media-'.$mid); } catch (\Throwable $e) { return self::message('Could not edit media',$e->getMessage(),422); }
    }
    public static function delete(string $id): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); if (($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm permanent deletion before deleting this media item.',422); $mid=self::id($id); if (!$mid||!MediaLibrary::find($mid)) return self::notFound(); try { MediaLibrary::delete($mid); Audit::log('media.delete','media',$mid); return Response::redirect(AdminPath::baseUrl().'/media?deleted=1'); } catch (\Throwable $e) { return self::message('Could not delete media',$e->getMessage(),422); }
    }
    public static function createFolder(): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); try { $u=Auth::user(); $id=MediaLibrary::createFolder((string)($_POST['name']??''),self::nullableId($_POST['parent_id']??null),(int)($u['id']??0)); Audit::log('media_folder.create','media_folder',$id); return Response::redirect(AdminPath::baseUrl().'/media?folder_created=1'); } catch (\Throwable $e) { return self::message('Could not create folder',$e->getMessage(),422); }
    }
    public static function deleteFolder(string $id): Response
    {
        if ($r=self::guard('media.manage')) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $fid=self::id($id); if (!$fid) return self::notFound(); try { MediaLibrary::deleteFolder($fid); Audit::log('media_folder.delete','media_folder',$fid); return Response::redirect(AdminPath::baseUrl().'/media?folder_deleted=1'); } catch (\Throwable $e) { return self::message('Could not delete folder',$e->getMessage(),422); }
    }
    private static function guard(string $permission): ?Response { if (!Auth::check()) return Response::redirect(AdminPath::loginUrl()); if (!Gate::allows($permission)) return new Response(View::render('errors/403',['title'=>'Forbidden']),403); return null; }
    private static function id(string $v): int { return ctype_digit($v)&&(int)$v>0?(int)$v:0; }
    private static function nullableId(mixed $v): ?int { $s=trim((string)$v); return $s!==''&&ctype_digit($s)&&(int)$s>0?(int)$s:null; }
    private static function csrf(): Response { return new Response('Invalid CSRF token',419,['Content-Type'=>'text/plain; charset=UTF-8']); }
    private static function notFound(): Response { return new Response(View::render('errors/404',['title'=>'Media not found']),404); }
    private static function message(string $title,string $message,int $status): Response { return new Response(View::render('errors/message',['title'=>$title,'message'=>$message]),$status); }
    private function __construct(){}
}
