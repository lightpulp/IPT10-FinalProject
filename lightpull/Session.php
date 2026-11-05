<?php

declare(strict_types=1);

namespace Lightpull;

/** The only class that touches $_SESSION. */
final class Session
{
    /** Flash data written by the previous request; readable once during this one. */
    private array $flashed = [];

    public function start(bool $secure = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        $this->flashed = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'],
            ]);
            session_destroy();
        }
    }

    public function flash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    public function getFlash(string $key): ?string
    {
        $message = $this->flashed[$key] ?? null;
        unset($this->flashed[$key]);
        return is_string($message) ? $message : null;
    }

    /** Keeps submitted values for the next request so a redirected form can be refilled with old(). */
    public function flashInput(array $input): void
    {
        $_SESSION['_flash']['_old'] = $input;
    }

    public function old(string $key): mixed
    {
        return $this->flashed['_old'][$key] ?? null;
    }

    public function csrfToken(): string
    {
        $_SESSION['_csrf'] ??= bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }

    public function verifyCsrf(?string $token): bool
    {
        $expected = $_SESSION['_csrf'] ?? null;
        return is_string($token) && is_string($expected) && hash_equals($expected, $token);
    }

    public function login(array $user): void
    {
        session_regenerate_id(true);
        unset($_SESSION['_csrf']);
        $_SESSION['_user'] = [
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ];
    }

    public function logout(): void
    {
        unset($_SESSION['_user'], $_SESSION['_csrf']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function user(): ?array
    {
        return $_SESSION['_user'] ?? null;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? null) === 'admin';
    }
}
