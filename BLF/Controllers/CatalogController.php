<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExchangeRates;
use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Request;
use Lightpull\Response;

final class CatalogController
{
    public const PRODUCT_SELECT = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug'
        . ' FROM products p JOIN categories c ON c.id = p.category_id';

    /** Categories with a count of their active products, for the public site. */
    public const CATEGORY_SELECT = 'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count'
        . ' FROM categories c LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1'
        . ' GROUP BY c.id, c.name, c.slug ORDER BY c.name';

    private const PER_PAGE = 12;

    public function __construct(
        private readonly Database $db,
        private readonly Request $request,
        private readonly ExchangeRates $exchangeRates,
    ) {
    }

    public function index(): Response
    {
        return $this->listing(null);
    }

    public function category(string $slug): Response
    {
        $category = $this->db->one('SELECT id, name, slug FROM categories WHERE slug = ?', [$slug]);
        return $this->listing($category ?? throw new HttpException(404));
    }

    public function show(string $slug): Response
    {
        $product = $this->db->one(self::PRODUCT_SELECT . ' WHERE p.slug = ? AND p.is_active = 1', [$slug])
            ?? throw new HttpException(404);
        $related = $this->db->all(
            self::PRODUCT_SELECT . ' WHERE p.category_id = ? AND p.id <> ? AND p.is_active = 1 ORDER BY p.created_at DESC LIMIT 4',
            [$product['category_id'], $product['id']],
        );
        // Only priced products need rates, so made-to-order pages never wait on the API.
        $rates = $product['price'] === null ? null : $this->exchangeRates->latest();
        return page_product(['product' => $product, 'related' => $related, 'rates' => $rates]);
    }

    private function listing(?array $category): Response
    {
        $q = mb_substr($this->request->string('q'), 0, 100);
        $where = ['p.is_active = 1'];
        $params = [];
        if ($category !== null) {
            $where[] = 'p.category_id = ?';
            $params[] = $category['id'];
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            array_push($params, Database::like($q), Database::like($q));
        }
        $sql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->value('SELECT COUNT(*) FROM products p' . $sql, $params);
        $paging = paginate($total, self::PER_PAGE, $this->request->input('page'));
        $products = $this->db->all(
            self::PRODUCT_SELECT . $sql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?',
            [...$params, $paging['limit'], $paging['offset']],
        );

        return page_catalog([
            'category' => $category,
            'categories' => $this->db->all(CatalogController::CATEGORY_SELECT),
            'products' => $products,
            'total' => $total,
            'q' => $q,
            'page' => $paging['page'],
            'pages' => $paging['pages'],
        ]);
    }
}
