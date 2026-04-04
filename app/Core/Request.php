<?php

declare(strict_types=1);

namespace BSPhotoGalerie\Core;

/**
 * Abstraktion über die aktuelle HTTP-Anfrage (ohne globale Seiteneffekte im Konstruktor).
 */
final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $server
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $server
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uriPath = is_string($uriPath) ? $uriPath : '/';
        $uriPath = str_replace('\\', '/', $uriPath);

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $script = str_replace('\\', '/', $script);
        $base = dirname($script);
        if ($base !== '/' && str_starts_with($uriPath, $base)) {
            $path = substr($uriPath, strlen($base)) ?: '';
        } else {
            $path = $uriPath;
        }

        $path = '/' . trim($path, '/');
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/') ?: '/';
        }

        $query = [];
        if (isset($_GET) && is_array($_GET)) {
            foreach ($_GET as $k => $v) {
                if (is_string($k) && (is_scalar($v) || $v === null)) {
                    $query[$k] = $v === null ? '' : (string) $v;
                }
            }
        }

        $body = [];
        if ($method === 'POST' && isset($_POST) && is_array($_POST)) {
            $body = $_POST;
        }

        $server = [];
        if (isset($_SERVER) && is_array($_SERVER)) {
            foreach ($_SERVER as $k => $v) {
                if (is_string($k) && (is_scalar($v) || $v === null)) {
                    $server[$k] = $v === null ? '' : (string) $v;
                }
            }
        }

        return new self($method, $path, $query, $body, $server);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method === 'GET';
    }

    /**
     * @return array<string, string>
     */
    public function queryParams(): array
    {
        return $this->query;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function body(): array
    {
        return $this->body;
    }

    public function post(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function server(string $key, ?string $default = null): ?string
    {
        return $this->server[$key] ?? $default;
    }
}
