<?php

declare(strict_types=1);

namespace App\Controllers;

use Lightpull\Database;
use Lightpull\Response;

final class HomeController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function index(): Response
    {
        return page_home([
            'products' => $this->db->all(
                CatalogController::PRODUCT_SELECT . ' WHERE p.is_active = 1 ORDER BY p.created_at DESC, p.id DESC LIMIT 6'
            ),
            'categories' => $this->db->all(CatalogController::CATEGORY_SELECT),
        ]);
    }
}
