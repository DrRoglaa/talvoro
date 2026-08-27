<?php
use CMS\Core\Csrf;
use CMS\Core\Env;

$baseUrl=rtrim((string)Env::get('APP_URL',''),'/');
$selectedPath=(string)$selected['path'];
$mediaAssets=is_array($mediaAssets??null)?$mediaAssets:[];
$metaTitle=trim((string)($selected['meta_title']??''));
$metaDescription=trim((string)($selected['meta_description']??''));
$socialTitle=trim((string)($selected['social_title']??''));
$socialDescription=trim((string)($selected['social_description']??''));
$socialMediaId=(int)($selected['social_media_id']??0);
$socialImage=null;
foreach($mediaAssets as $asset) if((int)$asset['id']===$socialMediaId){$socialImage=$asset;break;}
$previewUrl=$baseUrl.($selectedPath==='/'?'':$selectedPath);
?>
<header class="page-header">
  <div><p class="eyebrow">Search visibility</p><h1>SEO</h1><p class="muted">One place to tune search, social sharing and structured data for Talvoro's public pages.</p></div>
  <span class="health-chip <?= $coverage['percent']>=80?'ok':'warning' ?>"><?= (int)$coverage['percent'] ?>% coverage</span>
</header>
<?php if($saved):?><div class="notice success">SEO settings saved.</div><?php endif;?>
<?php if($errors):?><div class="notice error" role="alert"><strong>Could not save SEO settings.</strong><ul><?php foreach($errors as $error):?><li><?= e($error) ?></li><?php endforeach;?></ul></div><?php endif;?>

<section class="card seo-summary">
  <div><p class="eyebrow">SEO 2.0</p><h2><?= (int)$coverage['configured'] ?> of <?= (int)$coverage['total'] ?> managed pages configured</h2><p class="muted">Talvoro supplies sensible defaults. Add overrides only where they improve how a page is understood or shared.</p></div>
  <div class="seo-summary-grid"><div><span>Canonical domain</span><strong><?= e($baseUrl?:'Not configured') ?></strong></div><div><span>Managed pages</span><strong><?= (int)$coverage['total'] ?></strong></div><div><span>Sitemap</span><strong>Dynamic</strong></div><div><span>Structured data</span><strong>Enabled</strong></div></div>
</section>

