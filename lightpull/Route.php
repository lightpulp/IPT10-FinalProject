<?php

declare(strict_types=1);

namespace Lightpull;

use Closure;

/** One registered route: its handler and the middleware that guard it. */
final class Route
{
    /** @param array{0: class-string, 1: string}|Closure $handler */
    public function __construct(
        public readonly array|Closure $handler,
        private array $middleware = [],
    ) {
    }

    /** Adds route-level middleware, e.g. $router->get(...)->middleware(RequireAdmin::class). */
    public function middleware(string ...$classes): self
    {
        array_push($this->middleware, ...$classes);
        return $this;
    }

    /** @return list<class-string<Middleware>> */
    public function middlewareList(): array
    {
        return $this->middleware;
    }
}
