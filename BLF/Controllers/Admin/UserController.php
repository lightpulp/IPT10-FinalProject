<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Session;

/** Admin only: every action here is guarded in the constructor. */
final class UserController extends AdminController
{
    public function __construct(Database $db, Session $session, Request $request)
    {
        parent::__construct($db, $session, $request);
        $this->requireAdmin();
    }

    public function index(): Response
    {
        return admin_users([
            'users' => $this->db->all('SELECT id, name, email, role, created_at FROM users ORDER BY name'),
            'current_id' => $this->user['id'],
        ]);
    }

    public function create(): Response
    {
        return admin_user_form(['values' => ['role' => 'staff'], 'errors' => []]);
    }

    public function store(): Response
    {
        $values = [
            'name' => $this->request->string('name'),
            'email' => strtolower($this->request->string('email')),
            'password' => $this->request->string('password', trim: false),
            'role' => $this->request->string('role'),
        ];

        $errors = [];
        if (mb_strlen($values['name']) < 2 || mb_strlen($values['name']) > 100) {
            $errors['name'] = 'Enter a name of 2 to 100 characters.';
        }
        if (mb_strlen($values['email']) > 150 || filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Enter a valid email address.';
        } elseif ($this->db->value('SELECT COUNT(*) FROM users WHERE email = ?', [$values['email']]) > 0) {
            $errors['email'] = 'An account with this email already exists.';
        }
        // bcrypt only reads the first 72 bytes, so longer passwords would be silently truncated.
        if (strlen($values['password']) < 8 || strlen($values['password']) > 72) {
            $errors['password'] = 'Use 8 to 72 characters.';
        }
        if (!in_array($values['role'], ['staff', 'admin'], true)) {
            $errors['role'] = 'Choose a role.';
        }
        if ($errors !== []) {
            unset($values['password']);
            return admin_user_form(['values' => $values, 'errors' => $errors]);
        }

        $this->db->insert('users', [
            'name' => $values['name'],
            'email' => $values['email'],
            'password_hash' => password_hash($values['password'], PASSWORD_DEFAULT),
            'role' => $values['role'],
        ]);
        $this->session->flash('success', 'Account created for ' . $values['name'] . '.');
        return redirect('/admin/users');
    }

    public function delete(int $id): Response
    {
        if ($id === $this->user['id']) {
            $this->session->flash('error', 'You cannot delete your own account.');
            return redirect('/admin/users');
        }
        $this->db->one('SELECT id FROM users WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $this->db->delete('users', $id);
        $this->session->flash('success', 'Account deleted.');
        return redirect('/admin/users');
    }
}
