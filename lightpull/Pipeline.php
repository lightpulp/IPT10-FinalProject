<?php

declare(strict_types=1);

namespace Lightpull;

use Closure;
use LogicException;

/**
 * Wraps a destination (the router, or a controller action) in layers of middleware.
 * The first class in the list is the outermost layer: it runs first on the way in
 * and last on the way out. Middleware are built through the Container, so their
 * constructors can ask for Database, Session, and so on.
 */
final class Pipeline
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param list<class-string<Middleware>> $middleware
     * @param Closure(Request): Response $destination
     */
    public function run(array $middleware, Request $request, Closure $destination): Response
    {
        $next = $destination;
        foreach (array_reverse($middleware) as $class) {
            $next = function (Request $request) use ($class, $next): Response {
                $layer = $this->container->get($class);
                if (!$layer instanceof Middleware) {
                    throw new LogicException("[{$class}] is not a Lightpull\\Middleware.");
                }
                return $layer->handle($request, $next);
            };
        }
        return $next($request);
    }
}
