<?php

declare(strict_types=1);

use Lightpull\App;
use Lightpull\Response;
use Lightpull\Session;

function app(): App
{
    return App::instance();
}

function config(string $key, mixed $default = null): mixed
{
    return App::instance()->config($key, $default);
}

function env(string $key, ?string $default = null): ?string
{
    return App::env($key, $default);
}

function session(): Session
{
    return App::instance()->get(Session::class);
}

function e(mixed $value): string
{
    if (is_bool($value)) {
        $value = $value ? '1' : '0';
    }
    if (!is_scalar($value)) {
        return '';
    }
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugify(string $text): string
{
    $text = strtolower($text);
    $text = (string) preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(session()->csrfToken()) . '">';
}

/** Redirect to an app path such as '/admin'; the install's base path is added for you. */
function redirect(string $path): Response
{
    return Response::redirect(url($path));
}

function old(string $key, mixed $default = ''): mixed
{
    return session()->old($key) ?? $default;
}

function url(string $path = '/'): string
{
    return App::instance()->basePath() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url($path);
}

function money(float|int|string|null $amount): string
{
    if ($amount === null || $amount === '') {
        return (string) config('money.null_text', 'Price upon request');
    }
    return config('money.symbol', '₱') . number_format((float) $amount, 2);
}

/** Statuses are configured once as config('statuses') = [value => ['label' => ..., 'color' => ...]]. */
function status_label(string $status): string
{
    return (string) config("statuses.{$status}.label", ucfirst(str_replace('_', ' ', $status)));
}

function status_color(string $status): string
{
    return (string) config("statuses.{$status}.color", 'secondary');
}

/** @return array{page: int, pages: int, offset: int, limit: int} */
function paginate(int $total, int $perPage, mixed $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = is_string($page) && ctype_digit($page) ? (int) $page : 1;
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'limit' => $perPage];
}
