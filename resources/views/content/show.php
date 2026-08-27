<?php
use CMS\Core\ContentModels;
use CMS\Core\CustomContent;
use CMS\Core\MediaLibrary;
$values=is_array($entry['values']??null)?$entry['values']:[];
$responsiveImage=static function(int $mediaId,string $class=''): string {
    $data=MediaLibrary::responsive($mediaId); if(!$data) return '';
    $byMime=[]; foreach($data['sources']??[] as $source) $byMime[(string)$source['mime']][]=$source;
    $html='<picture'.($class!==''?' class="'.e($class).'"':'').'>';
    foreach(['image/avif','image/webp'] as $mime) if(!empty($byMime[$mime])) { $srcset=implode(', ',array_map(static fn(array $v): string => (string)$v['src'].' '.(int)$v['width'].'w',$byMime[$mime])); $html.='<source type="'.e($mime).'" srcset="'.e($srcset).'" sizes="(max-width: 760px) 100vw, 960px">'; }
    $html.='<img src="'.e((string)$data['src']).'" alt="'.e((string)$data['alt']).'" width="'.(int)$data['width'].'" height="'.(int)$data['height'].'" loading="lazy" style="object-position:'.(float)$data['focal_x'].'% '.(float)$data['focal_y'].'%">';
    return $html.'</picture>';
};
$renderPublic=null;
$renderPublic=static function(array $field,mixed $value) use (&$renderPublic,$entry,$responsiveImage): string {
    if ($value===null||$value===''||$value===[]) return '';
    $type=(string)$field['field_type'];
    if ($type==='boolean') return !empty($value)?'Yes':'No';
    if ($type==='rich_text') return '<div class="rich-public-content">'.(string)$value.'</div>';
    if ($type==='media') { $image=$responsiveImage((int)$value); return $image!==''?'<figure class="structured-public-image">'.$image.'</figure>':''; }
    if ($type==='gallery') { $html='<div class="structured-public-gallery">'; foreach ((array)$value as $id) { $image=$responsiveImage((int)$id); if ($image!=='') $html.='<figure>'.$image.'</figure>'; } return $html.'</div>'; }
    if ($type==='relation') { $targets=CustomContent::relationTargets((int)$entry['id'],(string)$field['field_key']); if (!$targets) return ''; $parts=[]; foreach ($targets as $target) { if (!empty($target['deleted_at']) || $target['status']!=='published' || (int)($target['is_public']??0)!==1) continue; $label=e($target['title']); if ((int)($target['has_urls']??0)===1) $parts[]='<a href="/'.e($target['model_slug']).'/'.e($target['slug']).'">'.$label.'</a>'; else $parts[]=$label; } return implode(', ',$parts); }
    if ($type==='component'||$type==='repeater') { $settings=is_array($field['settings']??null)?$field['settings']:ContentModels::decodeSettings($field['settings_json']??null); $subs=ContentModels::componentFields((int)($settings['component_id']??0)); $items=$type==='repeater'?(array)$value:[$value]; $out='<div class="structured-public-components">'; foreach ($items as $item) { if (!is_array($item)) continue; $out.='<div class="structured-public-component">'; foreach ($subs as $sub) { $subValue=$item[$sub['field_key']]??null; $rendered=$renderPublic($sub,$subValue); if ($rendered!=='') $out.='<div><strong>'.e($sub['label']).'</strong><div>'.$rendered.'</div></div>'; } $out.='</div>'; } return $out.'</div>'; }
    if (is_array($value)) return e(implode(', ',array_map('strval',$value)));
    if ($type==='email') return '<a href="mailto:'.e((string)$value).'">'.e((string)$value).'</a>';
    if ($type==='url') return '<a href="'.e((string)$value).'" rel="noopener">'.e((string)$value).'</a>';
    return e((string)$value);
};
?>
<article class="structured-public-entry">
<header class="structured-public-header"><a class="back-link" href="<?= (int)$model['has_archive']===1 ? '/'.e($model['slug']) : '/' ?>">← <?= (int)$model['has_archive']===1 ? e($model['plural_name']) : 'Home' ?></a><p class="eyebrow"><?= e($model['singular_name']) ?></p><h1><?= e($entry['title']) ?></h1><?php $featuredHtml=(int)($entry['featured_media_id']??0)>0?$responsiveImage((int)$entry['featured_media_id'],'structured-featured-picture'):''; if ($featuredHtml!==''): ?><figure class="structured-featured-image"><?= $featuredHtml ?></figure><?php endif; ?></header>
<div class="structured-public-fields"><?php foreach ($fields as $field): $rendered=$renderPublic($field,$values[$field['field_key']]??null); if ($rendered==='') continue; ?><section class="structured-public-field structured-public-field-<?= e($field['field_type']) ?>"><h2><?= e($field['label']) ?></h2><div><?= $rendered ?></div></section><?php endforeach; ?></div>
<?php if ($relatedTo): ?><section class="structured-related"><p class="eyebrow">Connected content</p><h2>Related stories and entries</h2><div class="content-archive-grid"><?php foreach ($relatedTo as $related): ?><?php if ($related['status']!=='published' || (int)($related['is_public']??0)!==1 || (int)($related['has_urls']??0)!==1) continue; ?><article class="content-archive-card"><small><?= e($related['singular_name']) ?></small><h3><a href="/<?= e($related['model_slug']) ?>/<?= e($related['slug']) ?>"><?= e($related['title']) ?></a></h3></article><?php endforeach; ?></div></section><?php endif; ?>
</article>
