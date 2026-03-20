<?php

declare(strict_types=1);

namespace App\Infrastructure\Renderer;

use App\Domain\Model\Invoice;
use App\Domain\Port\InvoiceRendererStrategy;

final class InvoiceHtmlRenderer implements InvoiceRendererStrategy
{
    public function render(Invoice $invoice): string
    {
        $html = '<div class="invoice">';

        $html .= '<h1>Invoice ' . $invoice->invoiceNumber . '</h1>';
        $html .= '<div class="customer">';
        $html .= '<p>' . htmlspecialchars($invoice->customer->name) . '</p>';
        $html .= '<p>' . htmlspecialchars($invoice->customer->email) . '</p>';
        if (isset($invoice->customer->address)) {
            $html .= '<p>' . nl2br(htmlspecialchars($invoice->customer->address->formatted())) . '</p>';
        }
        $html .= '</div>';

        $html .= '<table class="items">';
        $html .= '<tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>';
        foreach ($invoice->items as $item) {
            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($item->name) . '</td>';
            $html .= '<td>' . $item->quantity . '</td>';
            $html .= '<td>' . number_format($item->unitPrice, 2) . ' EUR</td>';
            $html .= '<td>' . number_format($item->lineTotal(), 2) . ' EUR</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<div class="totals">';
        $html .= '<p>Subtotal: ' . number_format($invoice->totals->subtotal, 2) . ' EUR</p>';
        $html .= '<p>Tax (21%): ' . number_format($invoice->totals->tax, 2) . ' EUR</p>';
        if ($invoice->totals->shipping > 0) {
            $html .= '<p>Shipping: ' . number_format($invoice->totals->shipping, 2) . ' EUR</p>';
        }
        $html .= '<p class="total"><strong>Total: ' . number_format($invoice->totals->total, 2) . ' EUR</strong></p>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}
