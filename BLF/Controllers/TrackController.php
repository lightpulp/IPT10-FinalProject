<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExchangeRates;
use Lightpull\Database;
use Lightpull\Request;
use Lightpull\Response;

final class TrackController
{
    public function __construct(
        private readonly Database $db,
        private readonly Request $request,
        private readonly ExchangeRates $exchangeRates,
    ) {
    }

    public function form(): Response
    {
        return page_track(['values' => ['reference' => '', 'phone' => ''], 'result' => null, 'not_found' => false, 'rates' => null]);
    }

    /**
     * The reference number and the phone number must both match, so nobody can read
     * another customer's request by guessing references. Internal notes are never selected.
     */
    public function result(): Response
    {
        $reference = strtoupper($this->request->string('reference'));
        $phone = $this->request->string('phone');

        $row = $reference === '' ? null : $this->db->one(
            'SELECT i.reference_no, i.phone, i.status, i.quoted_amount, i.created_at, p.name AS product_name'
            . ' FROM inquiries i LEFT JOIN products p ON p.id = i.product_id WHERE i.reference_no = ?',
            [$reference],
        );
        $matches = $row !== null && $this->digits($phone) !== '' && hash_equals($this->digits($row['phone']), $this->digits($phone));

        return page_track([
            'values' => ['reference' => $reference, 'phone' => $phone],
            'result' => $matches ? $row : null,
            'not_found' => !$matches,
            'rates' => $matches && $row['quoted_amount'] !== null ? $this->exchangeRates->latest() : null,
        ]);
    }

    /** "0917 555 0101" and "0917-555-0101" are the same number. */
    private function digits(string $phone): string
    {
        return (string) preg_replace('/\D+/', '', $phone);
    }
}
