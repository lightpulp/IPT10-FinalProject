<?php

declare(strict_types=1);

/*
 * Lightpull UI components. Each function takes an options array and returns a
 * Bootstrap 5.3 HTML string. Values are escaped here, option values are looked
 * up in fixed maps, and raw() is the only way to emit unescaped markup.
 */

use Lightpull\Response;

/** Returns its argument untouched. Never pass it anything a user typed. */
function raw(string $html): string
{
    return $html;
}

// ---------------------------------------------------------------- internals

function ui_color(mixed $color, string $default = 'primary'): string
{
    $allowed = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
    return in_array($color, $allowed, true) ? $color : $default;
}

/** App paths get the base path; http(s), mailto, tel and #fragments pass; anything else (e.g. javascript:) becomes '#'. */
function ui_href(mixed $url): string
{
    $url = is_string($url) ? $url : '';
    if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
        return e(url($url));
    }
    return preg_match('#^(https?://|mailto:|tel:|\#)#i', $url) ? e($url) : '#';
}

/** Builds HTML attributes; attribute names are always literals from this file. */
function ui_attrs(array $attrs): string
{
    $html = '';
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === false || $value === '') {
            continue;
        }
        $html .= $value === true ? ' ' . $name : ' ' . $name . '="' . e($value) . '"';
    }
    return $html;
}

function ui_field_id(array $o): string
{
    $id = $o['id'] ?? 'f-' . preg_replace('/[^a-z0-9]+/i', '-', (string) ($o['name'] ?? 'field'));
    return trim((string) $id, '-');
}

function ui_label(array $o): string
{
    if (empty($o['label'])) {
        return '';
    }
    $required = !empty($o['required']) ? ' <span class="text-danger">*</span>' : '';
    return '<label class="form-label" for="' . e(ui_field_id($o)) . '">' . e($o['label']) . $required . '</label>';
}

function ui_feedback(array $o): string
{
    return (!empty($o['help']) ? '<div class="form-text">' . e($o['help']) . '</div>' : '')
        . (!empty($o['error']) ? '<div class="invalid-feedback d-block">' . e($o['error']) . '</div>' : '');
}

function ui_invalid(array $o): string
{
    return !empty($o['error']) ? ' is-invalid' : '';
}

function ui_image(mixed $src, string $alt, string $class, int $height): string
{
    if (!is_string($src) || $src === '') {
        return placeholder_svg($alt, $height, $class);
    }
    return '<img class="' . $class . '" src="' . ui_href($src) . '" alt="' . e($alt) . '" loading="lazy"'
        . ' style="height: ' . $height . 'px; object-fit: cover;">';
}

// ---------------------------------------------------------------- shell

function page(string $title, array $parts, array $o = []): Response
{
    $site = (string) config('app.name', 'Lightpull');
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . e($title === '' ? $site : $title . ' | ' . $site) . '</title>'
        . (isset($o['description']) ? '<meta name="description" content="' . e($o['description']) . '">' : '')
        . '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"'
        . ' integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">'
        . '<link rel="stylesheet" href="' . e(asset('css/app.css')) . '">'
        . "</head>\n<body class=\"d-flex flex-column min-vh-100\">\n"
        . implode("\n", $parts)
        . "\n" . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"'
        . ' integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>'
        . "\n</body></html>";
    return Response::html($html, (int) ($o['status'] ?? 200));
}

function container(array $parts, array $o = []): string
{
    $class = !empty($o['fluid']) ? 'container-fluid px-lg-4' : 'container';
    $width = ['narrow' => ' style="max-width: 460px"', 'medium' => ' style="max-width: 860px"'][$o['width'] ?? ''] ?? '';
    return '<main class="' . $class . ' py-4 flex-grow-1"' . $width . '>' . implode("\n", $parts) . '</main>';
}

