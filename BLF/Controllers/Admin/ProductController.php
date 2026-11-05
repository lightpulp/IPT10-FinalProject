<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\CatalogController;
use finfo;
use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Response;

final class ProductController extends AdminController
{
    private const PER_PAGE = 20;
    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(): Response
    {
        $category = $this->request->string('category');
        $category = ctype_digit($category) ? $category : '';
        $q = mb_substr($this->request->string('q'), 0, 100);

        $where = ['1 = 1'];
        $params = [];
        if ($category !== '') {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $category;
        }
        if ($q !== '') {
            $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
            array_push($params, Database::like($q), Database::like($q));
        }
        $sql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->value('SELECT COUNT(*) FROM products p' . $sql, $params);
        $paging = paginate($total, self::PER_PAGE, $this->request->input('page'));

        return admin_products([
            'products' => $this->db->all(
                CatalogController::PRODUCT_SELECT . $sql . ' ORDER BY p.created_at DESC, p.id DESC LIMIT ? OFFSET ?',
                [...$params, $paging['limit'], $paging['offset']],
            ),
            'categories' => $this->categories(),
            'category' => $category,
            'q' => $q,
            'total' => $total,
            'page' => $paging['page'],
            'pages' => $paging['pages'],
        ]);
    }

    public function create(): Response
    {
        return $this->form(null, ['is_active' => 1], []);
    }

    public function store(): Response
    {
        [$values, $errors] = $this->validate();
        $slug = $this->uniqueSlug($values['name']);
        $image = null;
        if ($errors === []) {
            [$image, $imageError] = $this->storeUpload($slug);
            $errors = $imageError === null ? [] : ['image' => $imageError];
        }
        if ($errors !== []) {
            return $this->form(null, $values, $errors);
        }

        $this->db->insert('products', $this->row($values) + ['slug' => $slug, 'image' => $image]);
        $this->session->flash('success', 'Product saved.');
        return redirect('/admin/products');
    }

    public function edit(int $id): Response
    {
        $product = $this->find($id);
        return $this->form($product, $product, []);
    }

    public function update(int $id): Response
    {
        $product = $this->find($id);
        [$values, $errors] = $this->validate();
        $image = null;
        if ($errors === []) {
            // The slug is kept on edit so existing links and bookmarks keep working.
            [$image, $imageError] = $this->storeUpload($product['slug']);
            $errors = $imageError === null ? [] : ['image' => $imageError];
        }
        if ($errors !== []) {
            return $this->form($product, $values, $errors);
        }

        $row = $this->row($values);
        if ($image !== null) {
            $row['image'] = $image;
        }
        $this->db->update('products', $id, $row);
        if ($image !== null) {
            $this->deleteImage($product['image']);
        }
        $this->session->flash('success', 'Product saved.');
        return redirect('/admin/products');
    }

    public function delete(int $id): Response
    {
        $product = $this->find($id);
        $linked = (int) $this->db->value('SELECT COUNT(*) FROM inquiries WHERE product_id = ?', [$id]);
        $this->db->delete('products', $id);
        $this->deleteImage($product['image']);

        $this->session->flash('success', $linked === 0
            ? 'Product deleted.'
            : "Product deleted. {$linked} inquiries that mentioned it are kept and now show no product.");
        return redirect('/admin/products');
    }

    private function find(int $id): array
    {
        return $this->db->one('SELECT * FROM products WHERE id = ?', [$id]) ?? throw new HttpException(404);
    }

    private function categories(): array
    {
        $options = [];
        foreach ($this->db->all('SELECT id, name FROM categories ORDER BY name') as $category) {
            $options[$category['id']] = $category['name'];
        }
        return $options;
    }

    private function form(?array $product, array $values, array $errors): Response
    {
        return admin_product_form([
            'product' => $product,
            'values' => $values,
            'errors' => $errors,
            'categories' => $this->categories(),
        ]);
    }

