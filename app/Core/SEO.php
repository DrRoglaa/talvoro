<?php
declare(strict_types=1);

namespace CMS\Core;

use PDO;

final class SEO
{
    /** @return list<array<string,mixed>> */
    public static function inventory(): array
    {
        $items=[['path'=>'/','label'=>'Home','kind'=>'Page']];
        if (Settings::blogEnabled()) $items[]=['path'=>'/blog','label'=>'Blog','kind'=>'Page'];
        foreach (Pages::publishedForSeo() as $page) $items[]=['path'=>(string)$page['path'],'label'=>(string)$page['title'],'kind'=>'Page'];
        if (Settings::blogEnabled()) {
            try {
                $rows=Database::connection()->query("SELECT title,slug FROM posts WHERE deleted_at IS NULL AND status='published' AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP() ORDER BY published_at DESC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) $items[]=['path'=>'/blog/'.rawurlencode((string)$row['slug']),'label'=>(string)$row['title'],'kind'=>'Post'];
            } catch (\Throwable) {}
        }
        foreach ($items as &$item) { $row=self::get((string)$item['path']); $item['seo']=$row; $item['configured']=self::isConfigured($row); }
        unset($item);
        return $items;
    }

    public static function get(string $path): ?array
    {
        try { $stmt=Database::connection()->prepare('SELECT * FROM seo_pages WHERE path=? LIMIT 1'); $stmt->execute([self::normalizePath($path)]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null; }
        catch (\Throwable) { return null; }
    }

    public static function editable(string $path): array
    {
        $path=self::normalizePath($path); $row=self::get($path);
        return $row ?: ['path'=>$path,'search_phrase'=>'','meta_title'=>'','meta_description'=>'','social_title'=>'','social_description'=>'','social_media_id'=>null,'canonical_url'=>'','robots'=>'index,follow','sitemap_enabled'=>1,'schema_type'=>str_starts_with($path,'/blog/')?'Article':'WebPage'];
    }

    /** @return list<string> */
    public static function validate(array $input): array
    {
        $errors=[]; $path=self::normalizePath((string)($input['path']??'/')); $title=trim((string)($input['meta_title']??'')); $description=trim((string)($input['meta_description']??'')); $socialTitle=trim((string)($input['social_title']??'')); $socialDescription=trim((string)($input['social_description']??'')); $canonical=trim((string)($input['canonical_url']??'')); $robots=trim((string)($input['robots']??'index,follow')); $schema=(string)($input['schema_type']??'WebPage'); $mediaId=max(0,(int)($input['social_media_id']??0));
        if (strlen($path)>191 || AdminPath::isProtectedPublicPath($path)) $errors[]='Choose a valid public site path.';
        if (mb_strlen($title)>255) $errors[]='Meta title must be 255 characters or fewer.';
        if (mb_strlen($description)>500) $errors[]='Meta description must be 500 characters or fewer.';
        if (mb_strlen($socialTitle)>255) $errors[]='Social title must be 255 characters or fewer.';
        if (mb_strlen($socialDescription)>500) $errors[]='Social description must be 500 characters or fewer.';
        if ($canonical!=='' && filter_var($canonical,FILTER_VALIDATE_URL)===false) $errors[]='Canonical URL must be a complete URL.';
        if (!in_array($robots,['index,follow','index,nofollow','noindex,follow','noindex,nofollow'],true)) $errors[]='Invalid robots directive.';
        if (!in_array($schema,['WebPage','AboutPage','ContactPage','Article','CollectionPage'],true)) $errors[]='Choose a supported structured-data type.';
        if ($mediaId>0 && !MediaLibrary::find($mediaId)) $errors[]='Selected social image no longer exists.';
        return $errors;
    }

    public static function save(array $input,int $userId): void
    {
        $path=self::normalizePath((string)$input['path']);
        $values=[$path,self::nullable($input['search_phrase']??null),self::nullable($input['meta_title']??null),self::nullable($input['meta_description']??null),self::nullable($input['social_title']??null),self::nullable($input['social_description']??null),max(0,(int)($input['social_media_id']??0))?:null,self::nullable($input['canonical_url']??null),(string)($input['robots']??'index,follow'),isset($input['sitemap_enabled'])?1:0,(string)($input['schema_type']??'WebPage'),$userId];
        $stmt=Database::connection()->prepare('INSERT INTO seo_pages (path,search_phrase,meta_title,meta_description,social_title,social_description,social_media_id,canonical_url,robots,sitemap_enabled,schema_type,updated_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE search_phrase=VALUES(search_phrase),meta_title=VALUES(meta_title),meta_description=VALUES(meta_description),social_title=VALUES(social_title),social_description=VALUES(social_description),social_media_id=VALUES(social_media_id),canonical_url=VALUES(canonical_url),robots=VALUES(robots),sitemap_enabled=VALUES(sitemap_enabled),schema_type=VALUES(schema_type),updated_by=VALUES(updated_by),updated_at=UTC_TIMESTAMP()');
        $stmt->execute($values);
    }

    public static function coverage(): array
    {
        $inventory=self::inventory(); $total=count($inventory); $configured=count(array_filter($inventory,static fn(array $p):bool=>(bool)$p['configured']));
        return ['total'=>$total,'configured'=>$configured,'percent'=>$total>0?(int)round(($configured/$total)*100):100];
    }

    public static function metaForPath(string $path,string $fallbackTitle): array
    {
        $path=self::normalizePath($path); $row=self::get($path); $appName=HomePage::publicSiteName(); $base=rtrim((string)Env::get('APP_URL',''),'/');
        $title=trim((string)($row['meta_title']??'')); if ($title==='') $title=$path==='/'?$appName:$fallbackTitle.' — '.$appName;
        $description=trim((string)($row['meta_description']??'')); $socialTitle=trim((string)($row['social_title']??''))?:$title; $socialDescription=trim((string)($row['social_description']??''))?:$description; $canonical=trim((string)($row['canonical_url']??'')); if ($canonical===''&&$base!=='') $canonical=$base.($path==='/'?'':$path);
        $socialImage=''; $mediaId=(int)($row['social_media_id']??0); if ($mediaId>0) { $media=MediaLibrary::find($mediaId); if ($media) $socialImage=$base!==''?$base.(string)$media['storage_path']:(string)$media['storage_path']; }
        return ['title'=>$title,'description'=>$description,'social_title'=>$socialTitle,'social_description'=>$socialDescription,'canonical'=>$canonical,'robots'=>(string)($row['robots']??'index,follow'),'social_image'=>$socialImage,'schema_type'=>(string)($row['schema_type']??(str_starts_with($path,'/blog/')?'Article':'WebPage'))];
    }

    public static function structuredData(string $path,array $meta): array
    {
        $type=(string)($meta['schema_type']??'WebPage'); if (!in_array($type,['WebPage','AboutPage','ContactPage','Article','CollectionPage'],true)) $type='WebPage';
        $data=['@context'=>'https://schema.org','@type'=>$type,'name'=>(string)($meta['social_title']??$meta['title']??HomePage::publicSiteName())];
        if (trim((string)($meta['description']??''))!=='') $data['description']=(string)$meta['description']; if (trim((string)($meta['canonical']??''))!=='') $data['url']=(string)$meta['canonical']; if (trim((string)($meta['social_image']??''))!=='') $data['image']=(string)$meta['social_image'];
        if ($type==='Article') { $data['headline']=(string)($meta['social_title']??$meta['title']??''); $data['publisher']=['@type'=>'Organization','name'=>HomePage::publicSiteName()]; }
        return $data;
    }

    public static function robots(): string
    {
        $base=rtrim((string)Env::get('APP_URL',''),'/'); if (Settings::siteMode()==='development'&&Settings::searchHandling()==='maintenance') return "User-agent: *\nDisallow: /\n"; $text="User-agent: *\nAllow: /\n"; if ($base!=='') $text.="Sitemap: {$base}/sitemap.xml\n"; return $text;
    }

    public static function sitemap(): string
    {
        $base=rtrim((string)Env::get('APP_URL',''),'/'); $items=[];
        if (Settings::siteMode()==='development'&&Settings::searchHandling()==='maintenance') return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
        if (Settings::siteMode()==='development'&&Settings::searchHandling()==='prelaunch') $items[]='/';
        else {
            foreach (self::inventory() as $page) { $seo=$page['seo']; if ($seo&&((int)$seo['sitemap_enabled']!==1||str_starts_with((string)$seo['robots'],'noindex'))) continue; $items[]=(string)$page['path']; }
            if (Settings::blogEnabled()) { foreach (Categories::publicArchiveCategories() as $category) { $path='/blog/category/'.rawurlencode((string)$category['slug']); $row=self::get($path); if ($row&&((int)$row['sitemap_enabled']!==1||str_starts_with((string)$row['robots'],'noindex'))) continue; $items[]=$path; } }
            try { foreach (ContentModels::publicModels() as $model) { if ((int)($model['sitemap_enabled']??0)!==1) continue; if ((int)($model['has_archive']??0)===1) $items[]='/'.rawurlencode((string)$model['slug']); if ((int)($model['has_urls']??0)===1) foreach (CustomContent::publicSlugs((int)$model['id']) as $slug) $items[]='/'.rawurlencode((string)$model['slug']).'/'.rawurlencode($slug); } } catch (\Throwable) {}
        }
        $items=array_values(array_unique($items)); $xml='<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'; foreach ($items as $path) { $url=$base.($path==='/'?'':$path); $xml.='<url><loc>'.htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></url>'; } return $xml.'</urlset>';
    }

    private static function isConfigured(?array $row): bool { return $row!==null && trim((string)($row['meta_title']??''))!=='' && trim((string)($row['meta_description']??''))!==''; }
    private static function normalizePath(string $path): string { $path=trim($path); $parsed=parse_url($path,PHP_URL_PATH); $path=is_string($parsed)?$parsed:$path; $path='/'.ltrim($path,'/'); return $path!=='/'?rtrim($path,'/'):'/'; }
    private static function nullable(mixed $value): ?string { $value=trim((string)$value); return $value===''?null:$value; }
    private function __construct(){}
}