function row(array $parts, array $o = []): string
{
    $gap = ['sm' => 'g-2', 'md' => 'g-4', 'lg' => 'g-5'][$o['gap'] ?? 'md'] ?? 'g-4';
    return '<div class="row ' . $gap . '">' . implode("\n", $parts) . '</div>';
}

function col(array $parts, int $width = 12): string
{
    return '<div class="col-12 col-md-' . max(1, min(12, $width)) . '">' . implode("\n", $parts) . '</div>';
}

// ---------------------------------------------------------------- navigation and chrome

function navbar(array $o = []): string
{
    static $count = 0;
    $id = 'lp-nav-' . ++$count;
    $schemes = [
        'dark' => ['navbar-dark bg-dark', 'light'],
        'light' => ['bg-body-tertiary border-bottom', 'secondary'],
        'primary' => ['navbar-dark bg-primary', 'light'],
    ];
    [$scheme, $buttonColor] = $schemes[$o['color'] ?? 'dark'] ?? $schemes['dark'];
    $size = ['sm' => 'py-1', 'md' => 'py-2', 'lg' => 'py-3'][$o['size'] ?? 'md'] ?? 'py-2';

    $links = '';
    foreach ($o['links'] ?? [] as $label => $url) {
        $active = (string) $label === (string) ($o['active'] ?? '');
        $links .= '<li class="nav-item"><a class="nav-link' . ($active ? ' active" aria-current="page' : '') . '"'
            . ' href="' . ui_href($url) . '">' . e($label) . '</a></li>';
    }

    $right = !empty($o['text']) ? '<span class="navbar-text small">' . e($o['text']) . '</span>' : '';
    foreach ($o['right'] ?? [] as $label => $url) {
        $right .= button(['label' => (string) $label, 'url' => $url, 'color' => $buttonColor, 'outline' => true, 'size' => 'sm']);
    }
    foreach ($o['right_post'] ?? [] as $label => $url) {
        $right .= post_button(['label' => (string) $label, 'action' => $url, 'color' => $buttonColor]);
    }

    return '<nav class="navbar navbar-expand-lg ' . $scheme . ' ' . $size . '">'
        . '<div class="' . (!empty($o['fluid']) ? 'container-fluid px-lg-4' : 'container') . '">'
        . '<a class="navbar-brand" href="' . ui_href($o['brand_url'] ?? '/') . '">' . e($o['brand'] ?? config('app.name', 'Lightpull')) . '</a>'
        . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '"'
        . ' aria-controls="' . $id . '" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>'
        . '<div class="collapse navbar-collapse" id="' . $id . '"><ul class="navbar-nav me-auto">' . $links . '</ul>'
        . '<div class="d-flex flex-wrap align-items-center gap-2 py-2 py-lg-0">' . $right . '</div></div></div></nav>';
}

/** columns: heading => [label => url]; a null url renders the label as plain text. */
function footer(array $o = []): string
{
    $columns = '';
    foreach ($o['columns'] ?? [] as $heading => $links) {
        $items = '';
        foreach ($links as $label => $url) {
            $items .= '<li class="mb-1">' . ($url === null ? e($label)
                : '<a class="link-secondary text-decoration-none" href="' . ui_href($url) . '">' . e($label) . '</a>') . '</li>';
        }
        $columns .= '<div class="col-12 col-md"><h2 class="h6 text-uppercase">' . e($heading) . '</h2>'
            . '<ul class="list-unstyled small text-body-secondary">' . $items . '</ul></div>';
    }
    $links = '';
    foreach ($o['links'] ?? [] as $label => $url) {
        $links .= '<li class="list-inline-item"><a class="link-secondary" href="' . ui_href($url) . '">' . e($label) . '</a></li>';
    }
    return '<footer class="lp-footer border-top mt-auto py-4 bg-body-tertiary"><div class="container">'
        . ($columns !== '' ? '<div class="row g-4 mb-2">' . $columns . '</div>' : '')
        . '<div class="d-flex flex-wrap justify-content-between gap-2 small text-body-secondary">'
        . '<span>' . e($o['text'] ?? '') . '</span><ul class="list-inline mb-0">' . $links . '</ul></div>'
        . '</div></footer>';
}