    /** @return array{0: array, 1: array} the submitted values (as typed) and any errors */
    private function validate(): array
    {
        $r = $this->request;
        $values = [
            'name' => $r->string('name'),
            'category_id' => $r->string('category_id'),
            'description' => $r->string('description'),
            'wood_type' => $r->string('wood_type'),
            'dimensions' => $r->string('dimensions'),
            'price' => str_replace(',', '', $r->string('price')),
            'in_stock' => $r->has('in_stock') ? 1 : 0,
            'is_active' => $r->has('is_active') ? 1 : 0,
        ];

        $errors = [];
        $nameLength = mb_strlen($values['name']);
        if ($nameLength < 2 || $nameLength > 150) {
            $errors['name'] = 'Enter a name of 2 to 150 characters.';
        }
        $categoryExists = ctype_digit($values['category_id'])
            && $this->db->value('SELECT COUNT(*) FROM categories WHERE id = ?', [(int) $values['category_id']]) > 0;
        if (!$categoryExists) {
            $errors['category_id'] = 'Choose a category.';
        }
        if (mb_strlen($values['description']) > 5000) {
            $errors['description'] = 'Keep the description under 5,000 characters.';
        }
        if (mb_strlen($values['wood_type']) > 50) {
            $errors['wood_type'] = 'Keep this under 50 characters.';
        }
        if (mb_strlen($values['dimensions']) > 100) {
            $errors['dimensions'] = 'Keep this under 100 characters.';
        }
        if ($values['price'] !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $values['price'])) {
            $errors['price'] = 'Enter an amount like 38000 or 38000.50, or leave it blank.';
        }
        return [$values, $errors];
    }

    /** Field-by-field mapping from validated form values to columns; never the raw request. */
    private function row(array $values): array
    {
        return [
            'name' => $values['name'],
            'category_id' => (int) $values['category_id'],
            'description' => $values['description'] !== '' ? $values['description'] : null,
            'wood_type' => $values['wood_type'] !== '' ? $values['wood_type'] : null,
            'dimensions' => $values['dimensions'] !== '' ? $values['dimensions'] : null,
            'price' => $values['price'] !== '' ? $values['price'] : null,
            'in_stock' => $values['in_stock'],
            'is_active' => $values['is_active'],
        ];
    }

    private function uniqueSlug(string $name): string
    {
        $base = substr(slugify($name), 0, 140) ?: 'product';
        $slug = $base;
        for ($n = 2; $this->db->value('SELECT COUNT(*) FROM products WHERE slug = ?', [$slug]) > 0; $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    /**
     * Validates and stores the uploaded image. The type is read from the file's bytes,
     * never from the extension or the browser-supplied type, and we choose the filename.
     *
     * @return array{0: ?string, 1: ?string} [stored filename or null, error message or null]
     */
    private function storeUpload(string $slug): array
    {
        $file = $this->request->file('image');
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (!is_int($file['error'])) {
            return [null, 'Upload a single image.'];
        }
        $maxBytes = (int) config('uploads.max_bytes', 2 * 1024 * 1024);
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $file['size'] > $maxBytes) {
            return [null, 'Images must be 2 MB or smaller.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            return [null, 'The upload did not complete. Please try again.'];
        }

        $extension = self::IMAGE_TYPES[(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name'])] ?? null;
        if ($extension === null || @getimagesize($file['tmp_name']) === false) {
            return [null, 'Upload a JPG, PNG, or WebP image.'];
        }

        $dir = realpath((string) config('uploads.dir'));
        $name = substr($slug, 0, 100) . '-' . uniqid() . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if ($dir === false || dirname($target) !== $dir || !move_uploaded_file($file['tmp_name'], $target)) {
            return [null, 'The image could not be saved. Check that the uploads folder is writable.'];
        }
        return [$name, null];
    }

    private function deleteImage(?string $name): void
    {
        $dir = realpath((string) config('uploads.dir'));
        if ($dir === false || $name === null || $name === '') {
            return;
        }
        $path = realpath($dir . DIRECTORY_SEPARATOR . basename($name));
        if ($path !== false && str_starts_with($path, $dir . DIRECTORY_SEPARATOR) && is_file($path)) {
            unlink($path);
        }
    }
}
