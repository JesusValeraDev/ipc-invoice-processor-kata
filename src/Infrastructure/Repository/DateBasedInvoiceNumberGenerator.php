<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

final class DateBasedInvoiceNumberGenerator
{
    public function generate(): string
    {
        return 'INV-' . date('Ymd') . '-' . random_int(1000, 9999);
    }
}
