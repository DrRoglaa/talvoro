<?php
declare(strict_types=1);

use CMS\Core\AdminPath;
use CMS\Http\AdminSettingsController;
use CMS\Http\Controllers;
use CMS\Http\ContentSafetyController;
use CMS\Http\PageBuilderController;
use CMS\Http\ContentModelController;
use CMS\Http\CustomContentController;
use CMS\Http\MenusController;
use CMS\Http\MediaController;
use CMS\Http\DesignController;
use CMS\Core\ContentModels;

$router->get('/', [Controllers::class, 'home']);
$router->get('/blog', [Controllers::class, 'blog']);
$router->get('/blog/category/{slug}', [Controllers::class, 'blogCategory']);
$router->get('/blog/{slug}', [Controllers::class, 'blogPost']);
$router->get('/health', [Controllers::class, 'health']);
$router->get('/robots.txt', [Controllers::class, 'robots']);
$router->get('/sitemap.xml', [Controllers::class, 'sitemap']);
$router->get('/theme.css', [Controllers::class, 'themeCss']);

$admin = AdminPath::baseUrl();

$router->get($admin . '/login', [Controllers::class, 'loginForm']);
$router->post($admin . '/login', [Controllers::class, 'login']);
$router->get($admin . '/verify', [Controllers::class, 'mfaForm']);
$router->post($admin . '/verify', [Controllers::class, 'verifyMfa']);
$router->get($admin . '/account/password', [Controllers::class, 'passwordChangeForm']);
$router->post($admin . '/account/password', [Controllers::class, 'changeTemporaryPassword']);
$router->post($admin . '/logout', [Controllers::class, 'logout']);

$router->get($admin, [Controllers::class, 'dashboard']);

$router->get($admin . '/security', [AdminSettingsController::class, 'security']);
$router->post($admin . '/security', [AdminSettingsController::class, 'updateSecurity']);

$router->get($admin . '/design/styles', [DesignController::class, 'styles']);
$router->post($admin . '/design/styles', [DesignController::class, 'updateStyles']);
$router->post($admin . '/design/styles/reset', [DesignController::class, 'resetStyles']);

$router->get($admin . '/homepage', [AdminSettingsController::class, 'homepage']);
$router->post($admin . '/homepage', [AdminSettingsController::class, 'updateHomepage']);

$router->get($admin . '/system', [CMS\Http\SystemController::class, 'index']);
$router->post($admin . '/system/update/stage', [CMS\Http\SystemController::class, 'stage']);
$router->post($admin . '/system/update/apply', [CMS\Http\SystemController::class, 'apply']);
$router->post($admin . '/system/update/restore', [CMS\Http\SystemController::class, 'restore']);

$router->get($admin . '/site-mode', [Controllers::class, 'siteMode']);
$router->post($admin . '/site-mode', [Controllers::class, 'updateSiteMode']);

$router->get($admin . '/posts', [Controllers::class, 'posts']);
$router->get($admin . '/posts/new', [Controllers::class, 'newPost']);
$router->post($admin . '/posts/new', [Controllers::class, 'createPost']);
$router->get($admin . '/posts/{id}/edit', [Controllers::class, 'editPost']);
$router->post($admin . '/posts/{id}/edit', [Controllers::class, 'updatePost']);
$router->post($admin . '/posts/{id}/delete', [Controllers::class, 'deletePost']);
$router->get($admin . '/posts/{id}/edit/revisions', [ContentSafetyController::class, 'postRevisions']);
$router->post($admin . '/posts/{id}/edit/revisions/{revision}/restore', [ContentSafetyController::class, 'restorePostRevision']);
$router->post($admin . '/posts/{id}/autosave', [ContentSafetyController::class, 'autosavePost']);
$router->post($admin . '/posts/{id}/restore', [ContentSafetyController::class, 'restorePost']);
$router->post($admin . '/posts/{id}/permanent-delete', [ContentSafetyController::class, 'permanentlyDeletePost']);

