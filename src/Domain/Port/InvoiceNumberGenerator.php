<?php

declare(strict_types=1);

namespace App\Domain\Port;

interface InvoiceNumberGenerator
{
    public function generate(): string;
}
