<?php
use CMS\Core\ContentModels;
$modelKey=ContentModels::fieldKey((string)($model['model_key']??''));
if($modelKey==='') $modelKey='content';
$picture=static function(?array $image): string {
    if (!$image || empty($image['src'])) return '';
    $byMime=[]; foreach(($image['sources']??[]) as $source) if(is_array($source)) $byMime[(string)($source['mime']??'')][]=$source;
    $html='<picture class="content-archive-picture">';
    foreach(['image/avif','image/webp'] as $mime) if(!empty($byMime[$mime])) {
        $srcset=implode(', ',array_map(static fn(array $v): string => (string)$v['src'].' '.(int)$v['width'].'w',$byMime[$mime]));
        $html.='<source type="'.e($mime).'" srcset="'.e($srcset).'" sizes="(max-width: 760px) 100vw, 520px">';
    }
    $html.='<img src="'.e((string)$image['src']).'" alt="'.e((string)($image['alt']??'')).'" width="'.(int)($image['width']??0).'" height="'.(int)($image['height']??0).'" loading="lazy" style="object-position:'.(float)($image['focal_x']??50).'% '.(float)($image['focal_y']??50).'%">';
    return $html.'</picture>';
};
?>
<div class="content-archive model-<?= e($modelKey) ?>" data-model-key="<?= e($modelKey) ?>">
<header class="content-archive-hero"><p class="eyebrow"><?= e($model['singular_name']) ?> collection</p><h1><?= e($model['plural_name']) ?></h1><?php if (!empty($model['description'])): ?><p><?= e($model['description']) ?></p><?php endif; ?></header>
<?php if (!$entries): ?>
<section class="public-empty"><h2>Nothing published yet.</h2><p>Published <?= e(mb_strtolower((string)$model['plural_name'])) ?> will appear here.</p></section>
<?php else: ?>
<section class="content-archive-grid content-archive-rich presentation-<?= e((string)($presentation??'cards')) ?>">
<?php foreach ($entries as $entry): $url=(string)($entry['url']??''); $imageHtml=$picture(is_array($entry['image']??null)?$entry['image']:null); ?>
<article class="content-archive-card">
    <?php if ($imageHtml!==''): ?><figure class="content-archive-image"><?= $imageHtml ?></figure><?php endif; ?>
    <div class="content-archive-card-body">
        <div class="collection-card-meta-row"><p class="eyebrow"><?= e($model['singular_name']) ?></p><?php if (!empty($entry['badge'])): ?><span class="collection-badge"><?= e($entry['badge']) ?></span><?php endif; ?></div>
        <h2><?php if ($url!==''): ?><a href="<?= e($url) ?>"><?= e($entry['title']) ?></a><?php else: ?><?= e($entry['title']) ?><?php endif; ?></h2>
        <?php if (!empty($entry['meta'])): ?><p class="collection-meta"><?= e($entry['meta']) ?></p><?php endif; ?>
        <?php if (!empty($entry['summary'])): ?><p><?= e($entry['summary']) ?></p><?php endif; ?>
        <?php if ($url!==''): ?><a class="text-link" href="<?= e($url) ?>">View <?= e(mb_strtolower((string)$model['singular_name'])) ?> →</a><?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
</section>
<?php if (!empty($pagination) && (int)$pagination['pages'] > 1): $currentPage=(int)$pagination['page']; $totalPages=(int)$pagination['pages']; $base='/'.rawurlencode((string)$model['slug']); ?>
<nav class="pagination public-pagination" aria-label="<?= e($model['plural_name']) ?> pages">
<?php if ($currentPage > 1): ?><a href="<?= e($base) ?>?page=<?= $currentPage-1 ?>">← Newer</a><?php else: ?><span></span><?php endif; ?>
<span>Page <?= $currentPage ?> of <?= $totalPages ?></span>
<?php if ($currentPage < $totalPages): ?><a href="<?= e($base) ?>?page=<?= $currentPage+1 ?>">Older →</a><?php else: ?><span></span><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
</div>
