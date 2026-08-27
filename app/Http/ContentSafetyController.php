<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\ContentHistory;
use CMS\Core\ContentLifecycle;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\PageBlocks;
use CMS\Core\Pages;
use CMS\Core\Posts;
use CMS\Core\Response;
use CMS\Core\SiteAssets;
use CMS\Core\View;

final class ContentSafetyController
{
    public static function pageRevisions(string $id): Response { return self::revisions('page', $id); }
    public static function postRevisions(string $id): Response { return self::revisions('post', $id); }
    public static function restorePageRevision(string $id, string $revision): Response { return self::restoreRevision('page', $id, $revision); }
    public static function restorePostRevision(string $id, string $revision): Response { return self::restoreRevision('post', $id, $revision); }
    public static function autosavePage(string $id): Response { return self::autosave('page', $id); }
    public static function autosavePost(string $id): Response { return self::autosave('post', $id); }

    public static function restorePage(string $id): Response { return self::restoreTrash('page', $id); }
    public static function restorePost(string $id): Response { return self::restoreTrash('post', $id); }
    public static function permanentlyDeletePage(string $id): Response { return self::permanentDelete('page', $id); }
    public static function permanentlyDeletePost(string $id): Response { return self::permanentDelete('post', $id); }

    private static function revisions(string $type, string $id): Response
    {
        if ($r = self::requirePermission($type)) return $r;
        $contentId = self::positiveId($id);
        $item = $contentId ? self::find($type, $contentId) : null;
        if (!$item) return new Response(View::render('errors/404', ['title' => ucfirst($type) . ' not found']), 404);
        if (ContentHistory::count($type, $contentId) === 0) ContentHistory::capture($type, $contentId, null, 'baseline');

        $selected = max(0, (int)($_GET['revision'] ?? 0));
        $revision = $selected > 0 ? ContentHistory::revision($type, $contentId, $selected) : null;
        $changes = $revision ? ContentHistory::compareToCurrent($type, $contentId, $revision['snapshot']) : [];
        return new Response(View::render('admin/revisions/index', [
            'title' => 'Revision history', 'contentType' => $type, 'contentId' => $contentId,
            'contentTitle' => (string)($item['title'] ?? ucfirst($type)), 'revisions' => ContentHistory::list($type, $contentId),
            'selectedRevision' => $revision, 'changes' => $changes,
            'editUrl' => self::editUrl($type, $contentId), 'restored' => isset($_GET['restored']),
        ]));
    }

