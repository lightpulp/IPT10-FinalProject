<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Request;
use Lightpull\Session;

/**
 * Base class for every admin controller. The Authenticate middleware has
 * already checked the login and attached the fresh user row to the request.
 */
abstract class AdminController
{
    /** The signed-in user (id, name, email, role). */
    protected array $user;

    public function __construct(
        protected readonly Database $db,
        protected readonly Session $session,
        protected readonly Request $request,
    ) {
        // Defence in depth: a route registered outside the Authenticate group still cannot run.
        $this->user = $request->attribute('user') ?? throw HttpException::redirect(url('/admin/login'));
    }
}
