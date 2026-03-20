<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Model;

use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use PHPUnit\Framework\TestCase;

final class LineItemCollectionTest extends TestCase
{
    public function test_calculates_subtotal(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 2, 10.00),
            new LineItem('B', 1, 25.00),
        ]);

        $this->assertEquals(45.00, $collection->subtotal());
    }

    public function test_calculates_total_quantity(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 2, 10.00),
            new LineItem('B', 3, 5.00),
        ]);

        $this->assertEquals(5, $collection->totalQuantity());
    }

    public function test_counts_items(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 1, 10.00),
            new LineItem('B', 1, 5.00),
        ]);

        $this->assertCount(2, $collection);
    }

    public function test_empty_collection(): void
    {
        $collection = new LineItemCollection([]);

        $this->assertTrue($collection->isEmpty());
        $this->assertCount(0, $collection);
        $this->assertEquals(0.00, $collection->subtotal());
        $this->assertEquals(0, $collection->totalQuantity());
    }

    public function test_add_item(): void
    {
        $collection = new LineItemCollection([]);
        $collection->add(new LineItem('A', 1, 10.00));

        $this->assertFalse($collection->isEmpty());
        $this->assertCount(1, $collection);
    }

    public function test_is_iterable(): void
    {
        $collection = new LineItemCollection([
            new LineItem('A', 1, 10.00),
            new LineItem('B', 1, 20.00),
        ]);

        $names = [];
        foreach ($collection as $item) {
            $names[] = $item->name;
        }

        $this->assertEquals(['A', 'B'], $names);
    }
}
