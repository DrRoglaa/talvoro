<?php
declare(strict_types=1);

use CMS\Core\AdminPath;
use CMS\Core\Analytics;
use CMS\Core\Categories;
use CMS\Core\Database;
use CMS\Core\DesignSystem;
use CMS\Core\Env;
use CMS\Core\Pages;
use CMS\Core\PageBlocks;
use CMS\Core\PagePatterns;
use CMS\Core\MailSettings;
use CMS\Core\MediaLibrary;
use CMS\Core\Menus;
use CMS\Core\NotFoundMonitor;
use CMS\Core\ScannerGuard;
use CMS\Core\ThemeManager;
use CMS\Core\Posts;
use CMS\Core\RichText;
use CMS\Core\ReleaseIntegrity;
use CMS\Core\SEO;
use CMS\Core\Settings;
use CMS\Core\UpdateManager;
use CMS\Core\Security;
use CMS\Core\PasswordPolicy;
use CMS\Core\InstallState;
use CMS\Core\HomePage;
use CMS\Core\SiteHealth;
use CMS\Core\SiteAssets;
use CMS\Core\Auth;
use CMS\Core\UserManager;
use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\ContactFormContext;
use CMS\Core\ContactSettings;
use CMS\Core\ContactSubmissionService;
use CMS\Core\ContactSubmissions;
use CMS\Core\ContentModels;
use CMS\Core\ContentModelStarters;
use CMS\Core\PagePatternStarters;
use CMS\Core\CustomContent;
use CMS\Core\BackupManager;

require __DIR__ . '/../bootstrap/app.php';

$fail = false;
$checks = [];

$checks['PHP >= 8.5'] = version_compare(PHP_VERSION, '8.5.0', '>=');
$checks['APP_KEY configured'] = strlen((string)Env::get('APP_KEY', '')) >= 32;
$versionFile = base_path('VERSION');
$versionFromFile = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '';
$checks['App version matches VERSION'] = preg_match('/^\d+\.\d+\.\d+$/', $versionFromFile) === 1 && app_version() === $versionFromFile;
$checks['Content revision engine available'] = method_exists(ContentHistory::class, 'capture') && method_exists(ContentHistory::class, 'restore');
$revisionSampleOld = ['fields' => ['title' => 'Home', 'blocks_json' => '[]', 'status' => 'published', 'show_in_navigation' => 0, 'show_in_footer' => 0], 'seo' => [], 'branding' => []];
$revisionSampleNew = $revisionSampleOld;
$revisionSampleNew['fields']['blocks_json'] = json_encode([['id' => 'checkhero01', 'type' => 'hero', 'eyebrow' => 'Care', 'heading' => 'A clearer homepage', 'intro' => 'Checked', 'primary_enabled' => false, 'primary_label' => '', 'primary_url' => '', 'secondary_enabled' => false, 'secondary_label' => '', 'secondary_url' => '', 'image_path' => '', 'image_alt' => '']], JSON_UNESCAPED_SLASHES);
$revisionFriendlyDiff = ContentHistory::compareSnapshots('page', $revisionSampleOld, $revisionSampleNew);
$checks['Revision compare is editor friendly'] = count($revisionFriendlyDiff) === 1
    && ($revisionFriendlyDiff[0]['label'] ?? '') === 'Hero banner'
    && !str_contains((string)($revisionFriendlyDiff[0]['summary'] ?? ''), 'blocks_json');
