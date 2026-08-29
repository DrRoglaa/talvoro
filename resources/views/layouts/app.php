<?php
use CMS\Core\AdminPath;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\ContentModels;
use CMS\Core\Gate;
use CMS\Core\HomePage;
use CMS\Core\MediaLibrary;
use CMS\Core\Menus;
use CMS\Core\Pages;
use CMS\Core\SEO;
use CMS\Core\Settings;

$user = Auth::user();
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$adminBase = AdminPath::baseUrl();
$isAdminRequest = AdminPath::isAdminRequest($path);
$isPrivateAuth = AdminPath::isAuthRequest($path);
$publicPreview = !empty($publicPreview);
$isAdminArea = $user !== null && $isAdminRequest && !$isPrivateAuth && !$publicPreview;
$isActive = static function (string $target, bool $prefix = false) use ($path): string {
    $active = $prefix ? str_starts_with($path, $target) : $path === $target;
    return $active ? ' is-active' : '';
};
$siteName = HomePage::publicSiteName();
$pageTitle = (string)($title ?? $siteName);
$meta = (!$isAdminRequest || $publicPreview) ? SEO::metaForPath($publicPreview ? '/' : $path, $pageTitle) : null;
$pathSeo = $meta !== null ? SEO::get($publicPreview ? '/' : $path) : null;

if ($meta !== null && str_starts_with($path, '/blog/') && isset($post) && is_array($post)) {
    $postTitle = trim((string)($post['title'] ?? $pageTitle));
    $postExcerpt = trim((string)($post['excerpt'] ?? ''));
    if (trim((string)($pathSeo['meta_title'] ?? '')) === '') $meta['title'] = $postTitle . ' — ' . $siteName;
    if (trim((string)($pathSeo['meta_description'] ?? '')) === '') $meta['description'] = $postExcerpt;
    if (trim((string)($pathSeo['social_title'] ?? '')) === '' && trim((string)($pathSeo['meta_title'] ?? '')) === '') $meta['social_title'] = $postTitle;
    if (trim((string)($pathSeo['social_description'] ?? '')) === '' && trim((string)($pathSeo['meta_description'] ?? '')) === '') $meta['social_description'] = $postExcerpt;
}

if ($meta !== null && isset($category) && is_array($category) && str_starts_with($path, '/blog/category/')) {
    $categoryName = trim((string)($category['name'] ?? $pageTitle));
    $categoryTitle = trim((string)($category['seo_title'] ?? ''));
    $categoryDescription = trim((string)($category['meta_description'] ?? ''));
    if ($categoryDescription === '') $categoryDescription = trim((string)($category['description'] ?? ''));
    $meta['title'] = ($categoryTitle !== '' ? $categoryTitle : $categoryName) . ' — ' . $siteName;
    $meta['description'] = $categoryDescription;
    $meta['social_title'] = $categoryTitle !== '' ? $categoryTitle : $categoryName;
    $meta['social_description'] = $categoryDescription;
    $meta['robots'] = 'index,follow';
}

if ($meta !== null && isset($page) && is_array($page)) {
    $pageExcerpt = trim((string)($page['excerpt'] ?? ''));
    if ($pageExcerpt !== '' && trim((string)$meta['description']) === '') {
        $meta['description'] = $pageExcerpt;
        $meta['social_description'] = $pageExcerpt;
    }
}