function breadcrumbs(array $o = []): string
{
    $items = $o['items'] ?? [];
    $last = array_key_last($items);
    $html = '';
    foreach ($items as $label => $url) {
        $html .= $label === $last || $url === null
            ? '<li class="breadcrumb-item active" aria-current="page">' . e($label) . '</li>'
            : '<li class="breadcrumb-item"><a href="' . ui_href($url) . '">' . e($label) . '</a></li>';
    }
    return '<nav aria-label="breadcrumb"><ol class="breadcrumb">' . $html . '</ol></nav>';
}

function pills(array $o = []): string
{
    $html = '';
    foreach ($o['items'] ?? [] as $label => $url) {
        $active = (string) $label === (string) ($o['active'] ?? '');
        $html .= '<li class="nav-item"><a class="nav-link' . ($active ? ' active" aria-current="page' : '') . '"'
            . ' href="' . ui_href($url) . '">' . e($label) . '</a></li>';
    }
    return '<ul class="nav nav-pills flex-wrap gap-1 mb-3">' . $html . '</ul>';
}

/** A GET search box. 'hidden' (name => value) keeps other active filters in the query string. */
function search_form(array $o = []): string
{
    $hidden = '';
    foreach ($o['hidden'] ?? [] as $name => $value) {
        $hidden .= $value === '' || $value === null ? '' : input(['type' => 'hidden', 'name' => (string) $name, 'value' => $value]);
    }
    $placeholder = $o['placeholder'] ?? 'Search';
    return '<form class="d-flex gap-2 mb-3" role="search" method="get" action="' . ui_href($o['action'] ?? '#') . '">' . $hidden
        . '<input class="form-control"' . ui_attrs(['type' => 'search', 'name' => $o['name'] ?? 'q', 'value' => $o['value'] ?? null,
            'placeholder' => $placeholder, 'aria-label' => $placeholder]) . '>'
        . '<button class="btn btn-outline-primary" type="submit">Search</button></form>';
}

function pagination(array $o = []): string
{
    $page = (int) ($o['page'] ?? 1);
    $total = (int) ($o['total_pages'] ?? 1);
    if ($total <= 1) {
        return '';
    }
    $base = (string) ($o['base_url'] ?? '/');
    $separator = str_contains($base, '?') ? '&' : '?';
    $item = fn (int $n, string $label, string $state = '') => '<li class="page-item' . $state . '">'
        . '<a class="page-link" href="' . ui_href($base . $separator . 'page=' . $n) . '">' . e($label) . '</a></li>';

    $items = $item(max(1, $page - 1), 'Previous', $page <= 1 ? ' disabled' : '');
    for ($n = 1; $n <= $total; $n++) {
        $items .= $item($n, (string) $n, $n === $page ? ' active' : '');
    }
    $items .= $item(min($total, $page + 1), 'Next', $page >= $total ? ' disabled' : '');
    return '<nav aria-label="Pagination" class="mt-4"><ul class="pagination flex-wrap justify-content-center">' . $items . '</ul></nav>';
}

// ---------------------------------------------------------------- content

function hero(array $o = []): string
{
    $center = ($o['align'] ?? 'start') === 'center';
    $hasImage = !empty($o['image']);
    $text = '<div class="col-12 ' . ($hasImage ? 'col-lg-6' : 'col-lg-8') . ($center ? ' mx-auto text-center' : '') . '">'
        . '<h1 class="display-5 fw-bold">' . e($o['title'] ?? '') . '</h1>'
        . (!empty($o['text']) ? '<p class="lead my-3">' . e($o['text']) . '</p>' : '')
        . (isset($o['button']) ? button(['label' => $o['button'][0] ?? '', 'url' => $o['button'][1] ?? '/', 'size' => 'lg']) : '')
        . '</div>';
    $image = $hasImage ? '<div class="col-12 col-lg-6">' . ui_image($o['image'], (string) ($o['title'] ?? ''), 'img-fluid w-100', 360) . '</div>' : '';
    return '<section class="lp-hero py-5 border-bottom"><div class="container py-lg-4">'
        . '<div class="row align-items-center g-4">' . $text . $image . '</div></div></section>';
}

