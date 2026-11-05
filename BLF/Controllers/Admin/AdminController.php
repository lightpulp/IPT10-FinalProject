<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Request;
use Lightpull\Session;

/**
 * Every admin controller extends this. Construction fails with a redirect to the
 * login page unless the session belongs to a user who still exists.
 */
abstract class AdminController
{
    /** The signed-in user, re-read from the database on every request. */
    protected array $user;

    public function __construct(
        protected readonly Database $db,
        protected readonly Session $session,
        protected readonly Request $request,
    ) {
        $id = $session->user()['id'] ?? null;
        $user = $id === null ? null : $db->one('SELECT id, name, email, role FROM users WHERE id = ?', [$id]);
        if ($user === null) {
            $session->logout();
            throw HttpException::redirect(url('/admin/login'));
        }
        $this->user = $user;
    }

    protected function requireAdmin(): void
    {
        if (!$this->session->isAdmin() || $this->user['role'] !== 'admin') {
            throw new HttpException(403, 'Only administrators can manage staff accounts.');
        }
    }
}
