<?php

declare(strict_types=1);

use Lightpull\Middleware\LogRequests;
use Lightpull\Middleware\VerifyCsrfToken;

return [
    'app' => [
        'name' => 'Betis Legacy Furniture',
        // Base URL, e.g. http://localhost/IPT10/BLF/public. Blank = detect from where index.php lives.
        'url' => env('APP_URL', ''),
    ],
    'debug' => env('APP_DEBUG', 'false') === 'true',
    'timezone' => 'Asia/Manila',

    // Global middleware, outermost first. Route middleware (login, admin role) is in routes.php.
    'middleware' => [
        LogRequests::class,
        VerifyCsrfToken::class,
    ],

    'log' => [
        'file' => __DIR__ . '/storage/logs/requests.log',
    ],

    'cache' => [
        'dir' => __DIR__ . '/storage/cache',
    ],

    'http' => [
        'timeout' => 5,
    ],

    // ExchangeRate-API (https://www.exchangerate-api.com). With no key, the free open-access
    // endpoint is used; with EXCHANGE_RATE_API_KEY set, the authenticated v6 endpoint is used.
    'exchange_rates' => [
        'api_key' => env('EXCHANGE_RATE_API_KEY', ''),
        'currencies' => ['USD', 'AED', 'SAR', 'SGD', 'CAD', 'AUD'],
        'max_age' => 12 * 3600,       // refresh cached rates twice a day
        'stale_max_age' => 7 * 86400, // if the API is down, keep showing rates up to a week old
        'retry_after' => 10 * 60,     // after a failed call, wait before calling again
    ],

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
