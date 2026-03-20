<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use App\Domain\Service\InvoiceTotalsCalculator;
use PHPUnit\Framework\TestCase;

final class InvoiceTotalsCalculatorTest extends TestCase
{
    private InvoiceTotalsCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new InvoiceTotalsCalculator();
    }

    public function test_calculates_subtotal_from_line_items(): void
    {
        $items = new LineItemCollection([
            new LineItem('Widget', 2, 10.00),
            new LineItem('Gadget', 1, 25.00),
        ]);

        $totals = $this->calculator->calculate($items);

        $this->assertEquals(45.00, $totals->subtotal);
    }

    public function test_applies_21_percent_tax(): void
    {
        $items = new LineItemCollection([
            new LineItem('Product', 1, 100.00),
        ]);

        $totals = $this->calculator->calculate($items);

        $this->assertEquals(21.00, $totals->tax);
    }

    public function test_free_shipping_over_50_euros(): void
    {
        $items = new LineItemCollection([
            new LineItem('Expensive Item', 1, 60.00),
        ]);

        $totals = $this->calculator->calculate($items);

        $this->assertEquals(0.00, $totals->shipping);
    }

    public function test_shipping_495_for_small_orders(): void
    {
        $items = new LineItemCollection([
            new LineItem('Small Item', 1, 10.00),
        ]);

        $totals = $this->calculator->calculate($items);

        $this->assertEquals(4.95, $totals->shipping);
    }

    public function test_calculates_complete_total(): void
    {
        $items = new LineItemCollection([
            new LineItem('Widget', 2, 10.00),  // 20.00
            new LineItem('Gadget', 1, 25.00),  // 25.00
        ]);                                    // subtotal: 45.00

        $totals = $this->calculator->calculate($items);

        // subtotal: 45.00
        // tax (21%): 9.45
        // shipping (3 items): 6.95
        // total: 61.40
        $this->assertEquals(45.00, $totals->subtotal);
        $this->assertEquals(9.45, $totals->tax);
        $this->assertEquals(6.95, $totals->shipping);
        $this->assertEquals(61.40, $totals->total);
    }
}
