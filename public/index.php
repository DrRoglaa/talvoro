<?php
declare(strict_types=1);

use CMS\Core\AdminPath;
use CMS\Core\Analytics;
use CMS\Core\Auth;
use CMS\Core\Env;
use CMS\Core\InstallState;
use CMS\Core\NotFoundMonitor;
use CMS\Core\ScannerGuard;
use CMS\Core\Redirects;
use CMS\Core\Response;
use CMS\Core\Router;
use CMS\Core\Security;
use CMS\Core\Settings;
use CMS\Core\UpdateManager;
use CMS\Core\View;

require __DIR__ . '/../bootstrap/app.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');

if (!Security::validRequestPath($path)) {
    (new Response("Bad Request\n", 400, ['Content-Type' => 'text/plain; charset=UTF-8']))->send();
    exit;
}

/*
 * Fresh web-hosting installation is intentionally isolated from the normal CMS
 * router so no application query can run before a database has been configured.
 */
if (!InstallState::isInstalled()) {
    if (!str_starts_with($path, '/install')) {
        Response::redirect('/install', 302)->send();
        exit;
    }

    $router = new Router();
    require __DIR__ . '/../routes/install.php';
    try {
        $router->dispatch($method, $uri)->send();
    } catch (Throwable $e) {
        error_log((string)$e);
        (new Response('Installer error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']))->send();
    }
    exit;
}

// The installer route is permanently closed after installation, even if its code remains present.
if (str_starts_with($path, '/install')) {
    (new Response(View::render('errors/404', ['title' => 'Page not found']), 404))->send();
    exit;
}

Auth::enforceLifetime();

$router = new Router();
require __DIR__ . '/../routes/web.php';

$response = null;
$suppressAnalytics = false;

$operationalExceptions = ['/health', '/robots.txt', '/sitemap.xml', '/theme.css'];
$isPublicContent = !AdminPath::isProtectedPublicPath($path)
    && !str_starts_with($path, '/assets')
    && !str_starts_with($path, '/uploads')
    && !in_array($path, $operationalExceptions, true);

$isAuthenticated = Auth::check();
$isDevelopment = Settings::siteMode() === 'development';
$previewHolding = $isAuthenticated
    && $method === 'GET'
    && $path === '/'
    && isset($_GET['preview_holding']);

// While files/migrations are being changed, public requests fail closed with 503.
if ($response === null && UpdateManager::isLocked() && $isPublicContent) {
    $response = new Response(
        View::render('maintenance', [
            'title' => 'Website maintenance',
            'headline' => 'A secure update is being applied.',
            'message' => 'The website will return as soon as the update completes.',
            'searchHandling' => 'maintenance',
            'returnAtIso' => null,
            'returnDisplay' => null,
            'countdownEnabled' => false,
        ]),
        503,
        [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Retry-After' => '300',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]
    );
    $suppressAnalytics = true;
}

/* Reject obvious automated scanner probes before normal routing and before 404 persistence. */
if (
    $response === null
    && $isPublicContent
    && in_array($method, ['GET', 'HEAD'], true)
    && ScannerGuard::isLikelyScannerPath($path)
) {
    $response = new Response(
        "Not found\n",
        404,
        [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'X-Robots-Tag' => 'noindex, nofollow, noarchive',
        ]
    );
    $suppressAnalytics = true;
}

if (
    $response === null
    && $isPublicContent
    && ($previewHolding || ($isDevelopment && !$isAuthenticated))
) {
    $handling = Settings::searchHandling();

    if (!$previewHolding && $handling === 'prelaunch' && $path !== '/' && in_array($method, ['GET', 'HEAD'], true)) {
        $response = Response::redirect('/', 302);
        $suppressAnalytics = true;
    } else {
        $isMaintenance = !$previewHolding && $handling === 'maintenance';
        $headers = ['Content-Type' => 'text/html; charset=UTF-8'];
        if ($previewHolding || $isMaintenance) $headers['X-Robots-Tag'] = 'noindex, nofollow';
        if ($isMaintenance) $headers['Retry-After'] = '3600';

        $response = new Response(
            View::render('maintenance', [
                'title' => Settings::developmentHeadline(),
                'headline' => Settings::developmentHeadline(),
                'message' => Settings::developmentMessage(),
                'searchHandling' => $handling,
                'returnAtIso' => Settings::plannedReturnIso(),
                'returnDisplay' => Settings::plannedReturnDisplay(),
                'countdownEnabled' => Settings::countdownEnabled(),
            ]),
            $isMaintenance ? 503 : 200,
            $headers
        );
        $suppressAnalytics = true;
    }
}

if ($response === null && in_array($method, ['GET', 'HEAD'], true)) {
    $redirect = Redirects::match($path);
    if ($redirect) {
        $response = Response::redirect((string)$redirect['destination'], (int)$redirect['status_code']);
    }
}

if ($response === null) {
    try {
        $response = $router->dispatch($method, $uri);
    } catch (Throwable $e) {
        if (Env::bool('APP_DEBUG', false)) {
            $response = new Response('<pre>' . e($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>', 500);
        } else {
            error_log((string)$e);
            $response = new Response('Internal Server Error', 500, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }
    }
}

if (
    in_array($method, ['GET', 'HEAD'], true)
    && $isPublicContent
    && $response->status === 404
    && !ScannerGuard::isLikelyScannerPath($path)
) {
    NotFoundMonitor::record($path, (string)($_SERVER['HTTP_REFERER'] ?? ''));
}

if ($method === 'GET' && !$suppressAnalytics) {
    if (
        !AdminPath::isProtectedPublicPath($path)
        && !in_array($path, ['/health','/robots.txt','/sitemap.xml','/theme.css'], true)
        && !str_starts_with($path, '/assets/')
        && !str_starts_with($path, '/uploads/')
    ) {
        Analytics::recordPageView($path, $response->status);
    }
}

$response->send();
