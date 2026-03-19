<?php

declare(strict_types=1);

namespace App\Domain\Model;

final class InvoiceTotals
{
    public function __construct(
        public float $subtotal,
        public float $tax,
        public float $shipping,
        public float $total,
        public int $itemCount,
    ) {
    }
}
