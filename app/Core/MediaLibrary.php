<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;
use RuntimeException;

final class MediaLibrary
{
    /** @return list<array<string,mixed>> */
    public static function all(string $search = '', int $limit = 240, ?int $folderId = null, int $offset = 0): array
    {
        $limit=max(1,min(500,$limit)); $offset=max(0,$offset); $search=trim($search);
        $sql='SELECT m.id,m.folder_id,m.storage_path,m.original_name,m.title,m.alt_text,m.caption,m.mime_type,m.size_bytes,m.width,m.height,m.focal_x,m.focal_y,m.created_at,m.updated_at,m.replaced_at,u.display_name created_by_name,f.name folder_name '
            .'FROM media_assets m LEFT JOIN users u ON u.id=m.created_by LEFT JOIN media_folders f ON f.id=m.folder_id WHERE 1=1';
        $params=[];
        if ($search!=='') { $sql.=' AND (m.original_name LIKE ? OR m.title LIKE ? OR m.alt_text LIKE ? OR m.caption LIKE ?)'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like]; }
        if ($folderId!==null) { if ($folderId===0) $sql.=' AND m.folder_id IS NULL'; else { $sql.=' AND m.folder_id=?'; $params[]=$folderId; } }
        $sql.=' ORDER BY m.created_at DESC,m.id DESC LIMIT '.$limit.' OFFSET '.$offset;
        $stmt=Database::connection()->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function countAll(string $search='', ?int $folderId=null): int
    {
        $sql='SELECT COUNT(*) FROM media_assets m WHERE 1=1'; $params=[]; $search=trim($search);
        if ($search!=='') { $sql.=' AND (m.original_name LIKE ? OR m.title LIKE ? OR m.alt_text LIKE ? OR m.caption LIKE ?)'; $like='%'.$search.'%'; $params=[$like,$like,$like,$like]; }
        if ($folderId!==null) { if ($folderId===0) $sql.=' AND m.folder_id IS NULL'; else { $sql.=' AND m.folder_id=?'; $params[]=$folderId; } }
        $stmt=Database::connection()->prepare($sql); $stmt->execute($params); return (int)$stmt->fetchColumn();
    }

    /** @return list<array{id:int,path:string,label:string,width:int,height:int,focal_x:float,focal_y:float}> */
    public static function pickerAssets(int $limit = 200): array
    {
        $assets=[]; foreach (self::all('', $limit) as $asset) $assets[]=['id'=>(int)$asset['id'],'path'=>(string)$asset['storage_path'],'label'=>(string)($asset['title'] ?: $asset['original_name']),'width'=>(int)$asset['width'],'height'=>(int)$asset['height'],'focal_x'=>(float)$asset['focal_x'],'focal_y'=>(float)$asset['focal_y']]; return $assets;
    }

    public static function find(int $id): ?array
    {
        $stmt=Database::connection()->prepare('SELECT m.*,u.display_name created_by_name,f.name folder_name FROM media_assets m LEFT JOIN users u ON u.id=m.created_by LEFT JOIN media_folders f ON f.id=m.folder_id WHERE m.id=? LIMIT 1');
        $stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if (!$row) return null; $row['variants']=self::variants($id); $row['usage']=self::usageReferences($id); return $row;
    }