$structuredData = null;
if ($meta !== null && isset($entry, $model) && is_array($entry) && is_array($model)) {
    $entryName = trim((string)($entry['title'] ?? $pageTitle));
    $seoEnabled = (int)($model['enable_seo'] ?? 1) === 1;
    $entryTitle = $seoEnabled && trim((string)($entry['seo_title'] ?? '')) !== '' ? trim((string)$entry['seo_title']) : $entryName;
    $entryDescription = $seoEnabled ? trim((string)($entry['seo_description'] ?? '')) : '';
    if ($entryDescription === '' && isset($fields) && is_array($fields)) {
        $values = is_array($entry['values'] ?? null) ? $entry['values'] : [];
        foreach ($fields as $field) {
            if (!in_array((string)($field['field_type'] ?? ''), ['text','textarea','rich_text'], true)) continue;
            $candidate = trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)($values[(string)$field['field_key']] ?? '')),ENT_QUOTES|ENT_HTML5,'UTF-8')) ?: '');
            if ($candidate !== '') { $entryDescription = mb_substr($candidate,0,220); break; }
        }
    }
    $socialTitle = $seoEnabled && trim((string)($entry['social_title'] ?? '')) !== '' ? trim((string)$entry['social_title']) : $entryTitle;
    $socialDescription = $seoEnabled && trim((string)($entry['social_description'] ?? '')) !== '' ? trim((string)$entry['social_description']) : $entryDescription;
    $mediaId = $seoEnabled ? (int)($entry['social_media_id'] ?? 0) : 0;
    if ($mediaId < 1) $mediaId = (int)($entry['featured_media_id'] ?? 0);
    $socialImage = $mediaId > 0 ? MediaLibrary::find($mediaId) : null;
    $meta['title'] = $entryTitle . ' — ' . $siteName;
    $meta['description'] = $entryDescription;
    $meta['social_title'] = $socialTitle;
    $meta['social_description'] = $socialDescription;
    if ($seoEnabled && trim((string)($entry['canonical_url'] ?? '')) !== '') $meta['canonical'] = trim((string)$entry['canonical_url']);
    $meta['robots'] = $seoEnabled ? (string)($entry['robots'] ?? 'index,follow') : 'index,follow';
    $meta['social_image'] = is_array($socialImage) ? (rtrim((string)\CMS\Core\Env::get('APP_URL',''),'/') . (string)$socialImage['storage_path']) : '';

    $typeMap = ['event'=>'Event','testimonial'=>'Review','team_member'=>'Person','person'=>'Person','product'=>'Product','location'=>'Place','portfolio_item'=>'CreativeWork'];
    $schemaType = $typeMap[(string)($model['model_key'] ?? '')] ?? 'Thing';
    $structuredData = ['@context'=>'https://schema.org','@type'=>$schemaType,'name'=>$entryName];
    if ($entryDescription !== '') $structuredData['description']=$entryDescription;
    if (($meta['canonical'] ?? '') !== '') $structuredData['url']=$meta['canonical'];
    if (($meta['social_image'] ?? '') !== '') $structuredData['image']=$meta['social_image'];
}

if ($meta !== null && $structuredData === null) $structuredData = SEO::structuredData($path,$meta);

if ($publicPreview && $meta !== null) {
    $meta['robots']='noindex,nofollow';
    if (isset($entry,$model) && is_array($entry) && is_array($model) && (int)($model['is_public']??0)===1 && (int)($model['has_urls']??0)===1) {
        $root=rtrim((string)($meta['canonical']??''),'/');
        $meta['canonical']=$root . '/' . rawurlencode((string)$model['slug']) . '/' . rawurlencode((string)$entry['slug']);
    } else $meta['canonical']='';
}

$siteMode = Settings::siteMode();
$frontendTheme = Settings::frontendTheme();
$publicPages = (!$isAdminRequest || $publicPreview) ? Pages::navigation() : [];
$footerPages = (!$isAdminRequest || $publicPreview) ? Pages::footerNavigation() : [];
$primaryMenu = (!$isAdminRequest || $publicPreview) ? Menus::publicTree('primary') : [];
$mobileMenu = (!$isAdminRequest || $publicPreview) ? Menus::publicTree('mobile') : [];
$footerMenu = (!$isAdminRequest || $publicPreview) ? Menus::publicTree('footer') : [];
$renderPublicMenu = static function (array $items, string $class = '') use (&$renderPublicMenu, $path): void {
    foreach ($items as $item) {
        $url=(string)($item['url']??''); $children=is_array($item['children']??null)?$item['children']:[];
        $active=$url==='/' ? $path==='/' : ($path===$url || str_starts_with($path,rtrim($url,'/').'/'));
        if ($children) { echo '<div class="public-nav-group'.($active?' is-active':'').'"><a href="'.e($url).'"'.(!empty($item['open_new_tab'])?' target="_blank" rel="noopener noreferrer"':'').'>'.e((string)$item['label']).'</a><div class="public-nav-submenu">'; $renderPublicMenu($children,'submenu'); echo '</div></div>'; }
        else echo '<a class="'.($active?'is-active':'').'" href="'.e($url).'"'.(!empty($item['open_new_tab'])?' target="_blank" rel="noopener noreferrer"':'').'>'.e((string)$item['label']).'</a>';
    }
};
$publicLogo = HomePage::logoPath();
$publicTagline = HomePage::publicTagline();
$isTalvoroProductSite = (!$isAdminRequest || $publicPreview)
    && !$isPrivateAuth
    && $frontendTheme === 'talvoro-editorial'
    && mb_strtolower(trim($siteName)) === 'talvoro';
