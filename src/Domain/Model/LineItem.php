<?php

declare(strict_types=1);

namespace App\Domain\Model;

use InvalidArgumentException;

final class LineItem
{
    public function __construct(
        public string $name,
        public int $quantity,
        public float $unitPrice,
    ) {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1');
        }
        if ($unitPrice < 0) {
            throw new InvalidArgumentException('Price cannot be negative');
        }
    }

    public function lineTotal(): float
    {
        return $this->quantity * $this->unitPrice;
    }
}
