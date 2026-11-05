<?php

declare(strict_types=1);

namespace App\Controllers;

use Lightpull\Database;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Session;

final class AuthController
{
    /** Checked when the email is unknown, so a miss takes as long as a wrong password. */
    private const DUMMY_HASH = '$2y$10$kad6wekp02jN7NBBxPRid./sQ3zLoCOGXIV5ZuoJ9mqHDKVHgOf5S';

    public function __construct(
        private readonly Database $db,
        private readonly Session $session,
        private readonly Request $request,
    ) {
    }

    public function showLogin(): Response
    {
        return $this->session->check() ? redirect('/admin') : admin_login();
    }

    public function login(): Response
    {
        $email = strtolower($this->request->string('email'));
        $password = $this->request->string('password', trim: false);

        $user = $this->db->one('SELECT id, name, email, role, password_hash FROM users WHERE email = ?', [$email]);
        $valid = password_verify($password, $user['password_hash'] ?? self::DUMMY_HASH);

        if ($user === null || !$valid) {
            $this->session->flash('error', 'Those sign-in details are not correct.');
            $this->session->flashInput(['email' => $email]);
            return redirect('/admin/login');
        }

        $this->session->login($user);
        return redirect('/admin');
    }

    public function logout(): Response
    {
        $this->session->logout();
        $this->session->flash('info', 'You have been signed out.');
        return redirect('/admin/login');
    }
}