$router->get($admin . '/blog-categories', [Controllers::class, 'blogCategories']);
$router->get($admin . '/blog-categories/new', [Controllers::class, 'newBlogCategory']);
$router->post($admin . '/blog-categories/new', [Controllers::class, 'createBlogCategory']);
$router->get($admin . '/blog-categories/{id}/edit', [Controllers::class, 'editBlogCategory']);
$router->post($admin . '/blog-categories/{id}/edit', [Controllers::class, 'updateBlogCategory']);
$router->post($admin . '/blog-categories/{id}/delete', [Controllers::class, 'deleteBlogCategory']);

$router->get($admin . '/pages', [Controllers::class, 'pages']);
$router->get($admin . '/pages/new', [Controllers::class, 'newPage']);
$router->post($admin . '/pages/new', [Controllers::class, 'createPage']);
$router->get($admin . '/pages/{id}/edit', [Controllers::class, 'editPage']);
$router->post($admin . '/pages/{id}/edit', [Controllers::class, 'updatePage']);
$router->post($admin . '/pages/{id}/delete', [Controllers::class, 'deletePage']);
$router->get($admin . '/pages/{id}/edit/revisions', [ContentSafetyController::class, 'pageRevisions']);
$router->post($admin . '/pages/{id}/edit/revisions/{revision}/restore', [ContentSafetyController::class, 'restorePageRevision']);
$router->post($admin . '/pages/{id}/autosave', [ContentSafetyController::class, 'autosavePage']);
$router->post($admin . '/pages/{id}/restore', [ContentSafetyController::class, 'restorePage']);
$router->post($admin . '/pages/{id}/permanent-delete', [ContentSafetyController::class, 'permanentlyDeletePage']);

$router->get($admin . '/patterns', [PageBuilderController::class, 'patterns']);
$router->get($admin . '/patterns/new', [PageBuilderController::class, 'newPattern']);
$router->post($admin . '/patterns/starters/install', [PageBuilderController::class, 'installStarterPattern']);
$router->post($admin . '/patterns/new', [PageBuilderController::class, 'createPattern']);
$router->get($admin . '/patterns/{id}/edit', [PageBuilderController::class, 'editPattern']);
$router->post($admin . '/patterns/{id}/edit', [PageBuilderController::class, 'updatePattern']);
$router->post($admin . '/patterns/{id}/delete', [PageBuilderController::class, 'deletePattern']);
$router->post($admin . '/page-builder/patterns/create', [PageBuilderController::class, 'createPatternFromBlock']);
$router->get($admin . '/internal-links', [PageBuilderController::class, 'internalLinks']);

// Navigation menus.
$router->get($admin . '/menus', [MenusController::class, 'index']);
$router->get($admin . '/menus/new', [MenusController::class, 'newMenu']);
$router->post($admin . '/menus/new', [MenusController::class, 'createMenu']);
$router->get($admin . '/menus/target-search', [MenusController::class, 'targetSearch']);
$router->get($admin . '/menus/{id}/edit', [MenusController::class, 'editMenu']);
$router->post($admin . '/menus/{id}/edit', [MenusController::class, 'updateMenu']);
$router->post($admin . '/menus/{id}/delete', [MenusController::class, 'deleteMenu']);
$router->post($admin . '/menus/{id}/items', [MenusController::class, 'addItem']);
$router->post($admin . '/menus/{id}/items/{item}/edit', [MenusController::class, 'updateItem']);
$router->post($admin . '/menus/{id}/items/{item}/delete', [MenusController::class, 'deleteItem']);

