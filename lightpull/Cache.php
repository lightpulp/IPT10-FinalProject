<?php

declare(strict_types=1);

namespace Lightpull;

/**
 * A small file cache: one JSON file per key in config('cache.dir').
 * Used to keep API responses so the app does not call an external service
 * on every page view.
 */
final class Cache
{
    public function __construct(private readonly string $dir)
    {
    }

    /** The stored value, or null if it is missing or older than $maxAge seconds. */
    public function get(string $key, ?int $maxAge = null): mixed
    {
        $entry = $this->read($key);
        if ($entry === null || ($maxAge !== null && time() - $entry['stored_at'] > $maxAge)) {
            return null;
        }
        return $entry['value'];
    }

    /** Seconds since $key was stored, or null if it is not stored. */
    public function age(string $key): ?int
    {
        $entry = $this->read($key);
        return $entry === null ? null : time() - $entry['stored_at'];
    }

    public function put(string $key, mixed $value): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            return;
        }
        $file = $this->path($key);
        $json = json_encode(['stored_at' => time(), 'value' => $value], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        // Write then rename, so a reader never sees a half-written file.
        if ($json !== false && @file_put_contents($file . '.tmp', $json, LOCK_EX) !== false) {
            @rename($file . '.tmp', $file);
        }
    }

    public function forget(string $key): void
    {
        @unlink($this->path($key));
    }

    /** @return array{stored_at: int, value: mixed}|null */
    private function read(string $key): ?array
    {
        $json = @file_get_contents($this->path($key));
        $entry = $json === false ? null : json_decode($json, true);
        return is_array($entry) && isset($entry['stored_at']) && array_key_exists('value', $entry) ? $entry : null;
    }

    private function path(string $key): string
    {
        return rtrim($this->dir, '/\\') . DIRECTORY_SEPARATOR . sha1($key) . '.json';
    }
}
