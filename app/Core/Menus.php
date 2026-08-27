<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class Menus
{
    private const LOCATIONS = ['primary','footer','mobile','unassigned'];
    private const TARGETS = ['custom','page','post','content_entry'];

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        $sql = "SELECT m.*,COUNT(mi.id) item_count FROM menus m LEFT JOIN menu_items mi ON mi.menu_id=m.id GROUP BY m.id ORDER BY FIELD(m.location,'primary','mobile','footer','unassigned'),m.name";
        return Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM menus WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByLocation(string $location): ?array
    {
        if (!in_array($location,self::LOCATIONS,true) || $location==='unassigned') return null;
        $stmt=Database::connection()->prepare('SELECT * FROM menus WHERE location=? LIMIT 1');
        $stmt->execute([$location]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function items(int $menuId): array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM menu_items WHERE menu_id=? ORDER BY parent_id IS NOT NULL,parent_id,sort_order,id');
        $stmt->execute([$menuId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public static function publicTree(string $location): array
    {
        $menu=self::findByLocation($location);
        if (!$menu) return [];
        $rows=array_values(array_filter(self::items((int)$menu['id']),static fn(array $row):bool=>(int)$row['is_enabled']===1));
        $byParent=[];
        foreach ($rows as $row) {
            $resolved=self::resolvePublicItem($row);
            if ($resolved===null) continue;
            $parent=(int)($row['parent_id'] ?? 0);
            $byParent[$parent][]=$resolved;
        }
        $build=function(int $parent,int $depth=0) use (&$build,&$byParent): array {
            if ($depth>4) return [];
            $out=[];
            foreach ($byParent[$parent] ?? [] as $item) {
                $item['children']=$build((int)$item['id'],$depth+1);
                $out[]=$item;
            }
            return $out;
        };
        return $build(0);
    }

    /** @return array{data:array<string,mixed>,errors:list<string>} */
    public static function validateMenu(array $input, ?int $existingId=null): array
    {
        $name=trim((string)($input['name']??''));
        $key=self::key((string)($input['menu_key']??$name));
        $location=(string)($input['location']??'unassigned');
        $description=trim((string)($input['description']??''));
        $errors=[];
        if (mb_strlen($name)<2 || mb_strlen($name)>120) $errors[]='Menu name must be between 2 and 120 characters.';
        if ($key==='' || strlen($key)>100) $errors[]='Menu key is invalid.';
        if (!in_array($location,self::LOCATIONS,true)) $errors[]='Choose a valid menu location.';
        if (mb_strlen($description)>500) $errors[]='Description must be 500 characters or fewer.';
        $db=Database::connection();
        $stmt=$db->prepare('SELECT COUNT(*) FROM menus WHERE LOWER(menu_key)=LOWER(?) AND id<>?');
        $stmt->execute([$key,$existingId??0]);
        if ((int)$stmt->fetchColumn()>0) $errors[]='That menu key is already in use.';
        if ($location!=='unassigned') {
            $stmt=$db->prepare('SELECT COUNT(*) FROM menus WHERE location=? AND id<>?');
            $stmt->execute([$location,$existingId??0]);
            if ((int)$stmt->fetchColumn()>0) $errors[]='That menu location already has a menu. Move the existing menu first.';
        }
        return ['data'=>['name'=>$name,'menu_key'=>$key,'location'=>$location,'description'=>$description],'errors'=>$errors];
    }

    public static function create(array $data,int $userId): int
    {
        $stmt=Database::connection()->prepare('INSERT INTO menus (name,menu_key,location,description,created_by,created_at,updated_at) VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$data['name'],$data['menu_key'],$data['location'],$data['description']!==''?$data['description']:null,$userId?:null]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function update(int $id,array $data): void
    {
        $stmt=Database::connection()->prepare('UPDATE menus SET name=?,menu_key=?,location=?,description=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$data['name'],$data['menu_key'],$data['location'],$data['description']!==''?$data['description']:null,$id]);
    }

    public static function delete(int $id): void
    {
        Database::connection()->prepare('DELETE FROM menus WHERE id=?')->execute([$id]);
    }

    /** @return array{data:array<string,mixed>,errors:list<string>} */
    public static function validateItem(int $menuId,array $input,?int $existingId=null): array
    {
        if (!self::find($menuId)) return ['data'=>[],'errors'=>['Menu not found.']];
        $label=trim((string)($input['label']??''));
        $type=(string)($input['target_type']??'custom');
        $targetId=max(0,(int)($input['target_id']??0));
        $targetModelId=max(0,(int)($input['target_model_id']??0));
        $customUrl=trim((string)($input['custom_url']??''));
        $parentId=max(0,(int)($input['parent_id']??0));
        $sort=max(0,min(100000,(int)($input['sort_order']??100)));
        $newTab=isset($input['open_new_tab'])?1:0;
        $enabled=isset($input['is_enabled'])?1:0;
        $errors=[];
        if (mb_strlen($label)<1 || mb_strlen($label)>120) $errors[]='Menu label must be between 1 and 120 characters.';
        if (!in_array($type,self::TARGETS,true)) $errors[]='Choose a valid link type.';
        if ($type==='custom') {
            if (!self::safeUrl($customUrl)) $errors[]='Enter a relative path, http(s) URL, mailto link, or tel link.';
        } elseif ($targetId<1) $errors[]='Choose content to link to.';
        if ($type==='content_entry' && $targetModelId<1) $errors[]='Choose the structured content model.';
        if ($parentId>0) {
            $parent=self::item($parentId);
            if (!$parent || (int)$parent['menu_id']!==$menuId) $errors[]='Parent item does not belong to this menu.';
            if ($existingId!==null && ($parentId===$existingId || self::descendantOf($parentId,$existingId))) $errors[]='A menu item cannot be nested inside itself.';
        }
        return ['data'=>['label'=>$label,'target_type'=>$type,'target_id'=>$targetId?:null,'target_model_id'=>$targetModelId?:null,'custom_url'=>$customUrl!==''?$customUrl:null,'parent_id'=>$parentId?:null,'sort_order'=>$sort,'open_new_tab'=>$newTab,'is_enabled'=>$enabled],'errors'=>array_values(array_unique($errors))];
    }

    public static function createItem(int $menuId,array $data): int
    {
        $stmt=Database::connection()->prepare('INSERT INTO menu_items (menu_id,parent_id,label,target_type,target_id,target_model_id,custom_url,open_new_tab,is_enabled,sort_order,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$menuId,$data['parent_id'],$data['label'],$data['target_type'],$data['target_id'],$data['target_model_id'],$data['custom_url'],$data['open_new_tab'],$data['is_enabled'],$data['sort_order']]);
        return (int)Database::connection()->lastInsertId();
    }

    public static function updateItem(int $id,array $data): void
    {
        $stmt=Database::connection()->prepare('UPDATE menu_items SET parent_id=?,label=?,target_type=?,target_id=?,target_model_id=?,custom_url=?,open_new_tab=?,is_enabled=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$data['parent_id'],$data['label'],$data['target_type'],$data['target_id'],$data['target_model_id'],$data['custom_url'],$data['open_new_tab'],$data['is_enabled'],$data['sort_order'],$id]);
    }

    public static function deleteItem(int $id): void { Database::connection()->prepare('DELETE FROM menu_items WHERE id=?')->execute([$id]); }

    public static function item(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT * FROM menu_items WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /** @return array<string,list<array{id:int,label:string,secondary:string,model_id?:int}>> */
    public static function targetOptions(): array
    {
        return [
            'pages'=>self::searchTargets('page','',40),
            'posts'=>self::searchTargets('post','',40),
            'content'=>self::searchTargets('content_entry','',40),
        ];
    }

    /** @return list<array{id:int,label:string,secondary:string,model_id?:int}> */
    public static function searchTargets(string $type,string $query='',int $limit=30): array
    {
        if (!in_array($type,['page','post','content_entry'],true)) return [];
        $limit=max(1,min(50,$limit)); $query=mb_substr(trim($query),0,120); $like='%'.$query.'%'; $db=Database::connection(); $out=[];
        try {
            if ($type==='page') {
                $s=$db->prepare("SELECT id,title,path FROM pages WHERE deleted_at IS NULL AND status='published' AND (?='' OR title LIKE ? OR path LIKE ?) ORDER BY title LIMIT {$limit}");
                $s->execute([$query,$like,$like]); foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[]=['id'=>(int)$r['id'],'label'=>(string)$r['title'],'secondary'=>(string)$r['path']];
            } elseif ($type==='post') {
                $s=$db->prepare("SELECT id,title,slug FROM posts WHERE deleted_at IS NULL AND status='published' AND published_at<=UTC_TIMESTAMP() AND (?='' OR title LIKE ? OR slug LIKE ?) ORDER BY title LIMIT {$limit}");
                $s->execute([$query,$like,$like]); foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[]=['id'=>(int)$r['id'],'label'=>(string)$r['title'],'secondary'=>'/blog/'.(string)$r['slug']];
            } else {
                $sql="SELECT e.id,e.title,e.slug,m.id model_id,m.plural_name,m.slug model_slug FROM content_entries e JOIN content_models m ON m.id=e.model_id WHERE e.deleted_at IS NULL AND e.status='published' AND m.status='active' AND m.is_public=1 AND m.has_urls=1 AND (?='' OR e.title LIKE ? OR e.slug LIKE ? OR m.plural_name LIKE ?) ORDER BY m.plural_name,e.title LIMIT {$limit}";
                $s=$db->prepare($sql); $s->execute([$query,$like,$like,$like]); foreach($s->fetchAll(PDO::FETCH_ASSOC) as $r) $out[]=['id'=>(int)$r['id'],'model_id'=>(int)$r['model_id'],'label'=>(string)$r['title'],'secondary'=>'/'.(string)$r['model_slug'].'/'.(string)$r['slug']];
            }
        } catch (\Throwable) { return []; }
        return $out;
    }

    private static function resolvePublicItem(array $row): ?array
    {
        $url=''; $type=(string)$row['target_type']; $target=(int)($row['target_id']??0);
        try {
            if ($type==='custom') $url=(string)($row['custom_url']??'');
            elseif ($type==='page') { $s=Database::connection()->prepare("SELECT path FROM pages WHERE id=? AND deleted_at IS NULL AND status='published' LIMIT 1"); $s->execute([$target]); $url=(string)($s->fetchColumn()?:''); }
            elseif ($type==='post') { $s=Database::connection()->prepare("SELECT slug FROM posts WHERE id=? AND deleted_at IS NULL AND status='published' AND published_at<=UTC_TIMESTAMP() LIMIT 1"); $s->execute([$target]); $slug=(string)($s->fetchColumn()?:''); if ($slug!=='') $url='/blog/'.rawurlencode($slug); }
            elseif ($type==='content_entry') { $s=Database::connection()->prepare("SELECT e.slug,m.slug model_slug FROM content_entries e JOIN content_models m ON m.id=e.model_id WHERE e.id=? AND e.deleted_at IS NULL AND e.status='published' AND m.status='active' AND m.is_public=1 AND m.has_urls=1 LIMIT 1"); $s->execute([$target]); $r=$s->fetch(PDO::FETCH_ASSOC); if ($r) $url='/'.rawurlencode((string)$r['model_slug']).'/'.rawurlencode((string)$r['slug']); }
        } catch (\Throwable) { return null; }
        if ($url==='' || !self::safeUrl($url)) return null;
        return ['id'=>(int)$row['id'],'label'=>(string)$row['label'],'url'=>$url,'open_new_tab'=>(int)$row['open_new_tab']===1];
    }

    private static function descendantOf(int $candidate,int $ancestor): bool
    {
        for ($i=0;$i<20 && $candidate>0;$i++) { $row=self::item($candidate); if (!$row) return false; $candidate=(int)($row['parent_id']??0); if ($candidate===$ancestor) return true; }
        return false;
    }

    private static function safeUrl(string $url): bool
    {
        $url=trim($url); if ($url==='') return false;
        if (str_starts_with($url,'/') && !str_starts_with($url,'//')) return !str_contains($url,"\0");
        if (preg_match('#^(https?://|mailto:|tel:)#i',$url)!==1) return false;
        return preg_match('/[\x00-\x1F\x7F]/',$url)!==1;
    }

    private static function key(string $value): string
    {
        $value=mb_strtolower(trim($value)); $value=preg_replace('/[^a-z0-9]+/u','_',$value)??''; return trim($value,'_');
    }
    private function __construct(){}
}