    public static function upload(array $file,string $altText,int $userId,?int $folderId=null,string $title='',string $caption=''): int
    {
        $meta=self::validateMetadata($title,$altText,$caption,$folderId); $original=trim((string)($file['name']??'')); if ($original==='') throw new RuntimeException('Choose an image to upload.');
        $path=SiteAssets::storeImage($file,'media');
        try {
            $info=self::inspectStored($path);
            $stmt=Database::connection()->prepare('INSERT INTO media_assets (folder_id,storage_path,original_name,title,alt_text,caption,mime_type,size_bytes,width,height,focal_x,focal_y,created_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,50,50,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
            $stmt->execute([$meta['folder_id'],$path,mb_substr(basename($original),0,255),$meta['title'],$meta['alt_text'],$meta['caption'],$info['mime'],$info['size'],$info['width'],$info['height'],$userId>0?$userId:null]);
            $id=(int)Database::connection()->lastInsertId(); self::regenerateVariants($id); return $id;
        } catch (\Throwable $e) { SiteAssets::remove($path); throw $e; }
    }

    public static function updateDetails(int $id,array $input): void
    {
        $asset=self::find($id); if (!$asset) throw new RuntimeException('Media item not found.');
        $folderRaw=(string)($input['folder_id']??''); $folderId=$folderRaw===''?null:max(0,(int)$folderRaw);
        $meta=self::validateMetadata((string)($input['title']??''),(string)($input['alt_text']??''),(string)($input['caption']??''),$folderId);
        $fx=max(0,min(100,(float)($input['focal_x']??50))); $fy=max(0,min(100,(float)($input['focal_y']??50)));
        $stmt=Database::connection()->prepare('UPDATE media_assets SET folder_id=?,title=?,alt_text=?,caption=?,focal_x=?,focal_y=?,updated_at=UTC_TIMESTAMP() WHERE id=?');
        $stmt->execute([$meta['folder_id'],$meta['title'],$meta['alt_text'],$meta['caption'],$fx,$fy,$id]);
    }

    public static function updateAlt(int $id,string $altText): void
    {
        $asset=self::find($id); if (!$asset) throw new RuntimeException('Media item not found.');
        self::updateDetails($id,['title'=>(string)($asset['title']??''),'alt_text'=>$altText,'caption'=>(string)($asset['caption']??''),'folder_id'=>$asset['folder_id']===null?'':(string)$asset['folder_id'],'focal_x'=>(string)($asset['focal_x']??50),'focal_y'=>(string)($asset['focal_y']??50)]);
    }

    public static function replace(int $id,array $file): void
    {
        $asset=self::find($id); if (!$asset) throw new RuntimeException('Media item not found.');
        $original=trim((string)($file['name']??'')); if ($original==='') throw new RuntimeException('Choose a replacement image.');
        $new=SiteAssets::storeImage($file,'media');
        try {
            $info=self::inspectStored($new); $db=Database::connection(); $db->beginTransaction();
            $stmt=$db->prepare('UPDATE media_assets SET storage_path=?,original_name=?,mime_type=?,size_bytes=?,width=?,height=?,replaced_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE id=?');
            $stmt->execute([$new,mb_substr(basename($original),0,255),$info['mime'],$info['size'],$info['width'],$info['height'],$id]);
            $db->prepare('DELETE FROM media_variants WHERE media_id=?')->execute([$id]); $db->commit();
            self::removeVariantFiles($asset['variants']??[]); SiteAssets::remove((string)$asset['storage_path']); self::regenerateVariants($id);
        } catch (\Throwable $e) { if (Database::connection()->inTransaction()) Database::connection()->rollBack(); SiteAssets::remove($new); throw $e; }
    }

    public static function duplicateForUsage(int $id,string $kind): string
    {
        $asset=self::find($id); if (!$asset) throw new RuntimeException('Selected media item no longer exists.'); return SiteAssets::duplicateStoredImage((string)$asset['storage_path'],$kind);
    }

    /** @return list<array<string,mixed>> */
    public static function folders(): array
    {
        try { return Database::connection()->query('SELECT f.*,COUNT(m.id) asset_count FROM media_folders f LEFT JOIN media_assets m ON m.folder_id=f.id GROUP BY f.id ORDER BY f.parent_id IS NOT NULL,f.sort_order,f.name')->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (\Throwable) { return []; }
    }

    public static function createFolder(string $name,?int $parentId,int $userId): int
    {
        $name=trim($name); if (mb_strlen($name)<1 || mb_strlen($name)>120) throw new RuntimeException('Folder name must be between 1 and 120 characters.');
        if ($parentId!==null && $parentId>0 && !self::folder($parentId)) throw new RuntimeException('Parent folder not found.');
        $key=self::folderKey($name); $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM media_folders WHERE ((parent_id IS NULL AND ? IS NULL) OR parent_id=?) AND LOWER(folder_key)=LOWER(?)'); $stmt->execute([$parentId,$parentId,$key]); if ((int)$stmt->fetchColumn()>0) throw new RuntimeException('A folder with that name already exists here.');
        $stmt=Database::connection()->prepare('INSERT INTO media_folders (parent_id,name,folder_key,created_by,created_at,updated_at) VALUES (?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())'); $stmt->execute([$parentId?:null,$name,$key,$userId?:null]); return (int)Database::connection()->lastInsertId();
    }

    public static function deleteFolder(int $id): void
    {
        $folder=self::folder($id); if (!$folder) return;
        $stmt=Database::connection()->prepare('SELECT COUNT(*) FROM media_folders WHERE parent_id=?'); $stmt->execute([$id]); if ((int)$stmt->fetchColumn()>0) throw new RuntimeException('Move or delete child folders first.');
        $stmt=Database::connection()->prepare('UPDATE media_assets SET folder_id=NULL,updated_at=UTC_TIMESTAMP() WHERE folder_id=?'); $stmt->execute([$id]); Database::connection()->prepare('DELETE FROM media_folders WHERE id=?')->execute([$id]);
    }

    public static function folder(int $id): ?array { $s=Database::connection()->prepare('SELECT * FROM media_folders WHERE id=? LIMIT 1'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }

    /** @return list<array<string,mixed>> */
    public static function variants(int $id): array
    {
        try { $s=Database::connection()->prepare('SELECT * FROM media_variants WHERE media_id=? ORDER BY width'); $s->execute([$id]); return $s->fetchAll(PDO::FETCH_ASSOC) ?: []; } catch (\Throwable) { return []; }
    }

    public static function responsive(int $id): array
    {
        $asset=self::find($id); if (!$asset) return []; $sources=[]; foreach ($asset['variants'] as $v) $sources[]=['src'=>(string)$v['storage_path'],'width'=>(int)$v['width'],'mime'=>(string)$v['mime_type']]; return ['src'=>(string)$asset['storage_path'],'width'=>(int)$asset['width'],'height'=>(int)$asset['height'],'alt'=>(string)($asset['alt_text']??''),'focal_x'=>(float)$asset['focal_x'],'focal_y'=>(float)$asset['focal_y'],'sources'=>$sources];
    }

    /**
     * Resolve responsive data for multiple assets in two bounded queries.
     * Used by dynamic structured-content collections to avoid N+1 media lookups.
     *
     * @param list<int> $ids
     * @return array<int,array<string,mixed>>
     */
    public static function responsiveBatch(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id): bool=>$id>0)));
        if (!$ids) return [];
        $ids=array_slice($ids,0,100);
        $marks=implode(',',array_fill(0,count($ids),'?'));
        $db=Database::connection();
        $assets=$db->prepare("SELECT id,storage_path,alt_text,width,height,focal_x,focal_y FROM media_assets WHERE id IN ($marks)");
        $assets->execute($ids);
        $out=[];
        foreach ($assets->fetchAll(PDO::FETCH_ASSOC) ?: [] as $asset) {
            $id=(int)$asset['id'];
            $out[$id]=[
                'src'=>(string)$asset['storage_path'],
                'width'=>(int)$asset['width'],
                'height'=>(int)$asset['height'],
                'alt'=>(string)($asset['alt_text']??''),
                'focal_x'=>(float)($asset['focal_x']??50),
                'focal_y'=>(float)($asset['focal_y']??50),
                'sources'=>[],
            ];
        }
        if (!$out) return [];
        $variants=$db->prepare("SELECT media_id,storage_path,mime_type,width,height FROM media_variants WHERE media_id IN ($marks) ORDER BY media_id,width");
        $variants->execute($ids);
        foreach ($variants->fetchAll(PDO::FETCH_ASSOC) ?: [] as $variant) {
            $id=(int)$variant['media_id'];
            if (!isset($out[$id])) continue;
            $out[$id]['sources'][]=[
                'src'=>(string)$variant['storage_path'],
                'width'=>(int)$variant['width'],
                'mime'=>(string)$variant['mime_type'],
            ];
        }
        return $out;
    }

    public static function regenerateVariants(int $id): void
    {
        $asset=self::findRaw($id); if (!$asset || !function_exists('imagecreatetruecolor')) return;
        $source=base_path('public'.(string)$asset['storage_path']); if (!is_file($source)) return;
        $image=self::loadGdImage($source,(string)$asset['mime_type']); if (!$image) return;
        $root=base_path('public/uploads/site');
        foreach ([480,960,1440] as $targetW) {
            if ((int)$asset['width'] <= $targetW) continue;
            $ratio=$targetW/(int)$asset['width']; $targetH=max(1,(int)round((int)$asset['height']*$ratio));
            $dest=imagecreatetruecolor($targetW,$targetH); imagealphablending($dest,false); imagesavealpha($dest,true);
            imagecopyresampled($dest,$image,0,0,0,0,$targetW,$targetH,(int)$asset['width'],(int)$asset['height']);
            $written=0;
            if (function_exists('imageavif')) $written+=self::writeVariant($id,$dest,$targetW,$targetH,'avif','image/avif',static fn($img,$path): bool => imageavif($img,$path,58));
            if (function_exists('imagewebp')) $written+=self::writeVariant($id,$dest,$targetW,$targetH,'webp','image/webp',static fn($img,$path): bool => imagewebp($img,$path,82));
            if ($written===0) {
                $mime=(string)$asset['mime_type']; $ext=$mime==='image/png'?'png':'jpg';
                self::writeVariant($id,$dest,$targetW,$targetH,$ext,$mime,static fn($img,$path): bool => $ext==='png' ? imagepng($img,$path,6) : imagejpeg($img,$path,84));
            }
            imagedestroy($dest);
        }
        imagedestroy($image);
    }

    public static function transform(int $id,string $operation): void
    {
        $allowed=['rotate_left','rotate_right','crop_square','crop_4_3','crop_16_9'];
        if (!in_array($operation,$allowed,true)) throw new RuntimeException('Unsupported media edit operation.');
        $asset=self::find($id); if (!$asset) throw new RuntimeException('Media item not found.');
        if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('Image editing is unavailable because the GD image extension is not enabled.');
        $source=base_path('public'.(string)$asset['storage_path']); $image=self::loadGdImage($source,(string)$asset['mime_type']);
        if (!$image) throw new RuntimeException('This image format cannot be edited.');
        $edited=null;
        try {
            if (str_starts_with($operation,'rotate_')) {
                $angle=$operation==='rotate_left'?90:-90; $background=imagecolorallocatealpha($image,0,0,0,127); $edited=imagerotate($image,$angle,$background); if (!$edited) throw new RuntimeException('Image rotation failed.'); imagesavealpha($edited,true);
            } else {
                $ratio=match($operation){'crop_square'=>1.0,'crop_4_3'=>4/3,'crop_16_9'=>16/9,default=>1.0};
                $w=imagesx($image); $h=imagesy($image); $current=$w/$h;
                if ($current>$ratio) { $cropH=$h; $cropW=max(1,(int)round($h*$ratio)); } else { $cropW=$w; $cropH=max(1,(int)round($w/$ratio)); }
                $cx=(float)($asset['focal_x']??50)/100*$w; $cy=(float)($asset['focal_y']??50)/100*$h;
                $x=max(0,min($w-$cropW,(int)round($cx-$cropW/2))); $y=max(0,min($h-$cropH,(int)round($cy-$cropH/2)));
                $edited=imagecrop($image,['x'=>$x,'y'=>$y,'width'=>$cropW,'height'=>$cropH]); if (!$edited) throw new RuntimeException('Image crop failed.'); imagesavealpha($edited,true);
            }
            $new=self::saveEditedImage($edited,(string)$asset['mime_type']); $info=self::inspectStored($new); $db=Database::connection(); $db->beginTransaction();
            $db->prepare('UPDATE media_assets SET storage_path=?,mime_type=?,size_bytes=?,width=?,height=?,focal_x=50,focal_y=50,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$new,$info['mime'],$info['size'],$info['width'],$info['height'],$id]);
            $db->prepare('DELETE FROM media_variants WHERE media_id=?')->execute([$id]); $db->commit();
            self::removeVariantFiles($asset['variants']??[]); SiteAssets::remove((string)$asset['storage_path']); self::regenerateVariants($id);
        } catch (\Throwable $e) { if (Database::connection()->inTransaction()) Database::connection()->rollBack(); if (isset($new) && is_string($new)) SiteAssets::remove($new); throw $e; }
        finally { if (is_object($edited)) imagedestroy($edited); imagedestroy($image); }
    }

    /** @return array{current:int,revisions:int,total:int} */
    public static function structuredUsage(int $id): array
    {
        if ($id<1) return ['current'=>0,'revisions'=>0,'total'=>0]; try { $db=Database::connection(); $current=$db->prepare('SELECT COUNT(DISTINCT entry_id) FROM content_media_usage WHERE media_id=?'); $current->execute([$id]); $revisions=$db->prepare('SELECT COUNT(DISTINCT revision_id) FROM content_revision_media_usage WHERE media_id=?'); $revisions->execute([$id]); $c=(int)$current->fetchColumn(); $r=(int)$revisions->fetchColumn(); return ['current'=>$c,'revisions'=>$r,'total'=>$c+$r]; } catch (\Throwable) { return ['current'=>0,'revisions'=>0,'total'=>0]; }
    }

    /** @return list<array{kind:string,label:string,url:string}> */
    public static function usageReferences(int $id): array
    {
        if ($id<1) return []; $map=self::usageReferencesForAssets([$id]); return $map[$id]??[];
    }

    /** @param list<int> $ids @return array<int,list<array{kind:string,label:string,url:string}>> */
    public static function usageReferencesForAssets(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $id): bool=>$id>0))); if (!$ids) return [];
        $out=array_fill_keys($ids,[]); $db=Database::connection(); $marks=implode(',',array_fill(0,count($ids),'?'));
        try { $s=$db->prepare("SELECT DISTINCT u.media_id,e.id,e.title,m.plural_name,m.slug FROM content_media_usage u JOIN content_entries e ON e.id=u.entry_id JOIN content_models m ON m.id=e.model_id WHERE u.media_id IN ($marks) ORDER BY e.title LIMIT 5000"); $s->execute($ids); foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $mid=(int)$r['media_id']; $out[$mid][]=['kind'=>(string)$r['plural_name'],'label'=>(string)$r['title'],'url'=>admin_url('/content/'.(string)$r['slug'].'/'.(int)$r['id'].'/edit')]; } } catch (\Throwable) {}
        try { $s=$db->prepare("SELECT social_media_id,path FROM seo_pages WHERE social_media_id IN ($marks) ORDER BY path LIMIT 5000"); $s->execute($ids); foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { $mid=(int)$r['social_media_id']; $out[$mid][]=['kind'=>'SEO','label'=>(string)$r['path'],'url'=>admin_url('/seo?path='.rawurlencode((string)$r['path']))]; } } catch (\Throwable) {}
        return $out;
    }

    public static function delete(int $id): void
    {
        $asset=self::find($id); if (!$asset) return; $usage=self::structuredUsage($id); if ($usage['current']>0) throw new RuntimeException('This image is used by '.$usage['current'].' structured content '.($usage['current']===1?'entry':'entries').'. Remove or replace those references before permanently deleting the image.'); if ($usage['revisions']>0) throw new RuntimeException('This image is retained by '.$usage['revisions'].' saved structured content '.($usage['revisions']===1?'revision':'revisions').'. Talvoro keeps it so those revisions can still be restored.');
        $refs=self::usageReferences($id); if ($refs) throw new RuntimeException('This image is still referenced by Talvoro content or SEO. Use the Media usage panel to find and replace those references first.');
        $db=Database::connection(); $db->beginTransaction(); try { $db->prepare('DELETE FROM media_assets WHERE id=?')->execute([$id]); $db->commit(); } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw new RuntimeException('This image is still referenced by Talvoro content or revision history and cannot be permanently deleted yet.'); }
        self::removeVariantFiles($asset['variants']??[]); SiteAssets::remove((string)$asset['storage_path']);
    }


    private static function loadGdImage(string $file,string $mime): mixed
    {
        $fn=match($mime){'image/jpeg'=>'imagecreatefromjpeg','image/png'=>'imagecreatefrompng','image/webp'=>'imagecreatefromwebp',default=>null};
        return $fn && function_exists($fn) ? @$fn($file) : null;
    }
    private static function writeVariant(int $id,mixed $image,int $width,int $height,string $ext,string $mime,callable $writer): int
    {
        $file=base_path('public/uploads/site/media-'.$id.'-'.$width.'.'.$ext); if (!$writer($image,$file) || !is_file($file)) return 0; @chmod($file,0644);
        $public='/uploads/site/'.basename($file); $key='w'.$width.'_'.$ext; $stmt=Database::connection()->prepare('INSERT INTO media_variants (media_id,variant_key,storage_path,mime_type,width,height,size_bytes,created_at) VALUES (?,?,?,?,?,?,?,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE storage_path=VALUES(storage_path),mime_type=VALUES(mime_type),width=VALUES(width),height=VALUES(height),size_bytes=VALUES(size_bytes),created_at=VALUES(created_at)'); $stmt->execute([$id,$key,$public,$mime,$width,$height,(int)filesize($file)]); return 1;
    }
    private static function saveEditedImage(mixed $image,string $preferredMime): string
    {
        $dir=base_path('public/uploads/site'); if (!is_dir($dir) && !mkdir($dir,0755,true) && !is_dir($dir)) throw new RuntimeException('Could not prepare media storage.');
        $token=bin2hex(random_bytes(10));
        if ($preferredMime==='image/png' && function_exists('imagepng')) { $file=$dir.'/media-edit-'.$token.'.png'; if (!imagepng($image,$file,6)) throw new RuntimeException('Could not save edited image.'); }
        elseif ($preferredMime==='image/webp' && function_exists('imagewebp')) { $file=$dir.'/media-edit-'.$token.'.webp'; if (!imagewebp($image,$file,88)) throw new RuntimeException('Could not save edited image.'); }
        else { $file=$dir.'/media-edit-'.$token.'.jpg'; $flat=imagecreatetruecolor(imagesx($image),imagesy($image)); $white=imagecolorallocate($flat,255,255,255); imagefill($flat,0,0,$white); imagecopy($flat,$image,0,0,0,0,imagesx($image),imagesy($image)); $ok=imagejpeg($flat,$file,90); imagedestroy($flat); if (!$ok) throw new RuntimeException('Could not save edited image.'); }
        @chmod($file,0644); return '/uploads/site/'.basename($file);
    }

    private static function findRaw(int $id): ?array { $s=Database::connection()->prepare('SELECT * FROM media_assets WHERE id=? LIMIT 1'); $s->execute([$id]); return $s->fetch(PDO::FETCH_ASSOC) ?: null; }
    private static function inspectStored(string $publicPath): array { $safe=HomePage::safeStoredAssetPath($publicPath); if ($safe===''||!str_starts_with($safe,'/uploads/site/')) throw new RuntimeException('Stored media path is invalid.'); $file=base_path('public'.$safe); if (!is_file($file)) throw new RuntimeException('Stored media file is missing.'); $finfo=new \finfo(FILEINFO_MIME_TYPE); $mime=strtolower((string)$finfo->file($file)); if (!in_array($mime,['image/jpeg','image/png','image/webp'],true)) throw new RuntimeException('Stored media type is not supported.'); $size=(int)filesize($file); $image=@getimagesize($file); if (!is_array($image)||empty($image[0])||empty($image[1])) throw new RuntimeException('Stored media image could not be read.'); return ['mime'=>$mime,'size'=>$size,'width'=>(int)$image[0],'height'=>(int)$image[1]]; }
    private static function validateMetadata(string $title,string $alt,string $caption,?int $folderId): array { $title=trim($title); $alt=trim($alt); $caption=trim($caption); if (mb_strlen($title)>255) throw new RuntimeException('Media title must be 255 characters or fewer.'); if (mb_strlen($alt)>255) throw new RuntimeException('Alt text must be 255 characters or fewer.'); if (mb_strlen($caption)>500) throw new RuntimeException('Caption must be 500 characters or fewer.'); if ($folderId!==null && $folderId>0 && !self::folder($folderId)) throw new RuntimeException('Selected media folder no longer exists.'); return ['title'=>$title!==''?$title:null,'alt_text'=>$alt!==''?$alt:null,'caption'=>$caption!==''?$caption:null,'folder_id'=>$folderId&&$folderId>0?$folderId:null]; }
    private static function folderKey(string $name): string { $v=mb_strtolower(trim($name)); $v=preg_replace('/[^a-z0-9]+/u','-',$v)??''; $v=trim($v,'-'); return $v!==''?$v:'folder-'.bin2hex(random_bytes(3)); }
    private static function removeVariantFiles(array $variants): void { foreach ($variants as $variant) SiteAssets::remove((string)($variant['storage_path']??'')); }
    private function __construct(){}
}
