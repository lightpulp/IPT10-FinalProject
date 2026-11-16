<?php

declare(strict_types=1);

namespace Lightpull;

use Closure;

/**
 * A layer the request passes through on its way to the controller.
 *
 * A middleware can stop the request (return its own Response or throw an
 * HttpException), change the request before it continues, or look at the
 * response on its way back out. Calling $next($request) passes control inward.
 */
interface Middleware
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response;
}
