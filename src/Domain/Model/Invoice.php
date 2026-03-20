<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class Invoice
{
    public function __construct(
        public int $invoiceId,
        public string $invoiceNumber,
        public Customer $customer,
        public LineItemCollection $items,
        public InvoiceTotals $totals,
    ) {
    }
}
