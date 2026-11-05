<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\Database;
use Lightpull\HttpException;
use Lightpull\Response;

final class InquiryController extends AdminController
{
    public const LIST_SELECT = 'SELECT i.id, i.reference_no, i.customer_name, i.phone, i.status, i.created_at, p.name AS product_name'
        . ' FROM inquiries i LEFT JOIN products p ON p.id = i.product_id';

    private const PER_PAGE = 20;

    public function index(): Response
    {
        // ?status= takes one status or a comma-separated list (the dashboard links use lists).
        $selected = array_values(array_intersect(
            explode(',', $this->request->string('status')),
            array_keys(config('statuses')),
        ));
        $q = mb_substr($this->request->string('q'), 0, 100);

        $where = ['1 = 1'];
        $params = [];
        if ($selected !== []) {
            $where[] = 'i.status IN (' . implode(', ', array_fill(0, count($selected), '?')) . ')';
            array_push($params, ...$selected);
        }
        if ($q !== '') {
            $where[] = '(i.reference_no LIKE ? OR i.customer_name LIKE ? OR i.phone LIKE ?)';
            array_push($params, Database::like($q), Database::like($q), Database::like($q));
        }
        $sql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->value('SELECT COUNT(*) FROM inquiries i' . $sql, $params);
        $paging = paginate($total, self::PER_PAGE, $this->request->input('page'));

        return admin_inquiries([
            'inquiries' => $this->db->all(
                self::LIST_SELECT . $sql . ' ORDER BY i.created_at DESC, i.id DESC LIMIT ? OFFSET ?',
                [...$params, $paging['limit'], $paging['offset']],
            ),
            'selected' => $selected,
            'status' => implode(',', $selected),
            'q' => $q,
            'total' => $total,
            'page' => $paging['page'],
            'pages' => $paging['pages'],
        ]);
    }

    public function show(int $id): Response
    {
        $inquiry = $this->find($id);
        return admin_inquiry([
            'inquiry' => $inquiry,
            'values' => [
                'status' => $inquiry['status'],
                'quoted_amount' => $inquiry['quoted_amount'] ?? '',
                'notes' => $inquiry['notes'] ?? '',
            ],
            'errors' => [],
        ]);
    }

    /** Any status may follow any other: a single phone call can skip several steps. */
    public function update(int $id): Response
    {
        $inquiry = $this->find($id);
        $values = [
            'status' => $this->request->string('status'),
            'quoted_amount' => str_replace(',', '', $this->request->string('quoted_amount')),
            'notes' => $this->request->string('notes'),
        ];

        $errors = [];
        if (!array_key_exists($values['status'], config('statuses'))) {
            $errors['status'] = 'Choose a status from the list.';
        }
        if ($values['quoted_amount'] !== '' && !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $values['quoted_amount'])) {
            $errors['quoted_amount'] = 'Enter an amount like 38000 or 38000.50, or leave it blank.';
        }
        if (mb_strlen($values['notes']) > 5000) {
            $errors['notes'] = 'Keep notes under 5,000 characters.';
        }
        if ($errors !== []) {
            return admin_inquiry(['inquiry' => $inquiry, 'values' => $values, 'errors' => $errors]);
        }

        $this->db->update('inquiries', $id, [
            'status' => $values['status'],
            'quoted_amount' => $values['quoted_amount'] !== '' ? $values['quoted_amount'] : null,
            'notes' => $values['notes'] !== '' ? $values['notes'] : null,
        ]);

        $this->session->flash('success', 'Inquiry updated');
        if ($values['status'] === 'quoted' && $values['quoted_amount'] === '') {
            $this->session->flash('warning', 'Saved, but the status is Quoted with no quoted amount. Add the amount so the customer can see it.');
        }
        return redirect('/admin/inquiries/' . $id);
    }

    private function find(int $id): array
    {
        return $this->db->one(
            'SELECT i.*, p.name AS product_name, p.price AS product_price FROM inquiries i'
            . ' LEFT JOIN products p ON p.id = i.product_id WHERE i.id = ?',
            [$id],
        ) ?? throw new HttpException(404);
    }
}
