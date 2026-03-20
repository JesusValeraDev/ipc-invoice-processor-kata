<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;

final class InvoiceTotalsCalculator
{
    const float TAX_RATE = 0.21;

    const int FREE_SHIPPING_THRESHOLD = 50;

    const float SHIPPING_SMALL = 4.95;
    const float SHIPPING_MEDIUM = 6.95;
    const float SHIPPING_LARGE = 9.95;

    /**
     * @param list<LineItem> $items
     */
    public function calculate(array $items): InvoiceTotals
    {
        $subtotal = $this->calculateSubtotal($items);
        $itemCount = $this->totalQuantity($items);
        $tax = $this->calculateTax($subtotal);
        $shipping = $this->calculateShipping($subtotal, $itemCount);

        $total = $subtotal + $tax + $shipping;

        return new InvoiceTotals(
            subtotal: round($subtotal, 2),
            tax: round($tax, 2),
            shipping: round($shipping, 2),
            total: round($total, 2),
            itemCount: $itemCount,
        );
    }

    /**
     * @param list<LineItem> $items
     */
    private function calculateSubtotal(array $items): float
    {
        return array_reduce(
            array: $items,
            callback: fn(float $sum, LineItem $item) => $sum + $item->lineTotal(),
            initial: 0.0,
        );
    }

    /**
     * @param list<LineItem> $items
     */
    private function totalQuantity(array $items): int
    {
        return array_reduce(
            array: $items,
            callback: fn(int $count, LineItem $item) => $count + $item->quantity,
            initial: 0,
        );
    }

    private function calculateTax(float $subtotal): float
    {
        return $subtotal * self::TAX_RATE;
    }

    private function calculateShipping(float $subtotal, int $itemCount): float
    {
        if ($subtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0.0;
        }

        return match (true) {
            $itemCount <= 2 => self::SHIPPING_SMALL,
            $itemCount <= 5 => self::SHIPPING_MEDIUM,
            default => self::SHIPPING_LARGE,
        };
    }
}
