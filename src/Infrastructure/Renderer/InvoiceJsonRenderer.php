<?php

declare(strict_types=1);

namespace App\Infrastructure\Renderer;

use App\Domain\Model\Invoice;

final class InvoiceJsonRenderer
{
    public function render(Invoice $invoice): string
    {
        return (string) json_encode([
            'invoice_number' => $invoice->invoiceNumber,
            'customer' => $invoice->customer,
            'items' => $invoice->items,
            'subtotal' => $invoice->totals->subtotal,
            'tax' => $invoice->totals->tax,
            'shipping' => $invoice->totals->shipping,
            'total' => $invoice->totals->total
        ]);
    }
}
