<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Model\Customer;
use App\Domain\Model\LineItemCollection;

final class InvoiceRequest
{
    public function __construct(
        public Customer $customer,
        public LineItemCollection $items,
    ) {}
}
