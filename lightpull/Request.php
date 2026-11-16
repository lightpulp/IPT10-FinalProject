<?php

declare(strict_types=1);

namespace Lightpull;

/**
 * Read-only view of the incoming request. capture() is the only place in the
 * codebase that reads $_GET, $_POST, $_FILES, or $_SERVER.
 */
final class Request
{
    /** Values attached by middleware for later layers, e.g. the signed-in user. */
    private array $attributes = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $basePath,
        private readonly array $query,
        private readonly array $post,
        private readonly array $files,
        private readonly array $server,
    ) {
    }

    public static function capture(): self
    {
        // Supports installs in a sub-folder (e.g. http://localhost/project/app/public/).
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            '/' . trim($path, '/'),
            $base,
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /** A form value as a string; arrays and missing keys become ''. */
    public function string(string $key, bool $trim = true): string
    {
        $value = $this->input($key);
        if (!is_string($value)) {
            return '';
        }
        return $trim ? trim($value) : $value;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->post);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post) || array_key_exists($key, $this->query);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';
        return ($https !== '' && $https !== 'off') || (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '');
    }
}
