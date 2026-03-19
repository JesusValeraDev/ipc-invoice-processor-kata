<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Invoice
{
    /**
     * @param list<LineItem> $items
     */
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public Customer $customer,
        public array $items,
        public InvoiceTotals $totals,
    ) {
    }
}
