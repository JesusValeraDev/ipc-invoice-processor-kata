<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\DTO\InvoiceRequest;
use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Service\InvoiceTotalsCalculator;
use InvalidArgumentException;

final class InvoiceProcessor
{
    /**
     * @return array{success: true, invoice_number: string, invoice_id: int, total: float, output: string}
     */
    public function processInvoice(InvoiceRequest $invoiceData, \mysqli $conn, string $format = 'html'): array
    {
        $customer = $invoiceData->customer;
        $items = $invoiceData->items;

        $this->validate($customer, $items);

        $invoiceTotals = new InvoiceTotalsCalculator()->calculate($items);

        $invoice = $this->save($conn, $customer, $invoiceTotals, $items);

        $output = $this->render($format, $invoice);

        return [
            'success' => true,
            'invoice_number' => $invoice->invoiceNumber,
            'invoice_id' => $invoice->invoiceId,
            'total' => $invoiceTotals->total,
            'output' => $output,
        ];
    }

    /**
     * @param list<LineItem> $items
     */
    private function validate(Customer $customer, array $items): void
    {
        if (!isset($customer->email) || !filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }

        if ($items == null || count($items) == 0) {
            throw new InvalidArgumentException('No items');
        }
    }

    /**
     * @param list<LineItem> $items
     */
    private function save(
        \mysqli $conn,
        Customer $customer,
        InvoiceTotals $invoiceTotals,
        array $items
    ): Invoice {
        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceId = $this->insertInvoice($conn, $invoiceNumber, $customer, $invoiceTotals);
        $this->saveLineItems($conn, $invoiceId, $items);

        return new Invoice($invoiceId, $invoiceNumber, $customer, $items, $invoiceTotals);
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    }

    private function insertInvoice(
        \mysqli $conn,
        string $invoiceNumber,
        Customer $customer,
        InvoiceTotals $invoiceTotals,
    ): int {
        $sql = <<<SQL
INSERT INTO invoices (invoice_number, customer_id, subtotal, tax, shipping, total, created_at)
VALUES ('$invoiceNumber', $customer->id, {$invoiceTotals->subtotal}, {$invoiceTotals->tax}, {$invoiceTotals->shipping}, {$invoiceTotals->total}, NOW())
SQL;
        mysqli_query($conn, $sql);

        return mysqli_insert_id($conn);
    }

    /**
     * @param list<LineItem> $items
     */
    private function saveLineItems(\mysqli $conn, int $invoiceId, array $items): void
    {
        foreach ($items as $item) {
            $name = mysqli_real_escape_string($conn, $item->name);
            $quantity = $item->quantity;
            $price = $item->unitPrice;

            $sql = <<<SQL
INSERT INTO invoice_items (invoice_id, product_name, quantity, unit_price, line_total)
VALUES ($invoiceId, '$name', $quantity, $price, {$item->lineTotal()})
SQL;

            mysqli_query($conn, $sql);
        }
    }

    private function render(string $format, Invoice $invoice): string {
        if ($format == 'html') {
            $output = '<div class="invoice">';
            $output .= '<h1>Invoice ' . $invoice->invoiceNumber . '</h1>';
            $output .= '<div class="customer">';
            $output .= '<p>' . htmlspecialchars($invoice->customer->name) . '</p>';
            $output .= '<p>' . htmlspecialchars($invoice->customer->email) . '</p>';
            if (isset($invoice->customer->address)) {
                $output .= '<p>' . nl2br(htmlspecialchars($invoice->customer->address->formatted())) . '</p>';
            }
            $output .= '</div>';
            $output .= '<table class="items">';
            $output .= '<tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>';
            foreach ($invoice->items as $item) {
                $output .= '<tr>';
                $output .= '<td>' . htmlspecialchars($item->name) . '</td>';
                $output .= '<td>' . $item->quantity . '</td>';
                $output .= '<td>' . number_format($item->unitPrice, 2) . ' EUR</td>';
                $output .= '<td>' . number_format($item->lineTotal(), 2) . ' EUR</td>';
                $output .= '</tr>';
            }
            $output .= '</table>';
            $output .= '<div class="totals">';
            $output .= '<p>Subtotal: ' . number_format($invoice->totals->subtotal, 2) . ' EUR</p>';
            $output .= '<p>Tax (21%): ' . number_format($invoice->totals->tax, 2) . ' EUR</p>';
            if ($invoice->totals->shipping > 0) {
                $output .= '<p>Shipping: ' . number_format($invoice->totals->shipping, 2) . ' EUR</p>';
            }
            $output .= '<p class="total"><strong>Total: ' . number_format($invoice->totals->total, 2) . ' EUR</strong></p>';
            $output .= '</div>';
            $output .= '</div>';

            return $output;
        }

        if ($format == 'json') {
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

        if ($format == 'text') {
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

        return '';
    }
}