    private static function restoreRevision(string $type, string $id, string $revision): Response
    {
        if ($r = self::requirePermission($type)) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        if (($_POST['confirm_restore'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Restore not confirmed', 'message' => 'Confirm the revision before restoring it.']), 422);
        $contentId = self::positiveId($id);
        $revisionId = self::positiveId($revision);
        if (!$contentId || !$revisionId || !self::find($type, $contentId)) {
            return new Response(View::render('errors/404', ['title' => 'Revision not found']), 404);
        }
        $revisionRow = ContentHistory::revision($type, $contentId, $revisionId);
        if (!$revisionRow) return new Response(View::render('errors/404', ['title' => 'Revision not found']), 404);
        $status = (string)($revisionRow['snapshot']['fields']['status'] ?? 'draft');
        if ($status !== 'draft' && !Gate::allows(self::publishPermission($type))) {
            return new Response(View::render('errors/403', ['title' => 'Publishing permission required']), 403);
        }
        try {
            $user = Auth::user();
            ContentHistory::restore($type, $contentId, $revisionId, (int)$user['id']);
            Audit::log($type . '.revision.restore', $type, $contentId, ['revision_id' => $revisionId]);
            return Response::redirect(self::historyUrl($type, $contentId) . '?restored=1', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not restore revision', 'message' => $e->getMessage()]), 422);
        }
    }

    private static function autosave(string $type, string $id): Response
    {
        if ($r = self::requirePermission($type)) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('{"ok":false,"error":"csrf"}', 419, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
        $contentId = self::positiveId($id);
        if (!$contentId || !self::find($type, $contentId)) return new Response('{"ok":false,"error":"not_found"}', 404, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
        try {
            $user = Auth::user();
            $saved = ContentHistory::saveAutosave($type, $contentId, (int)$user['id'], $_POST);
            return new Response(json_encode(['ok' => true, 'saved_at' => $saved['saved_at']], JSON_UNESCAPED_SLASHES) ?: '{"ok":true}', 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
        } catch (\Throwable $e) {
            return new Response(json_encode(['ok' => false, 'error' => 'autosave_failed'], JSON_UNESCAPED_SLASHES) ?: '{"ok":false}', 422, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
    }

    private static function restoreTrash(string $type, string $id): Response
    {
        if ($r = self::requirePermission($type)) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $contentId = self::positiveId($id);
        if (!$contentId) return new Response(View::render('errors/404', ['title' => ucfirst($type) . ' not found']), 404);
        $trashed = ContentLifecycle::trashedItem($type, $contentId);
        if (!$trashed) return new Response(View::render('errors/404', ['title' => ucfirst($type) . ' not found']), 404);
        $status = (string)($trashed['status'] ?? 'draft');
        if ($status !== 'draft' && !Gate::allows(self::publishPermission($type))) {
            return new Response(View::render('errors/403', ['title' => 'Publishing permission required']), 403);
        }
        try {
            $user = Auth::user();
            ContentLifecycle::restore($type, $contentId, (int)$user['id']);
            Audit::log($type . '.trash.restore', $type, $contentId);
            return Response::redirect(AdminPath::baseUrl() . '/' . ($type === 'page' ? 'pages' : 'posts') . '?restored=1', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not restore content', 'message' => $e->getMessage()]), 422);
        }
    }

    private static function permanentDelete(string $type, string $id): Response
    {
        if ($r = self::requirePermission($type)) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        if (($_POST['confirm_delete'] ?? '') !== '1') {
            return new Response(View::render('errors/message', ['title' => 'Delete not confirmed', 'message' => 'Confirm permanent deletion before removing this item from Trash.']), 422);
        }
        $contentId = self::positiveId($id);
        if (!$contentId) return new Response(View::render('errors/404', ['title' => ucfirst($type) . ' not found']), 404);
        try {
            $revisionAssets = ContentHistory::assetPathsForContent($type, $contentId);
            $row = ContentLifecycle::permanentlyDelete($type, $contentId);
            if ($type === 'post') SiteAssets::remove((string)($row['featured_image_path'] ?? ''));
            if ($type === 'page') {
                foreach (PageBlocks::assetPaths(PageBlocks::decode((string)($row['blocks_json'] ?? '[]'))) as $path) SiteAssets::remove($path);
            }
            foreach ($revisionAssets as $path) SiteAssets::remove($path);
            Audit::log($type . '.permanent_delete', $type, $contentId, ['title' => (string)($row['title'] ?? '')]);
            return Response::redirect(AdminPath::baseUrl() . '/' . ($type === 'page' ? 'pages' : 'posts') . '?view=trash&purged=1', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not permanently delete content', 'message' => $e->getMessage()]), 422);
        }
    }

    private static function find(string $type, int $id): ?array
    {
        return $type === 'page' ? Pages::find($id) : Posts::find($id);
    }

    private static function publishPermission(string $type): string
    {
        return $type === 'page' ? 'pages.publish' : 'content.publish';
    }

    private static function requirePermission(string $type): ?Response
    {
        $permission = $type === 'page' ? 'pages.edit' : 'content.edit';
        $user = Auth::user();
        if (!$user) return Response::redirect(AdminPath::loginUrl());
        if (!Gate::allows($permission)) return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        return null;
    }

    private static function editUrl(string $type, int $id): string
    {
        return AdminPath::baseUrl() . '/' . ($type === 'page' ? 'pages' : 'posts') . '/' . $id . '/edit';
    }

    private static function historyUrl(string $type, int $id): string
    {
        return self::editUrl($type, $id) . '/revisions';
    }

    private static function positiveId(string $value): int
    {
        return ctype_digit($value) && (int)$value > 0 ? (int)$value : 0;
    }

    private static function csrfFailure(): Response
    {
        return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    private function __construct() {}
}
