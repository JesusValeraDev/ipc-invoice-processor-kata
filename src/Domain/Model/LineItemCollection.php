<?php

declare(strict_types=1);

namespace App\Domain\Model;

use ArrayIterator;
use Countable;
use IteratorAggregate;

final class LineItemCollection implements Countable, IteratorAggregate
{
    /**
     * @var list<LineItem> $items
     */
    private array $items;

    public function __construct(array $items = [])
    {
        $this->items = [];
        foreach ($items as $item) {
            $this->add($item);
        }
    }

    public function add(LineItem $item): void
    {
        $this->items[] = $item;
    }

    public function subtotal(): float
    {
        return array_reduce(
            $this->items,
            fn(float $sum, LineItem $item) => $sum + $item->lineTotal(),
            0.0
        );
    }

    public function totalQuantity(): int
    {
        return array_reduce(
            $this->items,
            fn(int $count, LineItem $item) => $count + $item->quantity,
            0
        );
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    /**
     * @return ArrayIterator<LineItem>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->items);
    }
}
