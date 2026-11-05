<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use Lightpull\Response;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $countByStatus = fn (array $statuses) => (int) $this->db->value(
            'SELECT COUNT(*) FROM inquiries WHERE status IN (' . implode(', ', array_fill(0, count($statuses), '?')) . ')',
            $statuses,
        );

        return admin_dashboard([
            'new' => $countByStatus(['new']),
            'awaiting_quote' => $countByStatus(['new', 'contacted']),
            'in_production' => $countByStatus(['confirmed', 'in_production']),
            'active_products' => (int) $this->db->value('SELECT COUNT(*) FROM products WHERE is_active = 1'),
            'recent' => $this->db->all(InquiryController::LIST_SELECT . ' ORDER BY i.created_at DESC, i.id DESC LIMIT 10'),
        ]);
    }
}
