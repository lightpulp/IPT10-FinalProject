<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Lightpull\HttpException;
use Lightpull\Middleware;
use Lightpull\Request;
use Lightpull\Response;

/** Authorization: only users with role 'admin' pass. Runs after Authenticate. */
final class RequireAdmin implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (($request->attribute('user')['role'] ?? null) !== 'admin') {
            throw new HttpException(403, 'Only administrators can manage staff accounts.');
        }
        return $next($request);
    }
}