function section_title(array $o = []): string
{
    [$tag, $class] = ['lg' => ['h1', 'display-6'], 'md' => ['h2', 'h3'], 'sm' => ['h2', 'h5']][$o['size'] ?? 'md'] ?? ['h2', 'h3'];
    $action = isset($o['action']) ? button(['label' => $o['action'][0] ?? '', 'url' => $o['action'][1] ?? '/', 'size' => 'sm', 'outline' => true]) : '';
    return '<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mt-4 mb-3"><div>'
        . '<' . $tag . ' class="' . $class . ' mb-1">' . e($o['title'] ?? '') . '</' . $tag . '>'
        . (!empty($o['subtitle']) ? '<p class="text-body-secondary mb-0">' . e($o['subtitle']) . '</p>' : '')
        . '</div>' . $action . '</div>';
}

/** Plain text; blank lines start new paragraphs and single newlines become line breaks. */
function text_block(array $o = []): string
{
    $class = !empty($o['lead']) ? ' class="lead"' : (!empty($o['muted']) ? ' class="text-body-secondary"' : '');
    $html = '';
    foreach (preg_split('/\R{2,}/', trim((string) ($o['text'] ?? ''))) ?: [] as $paragraph) {
        $html .= $paragraph === '' ? '' : '<p' . $class . '>' . nl2br(e($paragraph)) . '</p>';
    }
    return $html;
}

function button(array $o = []): string
{
    $style = !empty($o['outline']) ? 'btn-outline-' : 'btn-';
    $size = ['sm' => ' btn-sm', 'md' => '', 'lg' => ' btn-lg'][$o['size'] ?? 'md'] ?? '';
    return '<a class="btn ' . $style . ui_color($o['color'] ?? 'primary') . $size . '" href="' . ui_href($o['url'] ?? '#') . '">'
        . e($o['label'] ?? '') . '</a>';
}