$checks['Content autosave engine available'] = method_exists(ContentHistory::class, 'saveAutosave') && is_file(base_path('public/assets/js/content-safety.js'));
$checks['Trash lifecycle available'] = method_exists(ContentLifecycle::class, 'moveToTrash') && method_exists(ContentLifecycle::class, 'purgeExpired');
$checks['Database-aware update recovery available'] = method_exists(BackupManager::class, 'restoreDatabase') && method_exists(UpdateManager::class, 'restoreRecovery');
$checks['MFA recovery regeneration available'] = method_exists(UserManager::class, 'regenerateRecoveryCodes');
$checks['MFA session-preserving rotation available'] = method_exists(Auth::class, 'rotateCurrentSessionAfterSecurityChange');
$checks['Sidebar scroll persistence script present'] = is_file(base_path('public/assets/js/admin-nav.js'));
$checks['Admin form position persistence script present'] = is_file(base_path('public/assets/js/admin-form-state.js'));
$checks['Recovery-code helper script present'] = is_file(base_path('public/assets/js/recovery-codes.js'));
$checks['Media Library interaction script present'] = is_file(base_path('public/assets/js/media-library.js'));
$checks['Page Builder 2.0 script present'] = is_file(base_path('public/assets/js/page-builder.js'))
    && str_contains((string)@file_get_contents(base_path('public/assets/js/page-builder.js')), 'Save as pattern')
    && str_contains((string)@file_get_contents(base_path('public/assets/js/page-builder.js')), 'data-preview-block-id');
$checks['Internal linking UI present'] = str_contains((string)@file_get_contents(base_path('public/assets/js/rich-text-editor.js')), 'Link to content');
$checks['Page Builder migration present'] = is_file(base_path('database/migrations/015_page_builder_2.sql'));
$checks['Automatic page URL generation available'] = is_file(base_path('public/assets/js/page-slug.js'))
    && Pages::slugifyPath('Naši mladički') === '/nasi-mladicki';
$checks['Structured content migrations present'] = is_file(base_path('database/migrations/016_content_models.sql'))
    && is_file(base_path('database/migrations/017_content_models_hardening.sql'));
$checks['Menus Media SEO 2.0 migration present'] = is_file(base_path('database/migrations/018_menus_media_seo2.sql'));
$checks['Design System migration present'] = is_file(base_path('database/migrations/019_design_system.sql'));
$checks['Contact Forms migration present'] = is_file(base_path('database/migrations/022_contact_forms.sql'));
$checks['Contact Forms regression check available'] = is_file(base_path('bin/check-contact-forms.php'));
$checks['Contact Forms services available'] = class_exists(ContactFormContext::class)
    && class_exists(ContactSettings::class)
    && class_exists(ContactSubmissionService::class)
    && class_exists(ContactSubmissions::class);
$checks['Contact Form Page Builder block available'] = in_array('contact', PageBlocks::types(), true)
    && is_file(base_path('resources/views/page/blocks/contact.php'))
    && str_contains((string)@file_get_contents(base_path('public/assets/js/page-builder.js')), "contact: 'Contact form'");
$checks['Contact Form public route reserved'] = str_contains((string)@file_get_contents(base_path('routes/web.php')), "post('/_talvoro/contact'")
    && str_contains((string)@file_get_contents(base_path('app/Core/Pages.php')), "'/_talvoro'");