$router->get($admin . '/content-models', [ContentModelController::class, 'index']);
$router->get($admin . '/content-models/new', [ContentModelController::class, 'newModel']);
$router->post($admin . '/content-models/starters/install', [ContentModelController::class, 'installStarterModel']);
$router->post($admin . '/content-models/new', [ContentModelController::class, 'createModel']);
$router->get($admin . '/content-models/{id}/edit', [ContentModelController::class, 'editModel']);
$router->post($admin . '/content-models/{id}/edit', [ContentModelController::class, 'updateModel']);
$router->post($admin . '/content-models/{id}/delete', [ContentModelController::class, 'deleteModel']);
$router->get($admin . '/content-models/{id}/fields/new', [ContentModelController::class, 'newField']);
$router->post($admin . '/content-models/{id}/fields/new', [ContentModelController::class, 'createField']);
$router->get($admin . '/content-models/{id}/fields/{field}/edit', [ContentModelController::class, 'editField']);
$router->post($admin . '/content-models/{id}/fields/{field}/edit', [ContentModelController::class, 'updateField']);
$router->post($admin . '/content-models/{id}/fields/{field}/delete', [ContentModelController::class, 'deleteField']);
$router->post($admin . '/content-models/{id}/fields/{field}/restore', [ContentModelController::class, 'restoreField']);
$router->post($admin . '/content-models/{id}/fields/reorder', [ContentModelController::class, 'reorderFields']);

$router->get($admin . '/components/new', [ContentModelController::class, 'newComponent']);
$router->post($admin . '/components/new', [ContentModelController::class, 'createComponent']);
$router->get($admin . '/components/{id}/edit', [ContentModelController::class, 'editComponent']);
$router->post($admin . '/components/{id}/edit', [ContentModelController::class, 'updateComponent']);
$router->post($admin . '/components/{id}/delete', [ContentModelController::class, 'deleteComponent']);
$router->get($admin . '/components/{id}/fields/new', [ContentModelController::class, 'newComponentField']);
$router->post($admin . '/components/{id}/fields/new', [ContentModelController::class, 'createComponentField']);
$router->get($admin . '/components/{id}/fields/{field}/edit', [ContentModelController::class, 'editComponentField']);
$router->post($admin . '/components/{id}/fields/{field}/edit', [ContentModelController::class, 'updateComponentField']);
$router->post($admin . '/components/{id}/fields/{field}/delete', [ContentModelController::class, 'deleteComponentField']);
$router->post($admin . '/components/{id}/fields/{field}/restore', [ContentModelController::class, 'restoreComponentField']);
$router->post($admin . '/components/{id}/fields/reorder', [ContentModelController::class, 'reorderComponentFields']);

// Entries created from structured content models.
$router->get($admin . '/content/{modelSlug}', [CustomContentController::class, 'index']);
$router->get($admin . '/content/{modelSlug}/new', [CustomContentController::class, 'newEntry']);
$router->post($admin . '/content/{modelSlug}/new', [CustomContentController::class, 'createEntry']);
$router->get($admin . '/content/{modelSlug}/relation-search', [CustomContentController::class, 'relationSearch']);
$router->get($admin . '/content/{modelSlug}/{id}/preview', [CustomContentController::class, 'previewEntry']);
$router->get($admin . '/content/{modelSlug}/{id}/edit', [CustomContentController::class, 'editEntry']);
$router->post($admin . '/content/{modelSlug}/{id}/edit', [CustomContentController::class, 'updateEntry']);
$router->post($admin . '/content/{modelSlug}/{id}/delete', [CustomContentController::class, 'trashEntry']);
$router->post($admin . '/content/{modelSlug}/{id}/restore', [CustomContentController::class, 'restoreEntry']);
$router->post($admin . '/content/{modelSlug}/{id}/permanent-delete', [CustomContentController::class, 'permanentDeleteEntry']);
$router->get($admin . '/content/{modelSlug}/{id}/revisions', [CustomContentController::class, 'revisions']);
$router->post($admin . '/content/{modelSlug}/{id}/revisions/{revision}/restore', [CustomContentController::class, 'restoreRevision']);
$router->post($admin . '/content/{modelSlug}/{id}/autosave', [CustomContentController::class, 'autosave']);

$router->get($admin . '/media', [MediaController::class, 'index']);
$router->post($admin . '/media/upload', [MediaController::class, 'upload']);
$router->post($admin . '/media/folders', [MediaController::class, 'createFolder']);
$router->post($admin . '/media/folders/{id}/delete', [MediaController::class, 'deleteFolder']);
$router->post($admin . '/media/{id}/update', [MediaController::class, 'update']);
$router->post($admin . '/media/{id}/replace', [MediaController::class, 'replace']);
$router->post($admin . '/media/{id}/transform', [MediaController::class, 'transform']);
$router->post($admin . '/media/{id}/delete', [MediaController::class, 'delete']);