$reservedAdminNavLabels = ['posts','categories','pages','contact submissions','media','blog settings'];
$reservedAdminNavSlugs = ['posts','categories','pages','contact-submissions','media','blog-settings'];
$customModels = $isAdminArea && Gate::allows('custom_content.view') ? array_values(array_filter(
    ContentModels::all(true),
    static function (array $m) use ($reservedAdminNavLabels, $reservedAdminNavSlugs): bool {
        if (!Gate::allowsModel((int)$m['id'], 'view')) return false;
        $label = strtolower(trim((string)($m['plural_name'] ?? '')));
        $slug = strtolower(trim((string)($m['slug'] ?? '')));
        return !in_array($label, $reservedAdminNavLabels, true) && !in_array($slug, $reservedAdminNavSlugs, true);
    }
)) : [];
$navIcon = static function (string $name): string {
    $paths = [
        'overview' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>',
        'posts' => '<path d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        'categories' => '<path d="M4 7h7l2 3h7v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7Z"/><path d="M4 7V6a2 2 0 0 1 2-2h4l2 3"/>',
        'pages' => '<path d="M7 3h7l4 4v14H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z"/><path d="M14 3v5h5M9 13h6M9 17h5"/>',
        'patterns' => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><path d="M16.5 13v7M13 16.5h7"/>',
        'media' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m4 17 5-5 4 4 2-2 5 5"/>',
        'menus' => '<path d="M5 7h14M5 12h14M5 17h14"/><circle cx="3" cy="7" r=".7" fill="currentColor" stroke="none"/><circle cx="3" cy="12" r=".7" fill="currentColor" stroke="none"/><circle cx="3" cy="17" r=".7" fill="currentColor" stroke="none"/>',
        'models' => '<path d="M4 6.5 12 3l8 3.5-8 3.5-8-3.5Z"/><path d="m4 11 8 3.5 8-3.5M4 15.5 12 19l8-3.5"/>',
        'custom' => '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M8 9h8M8 13h5M8 17h7"/>',
        'collection' => '<path d="M4 6.5 12 3l8 3.5-8 3.5-8-3.5Z"/><path d="m4 11 8 3.5 8-3.5M4 15.5 12 19l8-3.5"/>',
        'paw' => '<circle cx="7" cy="8" r="2"/><circle cx="12" cy="6" r="2"/><circle cx="17" cy="8" r="2"/><path d="M7.5 16.5c0-3 2-5.5 4.5-5.5s4.5 2.5 4.5 5.5c0 2-1.5 3.5-4.5 3.5s-4.5-1.5-4.5-3.5Z"/>',
        'calendar' => '<rect x="4" y="5" width="16" height="15" rx="2"/><path d="M8 3v4M16 3v4M4 10h16"/>',
        'quote' => '<path d="M5 6h6v6H7c0 2 1 3 3 4v2c-3-1-5-3-5-7V6ZM13 6h6v6h-4c0 2 1 3 3 4v2c-3-1-5-3-5-7V6Z"/>',
        'person' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c.8-5 3.5-7 8-7s7.2 2 8 7"/>',
        'product' => '<path d="M5 8 12 4l7 4v9l-7 4-7-4V8Z"/><path d="m5 8 7 4 7-4M12 12v9"/>',
        'pin' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
        'portfolio' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V4h6v3M3 12h18M10 12v2h4v-2"/>',
        'star' => '<path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3Z"/>',
        'heart' => '<path d="M20.8 5.8a5.5 5.5 0 0 0-7.8 0L12 6.8l-1-1a5.5 5.5 0 0 0-7.8 7.8L12 22l8.8-8.4a5.5 5.5 0 0 0 0-7.8Z"/>',
        'blog' => '<path d="M5 5h14v14H5z"/><path d="M8 9h8M8 13h8M8 17h5"/>',
        'analytics' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'seo' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4M8 11h6M11 8v6"/>',
        'redirects' => '<path d="M4 7h11a4 4 0 0 1 4 4v1"/><path d="m16 9 3 3 3-3"/><path d="M20 17H9a4 4 0 0 1-4-4v-1"/><path d="m8 15-3-3-3 3"/>',
        'health' => '<path d="M3 12h4l2-5 4 10 2-5h6"/>',
        'design' => '<path d="M4 18.5V21h2.5L18.7 8.8l-2.5-2.5L4 18.5Z"/><path d="m14.8 7.7 2.5 2.5M13 4l2-2 5 5-2 2"/><path d="M4 5h6M4 9h5"/>',
        'themes' => '<path d="M12 3a9 9 0 1 0 0 18h1.2a1.8 1.8 0 0 0 1.2-3.1 1.8 1.8 0 0 1 1.2-3.1H18A3 3 0 0 0 21 12a9 9 0 0 0-9-9Z"/><circle cx="7.5" cy="10" r="1"/><circle cx="10" cy="6.5" r="1"/><circle cx="15" cy="7" r="1"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
        'security' => '<path d="M12 3 20 6v5c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
        'system' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6V21h-4v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H3v-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V3h4v.1a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.1v4H21a1.7 1.7 0 0 0-1.6 1Z"/>',
        'users' => '<circle cx="9" cy="8" r="3"/><path d="M3 20c.5-4 2.5-6 6-6s5.5 2 6 6"/><circle cx="17" cy="9" r="2"/><path d="M16 14c3 0 4.5 1.7 5 5"/>',
    ];
    $body = $paths[$name] ?? $paths['overview'];
    return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round">' . $body . '</svg>';
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="shortcut icon" href="/favicon.ico">
    <title><?= e($meta['title'] ?? $pageTitle) ?></title>
    <?php if ($isAdminRequest && !$publicPreview): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <?php if ($meta): ?>
        <?php if ($meta['description'] !== ''): ?><meta name="description" content="<?= e($meta['description']) ?>"><?php endif; ?>
        <meta name="robots" content="<?= e($meta['robots']) ?>">
        <?php if ($meta['canonical'] !== ''): ?><link rel="canonical" href="<?= e($meta['canonical']) ?>"><?php endif; ?>
        <meta property="og:title" content="<?= e($meta['social_title']) ?>">
        <?php if ($meta['social_description'] !== ''): ?><meta property="og:description" content="<?= e($meta['social_description']) ?>"><?php endif; ?>
        <meta property="og:type" content="<?= ($meta['schema_type'] ?? '') === 'Article' ? 'article' : 'website' ?>">
        <?php if ($meta['canonical'] !== ''): ?><meta property="og:url" content="<?= e($meta['canonical']) ?>"><?php endif; ?>
        <?php if (!empty($meta['social_image'])): ?><meta property="og:image" content="<?= e($meta['social_image']) ?>"><?php endif; ?>
        <meta name="twitter:card" content="<?= !empty($meta['social_image']) ? 'summary_large_image' : 'summary' ?>">
        <meta name="twitter:title" content="<?= e($meta['social_title']) ?>">
        <?php if ($meta['social_description'] !== ''): ?><meta name="twitter:description" content="<?= e($meta['social_description']) ?>"><?php endif; ?>
        <?php if (!empty($meta['social_image'])): ?><meta name="twitter:image" content="<?= e($meta['social_image']) ?>"><?php endif; ?>
    <?php endif; ?>
    <?php if (is_array($structuredData)): ?><script type="application/ld+json"><?= json_encode($structuredData, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script><?php endif; ?>
    <link rel="stylesheet" href="/assets/css/talvoro-foundation.css?v=<?= e(app_version()) ?>-<?= e((string)(@filemtime(base_path('public/assets/css/talvoro-foundation.css')) ?: 0)) ?>">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e(app_version()) ?>">
    <?php if ($isAdminArea): ?>
        <link rel="stylesheet" href="/assets/css/talvoro-admin.css?v=<?= e(app_version()) ?>-<?= e((string)(@filemtime(base_path('public/assets/css/talvoro-admin.css')) ?: 0)) ?>">
    <?php elseif (!$isPrivateAuth): ?>
        <link rel="stylesheet" href="/assets/css/talvoro-public.css?v=<?= e(app_version()) ?>-<?= e((string)(@filemtime(base_path('public/assets/css/talvoro-public.css')) ?: 0)) ?>">
    <?php endif; ?>
    <?php if (!$isAdminRequest || $publicPreview): ?>
        <link rel="stylesheet" href="/theme.css?v=<?= e(app_version()) ?>">
    <?php endif; ?>
    <?php if ($isAdminArea && $path === AdminPath::url('/security')): ?><script src="/assets/js/admin-security.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
    <?php if ($isAdminArea): ?><script src="/assets/js/admin-nav.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
    <?php if ($isAdminArea): ?><script src="/assets/js/admin-form-state.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
    <?php if ($isAdminArea && $path === AdminPath::url('/media')): ?><script src="/assets/js/media-library.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
    <?php if ($isAdminArea && str_starts_with($path, AdminPath::url('/menus'))): ?><script src="/assets/js/menus.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
    <?php if ($isAdminArea && preg_match('#^' . preg_quote($adminBase, '#') . '/users/\d+/security$#', $path)): ?><script src="/assets/js/recovery-codes.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
</head>
<body class="<?= $isAdminArea ? 'admin-body' : ($isPrivateAuth ? 'auth-body private-auth-body' : 'public-body theme-' . e($frontendTheme) . ($isTalvoroProductSite ? ' talvoro-product-site' : '')) ?>">
<div class="ambient ambient-one" aria-hidden="true"></div>
<div class="ambient ambient-two" aria-hidden="true"></div>

<?php if ($isAdminArea): ?>
<div class="shell admin-shell talvoro-admin-shell" data-admin-shell data-nav-state="closed">
    <a class="cms-skip-link" href="#cms-main-content">Skip to content</a>
    <button class="cms-sidebar-backdrop" type="button" data-admin-nav-close aria-label="Close navigation" tabindex="-1"></button>
    <aside class="cms-sidebar" id="cms-sidebar" data-admin-nav>
        <div class="cms-sidebar-head">
            <a class="brand cms-sidebar-brand" href="<?= e($adminBase) ?>">
                <span class="brand-mark" aria-hidden="true"><span></span></span>
                <div class="brand-copy"><strong>Talvoro</strong><small>Website CMS</small></div>
            </a>
            <button class="cms-sidebar-close" type="button" data-admin-nav-close aria-label="Close navigation">×</button>
        </div>

        <nav class="cms-sidebar-nav" aria-label="CMS navigation">
            <div class="cms-nav-group">
                <span class="cms-nav-label">Overview</span>
                <a class="cms-nav-link<?= $isActive($adminBase) ?>" href="<?= e($adminBase) ?>"><span class="cms-nav-icon"><?= $navIcon('overview') ?></span><span>Overview</span></a>
            </div>

            <div class="cms-nav-group">
                <span class="cms-nav-label">Content</span>
                <?php if (Gate::allows('pages.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/pages', true) ?>" href="<?= e(admin_url('/pages')) ?>"><span class="cms-nav-icon"><?= $navIcon('pages') ?></span><span>Pages</span></a><?php endif; ?>
                <?php if (Gate::allows('content.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/posts', true) ?>" href="<?= e(admin_url('/posts')) ?>"><span class="cms-nav-icon"><?= $navIcon('posts') ?></span><span>Posts</span></a><?php endif; ?>
                <?php if (Gate::allows('blog.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/blog-categories', true) ?>" href="<?= e(admin_url('/blog-categories')) ?>"><span class="cms-nav-icon"><?= $navIcon('categories') ?></span><span>Categories</span></a><?php endif; ?>
                <?php foreach ($customModels as $customModel): ?><a class="cms-nav-link<?= $isActive($adminBase . '/content/' . (string)$customModel['slug'], true) ?>" href="<?= e(admin_url('/content/' . (string)$customModel['slug'])) ?>"><span class="cms-nav-icon"><?= $navIcon((string)($customModel['icon'] ?? 'collection')) ?></span><span><?= e((string)$customModel['plural_name']) ?></span></a><?php endforeach; ?>
                <?php if (Gate::allows('media.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/media', true) ?>" href="<?= e(admin_url('/media')) ?>"><span class="cms-nav-icon"><?= $navIcon('media') ?></span><span>Media</span></a><?php endif; ?>
                <?php if (Gate::allows('contact.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/contact-submissions', true) ?>" href="<?= e(admin_url('/contact-submissions')) ?>"><span class="cms-nav-icon"><?= $navIcon('mail') ?></span><span>Contact submissions</span></a><?php endif; ?>
                <?php if (Gate::allows('blog.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/blog-settings', true) ?>" href="<?= e(admin_url('/blog-settings')) ?>"><span class="cms-nav-icon"><?= $navIcon('blog') ?></span><span>Blog settings</span></a><?php endif; ?>
            </div>

            <div class="cms-nav-group">
                <span class="cms-nav-label">Design</span>
                <?php if (Gate::allows('design.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/design/styles', true) ?>" href="<?= e(admin_url('/design/styles')) ?>"><span class="cms-nav-icon"><?= $navIcon('design') ?></span><span>Styles</span></a><?php endif; ?>
                <?php if (Gate::allows('pages.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/patterns', true) ?>" href="<?= e(admin_url('/patterns')) ?>"><span class="cms-nav-icon"><?= $navIcon('patterns') ?></span><span>Patterns</span></a><?php endif; ?>
                <?php if (Gate::allows('themes.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/themes', true) ?>" href="<?= e(admin_url('/themes')) ?>"><span class="cms-nav-icon"><?= $navIcon('themes') ?></span><span>Themes</span></a><?php endif; ?>
                <?php if (Gate::allows('menus.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/menus', true) ?>" href="<?= e(admin_url('/menus')) ?>"><span class="cms-nav-icon"><?= $navIcon('menus') ?></span><span>Menus</span></a><?php endif; ?>
                <?php if (Gate::allows('content_models.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/content-models', true) || $isActive($adminBase . '/components', true) ? ' is-active' : '' ?>" href="<?= e(admin_url('/content-models')) ?>"><span class="cms-nav-icon"><?= $navIcon('models') ?></span><span>Content models</span></a><?php endif; ?>
            </div>

            <div class="cms-nav-group">
                <span class="cms-nav-label">Insights</span>
                <?php if (Gate::allows('analytics.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/analytics', true) ?>" href="<?= e(admin_url('/analytics')) ?>"><span class="cms-nav-icon"><?= $navIcon('analytics') ?></span><span>Analytics</span></a><?php endif; ?>
                <?php if (Gate::allows('seo.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/seo', true) ?>" href="<?= e(admin_url('/seo')) ?>"><span class="cms-nav-icon"><?= $navIcon('seo') ?></span><span>SEO</span></a><?php endif; ?>
                <?php if (Gate::allows('redirects.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/redirects', true) ?>" href="<?= e(admin_url('/redirects')) ?>"><span class="cms-nav-icon"><?= $navIcon('redirects') ?></span><span>Redirects</span></a><?php endif; ?>
                <?php if (Gate::allows('sitehealth.view')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/site-health', true) ?>" href="<?= e(admin_url('/site-health')) ?>"><span class="cms-nav-icon"><?= $navIcon('health') ?></span><span>Site health</span></a><?php endif; ?>
            </div>

            <div class="cms-nav-group">
                <span class="cms-nav-label">System</span>
                <?php if (Gate::allows('site.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/site-mode', true) ?>" href="<?= e(admin_url('/site-mode')) ?>"><span class="cms-nav-icon"><?= $navIcon('site') ?></span><span>Site mode</span></a><?php endif; ?>
                <?php if (Gate::allows('mail.manage') || Gate::allows('contact.manage') || in_array((string)($user['role_name'] ?? ''), ['super_administrator','administrator'], true)): ?><a class="cms-nav-link<?= $isActive($adminBase . '/mail', true) ?>" href="<?= e(admin_url('/mail')) ?>"><span class="cms-nav-icon"><?= $navIcon('mail') ?></span><span>Email</span></a><?php endif; ?>
                <?php if (Gate::allows('security.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/security', true) ?>" href="<?= e(admin_url('/security')) ?>"><span class="cms-nav-icon"><?= $navIcon('security') ?></span><span>Security</span></a><?php endif; ?>
                <?php if (Gate::allows('users.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/users', true) ?>" href="<?= e(admin_url('/users')) ?>"><span class="cms-nav-icon"><?= $navIcon('users') ?></span><span>Users</span></a><?php endif; ?>
                <?php if (Gate::allows('system.manage')): ?><a class="cms-nav-link<?= $isActive($adminBase . '/system', true) ?>" href="<?= e(admin_url('/system')) ?>"><span class="cms-nav-icon"><?= $navIcon('system') ?></span><span>System</span></a><?php endif; ?>
            </div>
        </nav>

        <div class="cms-sidebar-foot">
            <span>Talvoro</span><strong>v<?= e(app_version()) ?></strong>
        </div>
    </aside>

    <div class="cms-workspace">
        <header class="cms-topbar">
            <div class="cms-topbar-left">
                <button class="cms-menu-button" type="button" data-admin-nav-toggle aria-controls="cms-sidebar" aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
                <a class="mobile-admin-brand" href="<?= e($adminBase) ?>"><span class="brand-mark small" aria-hidden="true"><span></span></span><strong>Talvoro</strong></a>
                <div class="cms-topbar-context"><small>Workspace</small><strong><?= e($pageTitle) ?></strong></div>
            </div>
            <div class="topbar-actions">
                <?php if (Gate::allows('site.manage')): ?>
                    <a class="site-mode-pill <?= e($siteMode) ?>" href="<?= e(admin_url('/site-mode')) ?>" title="Website availability settings">
                        <span class="site-mode-dot" aria-hidden="true"></span><?= $siteMode === 'live' ? 'Live' : 'In development' ?>
                    </a>
                <?php endif; ?>
                <a class="topbar-link" href="/" target="_blank" rel="noopener">View site ↗</a>
                <?php if (Gate::allows('users.security')): ?>
                    <a class="user-chip user-chip-link" href="<?= e(admin_url('/users/' . (int)$user['id'] . '/security')) ?>" title="My security">
                        <span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span>
                        <span class="user-chip-copy"><strong><?= e($user['display_name']) ?></strong><small><?= e($user['role_label'] ?? ucfirst(str_replace('_', ' ', $user['role_name']))) ?></small></span>
                    </a>
                <?php else: ?>
                    <span class="user-chip"><span class="user-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['display_name'], 0, 1))) ?></span><span class="user-chip-copy"><strong><?= e($user['display_name']) ?></strong><small><?= e($user['role_label'] ?? ucfirst(str_replace('_', ' ', $user['role_name']))) ?></small></span></span>
                <?php endif; ?>
                <form method="post" action="<?= e(AdminPath::logoutUrl()) ?>"><?= Csrf::field() ?><button class="topbar-link signout-button" type="submit">Sign out</button></form>
            </div>
        </header>

        <main class="main admin-main" id="cms-main-content" tabindex="-1"><?= $content ?></main>
    </div>
</div>
<?php elseif ($isPrivateAuth): ?>
<div class="private-auth-shell"><main class="main"><?= $content ?></main></div>
<?php else: ?>
<?php if ($publicPreview): ?><div class="public-preview-bar" role="status"><strong>Preview</strong><span>This is a private CMS preview. Visitors cannot see draft changes here.</span></div><?php endif; ?>
<div class="public-shell talvoro-public-shell" data-public-shell>
    <header class="public-nav talvoro-public-header<?= $isTalvoroProductSite ? ' product-site-header' : '' ?>">
        <a class="public-brand" href="/">
            <?php if ($publicLogo !== ''): ?><span class="public-logo"><img src="<?= e($publicLogo) ?>" alt=""></span><?php else: ?><span class="brand-mark small" aria-hidden="true"><span></span></span><?php endif; ?>
            <span class="public-brand-copy"><strong><?= e($siteName) ?></strong><?php if ($publicTagline !== ''): ?><small><?= e($publicTagline) ?></small><?php endif; ?></span>
        </a>
        <nav aria-label="Public navigation">
            <?php if ($primaryMenu): ?><?php $renderPublicMenu($primaryMenu); ?><?php else: ?>
                <a class="<?= $isActive('/') ?>" href="/">Home</a>
                <?php if (Settings::blogEnabled()): ?><a class="<?= $isActive('/blog', true) ?>" href="/blog">Blog</a><?php endif; ?>
                <?php foreach ($publicPages as $navPage): ?><a class="<?= $isActive((string)$navPage['path']) ?>" href="<?= e($navPage['path']) ?>"><?= e($navPage['navigation_label'] ?: $navPage['title']) ?></a><?php endforeach; ?>
            <?php endif; ?>
        </nav>
        <details class="public-mobile-menu">
            <summary aria-label="Open site navigation"><span aria-hidden="true">☰</span><span>Menu</span></summary>
            <nav aria-label="Mobile navigation">
                <?php if ($mobileMenu ?: $primaryMenu): ?><?php $renderPublicMenu($mobileMenu ?: $primaryMenu); ?><?php else: ?>
                    <a class="<?= $isActive('/') ?>" href="/">Home</a>
                    <?php if (Settings::blogEnabled()): ?><a class="<?= $isActive('/blog', true) ?>" href="/blog">Blog</a><?php endif; ?>
                    <?php foreach ($publicPages as $navPage): ?><a class="<?= $isActive((string)$navPage['path']) ?>" href="<?= e($navPage['path']) ?>"><?= e($navPage['navigation_label'] ?: $navPage['title']) ?></a><?php endforeach; ?>
                <?php endif; ?>
            </nav>
        </details>
    </header>

    <main class="main public-main"><?= $content ?></main>

    <footer class="public-footer rich-public-footer talvoro-public-footer">
        <?php if ($isTalvoroProductSite): ?>
        <div class="public-footer-main product-footer-main">
            <div class="public-footer-brand product-footer-brand">
                <a class="public-brand footer-brand" href="/">
                    <?php if ($publicLogo !== ''): ?><span class="public-logo"><img src="<?= e($publicLogo) ?>" alt=""></span><?php else: ?><span class="brand-mark small" aria-hidden="true"><span></span></span><?php endif; ?>
                    <span class="public-brand-copy"><strong><?= e($siteName) ?></strong><?php if ($publicTagline !== ''): ?><small><?= e($publicTagline) ?></small><?php endif; ?></span>
                </a>
                <p>Premium self-hosted publishing for people who want a polished website and operational ownership.</p>
                <div class="product-footer-actions"><a href="/self-hosting#install">Get Talvoro →</a><a href="https://github.com/DrRoglaa/talvoro" target="_blank" rel="noopener noreferrer">GitHub ↗</a></div>
            </div>
            <?php if ($footerMenu): ?>
                <?php foreach ($footerMenu as $group): $children = is_array($group['children'] ?? null) ? $group['children'] : []; ?>
                    <div class="public-footer-column product-footer-column">
                        <strong><?= e((string)($group['label'] ?? 'Explore')) ?></strong>
                        <?php if ($children): ?>
                            <?php foreach ($children as $item): ?>
                                <a href="<?= e((string)($item['url'] ?? '#')) ?>"<?= !empty($item['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string)($item['label'] ?? 'Link')) ?></a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <a href="<?= e((string)($group['url'] ?? '#')) ?>"<?= !empty($group['open_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e((string)($group['label'] ?? 'Explore')) ?></a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="public-footer-column product-footer-column"><strong>Product</strong><a href="/product">Product</a><a href="/themes">Themes</a><a href="/demo">Demo</a><a href="/self-hosting">Self-hosting</a></div>
                <div class="public-footer-column product-footer-column"><strong>Resources</strong><a href="/guides">Guides</a><a href="/docs">Documentation</a><a href="/changelog">Changelog</a><a href="/roadmap">Roadmap</a></div>
                <div class="public-footer-column product-footer-column"><strong>Project</strong><a href="/open-source">Open source</a><a href="/support">Support</a></div>
                <div class="public-footer-column product-footer-column"><strong>Legal</strong><a href="/privacy">Privacy</a><a href="/security">Security</a></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="public-footer-main">
            <div class="public-footer-brand">
                <a class="public-brand footer-brand" href="/">
                    <?php if ($publicLogo !== ''): ?><span class="public-logo"><img src="<?= e($publicLogo) ?>" alt=""></span><?php else: ?><span class="brand-mark small" aria-hidden="true"><span></span></span><?php endif; ?>
                    <span class="public-brand-copy"><strong><?= e($siteName) ?></strong><?php if ($publicTagline !== ''): ?><small><?= e($publicTagline) ?></small><?php endif; ?></span>
                </a>
                <p>Thoughtful, independent publishing with your content and your data under your control.</p>
            </div>
            <div class="public-footer-column">
                <strong>Menu</strong>
                <?php if ($primaryMenu): ?><?php $renderPublicMenu($primaryMenu); ?><?php else: ?>
                    <a href="/">Home</a>
                    <?php if (Settings::blogEnabled()): ?><a href="/blog">Blog</a><?php endif; ?>
                    <?php foreach ($publicPages as $navPage): ?><a href="<?= e($navPage['path']) ?>"><?= e($navPage['navigation_label'] ?: $navPage['title']) ?></a><?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="public-footer-column">
                <strong>Explore</strong>
                <?php if ($footerMenu): ?><?php $renderPublicMenu($footerMenu); ?><?php elseif ($footerPages): ?>
                    <?php foreach ($footerPages as $footerPage): ?><a href="<?= e($footerPage['path']) ?>"><?= e($footerPage['footer_label'] ?: $footerPage['title']) ?></a><?php endforeach; ?>
                <?php else: ?>
                    <span class="footer-empty">Assign a Footer menu in Talvoro.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="public-footer-bottom">
            <span>Independent software, built with <span aria-label="heart">♥</span> by Indie developer (David Rok Roglič) in Slovenia.</span>
            <span>Talvoro version: <?= e(app_version()) ?></span>
        </div>
    </footer>
</div>
<?php endif; ?>
<?php if (!$isAdminRequest || $publicPreview): ?><script src="/assets/js/contact-form.js?v=<?= e(app_version()) ?>" defer></script><?php endif; ?>
</body>
</html>