$checks['Design System service available'] = class_exists(DesignSystem::class) && count(DesignSystem::definitions()) >= 16 && method_exists(DesignSystem::class, 'activeTheme');
$designBuilder=(string)@file_get_contents(base_path('public/assets/js/page-builder.js'));
$checks['Visual editing field focus available'] = str_contains($designBuilder,'data-preview-field') && str_contains($designBuilder,'focusInspectorField') && str_contains($designBuilder,'data-preview-focus');
$checks['Menus module present'] = class_exists(Menus::class) && is_file(base_path('resources/views/admin/menus/index.php')) && is_file(base_path('public/assets/js/menus.js'));
$checks['Media 2.0 edit pipeline present'] = method_exists(MediaLibrary::class,'transform') && method_exists(MediaLibrary::class,'regenerateVariants') && method_exists(MediaLibrary::class,'usageReferencesForAssets');
$checks['SEO 2.0 structured data present'] = method_exists(SEO::class,'structuredData') && str_contains((string)@file_get_contents(base_path('resources/views/admin/seo.php')),'Social image');
$checks['v0.14 integration check available'] = is_file(base_path('bin/check-v014.php'));
$checks['v0.14.2 starter check available'] = is_file(base_path('bin/check-v0142.php'));
$checks['v0.14.3 expanded starter check available'] = is_file(base_path('bin/check-v0143.php'));
$checks['v0.14.4 connected-content check available'] = is_file(base_path('bin/check-v0144.php'));
$checks['v0.14.5 compatibility check available'] = is_file(base_path('bin/check-v0145.php'));
$checks['v0.14.6 regression check available'] = is_file(base_path('bin/check-v0146.php'));
$checks['v0.14.7 deployment check available'] = is_file(base_path('bin/check-v0147.php'));
$checks['v0.15.0 design check available'] = is_file(base_path('bin/check-v0150.php'));
$checks['Starter content models available'] = class_exists(ContentModelStarters::class) && count(ContentModelStarters::catalog()) >= 15;
$checks['Starter page patterns available'] = class_exists(PagePatternStarters::class) && count(PagePatternStarters::catalog()) >= 32;
$checks['Starter library search script present'] = is_file(base_path('public/assets/js/starter-library.js'));
$foundationCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-foundation.css'));
$publicRedesignCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-public.css'));
$adminRedesignCss = (string)@file_get_contents(base_path('public/assets/css/talvoro-admin.css'));
$checks['Talvoro redesign foundation stylesheet present'] = str_contains($foundationCss, '--tv-action-primary: #d85f4a');
$checks['Talvoro public shell stylesheet present'] = str_contains($publicRedesignCss, '.talvoro-public-shell');
$checks['Talvoro admin shell stylesheet present'] = str_contains($adminRedesignCss, '.talvoro-admin-shell');
$checks['Talvoro reduced-motion foundation present'] = str_contains($foundationCss, '@media (prefers-reduced-motion: reduce)');
$checks['Talvoro 01d typography check available'] = is_file(base_path('bin/check-redesign-01d.php'));
$checks['Talvoro 01d typography migration available'] = is_file(base_path('database/migrations/024_trenlume_typography_defaults.sql'));

$starterCss=(string)@file_get_contents(base_path('public/assets/css/app.css'));
$checks['Structured model toggle cards styled'] = str_contains($starterCss,'.toggle-card') && str_contains($starterCss,'.toggle-grid') && str_contains($starterCss,'grid-template-columns: 20px minmax(0, 1fr)');
$checks['Structured content editor scripts present'] = is_file(base_path('public/assets/js/structured-content.js'))
    && is_file(base_path('public/assets/js/schema-fields.js'))
    && is_file(base_path('public/assets/js/schema-sortable.js'));
$checks['Structured integration check available'] = is_file(base_path('bin/check-structured-content.php'));
$requiredStructuredTypes=['text','textarea','rich_text','number','boolean','date','datetime','select','multiselect','email','url','media','gallery','relation','repeater','component'];
$checks['Structured content field types registered'] = count(array_intersect($requiredStructuredTypes,array_keys(ContentModels::fieldTypes())))===count($requiredStructuredTypes);
$checks['Structured relation cardinalities supported'] = ContentModels::relationAllowsMultiple(['relation_type'=>'one_to_many'])
    && ContentModels::relationAllowsMultiple(['relation_type'=>'many_to_many'])
    && !ContentModels::relationAllowsMultiple(['relation_type'=>'one_to_one'])
    && !ContentModels::relationAllowsMultiple(['relation_type'=>'many_to_one'])
    && ContentModels::relationUsesExclusiveTargets(['relation_type'=>'one_to_one'])
    && ContentModels::relationUsesExclusiveTargets(['relation_type'=>'one_to_many']);
$checks['Generated structured keys normalize safely'] = ContentModels::fieldKey('Date of birth')==='date_of_birth'
    && ContentModels::fieldKey('../Admin Login')==='admin_login';
$checks['Structured relation picker is searchable'] = str_contains((string)@file_get_contents(base_path('public/assets/js/structured-content.js')),'data-relation-picker')
    && str_contains((string)@file_get_contents(base_path('app/Http/CustomContentController.php')),'relationSearch');
