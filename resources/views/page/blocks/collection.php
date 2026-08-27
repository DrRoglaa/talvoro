<?php
use CMS\Core\PageBlocks;
$collection=is_array($block['_collection']??null)?$block['_collection']:[];
$model=is_array($collection['model']??null)?$collection['model']:[];
$items=is_array($collection['items']??null)?$collection['items']:[];
$presentation=(string)($collection['presentation']??'cards');
$picture=static function(?array $image): string {
    if (!$image || empty($image['src'])) return '';
    $byMime=[];
    foreach (($image['sources']??[]) as $source) if (is_array($source)) $byMime[(string)($source['mime']??'')][]=$source;
    $html='<picture class="collection-picture">';
    foreach (['image/avif','image/webp'] as $mime) if (!empty($byMime[$mime])) {
        $srcset=implode(', ',array_map(static fn(array $v): string => (string)$v['src'].' '.(int)$v['width'].'w',$byMime[$mime]));
        $html.='<source type="'.e($mime).'" srcset="'.e($srcset).'" sizes="(max-width: 760px) 100vw, 520px">';
    }
    $html.='<img src="'.e((string)$image['src']).'" alt="'.e((string)($image['alt']??'')).'" width="'.(int)($image['width']??0).'" height="'.(int)($image['height']??0).'" loading="lazy" style="object-position:'.(float)($image['focal_x']??50).'% '.(float)($image['focal_y']??50).'%">';
    return $html.'</picture>';
};
$viewUrl=(string)($collection['view_url']??'');
?>
<section class="<?= e(PageBlocks::sectionClasses($block, 'page-collection collection-' . $presentation)) ?>" data-model-key="<?= e((string)($model['model_key']??'')) ?>">
    <div class="home-section-heading">
        <div>
            <?php if (!empty($block['eyebrow'])): ?><p class="home-section-kicker"><?= e($block['eyebrow']) ?></p><?php endif; ?>
            <?php if (!empty($block['heading'])): ?><h2><?= e($block['heading']) ?></h2><?php endif; ?>
        </div>
        <?php if (!empty($block['view_label']) && $viewUrl!==''): ?><a class="home-section-link" href="<?= e($viewUrl) ?>"><?= e($block['view_label']) ?> <span aria-hidden="true">→</span></a><?php endif; ?>
    </div>

    <?php if ($presentation==='testimonials'): ?>
        <div class="page-testimonial-grid collection-testimonial-grid">
            <?php foreach ($items as $item): ?>
            <blockquote class="page-testimonial collection-testimonial<?= !empty($item['highlighted'])?' is-highlighted':'' ?>">
                <?php if ((int)($item['rating']??0)>0): ?><div class="collection-rating" aria-label="<?= (int)$item['rating'] ?> out of 5 stars"><?= str_repeat('★',(int)$item['rating']) ?></div><?php endif; ?>
                <p>“<?= e((string)($item['quote']??$item['summary']??'')) ?>”</p>
                <footer><strong><?= e((string)($item['person']??$item['title']??'')) ?></strong><?php if (!empty($item['role'])): ?><span><?= e($item['role']) ?></span><?php endif; ?></footer>
            </blockquote>
            <?php endforeach; ?>
        </div>
    <?php elseif ($presentation==='pricing'): ?>
        <div class="collection-pricing-grid">
            <?php foreach ($items as $item): ?>
            <article class="collection-price-card<?= !empty($item['highlighted'])?' is-highlighted':'' ?>">
                <?php if (!empty($item['highlighted'])): ?><span class="collection-badge">Recommended</span><?php endif; ?>
                <h3><?= e($item['title']) ?></h3>
                <?php if (($item['price']??null)!==null): ?><p class="collection-price"><strong><?= e(trim((string)($item['currency']??'').' '.(fmod((float)$item['price'],1.0)===0.0?number_format((float)$item['price'],0,'.',','):number_format((float)$item['price'],2,'.',',')))) ?></strong><?php if (!empty($item['period'])): ?><span><?= e($item['period']) ?></span><?php endif; ?></p><?php endif; ?>
                <?php if (!empty($item['summary'])): ?><p><?= e($item['summary']) ?></p><?php endif; ?>
                <?php if (!empty($item['features'])): ?><p class="collection-features"><?= nl2br(e($item['features'])) ?></p><?php endif; ?>
                <?php if (!empty($item['cta_url'])): ?><a class="home-pill primary" href="<?= e($item['cta_url']) ?>"><?= e($item['cta_label']??'Learn more') ?> <span aria-hidden="true">→</span></a><?php endif; ?>
            </article>
            <?php endforeach; ?>
        </div>
    <?php elseif ($presentation==='faq'): ?>
        <div class="page-faq-list collection-faq-list">
            <?php foreach ($items as $index=>$item): ?><details<?= $index===0?' open':'' ?>><summary><?= e($item['title']) ?></summary><p><?= e((string)($item['answer']??$item['summary']??'')) ?></p></details><?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="collection-grid presentation-<?= e($presentation) ?>">
            <?php foreach ($items as $item): $itemUrl=(string)($item['url']??''); if ($itemUrl==='' && !empty($item['external_url'])) $itemUrl=(string)$item['external_url']; ?>
            <article class="collection-card<?= !empty($item['highlighted'])?' is-highlighted':'' ?>">
                <?php $imageHtml=$picture(is_array($item['image']??null)?$item['image']:null); if ($imageHtml!==''): ?><figure class="collection-card-media"><?= $imageHtml ?></figure><?php endif; ?>
                <div class="collection-card-body">
                    <div class="collection-card-meta-row"><?php if (!empty($item['badge'])): ?><span class="collection-badge"><?= e($item['badge']) ?></span><?php endif; ?><?php if (!empty($item['meta'])): ?><span class="collection-meta"><?= e($item['meta']) ?></span><?php endif; ?></div>
                    <h3><?php if ($itemUrl!==''): ?><a href="<?= e($itemUrl) ?>"><?= e($item['title']) ?></a><?php else: ?><?= e($item['title']) ?><?php endif; ?></h3>
                    <?php if ($presentation==='events' && !empty($item['date'])): ?><p class="collection-date"><?= e($item['date']) ?><?php if (!empty($item['location'])): ?> <span>· <?= e($item['location']) ?></span><?php endif; ?></p><?php endif; ?>
                    <?php if ($presentation==='people' && !empty($item['role'])): ?><p class="collection-role"><?= e($item['role']) ?><?php if (!empty($item['location'])): ?> <span>· <?= e($item['location']) ?></span><?php endif; ?></p><?php endif; ?>
                    <?php if (!empty($item['summary'])): ?><p><?= e($item['summary']) ?></p><?php endif; ?>
                    <?php if ($itemUrl!==''): ?><a class="text-link collection-item-link" href="<?= e($itemUrl) ?>">Learn more <span aria-hidden="true">→</span></a><?php endif; ?>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
