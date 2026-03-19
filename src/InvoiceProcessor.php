<?php

declare(strict_types=1);

namespace App;

use App\Domain\Model\Customer;
use App\Domain\Model\LineItem;
use InvalidArgumentException;

final class InvoiceProcessor
{
    const float TAX_RATE = 0.21;

    const int FREE_SHIPPING_THRESHOLD = 50;

    const float SHIPPING_SMALL = 4.95;
    const float SHIPPING_MEDIUM = 6.95;
    const float SHIPPING_LARGE = 9.95;

    /**
     * @param array{customer: Customer, items: list<LineItem>} $invoiceData
     * @return array{success: true, invoice_number: string, invoice_id: int, total: float, output: string}
     */
    public function processInvoice(array $invoiceData, \mysqli $conn, string $format = 'html'): array
    {
        $customer = $invoiceData['customer'];
        $items = $invoiceData['items'];

        $this->validate($customer, $items);

        [$subtotal, $tax, $shipping, $total] = $this->calculate($items);

        [$invoiceId, $invoiceNumber, $customer, $items, $subtotal, $tax, $shipping, $total] = $this->save(
            $conn, $customer, $subtotal, $tax, $shipping, $total, $items
        );

        $output = $this->render($format, $invoiceNumber, $customer, $items, $subtotal, $tax, $shipping, $total);

        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'invoice_id' => $invoiceId,
            'total' => $total,
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
     * @return array{float, float, float, float}
     */
    private function calculate(array $items): array
    {
        $subtotal = $this->calculateSubtotal($items);
        $itemCount = $this->totalQuantity($items);
        $tax = $this->calculateTax($subtotal);
        $shipping = $this->calculateShipping($subtotal, $itemCount);

        $total = $subtotal + $tax + $shipping;

        return [
            round($subtotal, 2),
            round($tax, 2),
            round($shipping, 2),
            round($total, 2),
        ];
    }

    /**
     * @param list<LineItem> $items
     */
    public function calculateSubtotal(array $items): float
    {
        return array_reduce(
            array: $items,
            callback: fn(float $sum, LineItem $item) => $sum + $item->lineTotal(),
            initial: 0.0,
        );
    }

    /**
     * @param list<LineItem> $items
     */
    public function totalQuantity(array $items): int
    {
        return array_reduce(
            array: $items,
            callback: fn(int $count, LineItem $item) => $count + $item->quantity,
            initial: 0,
        );
    }

    private function calculateTax(float $subtotal): float
    {
        return $subtotal * self::TAX_RATE;
    }

    private function calculateShipping(float $subtotal, int $itemCount): float
    {
        if ($subtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0.0;
        }

        return match (true) {
            $itemCount <= 2 => self::SHIPPING_SMALL,
            $itemCount <= 5 => self::SHIPPING_MEDIUM,
            default => self::SHIPPING_LARGE,
        };
    }

    /**
     * @param list<LineItem> $items
     * @return array{int, string, array, list<LineItem>, float, float, float, float}
     */
    private function save(
        \mysqli $conn,
        Customer $customer,
        float $subtotal,
        float $tax,
        float $shipping,
        float $total,
        array $items
    ): array {
        $invoiceNumber = $this->generateInvoiceNumber();
        $invoiceId = $this->insertInvoice($conn, $invoiceNumber, $customer, $subtotal, $tax, $shipping, $total);
        $this->saveLineItems($conn, $invoiceId, $items);

        return [$invoiceId, $invoiceNumber, $customer, $items, $subtotal, $tax, $shipping, $total];
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-' . date('Ymd') . '-' . rand(1000, 9999);
    }

    private function insertInvoice(
        \mysqli $conn,
        string $invoiceNumber,
        Customer $customer,
        float $subtotal,
        float $tax,
        float $shipping,
        float $total
    ): int {
        $sql = <<<SQL
INSERT INTO invoices (invoice_number, customer_id, subtotal, tax, shipping, total, created_at)
VALUES ('$invoiceNumber', $customer->id, $subtotal, $tax, $shipping, $total, NOW())
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

    /**
     * @param list<LineItem> $items
     */
    private function render(
        string $format,
        string $invoiceNumber,
        Customer $customer,
        array $items,
        float $subtotal,
        float $tax,
        float $shipping,
        float $total
    ): string {
        if ($format == 'html') {
            $output = '<div class="invoice">';
            $output .= '<h1>Invoice ' . $invoiceNumber . '</h1>';
            $output .= '<div class="customer">';
            $output .= '<p>' . htmlspecialchars($customer->name) . '</p>';
            $output .= '<p>' . htmlspecialchars($customer->email) . '</p>';
            if (isset($customer->address)) {
                $output .= '<p>' . nl2br(htmlspecialchars($customer->address->formatted())) . '</p>';
            }
            $output .= '</div>';
            $output .= '<table class="items">';
            $output .= '<tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>';
            foreach ($items as $item) {
                $output .= '<tr>';
                $output .= '<td>' . htmlspecialchars($item->name) . '</td>';
                $output .= '<td>' . $item->quantity . '</td>';
                $output .= '<td>' . number_format($item->unitPrice, 2) . ' EUR</td>';
                $output .= '<td>' . number_format($item->lineTotal(), 2) . ' EUR</td>';
                $output .= '</tr>';
            }
            $output .= '</table>';
            $output .= '<div class="totals">';
            $output .= '<p>Subtotal: ' . number_format($subtotal, 2) . ' EUR</p>';
            $output .= '<p>Tax (21%): ' . number_format($tax, 2) . ' EUR</p>';
            if ($shipping > 0) {
                $output .= '<p>Shipping: ' . number_format($shipping, 2) . ' EUR</p>';
            }
            $output .= '<p class="total"><strong>Total: ' . number_format($total, 2) . ' EUR</strong></p>';
            $output .= '</div>';
            $output .= '</div>';

            return $output;
        }

        if ($format == 'json') {
            return (string) json_encode([
                'invoice_number' => $invoiceNumber,
                'customer' => $customer,
                'items' => $items,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total
            ]);
        }

        if ($format == 'text') {
            $output = "INVOICE: $invoiceNumber\n";
            $output .= "========================\n";
            $output .= "Customer: " . $customer->name . "\n";
            $output .= "Email: " . $customer->email . "\n\n";
            $output .= "Items:\n";
            foreach ($items as $item) {
                $output .= "- " . $item->name . " x" . $item->quantity . " @ " . $item->unitPrice . " = " . $item->lineTotal() . " EUR\n";
            }
            $output .= "\nSubtotal: $subtotal EUR\n";
            $output .= "Tax: $tax EUR\n";
            if ($shipping > 0) {
                $output .= "Shipping: $shipping EUR\n";
            }
            $output .= "========================\n";
            $output .= "TOTAL: $total EUR\n";
            return $output;
        }

        return '';
    }
}
