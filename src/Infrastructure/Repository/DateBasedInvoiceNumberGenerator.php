<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Port\InvoiceNumberGenerator;

final class DateBasedInvoiceNumberGenerator implements InvoiceNumberGenerator
{
    public function generate(): string
    {
        return 'INV-' . date('Ymd') . '-' . random_int(1000, 9999);
    }
}
