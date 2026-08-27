<?php
declare(strict_types=1);

namespace CMS\Core;

use RuntimeException;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8']
    ) {}

    public static function redirect(string $location, int $status = 302): self
    {
        if (!in_array($status, [301,302,303,307,308], true)) throw new RuntimeException('Invalid redirect status.');
        if ($location === '' || str_contains($location, "\r") || str_contains($location, "\n") || str_contains($location, "\0")) {
            throw new RuntimeException('Unsafe redirect target.');
        }
        return new self('', $status, ['Location' => $location]);
    }

    public function send(): void
    {
        http_response_code($this->status);
        $headers = array_merge(Security::secureHeaders(), $this->headers);
        $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        if (AdminPath::isProtectedPublicPath($path) || str_starts_with($path, '/install')) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate';
            $headers['Pragma'] = 'no-cache';
        }
        foreach ($headers as $name => $value) {
            if (!preg_match('/^[A-Za-z0-9-]+$/', (string)$name)) continue;
            $value = str_replace(["\r","\n"], '', (string)$value);
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
