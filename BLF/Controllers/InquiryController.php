<?php

declare(strict_types=1);

namespace App\Controllers;

use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Request;
use Lightpull\Response;
use Lightpull\Session;
use PDOException;

final class InquiryController
{
    public function __construct(
        private readonly Database $db,
        private readonly Request $request,
        private readonly Session $session,
    ) {
    }

    public function create(?string $slug = null): Response
    {
        $product = null;
        if ($slug !== null) {
            $product = $this->db->one('SELECT id, name, slug FROM products WHERE slug = ? AND is_active = 1', [$slug])
                ?? throw new HttpException(404);
        }
        return $this->form($product, [], []);
    }

    public function store(): Response
    {
        // Honeypot: people never see this field, bots fill it. Drop the submission without a word.
        if ($this->request->string('website') !== '') {
            return redirect('/');
        }

        $values = [
            'customer_name' => $this->request->string('customer_name'),
            'phone' => $this->request->string('phone'),
            'email' => $this->request->string('email'),
            'product_id' => $this->request->string('product_id'),
            'message' => $this->request->string('message'),
        ];
        $errors = $this->validate($values);

        $product = null;
        if ($values['product_id'] !== '') {
            $product = ctype_digit($values['product_id'])
                ? $this->db->one('SELECT id, name, slug FROM products WHERE id = ? AND is_active = 1', [(int) $values['product_id']])
                : null;
            if ($product === null) {
                $errors['product_id'] = 'Please choose a product from the list, or General inquiry.';
            }
        }

        if ($errors !== []) {
            $locked = $this->request->string('lock_product') === '1' ? $product : null;
            return $this->form($locked, $values, $errors);
        }

        $reference = $this->save($values, $product);
        $this->session->set('last_reference', $reference);
        return redirect('/inquiry/sent/' . $reference);
    }

    public function sent(string $reference): Response
    {
        // Only the browser that submitted the request sees this page.
        if ($this->session->get('last_reference') !== $reference) {
            throw new HttpException(404);
        }
        return page_inquiry_sent(['reference' => $reference]);
    }

    private function form(?array $product, array $values, array $errors): Response
    {
        $products = [];
        foreach ($this->db->all('SELECT id, name FROM products WHERE is_active = 1 ORDER BY name') as $row) {
            $products[$row['id']] = $row['name'];
        }
        return page_inquiry_form([
            'product' => $product,
            'products' => $products,
            'values' => $values,
            'errors' => $errors,
        ]);
    }

    private function validate(array $v): array
    {
        $errors = [];
        $nameLength = mb_strlen($v['customer_name']);
        if ($nameLength < 2 || $nameLength > 100) {
            $errors['customer_name'] = 'Enter your full name (2 to 100 characters).';
        }
        if (!preg_match('/^[0-9+\-() ]{7,30}$/', $v['phone']) || !preg_match('/\d{3}/', $v['phone'])) {
            $errors['phone'] = 'Enter a phone number of 7 to 30 characters using digits, spaces, and + - ( ).';
        }
        if ($v['email'] !== '' && (mb_strlen($v['email']) > 150 || filter_var($v['email'], FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = 'Enter a valid email address, or leave it blank.';
        }
        $messageLength = mb_strlen($v['message']);
        if ($messageLength < 10 || $messageLength > 2000) {
            $errors['message'] = 'Tell us a little more (10 to 2,000 characters).';
        }
        return $errors;
    }

    /**
     * Reference numbers are BLF-YYYY-NNNN, restarting each year. The UNIQUE index on
     * reference_no catches the rare race between two submissions; we retry once.
     */
    private function save(array $v, ?array $product): string
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->db->transaction(function (Database $db) use ($v, $product): string {
                    $year = (int) date('Y');
                    $count = (int) $db->value('SELECT COUNT(*) FROM inquiries WHERE YEAR(created_at) = ?', [$year]);
                    $reference = sprintf('BLF-%d-%04d', $year, $count + 1);
                    $db->insert('inquiries', [
                        'reference_no' => $reference,
                        'product_id' => $product['id'] ?? null,
                        'customer_name' => $v['customer_name'],
                        'phone' => $v['phone'],
                        'email' => $v['email'] !== '' ? $v['email'] : null,
                        'message' => $v['message'],
                        'status' => 'new',
                    ]);
                    return $reference;
                });
            } catch (PDOException $e) {
                if ($attempt >= 2 || !Database::isDuplicateKey($e)) {
                    throw $e;
                }
            }
        }
    }
}