$checks['Per-model permission gate available'] = method_exists(CMS\Core\Gate::class,'allowsModel');
$checks['Structured media values preserved without Media browse access'] = str_contains((string)@file_get_contents(base_path('resources/views/admin/custom-content/form.php')),'Media Library access is required to change');
$checks['Structured content pagination available'] = method_exists(CustomContent::class, 'adminPage') && method_exists(CustomContent::class, 'publicPage') && method_exists(CustomContent::class, 'publicSlugs');
$checks['Structured content public templates present'] = is_file(base_path('resources/views/content/archive.php')) && is_file(base_path('resources/views/content/show.php'));
$checks['Structured content revision type registered'] = str_contains((string)@file_get_contents(base_path('app/Core/ContentHistory.php')), "'entry'");
$expandedBlocks = ['gallery','testimonials','faq','stats','custom'];
$checks['Expanded Page Builder block library available'] = count(array_intersect($expandedBlocks, PageBlocks::types())) === count($expandedBlocks)
    && is_file(base_path('resources/views/page/blocks/gallery.php'))
    && is_file(base_path('resources/views/page/blocks/custom.php'));
$checks['Visual preview CSP permits local blob images'] = str_contains((string)(Security::secureHeaders()['Content-Security-Policy'] ?? ''), "img-src 'self' data: blob:");
$adminLayoutSource = (string)@file_get_contents(base_path('resources/views/layouts/app.php'));
$checks['Admin form position persistence wired globally'] = str_contains($adminLayoutSource, 'admin-form-state.js');
$checks['Media Library interaction wired'] = str_contains($adminLayoutSource, 'media-library.js');
$userSecuritySource = (string)@file_get_contents(base_path('resources/views/admin/user-security.php'));
$checks['Current-password credential hints present'] = substr_count($userSecuritySource, 'autocomplete="username"') >= 2
    && substr_count($userSecuritySource, 'autocomplete="current-password"') >= 2;
$checks['2FA return section stable'] = str_contains($userSecuritySource, 'id="mfa-security"')
    && str_contains($userSecuritySource, 'data-return-section="mfa-security"');
$mfaSummaryPos = strpos($userSecuritySource, 'class="mfa-summary"');
$recoveryPanelPos = strpos($userSecuritySource, 'class="mfa-action-panel recovery-card mfa-recovery-panel"');
$replaceRecoveryPos = strpos($userSecuritySource, '<h3>Replace recovery codes</h3>');
$checks['Recovery codes nested in Authenticator security'] = $mfaSummaryPos !== false
    && $recoveryPanelPos !== false
    && $replaceRecoveryPos !== false
    && $mfaSummaryPos < $recoveryPanelPos
    && $recoveryPanelPos < $replaceRecoveryPos;

$themeLimits = ThemeManager::importLimits();
$checks['Theme package limit 20 MB'] = (int)($themeLimits['package_mb'] ?? 0) === 20
    && (int)($themeLimits['files'] ?? 0) === 100
    && (int)($themeLimits['expanded_mb'] ?? 0) === 50;
$uploadRoot = base_path('public/uploads');
$checks['Theme asset storage writable'] = is_dir($uploadRoot)
    ? is_writable($uploadRoot)
    : is_writable(base_path('public'));
$siteUploadRoot = base_path('public/uploads/site');
$checks['Homepage asset storage writable'] = is_dir($siteUploadRoot)
    ? is_writable($siteUploadRoot)
    : is_writable($uploadRoot);
$checks['Homepage asset limit 12 MB'] = SiteAssets::maxUploadMb() === 12;
$iniBytes = static function (string $value): int {
    $value = trim($value);
    if ($value === '' || $value === '-1') return PHP_INT_MAX;
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return match ($unit) {
        'g' => (int)($number * 1024 * 1024 * 1024),
        'm' => (int)($number * 1024 * 1024),
        'k' => (int)($number * 1024),
        default => (int)$number,
    };
};
$themePackageBytes = (int)$themeLimits['package_mb'] * 1024 * 1024;
$checks['PHP theme upload capacity'] = $iniBytes((string)ini_get('upload_max_filesize')) >= $themePackageBytes
    && $iniBytes((string)ini_get('post_max_size')) >= ($themePackageBytes + 1024 * 1024);