$router->get($admin . '/blog-settings', [Controllers::class, 'blogSettings']);
$router->post($admin . '/blog-settings', [Controllers::class, 'updateBlogSettings']);

$router->get($admin . '/analytics', [Controllers::class, 'analytics']);

$router->get($admin . '/seo', [Controllers::class, 'seo']);
$router->post($admin . '/seo', [Controllers::class, 'saveSeo']);

$router->get($admin . '/redirects', [Controllers::class, 'redirects']);
$router->post($admin . '/redirects', [Controllers::class, 'createRedirect']);
$router->post($admin . '/redirects/{id}/delete', [Controllers::class, 'deleteRedirect']);

$router->get($admin . '/site-health', [Controllers::class, 'siteHealth']);
$router->post($admin . '/site-health/404/dismiss', [Controllers::class, 'dismissNotFound']);
$router->post($admin . '/site-health/404/dismiss-selected', [Controllers::class, 'dismissSelectedNotFound']);
$router->post($admin . '/site-health/404/dismiss-scanner', [Controllers::class, 'dismissScannerNotFound']);
$router->post($admin . '/site-health/404/dismiss-all', [Controllers::class, 'dismissAllNotFound']);

$router->get($admin . '/themes', [Controllers::class, 'themes']);
$router->post($admin . '/themes/create', [Controllers::class, 'createTheme']);
$router->post($admin . '/themes/import', [Controllers::class, 'importTheme']);
$router->post($admin . '/themes/{id}/activate', [Controllers::class, 'activateTheme']);
$router->post($admin . '/themes/{id}/deactivate', [Controllers::class, 'deactivateTheme']);
$router->post($admin . '/themes/{id}/delete', [Controllers::class, 'deleteTheme']);

$router->get($admin . '/mail', [Controllers::class, 'mailSettings']);
$router->post($admin . '/mail', [Controllers::class, 'updateMailSettings']);
$router->post($admin . '/mail/test', [Controllers::class, 'testMail']);

$router->get($admin . '/users', [Controllers::class, 'users']);
$router->post($admin . '/users', [Controllers::class, 'createUser']);
$router->get($admin . '/users/{id}/security', [Controllers::class, 'userSecurity']);
$router->post($admin . '/users/{id}/security', [Controllers::class, 'updateUserSecurity']);
$router->post($admin . '/users/{id}/password', [Controllers::class, 'resetUserPassword']);
$router->post($admin . '/users/{id}/sessions/revoke', [Controllers::class, 'revokeUserSessions']);
$router->post($admin . '/users/{id}/audit/purge', [Controllers::class, 'purgeUserAudit']);
$router->post($admin . '/users/{id}/mfa/start', [Controllers::class, 'startUserMfa']);
$router->post($admin . '/users/{id}/mfa/enable', [Controllers::class, 'enableUserMfa']);
$router->post($admin . '/users/{id}/mfa/recovery/regenerate', [Controllers::class, 'regenerateUserMfaRecovery']);
$router->post($admin . '/users/{id}/mfa/reset', [Controllers::class, 'resetUserMfa']);
$router->post($admin . '/users/{id}/delete', [Controllers::class, 'deleteUser']);

// Public structured content routes are registered from active model URL bases.
// Model slugs are reserved against Pages, so /dogs/luna cannot shadow a CMS page.
foreach (ContentModels::publicModels() as $publicModel) {
    if ((int)$publicModel['has_archive'] === 1) {
        $modelSlug = (string)$publicModel['slug'];
        $router->get('/' . $modelSlug, static fn() => CustomContentController::publicArchive($modelSlug));
    }
    if ((int)$publicModel['has_urls'] === 1) {
        $modelSlug = (string)$publicModel['slug'];
        $router->get('/' . $modelSlug . '/{slug}', static fn(string $slug) => CustomContentController::publicEntry($modelSlug, $slug));
    }
}

// Public CMS pages must be last so explicit routes always win.
$router->get('/{pagePath*}', [Controllers::class, 'publicPage']);