<section class="card">
  <div class="section-heading"><div><p class="eyebrow">Page settings</p><h2>Search & social appearance</h2></div></div>
  <form class="page-picker" method="get" action="<?= e(admin_url('/seo')) ?>">
    <label>Page<select name="path"><?php foreach($inventory as $page):?><option value="<?= e($page['path']) ?>" <?= $page['path']===$selectedPath?'selected':'' ?>><?= e(($page['kind']??'Page').' · '.$page['label'].' — '.$page['path']) ?></option><?php endforeach;?></select></label>
    <button class="button secondary" type="submit">Load page</button>
  </form>

  <form class="seo-form" method="post" action="<?= e(admin_url('/seo')) ?>">
    <?= Csrf::field() ?><input type="hidden" name="path" value="<?= e($selectedPath) ?>">
    <div class="form-section-numbered"><span class="step-number">1</span><div><h3>Search intent</h3><p class="muted">An optional internal note describing what this page should answer. It is never rendered publicly.</p></div></div>
    <label>Primary search phrase<input name="search_phrase" maxlength="255" value="<?= e($selected['search_phrase']??'') ?>" placeholder="e.g. privacy-first visual CMS"></label>

    <div class="form-section-numbered"><span class="step-number">2</span><div><h3>Search result</h3><p class="muted">Leave fields blank to let Talvoro use the page's own title and content defaults.</p></div></div>
    <label>Search title<input name="meta_title" maxlength="255" value="<?= e($metaTitle) ?>"><small class="field-hint"><?= mb_strlen($metaTitle) ?> characters</small></label>
    <label>Meta description<textarea name="meta_description" rows="4" maxlength="500"><?= e($metaDescription) ?></textarea><small class="field-hint"><?= mb_strlen($metaDescription) ?> characters</small></label>
    <div class="search-preview" aria-label="Search result preview"><small><?= e($previewUrl) ?></small><strong><?= e($metaTitle?:($selected['default_title']??'Search title preview')) ?></strong><p><?= e($metaDescription?:($selected['default_description']??'Talvoro will use the page description when available.')) ?></p></div>

    <div class="form-section-numbered"><span class="step-number mint">3</span><div><h3>Social sharing</h3><p class="muted">Override the search presentation for Open Graph and social cards, or inherit it automatically.</p></div></div>
    <div class="two-fields"><label>Social title<input name="social_title" maxlength="255" value="<?= e($socialTitle) ?>"></label><label>Social description<textarea name="social_description" rows="3" maxlength="500"><?= e($socialDescription) ?></textarea></label></div>
    <?php if($mediaAssets):?><label>Social image<select name="social_media_id"><option value="">Use page/default image</option><?php foreach($mediaAssets as $asset):?><option value="<?= (int)$asset['id'] ?>" <?= (int)$asset['id']===$socialMediaId?'selected':'' ?>><?= e($asset['label']) ?> (#<?= (int)$asset['id'] ?>)</option><?php endforeach;?></select></label><?php else:?><input type="hidden" name="social_media_id" value="<?= $socialMediaId>0?$socialMediaId:'' ?>"><p class="field-help">Media selection is unavailable for this role. Existing social-image references are preserved.</p><?php endif;?>
    <div class="seo-social-preview"><div class="seo-social-preview-image"><?php if($socialImage):?><img src="<?= e($socialImage['path']) ?>" alt="" loading="lazy"><?php else:?><span>Social image preview</span><?php endif;?></div><div><small><?= e(parse_url($baseUrl?:'https://example.com',PHP_URL_HOST)?:'Website') ?></small><strong><?= e($socialTitle?:$metaTitle?:($selected['default_title']??'Page title')) ?></strong><p><?= e($socialDescription?:$metaDescription?:($selected['default_description']??'Page description')) ?></p></div></div>

    <div class="form-section-numbered"><span class="step-number">4</span><div><h3>Technical SEO</h3><p class="muted">Advanced controls. Defaults are appropriate for most public pages.</p></div></div>
    <div class="seo-technical-grid">
      <label>Canonical URL<input name="canonical_url" value="<?= e($selected['canonical_url']??'') ?>" placeholder="<?= e($previewUrl) ?>"></label>
      <label>Robots<select name="robots"><?php foreach(['index,follow','index,nofollow','noindex,follow','noindex,nofollow'] as $robots):?><option value="<?= e($robots) ?>" <?= ($selected['robots']??'index,follow')===$robots?'selected':'' ?>><?= e($robots) ?></option><?php endforeach;?></select></label>
      <label>Structured data type<select name="schema_type"><?php foreach(['WebPage'=>'Web page','AboutPage'=>'About page','ContactPage'=>'Contact page','Article'=>'Article','CollectionPage'=>'Collection page'] as $value=>$label):?><option value="<?= e($value) ?>" <?= ($selected['schema_type']??'WebPage')===$value?'selected':'' ?>><?= e($label) ?></option><?php endforeach;?></select></label>
      <label class="check-label"><input type="checkbox" name="sitemap_enabled" value="1" <?= (int)($selected['sitemap_enabled']??1)===1?'checked':'' ?>> Include in sitemap</label>
    </div>
    <div class="form-actions"><button class="button" type="submit">Save SEO</button></div>
  </form>
</section>

<section class="card"><div class="section-heading"><div><p class="eyebrow">Site-wide audit</p><h2>Technical page inventory</h2></div><span class="soft-badge"><?= (int)$coverage['total'] ?> managed pages</span></div><div class="inventory-table"><div class="inventory-row header"><span>Page</span><span>Search metadata</span><span>Robots</span><span>Discovery</span></div><?php foreach($inventory as $page):?><a class="inventory-row" href="<?= e(admin_url('/seo?path='.rawurlencode($page['path']))) ?>"><span><strong><?= e($page['label']) ?></strong><small><?= e(($page['kind']??'Page').' · '.$page['path']) ?></small></span><span class="health-chip <?= $page['configured']?'ok':'warning' ?>"><?= $page['configured']?'Configured':'Using defaults' ?></span><span><?= e($page['seo']['robots']??'index,follow') ?></span><span><?= isset($page['seo'])&&(int)($page['seo']['sitemap_enabled']??1)===0?'Sitemap off':'Sitemap on' ?></span></a><?php endforeach;?></div></section>
