<?php

declare(strict_types=1);

namespace App\Domain\Port;

use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItemCollection;

interface InvoiceRepository
{
    public function save(Customer $customer, InvoiceTotals $invoiceTotals, LineItemCollection $items): Invoice;
}
