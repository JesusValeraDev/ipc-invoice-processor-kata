<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Model\Customer;
use App\Domain\Model\LineItem;

final class InvoiceRequest
{
    /**
     * @param list<LineItem> $items
     */
    public function __construct(
        public Customer $customer,
        public array $items,
    ) {}
}
