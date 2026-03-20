<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\LineItem;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class LineItemTest extends TestCase
{
    public function test_calculates_line_total(): void
    {
        $item = new LineItem('Widget', 3, 10.50);

        $this->assertEquals(31.50, $item->lineTotal());
    }

    public function test_line_total_with_single_quantity(): void
    {
        $item = new LineItem('Widget', 1, 25.99);

        $this->assertEquals(25.99, $item->lineTotal());
    }

    public function test_throws_when_quantity_is_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        new LineItem('Widget', 0, 10.00);
    }

    public function test_throws_when_quantity_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be at least 1');

        new LineItem('Widget', -1, 10.00);
    }

    public function test_throws_when_price_is_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Price cannot be negative');

        new LineItem('Widget', 1, -5.00);
    }

    public function test_allows_zero_price(): void
    {
        $item = new LineItem('Free Sample', 1, 0.00);

        $this->assertEquals(0.00, $item->lineTotal());
    }
}