$checks['Installer locked'] = InstallState::isInstalled();
$checks['Request-path validation'] = Security::validRequestPath('/admin/system') && !Security::validRequestPath("/bad\0path");
$checks['Admin path syntax valid'] = AdminPath::validate(AdminPath::current(), false) === [];
$checks['Legacy admin routes protected'] = AdminPath::isProtectedPublicPath('/admin')
    && AdminPath::isProtectedPublicPath('/admin/login')
    && AdminPath::isProtectedPublicPath('/login');
$checks['Password policy'] = PasswordPolicy::validate('Short1!') !== [] && PasswordPolicy::validate('A-very-long-random-password-2026!') === [];

try {
    $db = Database::connection();
    $db->query('SELECT 1');
    $checks['Database connection'] = true;

    $required = [
        'schema_migrations',
        'roles',
        'permissions',
        'role_permissions',
        'users',
        'login_attempts',
        'audit_log',
        'analytics_events',
        'posts',
        'pages',
        'cms_settings',
        'seo_pages',
        'redirects',
        'user_sessions',
        'mail_delivery_log',
        'themes',
        'not_found_events',
        'system_updates',
        'blog_categories',
        'blog_post_categories',
        'media_assets',
        'content_revisions',
        'content_autosaves',
        'page_patterns',
        'content_models',
        'content_components',
        'content_fields',
        'component_fields',
        'content_entries',
        'content_media_usage',
        'content_revision_media_usage',
        'content_relations',
        'content_search_values',
        'content_unique_values',
        'content_model_role_permissions',
        'menus',
        'menu_items',
        'media_folders',
        'media_variants',
        'contact_submissions',
    ];

    foreach ($required as $table) {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM information_schema.tables
             WHERE table_schema=DATABASE()
               AND table_name=?'
        );
        $stmt->execute([$table]);
        $checks['Table ' . $table] = (int)$stmt->fetchColumn() === 1;
    }

    if ($checks['Table posts']) {
        Posts::publishDue();
        $checks['Posts query'] = is_array(Posts::adminList('', '', 1, 5));
    }

    if (($checks['Table blog_categories'] ?? false) && ($checks['Table blog_post_categories'] ?? false)) {
        $defaultCategory = Categories::defaultCategory();
        $checks['Default blog category exists'] = is_array($defaultCategory)
            && (int)($defaultCategory['is_default'] ?? 0) === 1
            && (string)($defaultCategory['status'] ?? '') === 'active';
        $checks['Blog categories query'] = is_array(Categories::adminList());
        $checks['Posts have categories'] = (int)$db->query(
            'SELECT COUNT(*) FROM posts p WHERE NOT EXISTS (SELECT 1 FROM blog_post_categories pc WHERE pc.post_id=p.id)'
        )->fetchColumn() === 0;
        $checks['Posts have one primary category'] = (int)$db->query(
            'SELECT COUNT(*) FROM posts p WHERE (SELECT COUNT(*) FROM blog_post_categories pc WHERE pc.post_id=p.id AND pc.is_primary=1) <> 1'
        )->fetchColumn() === 0;
    }

    if ($checks['Table pages']) {
        $columnExists = static function (string $table, string $column) use ($db): bool {
            $stmt = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?'
            );
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() === 1;
        };
        $checks['Pages Home/footer schema'] = $columnExists('pages', 'page_template')
            && $columnExists('pages', 'show_in_footer')
            && $columnExists('pages', 'footer_label')
            && $columnExists('pages', 'footer_order')
            && $columnExists('pages', 'blocks_json');
        $checks['Post featured image schema'] = $columnExists('posts', 'featured_image_path');
        $checks['Content Trash schema'] = $columnExists('pages', 'deleted_at')
            && $columnExists('pages', 'deleted_by')
            && $columnExists('posts', 'deleted_at')
            && $columnExists('posts', 'deleted_by');
        $checks['Pages query'] = is_array(Pages::adminList('', ''));
        $checks['Pages navigation query'] = is_array(Pages::navigation());
        $checks['Footer navigation query'] = is_array(Pages::footerNavigation());
        $homePageId = Pages::ensureHomePage();
        $homePage = $homePageId > 0 ? Pages::find($homePageId) : null;
        $checks['Protected Home page exists'] = is_array($homePage)
            && (string)($homePage['path'] ?? '') === '/'
            && (string)($homePage['page_template'] ?? '') === 'home'
            && (string)($homePage['status'] ?? '') === 'published';
        $checks['Home page block payload valid'] = is_array($homePage['blocks'] ?? null);
        $checks['Content revision query'] = $homePageId < 1 || ContentHistory::count('page', $homePageId) >= 0;
        $checks['Trash retention policy'] = ContentLifecycle::retentionDays() >= 1 && ContentLifecycle::retentionDays() <= 3650;
        $checks['Empty Home blocks are respected'] = PageBlocks::decode('[]') === [];
        $sampleBlocks = PageBlocks::validateSubmitted(json_encode([[
            'id' => 'checkvalue01', 'type' => 'values', 'items' => [[
                'icon' => 'award', 'title' => 'Standards', 'body' => 'Checked',
            ]],
        ]], JSON_UNESCAPED_SLASHES) ?: '[]');
        $checks['Page block validation'] = $sampleBlocks['errors'] === [] && count($sampleBlocks['blocks']) === 1;
        $checks['Page block icon library'] = isset(PageBlocks::icons()['heart'], PageBlocks::icons()['home'], PageBlocks::icons()['award'], PageBlocks::icons()['clock']);
        $checks['Page Builder pattern block supported'] = in_array('pattern', PageBlocks::types(), true);
    }

    if ($checks['Table page_patterns'] ?? false) {
        $checks['Pattern library query'] = is_array(PagePatterns::all());
        $checks['Pattern library modes supported'] = method_exists(PagePatterns::class, 'create')
            && method_exists(PagePatterns::class, 'update')
            && method_exists(PagePatterns::class, 'usageCount');
    }

    if ($checks['Table cms_settings']) {
        $checks['Site mode valid'] = in_array(Settings::siteMode(), ['live','development'], true);
        $checks['Search handling valid'] = in_array(Settings::searchHandling(), ['prelaunch','maintenance'], true);
        $checks['Frontend theme active'] = Settings::frontendTheme() !== '';
        $checks['Blog setting valid'] = in_array(Settings::get('blog.enabled', '1'), ['0','1'], true);
        $home = HomePage::current();
        $checks['Homepage settings readable'] = isset($home['homepage.heading'], $home['homepage.intro'], $home['homepage.latest_posts_count']);
        $checks['Public site name readable'] = HomePage::publicSiteName() !== '';
        $checks['Admin path setting exists'] = trim((string)Settings::get('security.admin_path', '')) !== '';
        $checks['Retired admin path history readable'] = is_array(AdminPath::history())
            && Settings::get('security.admin_path_history', null) !== null;
        $checks['Homepage branding settings exist'] = Settings::get('homepage.heading', null) !== null
            && Settings::get('branding.tagline', null) !== null;
    }


    if ($checks['Table media_assets'] ?? false) {
        $checks['Media Library query'] = is_array(MediaLibrary::all('', 5));
        $checks['Media Library total count readable'] = MediaLibrary::countAll() >= 0;
        $checks['Media permissions exist'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name IN ('media.view','media.manage')") ->fetchColumn() === 2;
        $mediaColumns=(int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='media_assets' AND column_name IN ('folder_id','title','caption','focal_x','focal_y','replaced_at')")->fetchColumn();
        $checks['Media 2.0 schema'] = $mediaColumns===6 && ($checks['Table media_folders']??false) && ($checks['Table media_variants']??false);
        $checks['GD image editing available'] = extension_loaded('gd') && function_exists('imagewebp');
        $checks['AVIF variant support available'] = function_exists('imageavif');
    }

    if (($checks['Table menus']??false) && ($checks['Table menu_items']??false)) {
        $checks['Menus query'] = is_array(Menus::all());
        $checks['Menus permission exists'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name='menus.manage'")->fetchColumn()===1;
        $checks['Administrator has menus permission'] = (int)$db->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='administrator' AND p.name='menus.manage'")->fetchColumn()===1;
    }

    if ($checks['Table seo_pages']??false) {
        $seo2Columns=(int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='seo_pages' AND column_name IN ('social_media_id','schema_type')")->fetchColumn();
        $checks['SEO 2.0 schema'] = $seo2Columns===2;
    }

    if (($checks['Table content_models'] ?? false) && ($checks['Table content_entries'] ?? false)) {
        $checks['Content models query'] = is_array(ContentModels::all());
        $checks['Reusable components query'] = is_array(ContentModels::components());
        $checks['Structured content permissions exist'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name IN ('content_models.manage','custom_content.view','custom_content.create','custom_content.edit','custom_content.publish','custom_content.delete')")->fetchColumn() === 6;
        $checks['Structured content schema relations present'] = ($checks['Table content_fields'] ?? false)
            && ($checks['Table component_fields'] ?? false)
            && ($checks['Table content_relations'] ?? false)
            && ($checks['Table content_media_usage'] ?? false)
            && ($checks['Table content_revision_media_usage'] ?? false);
        $entryTrashColumns = $db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='content_entries' AND column_name IN ('deleted_at','deleted_by')")->fetchColumn();
        $checks['Structured content Trash schema'] = (int)$entryTrashColumns === 2;
        $modelColumns = (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='content_models' AND column_name IN ('model_key','icon','enable_revisions','enable_autosave','enable_trash','enable_seo','enable_featured_image','enable_scheduling')")->fetchColumn();
        $checks['Structured model hardening schema'] = $modelColumns === 8;
        $entryColumns = (int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='content_entries' AND column_name IN ('featured_media_id','canonical_url','robots','social_title','social_description','social_media_id')")->fetchColumn();
        $checks['Structured SEO/media schema'] = $entryColumns === 6;
        $archivedColumns=(int)$db->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND ((table_name='content_fields' AND column_name='archived_at') OR (table_name='component_fields' AND column_name='archived_at'))")->fetchColumn();
        $checks['Structured archived-field schema'] = $archivedColumns===2;
        $checks['Structured model permission rows seeded'] = (int)$db->query("SELECT COUNT(*) FROM content_models m JOIN roles r ON r.name IN ('administrator','editor') LEFT JOIN content_model_role_permissions mp ON mp.model_id=m.id AND mp.role_id=r.id WHERE mp.model_id IS NULL")->fetchColumn() === 0;
        $checks['Structured media usage guard available'] = method_exists(MediaLibrary::class, 'structuredUsage');
    }

        $checks['Design manage permission exists'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name='design.manage'")->fetchColumn() === 1;
$checks['Security manage permission exists'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name='security.manage'")->fetchColumn() === 1;
    $checks['Super Administrator has security permission'] = (int)$db->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='super_administrator' AND p.name='security.manage'")->fetchColumn() === 1;

    if ($checks['Table themes']) {
        $activeThemes = (int)$db->query('SELECT COUNT(*) FROM themes WHERE is_active=1')->fetchColumn();
        $checks['Exactly one active theme'] = $activeThemes === 1;
        $checks['Trenlume Light theme removed'] = (int)$db->query("SELECT COUNT(*) FROM themes WHERE slug='trenlume-light'")->fetchColumn() === 0;
    }

    $checks['OpenSSL available'] = function_exists('openssl_encrypt') && function_exists('openssl_decrypt');
    $checks['DOM extension available'] = class_exists(DOMDocument::class);
    $checks['ZIP extension available'] = class_exists(ZipArchive::class);
    $checks['Stream sockets available'] = function_exists('stream_socket_client');
$checks['Update recovery state readable'] = is_array(UpdateManager::lockData());
$releaseManifest = base_path('release.json');
$releaseIntegrity = ReleaseIntegrity::report();
$checks['Release manifest integrity'] = is_file($releaseManifest)
    ? (bool)($releaseIntegrity['ok'] ?? false)
    : is_dir(base_path('scripts/release')); // Source checkouts generate release.json during packaging.
    $checks['Mail settings readable'] = is_array(MailSettings::config(false));
    $checks['Contact settings readable'] = is_array(ContactSettings::config());
    $checks['Contact permissions exist'] = (int)$db->query("SELECT COUNT(*) FROM permissions WHERE name IN ('contact.view','contact.manage')")->fetchColumn() === 2;
    $checks['Super Administrator has contact permissions'] = (int)$db->query("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.name='super_administrator' AND p.name IN ('contact.view','contact.manage')")->fetchColumn() === 2;


    $checks['Scanner probe classification'] = ScannerGuard::isLikelyScannerPath('/wp-login.php')
        && ScannerGuard::isLikelyScannerPath('/.env')
        && !ScannerGuard::isLikelyScannerPath('/missing-page');

    $unsafeRichText = RichText::sanitize('<p class="rt-align-center evil" onclick="alert(1)"><strong>Safe</strong><script>alert(2)</script><a href="javascript:alert(3)">bad</a></p>');
    $checks['Rich-text sanitizer'] = str_contains($unsafeRichText, '<strong>Safe</strong>')
        && !str_contains(strtolower($unsafeRichText), '<script')
        && !str_contains(strtolower($unsafeRichText), 'onclick=')
        && !str_contains(strtolower($unsafeRichText), 'javascript:');
    $checks['Rich-text safe alignment'] = !class_exists(DOMDocument::class)
        || str_contains($unsafeRichText, 'class="rt-align-center"');

    if ($checks['Table not_found_events']) {
        $checks['404 monitor query'] = is_array(NotFoundMonitor::page(1, 20));
    }

    $checks['Active Super Administrator exists'] =
        (int)$db->query(
            "SELECT COUNT(*)
             FROM users u
             JOIN roles r ON r.id=u.role_id
             WHERE r.name='super_administrator'
               AND u.status='active'"
        )->fetchColumn() >= 1;

    if ($checks['Table seo_pages']) {
        $inventory = SEO::inventory();
        $checks['SEO inventory'] = is_array($inventory);
        $inventoryPostCount = count(array_filter(
            $inventory,
            static fn(array $page): bool =>
                ($page['kind'] ?? '') === 'Post'
                && str_starts_with((string)($page['path'] ?? ''), '/blog/')
        ));
        $publishedPostCount = Settings::blogEnabled()
            ? (int)$db->query(
                "SELECT COUNT(*) FROM posts WHERE deleted_at IS NULL AND status='published' AND published_at IS NOT NULL AND published_at<=UTC_TIMESTAMP()"
            )->fetchColumn()
            : 0;
        $checks['SEO inventory includes published blog posts'] =
            $inventoryPostCount === min(2000, $publishedPostCount);
    }

    if ($checks['Table analytics_events']) {
        $analytics = Analytics::overview(30);
        $checks['Analytics overview schema'] =
            isset($analytics['metrics'])
            && isset($analytics['channels'])
            && is_array($analytics['channels'])
            && isset($analytics['devices'])
            && is_array($analytics['devices'])
            && isset($analytics['campaigns'])
            && is_array($analytics['campaigns']);
    }

    if ($checks['Table redirects']) {
        $checks['Site health report'] = isset(SiteHealth::report()['score']);
    }
} catch (Throwable $e) {
    $checks['Database connection'] = false;
    echo "Database error: {$e->getMessage()}\n";
}

foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $label . "\n";
    $fail = $fail || !$ok;
}

exit($fail ? 1 : 0);
