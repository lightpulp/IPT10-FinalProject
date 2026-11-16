<?php

declare(strict_types=1);

namespace Lightpull\Middleware;

use Closure;
use Lightpull\App;
use Lightpull\HttpException;
use Lightpull\Middleware;
use Lightpull\Request;
use Lightpull\Response;
use Throwable;

/**
 * Logging: writes one line per request to config('log.file'), e.g.
 *
 *     [2026-11-16 10:42:07] GET /catalog 200 18.4ms 127.0.0.1
 *
 * Register it first so it times every other layer and also records requests
 * that end in an exception (logged with the status the visitor will see).
 */
final class LogRequests implements Middleware
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->status();
            return $response;
        } catch (HttpException $e) {
            $status = $e->status();
            throw $e;
        } finally {
            $this->write(sprintf(
                "[%s] %s %s %d %.1fms %s\n",
                date('Y-m-d H:i:s'),
                $request->method(),
                $request->path(),
                $status,
                (hrtime(true) - $started) / 1e6,
                $request->ip(),
            ));
        }
    }

    private function write(string $line): void
    {
        $file = $this->app->config('log.file');
        if (!is_string($file) || $file === '') {
            return;
        }
        try {
            if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
                error_log('Lightpull could not write to the request log ' . $file);
            }
        } catch (Throwable) {
            // Logging must never break a page.
        }
    }
}
