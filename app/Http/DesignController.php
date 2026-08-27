<?php
declare(strict_types=1);

namespace CMS\Http;

use CMS\Core\AdminPath;
use CMS\Core\Audit;
use CMS\Core\Auth;
use CMS\Core\Csrf;
use CMS\Core\DesignSystem;
use CMS\Core\Gate;
use CMS\Core\Response;
use CMS\Core\View;

final class DesignController
{
    public static function styles(): Response
    {
        if ($r = self::requireAuth()) return $r;
        $values = DesignSystem::values();
        return new Response(View::render('admin/design/styles', [
            'title' => 'Styles', 'definitions' => DesignSystem::definitions(), 'values' => $values,
            'warnings' => DesignSystem::warnings($values), 'saved' => isset($_GET['saved']), 'reset' => isset($_GET['reset']), 'errors' => [],
            'tokens' => DesignSystem::tokenExport(), 'theme' => DesignSystem::activeTheme(),
        ]));
    }

    public static function updateStyles(): Response
    {
        if ($r = self::requireAuth()) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $user = Auth::user();
        $validated = DesignSystem::validate($_POST);
        if ($validated['errors']) {
            return new Response(View::render('admin/design/styles', [
                'title' => 'Styles', 'definitions' => DesignSystem::definitions(), 'values' => $validated['values'],
                'warnings' => $validated['warnings'], 'saved' => false, 'reset' => false, 'errors' => $validated['errors'],
                'tokens' => DesignSystem::tokenExport(), 'theme' => DesignSystem::activeTheme(),
            ]), 422);
        }
        DesignSystem::save($validated['values'], (int)$user['id']);
        Audit::log('design.styles.update', 'design', null, ['tokens' => array_keys($validated['values'])]);
        return Response::redirect(AdminPath::baseUrl() . '/design/styles?saved=1', 303);
    }

    public static function resetStyles(): Response
    {
        if ($r = self::requireAuth()) return $r;
        if (!Csrf::valid($_POST['_csrf'] ?? null)) return new Response('Invalid CSRF token', 419, ['Content-Type' => 'text/plain; charset=UTF-8']);
        if (($_POST['confirm_reset'] ?? '') !== '1') return new Response(View::render('errors/message', ['title' => 'Reset not confirmed', 'message' => 'Confirm the design reset first.']), 422);
        $user = Auth::user();
        DesignSystem::reset((int)$user['id']);
        Audit::log('design.styles.reset', 'design');
        return Response::redirect(AdminPath::baseUrl() . '/design/styles?reset=1', 303);
    }

    private static function requireAuth(): ?Response
    {
        if (!Auth::check()) return Response::redirect(AdminPath::loginUrl());
        if (Auth::requiresPasswordChange()) return Response::redirect(AdminPath::passwordUrl());
        if (!Gate::allows('design.manage')) return new Response(View::render('errors/403', ['title' => 'Forbidden']), 403);
        return null;
    }

    private function __construct() {}
}
