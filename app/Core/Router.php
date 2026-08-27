<?php
declare(strict_types=1);

namespace CMS\Core;

final class Router
{
    /** @var array<int,array{method:string,path:string,pattern:string,handler:callable}> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->compile($path),
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): Response
    {
        $path = rawurldecode(parse_url($uri, PHP_URL_PATH) ?: '/');
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $parameters = $this->match($route['pattern'], $path);
            if ($parameters === null) {
                continue;
            }

            $result = ($route['handler'])(...$parameters);
            return $result instanceof Response ? $result : new Response((string)$result);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method) {
                continue;
            }

            if ($this->match($route['pattern'], $path) !== null) {
                return new Response(View::render('errors/405', ['title' => 'Method not allowed']), 405);
            }
        }

        return new Response(View::render('errors/404', ['title' => 'Page not found']), 404);
    }

    private function compile(string $path): string
    {
        if ($path === '/') {
            return '#^/$#';
        }

        $segments = explode('/', trim($path, '/'));
        $parts = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\*\}$/', $segment, $matches) === 1) {
                $parts[] = '(?P<' . $matches[1] . '>.+)';
            } elseif (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $parts[] = '(?P<' . $matches[1] . '>[^/]+)';
            } else {
                $parts[] = preg_quote($segment, '#');
            }
        }

        return '#^/' . implode('/', $parts) . '/?$#';
    }

    /** @return list<string>|null */
    private function match(string $pattern, string $path): ?array
    {
        if (preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[] = $value;
            }
        }

        return $parameters;
    }
}
