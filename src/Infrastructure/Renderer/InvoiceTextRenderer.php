<?php

declare(strict_types=1);

namespace App\Infrastructure\Renderer;

use App\Domain\Model\Invoice;
use App\Domain\Port\InvoiceRendererStrategy;

final class InvoiceTextRenderer implements InvoiceRendererStrategy
{
    public function render(Invoice $invoice): string
    {
        $output = "INVOICE: $invoice->invoiceNumber\n";
        $output .= "========================\n";
        $output .= "Customer: " . $invoice->customer->name . "\n";
        $output .= "Email: " . $invoice->customer->email . "\n\n";
        $output .= "Items:\n";
        foreach ($invoice->items as $item) {
            $output .= "- " . $item->name . " x" . $item->quantity . " @ " . $item->unitPrice . " = " . $item->lineTotal() . " EUR\n";
        }
        $output .= "\nSubtotal: {$invoice->totals->subtotal} EUR\n";
        $output .= "Tax: {$invoice->totals->tax} EUR\n";
        if ($invoice->totals->shipping > 0) {
            $output .= "Shipping: {$invoice->totals->shipping} EUR\n";
        }
        $output .= "========================\n";
        $output .= "TOTAL: {$invoice->totals->total} EUR\n";

        return $output;
    }
}
