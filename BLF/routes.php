<?php

declare(strict_types=1);

use App\Controllers\Admin\CategoryController as AdminCategories;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\InquiryController as AdminInquiries;
use App\Controllers\Admin\ProductController as AdminProducts;
use App\Controllers\Admin\UserController as AdminUsers;
use App\Controllers\AuthController;
use App\Controllers\CatalogController;
use App\Controllers\HomeController;
use App\Controllers\InquiryController;
use App\Controllers\PageController;
use App\Controllers\TrackController;
use App\Middleware\Authenticate;
use App\Middleware\RequireAdmin;
use Lightpull\Router;

/** @var Lightpull\App $app */
$router = $app->router;

// Public site
$router->get('/', [HomeController::class, 'index']);
$router->get('/catalog', [CatalogController::class, 'index']);
$router->get('/catalog/{slug}', [CatalogController::class, 'category']);
$router->get('/product/{slug}', [CatalogController::class, 'show']);
$router->get('/inquire', [InquiryController::class, 'create']);
$router->get('/inquire/{slug}', [InquiryController::class, 'create']);
$router->post('/inquire', [InquiryController::class, 'store']);
$router->get('/inquiry/sent/{reference}', [InquiryController::class, 'sent']);
$router->get('/track', [TrackController::class, 'form']);
$router->post('/track', [TrackController::class, 'result']);
$router->get('/about', [PageController::class, 'about']);
$router->get('/contact', [PageController::class, 'contact']);

// Staff sign-in
$router->get('/admin/login', [AuthController::class, 'showLogin']);
$router->post('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/logout', [AuthController::class, 'logout']);

// Admin area: every route in this group passes the Authenticate middleware first.
$router->group([Authenticate::class], function (Router $router): void {
    $router->get('/admin', [DashboardController::class, 'index']);

    $router->get('/admin/products', [AdminProducts::class, 'index']);
    $router->get('/admin/products/create', [AdminProducts::class, 'create']);
    $router->post('/admin/products/create', [AdminProducts::class, 'store']);
    $router->get('/admin/products/{id}/edit', [AdminProducts::class, 'edit']);
    $router->post('/admin/products/{id}/edit', [AdminProducts::class, 'update']);
    $router->post('/admin/products/{id}/delete', [AdminProducts::class, 'delete']);

    $router->get('/admin/categories', [AdminCategories::class, 'index']);
    $router->post('/admin/categories', [AdminCategories::class, 'store']);
    $router->post('/admin/categories/{id}/delete', [AdminCategories::class, 'delete']);

    $router->get('/admin/inquiries', [AdminInquiries::class, 'index']);
    $router->get('/admin/inquiries/{id}', [AdminInquiries::class, 'show']);
    $router->post('/admin/inquiries/{id}', [AdminInquiries::class, 'update']);

    // Admin only: a nested group adds RequireAdmin after Authenticate.
    $router->group([RequireAdmin::class], function (Router $router): void {
        $router->get('/admin/users', [AdminUsers::class, 'index']);
        $router->get('/admin/users/create', [AdminUsers::class, 'create']);
        $router->post('/admin/users/create', [AdminUsers::class, 'store']);
        $router->post('/admin/users/{id}/delete', [AdminUsers::class, 'delete']);
    });
});