/** A one-button POST form (delete, logout) with CSRF token and optional confirm() prompt. */
function post_button(array $o = []): string
{
    $confirm = '';
    if (!empty($o['confirm'])) {
        $message = json_encode((string) $o['confirm'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $confirm = ' onsubmit="return confirm(' . e($message) . ');"';
    }
    return '<form class="d-inline" method="post" action="' . ui_href($o['action'] ?? '#') . '"' . $confirm . '>' . csrf_field()
        . '<button type="submit" class="btn btn-sm btn-outline-' . ui_color($o['color'] ?? 'danger', 'danger') . '">'
        . e($o['label'] ?? 'Submit') . '</button></form>';
}

function card(array $o = []): string
{
    $title = e($o['title'] ?? '');
    if (!empty($o['link'])) {
        $title = '<a class="stretched-link text-reset text-decoration-none" href="' . ui_href($o['link']) . '">' . $title . '</a>';
    }
    $badge = $o['badge'] ?? null;
    return '<div class="card h-100 shadow-sm">'
        . (array_key_exists('image', $o) ? ui_image($o['image'], (string) ($o['title'] ?? ''), 'card-img-top', 220) : '')
        . '<div class="card-body">'
        . (!empty($badge) ? '<div class="mb-2">' . badge(is_array($badge) ? $badge : ['text' => $badge]) . '</div>' : '')
        . '<h3 class="h5 card-title">' . $title . '</h3>'
        . (!empty($o['subtitle']) ? '<p class="card-subtitle small text-body-secondary mb-2">' . e($o['subtitle']) . '</p>' : '')
        . (isset($o['text']) && $o['text'] !== '' ? '<p class="card-text">' . e($o['text']) . '</p>' : '')
        . '</div>'
        . (!empty($o['footer']) ? '<div class="card-footer small text-body-secondary">' . e($o['footer']) . '</div>' : '')
        . '</div>';
}

/**
 * A responsive grid of card()s. $map turns each item into card options:
 * each value is either a key of the item or a Closure receiving the item.
 */
function card_grid(array $items, array $map, array $o = []): string
{
    $columns = [
        3 => 'row-cols-1 row-cols-sm-2 row-cols-lg-3',
        4 => 'row-cols-1 row-cols-sm-2 row-cols-lg-4',
        6 => 'row-cols-2 row-cols-md-3 row-cols-lg-6',
    ][$o['columns'] ?? 3] ?? 'row-cols-1 row-cols-sm-2 row-cols-lg-3';
    $cards = '';
    foreach ($items as $item) {
        $options = [];
        foreach ($map as $option => $source) {
            $options[$option] = $source instanceof Closure ? $source($item) : ($item[$source] ?? null);
        }
        $cards .= '<div class="col">' . card($options) . '</div>';
    }
    return '<div class="row ' . $columns . ' g-4">' . $cards . '</div>';
}

function image(array $o = []): string
{
    return ui_image($o['src'] ?? null, (string) ($o['alt'] ?? ''), 'img-fluid w-100 border', (int) ($o['height'] ?? 400));
}

function placeholder_svg(string $text, int $height = 220, string $class = ''): string
{
    // "meet" scales the text down on narrow cards instead of cropping it; the background fills any letterbox.
    return '<svg class="' . $class . ' d-block w-100" style="height: ' . $height . 'px; background-color: #e2dccf"'
        . ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' . e($text) . '"'
        . ' preserveAspectRatio="xMidYMid meet" viewBox="0 0 400 ' . $height . '">'
        . '<text x="200" y="' . intdiv($height, 2) . '" fill="#6c6356" font-family="Georgia, serif" font-size="20"'
        . ' text-anchor="middle" dominant-baseline="middle">' . e(mb_strimwidth($text, 0, 36, '…')) . '</text></svg>';
}

function alert(array $o = []): string
{
    $type = ['success' => 'success', 'danger' => 'danger', 'warning' => 'warning', 'info' => 'info'][$o['type'] ?? 'info'] ?? 'info';
    $dismissible = !empty($o['dismissible']);
    return '<div class="alert alert-' . $type . ($dismissible ? ' alert-dismissible fade show' : '') . '" role="alert">'
        . e($o['message'] ?? '')
        . ($dismissible ? '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' : '')
        . '</div>';
}

/** Read-once session messages under the keys success, error, warning and info. */
function flash_messages(): string
{
    $html = '';
    foreach (['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'] as $key => $type) {
        $message = session()->getFlash($key);
        $html .= $message === null ? '' : alert(['type' => $type, 'message' => $message, 'dismissible' => true]);
    }
    return $html;
}

function empty_state(array $o = []): string
{
    $action = isset($o['action']) ? button(['label' => $o['action'][0] ?? '', 'url' => $o['action'][1] ?? '/', 'outline' => true]) : '';
    return '<div class="text-center py-5 px-3 border bg-body">'
        . '<p class="text-body-secondary mb-3">' . e($o['message'] ?? 'Nothing here yet.') . '</p>' . $action . '</div>';
}

function badge(array $o = []): string
{
    return '<span class="badge text-bg-' . ui_color($o['color'] ?? 'secondary', 'secondary') . '">' . e($o['text'] ?? '') . '</span>';
}

function definition_list(array $o = []): string
{
    $html = '';
    foreach ($o['items'] ?? [] as $label => $value) {
        if ($value !== null && $value !== '') {
            $html .= '<dt class="col-sm-4">' . e($label) . '</dt><dd class="col-sm-8">' . nl2br(e($value)) . '</dd>';
        }
    }
    return '<dl class="row mb-3">' . $html . '</dl>';
}

/** A boxed label and large value, e.g. a confirmation number. */
function callout(array $o = []): string
{
    return '<div class="border bg-body p-4 text-center mb-4">'
        . '<div class="small text-uppercase text-body-secondary">' . e($o['label'] ?? '') . '</div>'
        . '<div class="display-5 fw-bold my-2">' . e($o['value'] ?? '') . '</div>'
        . (!empty($o['text']) ? '<p class="mb-0">' . e($o['text']) . '</p>' : '')
        . '</div>';
}

/** A horizontal progress bar over named steps; 'current' is the zero-based index reached. */
function progress_steps(array $o = []): string
{
    $steps = array_values($o['steps'] ?? []);
    $count = count($steps);
    if ($count === 0) {
        return '';
    }
    $current = max(0, min($count - 1, (int) ($o['current'] ?? 0)));
    $percent = (int) round(($current + 1) / $count * 100);
    $labels = '';
    foreach ($steps as $i => $label) {
        $class = $i === $current ? 'fw-bold' : ($i < $current ? '' : 'text-body-secondary');
        $labels .= '<li class="small text-center flex-fill px-1 ' . $class . '">' . ($i <= $current ? '✓ ' : '') . e($label) . '</li>';
    }
    return '<div class="my-3"><div class="progress" role="progressbar" aria-label="Progress" aria-valuenow="' . $percent . '"'
        . ' aria-valuemin="0" aria-valuemax="100" style="height: 10px"><div class="progress-bar bg-'
        . ui_color($o['color'] ?? 'success', 'success') . '" style="width: ' . $percent . '%"></div></div>'
        . '<ol class="list-unstyled d-none d-md-flex mt-2 mb-0">' . $labels . '</ol>'
        . '<p class="small mt-2 mb-0 d-md-none">Step ' . ($current + 1) . ' of ' . $count . ': ' . e($steps[$current]) . '</p></div>';
}

function code_block(array $o = []): string
{
    return '<pre class="bg-dark text-light p-3 small overflow-auto">' . e($o['text'] ?? '') . '</pre>';
}

/** A generic error page, used when the app does not configure its own 'error_view'. */
function error_page(int $status, string $message, ?string $detail = null): Response
{
    return page('Error ' . $status, [container([
        section_title(['title' => 'Error ' . $status, 'size' => 'lg']),
        text_block(['text' => $message, 'lead' => true]),
        button(['label' => 'Go to the home page', 'url' => '/']),
        $detail !== null ? code_block(['text' => $detail]) : '',
    ])], ['status' => $status]);
}

// ---------------------------------------------------------------- forms

function form_open(array $o = []): string
{
    $method = strtolower((string) ($o['method'] ?? 'post')) === 'get' ? 'get' : 'post';
    return '<form method="' . $method . '" action="' . ui_href($o['action'] ?? '#') . '"'
        . (!empty($o['multipart']) ? ' enctype="multipart/form-data"' : '') . '>'
        . ($method === 'post' ? csrf_field() : '');
}

function form_close(array $o = []): string
{
    return '</form>';
}

function input(array $o = []): string
{
    $allowed = ['text', 'email', 'password', 'number', 'tel', 'search', 'url', 'date', 'hidden'];
    $type = in_array($o['type'] ?? 'text', $allowed, true) ? ($o['type'] ?? 'text') : 'text';
    $attrs = ui_attrs([
        'type' => $type,
        'name' => $o['name'] ?? '',
        'id' => $type === 'hidden' ? null : ui_field_id($o),
        'value' => $type === 'password' ? null : ($o['value'] ?? null),
        'placeholder' => $o['placeholder'] ?? null,
        'required' => !empty($o['required']),
        'readonly' => !empty($o['readonly']),
        'min' => $o['min'] ?? null,
        'step' => $o['step'] ?? null,
        'maxlength' => $o['maxlength'] ?? null,
        'autocomplete' => $o['autocomplete'] ?? null,
    ]);
    if ($type === 'hidden') {
        return '<input' . $attrs . '>';
    }
    return '<div class="mb-3">' . ui_label($o) . '<input class="form-control' . ui_invalid($o) . '"' . $attrs . '>' . ui_feedback($o) . '</div>';
}

function textarea(array $o = []): string
{
    $attrs = ui_attrs([
        'name' => $o['name'] ?? '',
        'id' => ui_field_id($o),
        'rows' => (string) max(2, min(30, (int) ($o['rows'] ?? 4))),
        'placeholder' => $o['placeholder'] ?? null,
        'required' => !empty($o['required']),
        'maxlength' => $o['maxlength'] ?? null,
    ]);
    return '<div class="mb-3">' . ui_label($o) . '<textarea class="form-control' . ui_invalid($o) . '"' . $attrs . '>'
        . e($o['value'] ?? '') . '</textarea>' . ui_feedback($o) . '</div>';
}

function select(array $o = []): string
{
    $selected = (string) ($o['selected'] ?? '');
    $options = isset($o['placeholder']) ? '<option value="">' . e($o['placeholder']) . '</option>' : '';
    foreach ($o['options'] ?? [] as $value => $label) {
        $options .= '<option value="' . e($value) . '"' . ((string) $value === $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $attrs = ui_attrs(['name' => $o['name'] ?? '', 'id' => ui_field_id($o), 'required' => !empty($o['required'])]);
    return '<div class="mb-3">' . ui_label($o) . '<select class="form-select' . ui_invalid($o) . '"' . $attrs . '>' . $options . '</select>'
        . ui_feedback($o) . '</div>';
}

function checkbox(array $o = []): string
{
    $attrs = ui_attrs(['name' => $o['name'] ?? '', 'id' => ui_field_id($o), 'value' => '1', 'checked' => !empty($o['checked'])]);
    return '<div class="form-check mb-3"><input class="form-check-input' . ui_invalid($o) . '" type="checkbox"' . $attrs . '>'
        . '<label class="form-check-label" for="' . e(ui_field_id($o)) . '">' . e($o['label'] ?? '') . '</label>'
        . ui_feedback($o) . '</div>';
}

function file_input(array $o = []): string
{
    $current = !empty($o['current'])
        ? '<div class="mb-2">' . ui_image($o['current'], 'Current image', 'border', 120) . '</div>'
        : '';
    $attrs = ui_attrs(['type' => 'file', 'name' => $o['name'] ?? '', 'id' => ui_field_id($o), 'accept' => $o['accept'] ?? null]);
    return '<div class="mb-3">' . ui_label($o) . $current . '<input class="form-control' . ui_invalid($o) . '"' . $attrs . '>'
        . ui_feedback($o) . '</div>';
}

function submit(array $o = []): string
{
    $cancel = isset($o['cancel'])
        ? button(['label' => $o['cancel'][0] ?? 'Cancel', 'url' => $o['cancel'][1] ?? '/', 'color' => 'secondary', 'outline' => true])
        : '';
    return '<div class="d-flex flex-wrap gap-2 mt-3"><button type="submit" class="btn btn-' . ui_color($o['color'] ?? 'primary') . '">'
        . e($o['label'] ?? 'Save') . '</button>' . $cancel . '</div>';
}

/** A field hidden from people; bots that fill every input reveal themselves. */
function honeypot(array $o = []): string
{
    return '<div class="position-absolute overflow-hidden" style="left: -10000px; width: 1px; height: 1px;" aria-hidden="true">'
        . '<label>Leave this field empty <input type="text" name="' . e($o['name'] ?? 'website') . '" tabindex="-1" autocomplete="off"></label></div>';
}
