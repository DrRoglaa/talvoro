<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\Response;
use CMS\Core\StarterSite;
use CMS\Core\ThemeManager;
use CMS\Core\View;

final class StarterSiteController
{
    public static function review(string $id): Response
    {
        if($response=self::guard()) return $response;
        $themeId=self::positiveId($id);
        if($themeId<1) return self::notFound();
        try{
            $review=StarterSite::review($themeId);
            return new Response(View::render('admin/theme-starter',[
                'title'=>(string)($review['definition']['name']??'Starter Site'),
                'review'=>$review,
                'installed'=>isset($_GET['installed']),
                'repaired'=>isset($_GET['repaired']),
                'demoDeleted'=>isset($_GET['demo_deleted']),
            ]));
        }catch(\Throwable $e){
            return self::message('Starter Site unavailable',$e->getMessage(),422);
        }
    }

    public static function install(string $id): Response
    {
        if($response=self::guard()) return $response;
        if(!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if(($_POST['confirm_starter']??'')!=='1') return self::message('Installation not confirmed','Review the Starter Site and confirm before adding demo content.',422);
        $themeId=self::positiveId($id);
        if($themeId<1) return self::notFound();
        try{
            $actor=Auth::user();
            $result=StarterSite::install($themeId,(int)($actor['id']??0),($_POST['confirm_mutations']??'')==='1');
            Audit::log('starter.install','theme',$themeId,['installation_id'=>$result['installation_id']??null,'idempotent'=>(bool)($result['idempotent']??false)]);
            return Response::redirect(AdminPath::baseUrl().'/themes/'.$themeId.'/starter?installed=1',303);
        }catch(\Throwable $e){
            return self::message('Could not install Starter Site',$e->getMessage(),422);
        }
    }

    public static function repair(string $id): Response
    {
        if($response=self::guard()) return $response;
        if(!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $themeId=self::positiveId($id);
        if($themeId<1) return self::notFound();
        try{
            $actor=Auth::user();
            $result=StarterSite::repair($themeId,(int)($actor['id']??0));
            Audit::log('starter.repair','theme',$themeId,['repaired'=>$result['repaired']??[],'preserved'=>$result['preserved']??[],'conflicts'=>$result['conflicts']??[]]);
            return Response::redirect(AdminPath::baseUrl().'/themes/'.$themeId.'/starter?repaired=1',303);
        }catch(\Throwable $e){
            return self::message('Could not repair Starter Site',$e->getMessage(),422);
        }
    }

    public static function deleteDemoData(string $id): Response
    {
        if($response=self::guard()) return $response;
        if(!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        if(($_POST['confirm_delete_demo']??'')!=='1') return self::message('Delete Demo Data not confirmed','Confirm that Talvoro should remove only safely owned Starter Site demo data.',422);
        $themeId=self::positiveId($id);
        if($themeId<1) return self::notFound();
        try{
            $actor=Auth::user();
            $result=StarterSite::deleteDemoData($themeId,(int)($actor['id']??0));
            Audit::log('starter.delete_demo_data','theme',$themeId,[
                'removed'=>$result['removed']??[],'restored'=>$result['restored']??[],
                'detached'=>$result['detached']??[],'already_missing'=>$result['already_missing']??[],
            ]);
            return Response::redirect(AdminPath::baseUrl().'/themes/'.$themeId.'/starter?demo_deleted=1',303);
        }catch(\Throwable $e){
            return self::message('Could not delete demo data',$e->getMessage(),422);
        }
    }

    private static function guard(): ?Response
    {
        if(!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if(Auth::requiresPasswordChange()) return Response::redirect(AdminPath::passwordUrl());
        if(!Gate::allows('starter_sites.manage')) return new Response(View::render('errors/403',['title'=>'Forbidden']),403);
        return null;
    }

    private static function positiveId(string $value): int { return ctype_digit($value)&&(int)$value>0?(int)$value:0; }
    private static function csrf(): Response { return new Response('Invalid CSRF token',419,['Content-Type'=>'text/plain; charset=UTF-8']); }
    private static function notFound(): Response { return new Response(View::render('errors/404',['title'=>'Theme not found']),404); }
    private static function message(string $title,string $message,int $status): Response { return new Response(View::render('errors/message',['title'=>$title,'message'=>$message]),$status); }
    private function __construct(){}
}
