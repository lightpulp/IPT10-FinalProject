<?php

declare(strict_types=1);

/*
 * Lightpull UI components for back-office screens. Same rules as ui.php:
 * options in, escaped Bootstrap HTML string out.
 */

function sidebar(array $o = []): string
{
    $html = '';
    foreach ($o['links'] ?? [] as $label => $url) {
        $active = (string) $label === (string) ($o['active'] ?? '');
        $html .= '<a class="list-group-item list-group-item-action' . ($active ? ' active" aria-current="page' : '') . '"'
            . ' href="' . ui_href($url) . '">' . e($label) . '</a>';
    }
    return '<nav class="list-group mb-4">' . $html . '</nav>';
}

function stat_card(array $o = []): string
{
    $tag = empty($o['link']) ? 'div' : 'a';
    $href = $tag === 'a' ? ' href="' . ui_href($o['link']) . '"' : '';
    return '<' . $tag . $href . ' class="card h-100 shadow-sm text-decoration-none border-0 border-start border-4 border-'
        . ui_color($o['color'] ?? 'primary') . '"><div class="card-body">'
        . '<div class="small text-uppercase text-body-secondary">' . e($o['label'] ?? '') . '</div>'
        . '<div class="display-6 fw-bold text-body">' . e($o['value'] ?? 0) . '</div>'
        . '</div></' . $tag . '>';
}

/** A select over config('statuses'), so the list of statuses lives in one place. */
function status_select(array $o = []): string
{
    $options = [];
    foreach ((array) config('statuses', []) as $value => $status) {
        $options[$value] = $status['label'];
    }
    return select([
        'name' => $o['name'] ?? 'status',
        'label' => $o['label'] ?? 'Status',
        'options' => $options,
        'selected' => $o['selected'] ?? '',
        'required' => true,
        'error' => $o['error'] ?? null,
    ]);
}

/**
 * columns: key => 'Header' for plain text, or key => ['Header', type] where type is
 *   text | strong | money | date | datetime | badge (value = badge options) |
 *   image (value = ['src' => ?url, 'alt' => text]) | link (value = [label, url]).
 * actions: Closure(row) returning a list of ['label', 'url', 'method' => 'post', 'confirm' => '...'].
 */
function data_table(array $o = []): string
{
    $rows = $o['rows'] ?? [];
    if ($rows === []) {
        return empty_state(['message' => $o['empty'] ?? 'Nothing to show yet.']);
    }
    $columns = [];
    foreach ($o['columns'] ?? [] as $key => $spec) {
        $columns[$key] = is_array($spec) ? [$spec[0] ?? '', $spec[1] ?? 'text'] : [$spec, 'text'];
    }
    $actions = ($o['actions'] ?? null) instanceof Closure ? $o['actions'] : null;

    $head = '';
    foreach ($columns as [$label]) {
        $head .= '<th scope="col">' . e($label) . '</th>';
    }
    $head .= $actions ? '<th scope="col" class="text-end">Actions</th>' : '';

    $body = '';
    foreach ($rows as $row) {
        $body .= '<tr>';
        foreach ($columns as $key => [, $type]) {
            $body .= '<td>' . ui_cell($row[$key] ?? null, $type) . '</td>';
        }
        $body .= $actions ? '<td class="text-end text-nowrap">' . ui_row_actions($actions($row)) . '</td>' : '';
        $body .= '</tr>';
    }
    return '<div class="table-responsive border bg-body mb-3"><table class="table table-hover align-middle mb-0">'
        . '<thead class="table-light"><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table></div>';
}

function ui_cell(mixed $value, string $type): string
{
    $time = is_string($value) && $value !== '' ? strtotime($value) : false;
    return match ($type) {
        'strong' => '<strong>' . e($value) . '</strong>',
        'money' => e(is_scalar($value) || $value === null ? money($value) : ''),
        'date' => $time ? e(date('M j, Y', $time)) : '',
        'datetime' => $time ? e(date('M j, Y g:i A', $time)) : '',
        'badge' => is_array($value) ? badge($value) : '',
        'image' => is_array($value)
            ? '<div style="width: 64px">' . ui_image($value['src'] ?? null, (string) ($value['alt'] ?? ''), 'border w-100', 48) . '</div>'
            : '',
        'link' => is_array($value) ? '<a href="' . ui_href($value[1] ?? '#') . '">' . e($value[0] ?? '') . '</a>' : e($value),
        default => e($value),
    };
}

function ui_row_actions(array $actions): string
{
    $html = [];
    foreach ($actions as $action) {
        $html[] = ($action['method'] ?? 'get') === 'post'
            ? post_button(['label' => $action[0] ?? '', 'action' => $action[1] ?? '#', 'confirm' => $action['confirm'] ?? null])
            : button(['label' => $action[0] ?? '', 'url' => $action[1] ?? '#', 'size' => 'sm', 'outline' => true]);
    }
    return implode(' ', $html);
}
