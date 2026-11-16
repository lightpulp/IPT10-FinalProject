<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use Lightpull\Database;
use Lightpull\Middleware;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Session;

/**
 * Authentication: guards every /admin route. The session only remembers who
 * signed in; the user is re-read from the database on each request so a
 * deleted account is locked out immediately. The fresh row is attached to the
 * request as the 'user' attribute for RequireAdmin and the controllers.
 */
final class Authenticate implements Middleware
{
    public function __construct(
        private readonly Session $session,
        private readonly Database $db,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->session->user()['id'] ?? null;
        $user = $id === null ? null : $this->db->one('SELECT id, name, email, role FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            $this->session->logout();
            return redirect('/admin/login');
        }

        $request->setAttribute('user', $user);
        return $next($request);
    }
}
