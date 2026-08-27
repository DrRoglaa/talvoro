<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\Menus;
use CMS\Core\Response;
use CMS\Core\View;

final class MenusController
{
    public static function index(): Response
    {
        if ($r=self::guard()) return $r;
        return new Response(View::render('admin/menus/index',[
            'title'=>'Menus','menus'=>Menus::all(),'created'=>isset($_GET['created']),'deleted'=>isset($_GET['deleted']),
        ]));
    }

    public static function newMenu(): Response
    {
        if ($r=self::guard()) return $r;
        return self::form(null,['name'=>'','menu_key'=>'','location'=>'unassigned','description'=>''],[]);
    }

    public static function createMenu(): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf();
        $v=Menus::validateMenu($_POST); if ($v['errors']) return self::form(null,$v['data'],$v['errors'],422);
        try { $u=Auth::user(); $id=Menus::create($v['data'],(int)($u['id']??0)); Audit::log('menu.create','menu',$id,['location'=>$v['data']['location']]); return Response::redirect(AdminPath::baseUrl().'/menus/'.$id.'/edit?created=1'); }
        catch (\Throwable $e) { return self::form(null,$v['data'],[$e->getMessage()],422); }
    }

    public static function editMenu(string $id): Response
    {
        if ($r=self::guard()) return $r; $mid=self::id($id); $menu=$mid?Menus::find($mid):null; if (!$menu) return self::notFound(); return self::form($mid,$menu,[]);
    }

    public static function updateMenu(string $id): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); $menu=$mid?Menus::find($mid):null; if (!$menu) return self::notFound();
        $v=Menus::validateMenu($_POST,$mid); if ($v['errors']) return self::form($mid,array_merge($menu,$v['data']),$v['errors'],422);
        try { Menus::update($mid,$v['data']); Audit::log('menu.update','menu',$mid,['location'=>$v['data']['location']]); return Response::redirect(AdminPath::baseUrl().'/menus/'.$mid.'/edit?saved=1'); }
        catch (\Throwable $e) { return self::form($mid,array_merge($menu,$v['data']),[$e->getMessage()],422); }
    }

    public static function deleteMenu(string $id): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); if (($_POST['confirm_delete']??'')!=='1') return self::message('Delete not confirmed','Confirm deletion of this menu. Content linked by the menu will not be deleted.',422);
        $mid=self::id($id); if (!$mid || !Menus::find($mid)) return self::notFound(); Menus::delete($mid); Audit::log('menu.delete','menu',$mid); return Response::redirect(AdminPath::baseUrl().'/menus?deleted=1');
    }

    public static function targetSearch(): Response
    {
        if ($r=self::guard()) return $r;
        $type=(string)($_GET['type']??''); $q=(string)($_GET['q']??'');
        $payload=['ok'=>true,'items'=>Menus::searchTargets($type,$q,30)];
        return new Response(json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?:'{"ok":true,"items":[]}',200,['Content-Type'=>'application/json; charset=UTF-8','Cache-Control'=>'no-store']);
    }

    public static function addItem(string $id): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); if (!$mid || !Menus::find($mid)) return self::notFound();
        $v=Menus::validateItem($mid,$_POST); if ($v['errors']) return self::form($mid,Menus::find($mid)?:[],$v['errors'],422,$_POST);
        $item=Menus::createItem($mid,$v['data']); Audit::log('menu_item.create','menu_item',$item,['menu_id'=>$mid]); return Response::redirect(AdminPath::baseUrl().'/menus/'.$mid.'/edit?item_created=1');
    }

    public static function updateItem(string $id,string $item): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); $iid=self::id($item); $existing=$iid?Menus::item($iid):null; if (!$mid||!$existing||(int)$existing['menu_id']!==$mid) return self::notFound();
        $v=Menus::validateItem($mid,$_POST,$iid); if ($v['errors']) return self::message('Could not update menu item',implode(' ',$v['errors']),422); Menus::updateItem($iid,$v['data']); Audit::log('menu_item.update','menu_item',$iid,['menu_id'=>$mid]); return Response::redirect(AdminPath::baseUrl().'/menus/'.$mid.'/edit?item_saved=1');
    }

    public static function deleteItem(string $id,string $item): Response
    {
        if ($r=self::guard()) return $r; if (!Csrf::valid($_POST['_csrf']??null)) return self::csrf(); $mid=self::id($id); $iid=self::id($item); $existing=$iid?Menus::item($iid):null; if (!$mid||!$existing||(int)$existing['menu_id']!==$mid) return self::notFound(); Menus::deleteItem($iid); Audit::log('menu_item.delete','menu_item',$iid,['menu_id'=>$mid]); return Response::redirect(AdminPath::baseUrl().'/menus/'.$mid.'/edit?item_deleted=1');
    }

    private static function form(?int $id,array $menu,array $errors,int $status=200,array $oldItem=[]): Response
    {
        return new Response(View::render('admin/menus/form',[
            'title'=>$id?'Edit menu':'New menu','menuId'=>$id,'menu'=>$menu,'errors'=>$errors,'items'=>$id?Menus::items($id):[],
            'targets'=>Menus::targetOptions(),'oldItem'=>$oldItem,'created'=>isset($_GET['created']),'saved'=>isset($_GET['saved']),
            'itemCreated'=>isset($_GET['item_created']),'itemSaved'=>isset($_GET['item_saved']),'itemDeleted'=>isset($_GET['item_deleted']),
        ]),$status);
    }
    private static function guard(): ?Response { if (!Auth::check()) return Response::redirect(AdminPath::loginUrl()); if (!Gate::allows('menus.manage')) return new Response(View::render('errors/403',['title'=>'Forbidden']),403); return null; }
    private static function id(string $v): int { return ctype_digit($v)&&(int)$v>0?(int)$v:0; }
    private static function csrf(): Response { return new Response('Invalid CSRF token',419,['Content-Type'=>'text/plain; charset=UTF-8']); }
    private static function notFound(): Response { return new Response(View::render('errors/404',['title'=>'Menu not found']),404); }
    private static function message(string $title,string $message,int $status): Response { return new Response(View::render('errors/message',['title'=>$title,'message'=>$message]),$status); }
    private function __construct(){}
}
