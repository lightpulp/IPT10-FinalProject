<?php

declare(strict_types=1);

namespace Lightpull\Middleware;

use Closure;
use Lightpull\HttpException;
use Lightpull\Middleware;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Session;

/**
 * Request filter: every POST must carry the session's CSRF token in `_token`,
 * or the request stops here with 419 before any route or controller runs.
 * Registered globally in config('middleware'), so no handler can forget it.
 */
final class VerifyCsrfToken implements Middleware
{
    public function __construct(private readonly Session $session)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isPost()) {
            $token = $request->input('_token');
            if (!$this->session->verifyCsrf(is_string($token) ? $token : null)) {
                throw new HttpException(419);
            }
        }
        return $next($request);
    }
}
