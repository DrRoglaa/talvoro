<?php
declare(strict_types=1);

namespace CMS\Core\StarterResources;

use CMS\Core\CustomContent;
use CMS\Core\Database;
use CMS\Core\Menus;
use CMS\Core\StarterReferences;
use CMS\Core\StarterResourceAdapter;
use PDO;
use RuntimeException;

final class NavigationStarterResource extends Support implements StarterResourceAdapter
{
    public function types(): array { return ['menu','menu_item']; }

    public function preflight(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);
        if($type==='menu'){
            $db=Database::connection();
            $menuKey=(string)$data['menu_key'];
            $location=(string)($data['location']??'unassigned');
            $stmt=$db->prepare('SELECT id,menu_key,location,name FROM menus WHERE LOWER(menu_key)=LOWER(?) LIMIT 1');
            $stmt->execute([$menuKey]);
            $sameKey=$stmt->fetch(PDO::FETCH_ASSOC);
            if($sameKey){
                return ['action'=>'conflict','message'=>'A menu already uses key '.$menuKey.'. Starter menu keys must remain unique.','existing_id'=>(int)$sameKey['id'],'existing_locator'=>$menuKey];
            }
            if($location!=='unassigned'){
                $stmt=$db->prepare('SELECT id,menu_key,location,name FROM menus WHERE location=? LIMIT 1');
                $stmt->execute([$location]);
                $displaced=$stmt->fetch(PDO::FETCH_ASSOC);
                if($displaced){
                    return [
                        'action'=>'controlled_mutation',
                        'message'=>'The existing '.$location.' menu will be temporarily unassigned, not deleted. It is restored when Demo Data is deleted if it remains safe to do so.',
                        'existing_id'=>(int)$displaced['id'],
                        'existing_locator'=>$location,
                        'ownership_mode'=>'mutated',
                        'change_title'=>ucfirst($location).' navigation',
                        'change_before'=>(string)$displaced['name'],
                        'change_after'=>(string)$data['name'],
                        'change_note'=>'The existing menu is preserved intact and moved to Unassigned while this Starter Site owns the location.',
                    ];
                }
            }
            return ['action'=>'create','ownership_mode'=>'created'];
        }
        if($type==='menu_item'){
            return ['action'=>'create','message'=>'A new menu item will be added to the starter-owned menu.','ownership_mode'=>'created'];
        }
        throw new RuntimeException('Unsupported navigation starter resource.');
    }

    public function create(array $resource,array $context): array
    {
        $type=(string)($resource['type']??'');
        $data=self::data($resource);
        if($type==='menu'){
            $location=(string)($data['location']??'unassigned');
            $displaced=$location!=='unassigned'?Menus::findByLocation($location):null;
            $previous=null;
            if($displaced){
                $previous=['displaced_menu'=>['id'=>(int)$displaced['id'],'location'=>(string)$displaced['location']]];
                Menus::update((int)$displaced['id'],[
                    'name'=>(string)$displaced['name'],
                    'menu_key'=>(string)$displaced['menu_key'],
                    'location'=>'unassigned',
                    'description'=>(string)($displaced['description']??''),
                ]);
            }
            $validated=self::validation(Menus::validateMenu([
                'name'=>(string)$data['name'],'menu_key'=>(string)$data['menu_key'],
                'location'=>$location,'description'=>'',
            ]), 'Starter menu');
            $id=Menus::create($validated,self::userId($context));
            return self::created($id,(string)$validated['menu_key'],$this->snapshot(['resource_type'=>'menu','record_id'=>$id],$context)??[],$previous);
        }
        if($type==='menu_item'){
            $menuId=self::refId($data['menu']??null,$context,'menu');
            $input=[
                'label'=>(string)$data['label'],'sort_order'=>(int)($data['sort_order']??100),'is_enabled'=>'1',
            ];
            if(!empty($data['new_tab'])) $input['open_new_tab']='1';
            if(isset($data['parent'])) $input['parent_id']=(string)self::refId($data['parent'],$context,'menu_item');

            if(isset($data['target'])){
                $target=StarterReferences::resolved($data['target'],self::resolved($context),['page','post','content_entry']);
                $targetType=(string)$target['resource_type'];
                $input['target_type']=$targetType;
                $input['target_id']=(string)(int)$target['record_id'];
                if($targetType==='content_entry'){
                    $entry=CustomContent::rawFind((int)$target['record_id'],false);
                    if(!$entry) throw new RuntimeException('Starter menu target entry no longer exists.');
                    $input['target_model_id']=(string)(int)$entry['model_id'];
                }
            }else{
                $input['target_type']='custom';
                $input['custom_url']=(string)($data['url']??'/');
            }
            $validated=self::validation(Menus::validateItem($menuId,$input), 'Starter menu item');
            $id=Menus::createItem($menuId,$validated);
            return self::created($id,'menu-item:'.$id,$this->snapshot(['resource_type'=>'menu_item','record_id'=>$id],$context)??[]);
        }
        throw new RuntimeException('Unsupported navigation starter resource.');
    }

    public function snapshot(array $ownership,array $context): ?array
    {
        $type=(string)($ownership['resource_type']??'');
        $id=(int)($ownership['record_id']??0);
        if($id<1) return null;
        if($type==='menu'){
            $row=Menus::find($id); if(!$row) return null;
            return ['name'=>(string)$row['name'],'menu_key'=>(string)$row['menu_key'],'location'=>(string)$row['location'],'description'=>(string)($row['description']??'')];
        }
        if($type==='menu_item'){
            $row=Menus::item($id); if(!$row) return null;
            return [
                'menu_id'=>(int)$row['menu_id'],'parent_id'=>$row['parent_id']===null?null:(int)$row['parent_id'],'label'=>(string)$row['label'],
                'target_type'=>(string)$row['target_type'],'target_id'=>$row['target_id']===null?null:(int)$row['target_id'],
                'target_model_id'=>$row['target_model_id']===null?null:(int)$row['target_model_id'],'custom_url'=>(string)($row['custom_url']??''),
                'open_new_tab'=>(int)$row['open_new_tab'],'is_enabled'=>(int)$row['is_enabled'],'sort_order'=>(int)$row['sort_order'],
            ];
        }
        return null;
    }

    public function remove(array $ownership,array $context): array
    {
        $type=(string)($ownership['resource_type']??'');
        $id=(int)($ownership['record_id']??0);
        try{
            if($id<1) return ['removed'=>true];
            if($type==='menu_item'){
                if(!Menus::item($id)) return ['removed'=>true];
                $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM menu_items WHERE parent_id=?');
                $stmt->execute([$id]);
                if((int)$stmt->fetchColumn()>0) return ['removed'=>false,'reason'=>'The starter menu item has child items and was preserved.'];
                Menus::deleteItem($id);
                return ['removed'=>true];
            }
            if($type==='menu'){
                if(!Menus::find($id)) return ['removed'=>true];
                $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM menu_items WHERE menu_id=?');
                $stmt->execute([$id]);
                if((int)$stmt->fetchColumn()>0) return ['removed'=>false,'reason'=>'The starter menu contains remaining menu items and was preserved.'];
                Menus::delete($id);
                return ['removed'=>true];
            }
        }catch(\Throwable $e){ return ['removed'=>false,'reason'=>$e->getMessage()]; }
        return ['removed'=>false,'reason'=>'Unsupported navigation starter resource.'];
    }

    public function restore(array $ownership,array $context): array
    {
        if((string)($ownership['resource_type']??'')!=='menu') return ['restored'=>false,'reason'=>'This navigation resource has no restorable pre-starter state.'];
        $previous=$ownership['previous_state']??null;
        $displaced=is_array($previous)?($previous['displaced_menu']??null):null;
        if(!is_array($displaced)) return ['restored'=>false,'reason'=>'The displaced menu snapshot is unavailable.'];
        $starterId=(int)($ownership['record_id']??0);
        $starter=$starterId>0?Menus::find($starterId):null;
        if(!$starter) return ['restored'=>true];
        try{
            $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM menu_items WHERE menu_id=?');
            $stmt->execute([$starterId]);
            if((int)$stmt->fetchColumn()>0) return ['restored'=>false,'reason'=>'The starter menu still contains menu items and was preserved.'];

            $displacedId=(int)($displaced['id']??0);
            $originalLocation=(string)($displaced['location']??'unassigned');
            $current=$displacedId>0?Menus::find($displacedId):null;
            if($current && $originalLocation!=='unassigned' && (string)$current['location']==='unassigned'){
                $stmt=Database::connection()->prepare('SELECT id FROM menus WHERE location=? AND id<>? AND id<>? LIMIT 1');
                $stmt->execute([$originalLocation,$starterId,$displacedId]);
                if((int)($stmt->fetchColumn()?:0)>0) return ['restored'=>false,'reason'=>'The previous menu location is now used by another menu.'];
            }

            Menus::delete($starterId);
            $this->restoreDisplacedMenu($displacedId,$originalLocation);
            return ['restored'=>true];
        }catch(\Throwable $e){ return ['restored'=>false,'reason'=>$e->getMessage()]; }
    }

    private function restoreDisplacedMenu(int $menuId,string $location): void
    {
        if($menuId<1 || $location==='unassigned') return;
        $menu=Menus::find($menuId);
        if(!$menu || (string)$menu['location']!=='unassigned') return;
        Menus::update($menuId,[
            'name'=>(string)$menu['name'],
            'menu_key'=>(string)$menu['menu_key'],
            'location'=>$location,
            'description'=>(string)($menu['description']??''),
        ]);
    }
}
