<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Betis Legacy Furniture',
    ],
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'timezone' => 'Asia/Manila',

    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'blf'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
    ],

    // Function in pages.php that renders 403/404/419/500 pages.
    'error_view' => 'page_error',

    'money' => [
        'symbol' => '₱',
        'null_text' => 'Price upon request',
    ],

    'uploads' => [
        'dir' => __DIR__ . '/public/uploads',
        'max_bytes' => 2 * 1024 * 1024,
    ],

    // The inquiry lifecycle. A general inquiry, a quote request and the resulting
    // order are one record moving through these statuses, in this order.
    'statuses' => [
        'new' => ['label' => 'New', 'color' => 'secondary'],
        'contacted' => ['label' => 'Contacted', 'color' => 'info'],
        'quoted' => ['label' => 'Quoted', 'color' => 'primary'],
        'confirmed' => ['label' => 'Confirmed', 'color' => 'warning'],
        'in_production' => ['label' => 'In production', 'color' => 'warning'],
        'ready' => ['label' => 'Ready for pickup', 'color' => 'success'],
        'completed' => ['label' => 'Completed', 'color' => 'success'],
        'cancelled' => ['label' => 'Cancelled', 'color' => 'danger'],
    ],
];
