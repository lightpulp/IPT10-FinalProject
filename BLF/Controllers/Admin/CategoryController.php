<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\HttpException;
use Lightpull\Response;

final class CategoryController extends AdminController
{
    public function index(): Response
    {
        return $this->page([], []);
    }

    public function store(): Response
    {
        $name = $this->request->string('name');
        $slug = slugify($name);
        $error = null;
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || $slug === '') {
            $error = 'Enter a name of 2 to 100 characters that includes letters or numbers.';
        } elseif ($this->db->value('SELECT COUNT(*) FROM categories WHERE slug = ?', [$slug]) > 0) {
            $error = 'A category with that name already exists.';
        }
        if ($error !== null) {
            return $this->page(['name' => $name], ['name' => $error]);
        }

        $this->db->insert('categories', ['name' => $name, 'slug' => $slug]);
        $this->session->flash('success', 'Category added.');
        return redirect('/admin/categories');
    }

    public function delete(int $id): Response
    {
        $category = $this->db->one('SELECT id, name FROM categories WHERE id = ?', [$id]) ?? throw new HttpException(404);
        $products = (int) $this->db->value('SELECT COUNT(*) FROM products WHERE category_id = ?', [$id]);
        if ($products > 0) {
            $this->session->flash('error', sprintf(
                '"%s" still has %d product%s. Move or delete those products first, then delete the category.',
                $category['name'],
                $products,
                $products === 1 ? '' : 's',
            ));
            return redirect('/admin/categories');
        }

        $this->db->delete('categories', $id);
        $this->session->flash('success', 'Category deleted.');
        return redirect('/admin/categories');
    }

    private function page(array $values, array $errors): Response
    {
        return admin_categories([
            'categories' => $this->db->all(
                'SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count FROM categories c'
                . ' LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name, c.slug ORDER BY c.name'
            ),
            'values' => $values,
            'errors' => $errors,
        ]);
    }
}
