<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\Gate;
use CMS\Core\InternalLinks;
use CMS\Core\MediaLibrary;
use CMS\Core\PageBlocks;
use CMS\Core\PagePatterns;
use CMS\Core\PagePatternStarters;
use CMS\Core\Response;
use CMS\Core\SiteAssets;
use CMS\Core\View;

final class PageBuilderController
{
    public static function patterns(): Response
    {
        if ($r = self::requirePages('pages.view')) return $r;
        $patterns = PagePatterns::all();
        return new Response(View::render('admin/patterns/index', [
            'title' => 'Patterns', 'patterns' => $patterns, 'starterPatterns' => PagePatternStarters::catalog($patterns),
            'created' => isset($_GET['created']), 'saved' => isset($_GET['saved']), 'deleted' => isset($_GET['deleted']), 'starterInstalled' => isset($_GET['starter_installed']),
        ]));
    }

    public static function newPattern(): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        return self::patternForm(null, ['name' => '', 'mode' => 'regular', 'blocks_json' => '[]', 'blocks' => []], [], false);
    }

    public static function installStarterPattern(): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $key = trim((string)($_POST['starter_key'] ?? ''));
        try {
            $user = Auth::user();
            $id = PagePatternStarters::install($key, (int)$user['id']);
            Audit::log('pattern.starter_install', 'page_pattern', $id, ['starter' => $key]);
            return Response::redirect(AdminPath::baseUrl() . '/patterns?starter_installed=1', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not install starter pattern', 'message' => $e->getMessage()]), 422);
        }
    }

    public static function createPattern(): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $validated = PagePatterns::validate($_POST);
        if ($validated['errors']) return self::patternForm(null, $validated['data'], $validated['errors'], false, 422);
        $newAssets = [];
        try {
            $uploads = PageBlocks::applyUploads($validated['data']['blocks'], $_FILES, $_POST, 'pattern');
            $validated['data']['blocks'] = $uploads['blocks'];
            $newAssets = $uploads['new_assets'];
            $user = Auth::user();
            $id = PagePatterns::create($validated['data'], (int)$user['id']);
            Audit::log('pattern.create', 'page_pattern', $id, ['name' => $validated['data']['name'], 'mode' => $validated['data']['mode']]);
            return Response::redirect(AdminPath::baseUrl() . '/patterns/' . $id . '/edit?created=1', 303);
        } catch (\Throwable $e) {
            foreach ($newAssets as $path) SiteAssets::remove($path);
            return self::patternForm(null, $validated['data'], [$e->getMessage()], false, 422);
        }
    }

    public static function editPattern(string $id): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        $patternId = self::positiveId($id);
        $pattern = $patternId ? PagePatterns::find($patternId) : null;
        if (!$pattern) return new Response(View::render('errors/404', ['title' => 'Pattern not found']), 404);
        return self::patternForm($patternId, $pattern, [], true);
    }

    public static function updatePattern(string $id): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        $patternId = self::positiveId($id);
        $existing = $patternId ? PagePatterns::find($patternId) : null;
        if (!$existing) return new Response(View::render('errors/404', ['title' => 'Pattern not found']), 404);
        $validated = PagePatterns::validate($_POST);
        if ($validated['errors']) return self::patternForm($patternId, array_merge($existing, $validated['data']), $validated['errors'], true, 422);
        $newAssets = [];
        try {
            $uploads = PageBlocks::applyUploads($validated['data']['blocks'], $_FILES, $_POST, 'pattern');
            $validated['data']['blocks'] = $uploads['blocks'];
            $newAssets = $uploads['new_assets'];
            $oldAssets = PageBlocks::assetPaths($existing['blocks']);
            $user = Auth::user();
            PagePatterns::update($patternId, $validated['data'], (int)$user['id']);
            $newPaths = PageBlocks::assetPaths($validated['data']['blocks']);
            foreach (array_diff($oldAssets, $newPaths) as $path) SiteAssets::remove($path);
            Audit::log('pattern.update', 'page_pattern', $patternId, ['name' => $validated['data']['name'], 'mode' => $validated['data']['mode']]);
            return Response::redirect(AdminPath::baseUrl() . '/patterns/' . $patternId . '/edit?saved=1', 303);
        } catch (\Throwable $e) {
            foreach ($newAssets as $path) SiteAssets::remove($path);
            return self::patternForm($patternId, array_merge($existing, $validated['data']), [$e->getMessage()], true, 422);
        }
    }

    public static function deletePattern(string $id): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::csrfFailure();
        if (($_POST['confirm_delete'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Delete not confirmed', 'message' => 'Confirm pattern deletion first.']), 422);
        $patternId = self::positiveId($id);
        if (!$patternId) return new Response(View::render('errors/404', ['title' => 'Pattern not found']), 404);
        try {
            PagePatterns::delete($patternId);
            Audit::log('pattern.delete', 'page_pattern', $patternId);
            return Response::redirect(AdminPath::baseUrl() . '/patterns?deleted=1', 303);
        } catch (\Throwable $e) {
            return new Response(View::render('errors/message', ['title' => 'Could not delete pattern', 'message' => $e->getMessage()]), 422);
        }
    }

    public static function createPatternFromBlock(): Response
    {
        if ($r = self::requirePages('pages.edit')) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return self::json(['ok' => false, 'error' => 'csrf'], 419);
        $payload = [
            'name' => (string)($_POST['name'] ?? ''),
            'mode' => (string)($_POST['mode'] ?? 'regular'),
            'page_blocks_json' => (string)($_POST['page_blocks_json'] ?? '[]'),
        ];
        $validated = PagePatterns::validate($payload);
        if ($validated['errors']) return self::json(['ok' => false, 'errors' => $validated['errors']], 422);
        $newAssets = [];
        try {
            $uploads = PageBlocks::applyUploads($validated['data']['blocks'], $_FILES, $_POST, 'pattern');
            $validated['data']['blocks'] = $uploads['blocks'];
            $newAssets = $uploads['new_assets'];
            $user = Auth::user();
            $id = PagePatterns::create($validated['data'], (int)$user['id'], true, $newAssets);
            $pattern = PagePatterns::find($id);
            Audit::log('pattern.create', 'page_pattern', $id, ['source' => 'page_builder', 'mode' => $validated['data']['mode']]);
            return self::json(['ok' => true, 'pattern' => [
                'id' => $id, 'name' => (string)$pattern['name'], 'mode' => (string)$pattern['mode'],
                'blocks' => $pattern['blocks'], 'usage_count' => 0,
            ]]);
        } catch (\Throwable $e) {
            foreach ($newAssets as $path) SiteAssets::remove($path);
            return self::json(['ok' => false, 'errors' => [$e->getMessage()]], 422);
        }
    }

    public static function internalLinks(): Response
    {
        $user = Auth::user();
        if (!$user) return Response::redirect(AdminPath::loginUrl());
        if (!Gate::allows('content.view') && !Gate::allows('pages.view') && !Gate::allows('custom_content.view')) {
            return self::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $query = trim((string)($_GET['q'] ?? ''));
        $items = mb_strlen($query) >= 1 ? InternalLinks::search($query) : [];
        if (!Gate::allows('pages.view')) {
            $items = array_values(array_filter($items, static fn(array $item): bool => ($item['type'] ?? '') !== 'Page'));
        }
        if (!Gate::allows('content.view')) {
            $items = array_values(array_filter($items, static fn(array $item): bool => !in_array(($item['type'] ?? ''), ['Post','Category'], true)));
        }
        if (!Gate::allows('custom_content.view')) {
            $items = array_values(array_filter($items, static fn(array $item): bool => in_array(($item['type'] ?? ''), ['Page','Post','Category'], true)));
        }
        return self::json(['ok' => true, 'items' => $items]);
    }

    private static function patternForm(?int $id, array $pattern, array $errors, bool $isEdit, int $status = 200): Response
    {
        $patterns = PagePatterns::picker();
        if ($id) $patterns = array_values(array_filter($patterns, static fn(array $item): bool => (int)$item['id'] !== $id));
        return new Response(View::render('admin/patterns/form', [
            'title' => $isEdit ? 'Edit pattern' : 'New pattern',
            'pattern' => $pattern, 'errors' => $errors, 'isEdit' => $isEdit,
            'action' => $isEdit ? AdminPath::baseUrl() . '/patterns/' . $id . '/edit' : AdminPath::baseUrl() . '/patterns/new',
            'patterns' => $patterns,
            'mediaAssets' => Gate::allows('media.view') ? MediaLibrary::pickerAssets() : [],
            'maxUploadMb' => SiteAssets::maxUploadMb(),
            'created' => isset($_GET['created']), 'saved' => isset($_GET['saved']),
        ]), $status);
    }

    private static function requirePages(string $permission): ?Response
    {
        $user = Auth::user();
        if (!$user) return Response::redirect(AdminPath::loginUrl());
        if (!Gate::allows($permission)) return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        return null;
    }

    private static function positiveId(string $value): int { return ctype_digit($value) && (int)$value > 0 ? (int)$value : 0; }
    private static function csrfFailure(): Response { return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']); }
    private static function json(array $payload, int $status = 200): Response
    {
        return new Response(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{"ok":false}', $status, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function __construct() {}
}
