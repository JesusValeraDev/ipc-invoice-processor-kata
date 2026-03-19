<?php

declare(strict_types=1);

namespace App;

use InvalidArgumentException;

final class InvoiceProcessor
{
    const float TAX_RATE = 0.21;

    const int FREE_SHIPPING_THRESHOLD = 50;

    const float SHIPPING_SMALL = 4.95;
    const float SHIPPING_MEDIUM = 6.95;
    const float SHIPPING_LARGE = 9.95;

    /**
     * @param array{
     *   customer: array{id: int, name: string, email: string, address: array{street: string, city: string, zip: string}},
     *   items: list<array{name: string, qty: int, price: float}>
     * } $invoiceData
     * @return array{success: true, invoice_number: string, invoice_id: int, total: float, output: string}
     */
    public function processInvoice(array $invoiceData, \mysqli $conn, string $format = 'html'): array
    {
        $customer = $invoiceData['customer'];
        $items = $invoiceData['items'];

        $this->validate($customer, $items);

        [$subtotal, $tax, $shipping, $total] = $this->calculate($items);

        // Round to 2 decimals
        $total = round($total, 2);
        $tax = round($tax, 2);
        $subtotal = round($subtotal, 2);
        $shipping = round($shipping, 2);

        // Generate invoice number
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);

        $customerId = $customer['id'];

        // Save to database
        $insertInvoiceSql = "INSERT INTO invoices (invoice_number, customer_id, subtotal, tax, shipping, total, created_at)
            VALUES ('$invoiceNumber', $customerId, $subtotal, $tax, $shipping, $total, NOW())";
        mysqli_query($conn, $insertInvoiceSql);
        $invoiceId = mysqli_insert_id($conn);

        // Save line items
        foreach ($items as $item) {
            $name = mysqli_real_escape_string($conn, $item['name']);
            $qty = $item['qty'];
            $price = $item['price'];
            $insertItemSql = "INSERT INTO invoice_items (invoice_id, product_name, quantity, unit_price, line_total)
                 VALUES ($invoiceId, '" . $name . "', " . $qty . ", " . $price . ", " . ($qty * $price) . ")";
            mysqli_query($conn, $insertItemSql);
        }

        // Generate output
        if ($format == 'html') {
            $output = '<div class="invoice">';
            $output .= '<h1>Invoice ' . $invoiceNumber . '</h1>';
            $output .= '<div class="customer">';
            $output .= '<p>' . htmlspecialchars($customer['name']) . '</p>';
            $output .= '<p>' . htmlspecialchars($customer['email']) . '</p>';
            if (isset($customer['address'])) {
                $output .= '<p>' . htmlspecialchars($customer['address']['street']) . '</p>';
                $output .= '<p>' . htmlspecialchars($customer['address']['city'])
                    . ', ' . htmlspecialchars($customer['address']['zip']) . '</p>';
            }
            $output .= '</div>';
            $output .= '<table class="items">';
            $output .= '<tr><th>Item</th><th>Qty</th><th>Price</th><th>Total</th></tr>';
            foreach ($items as $item) {
                $output .= '<tr>';
                $output .= '<td>' . htmlspecialchars($item['name']) . '</td>';
                $output .= '<td>' . $item['qty'] . '</td>';
                $output .= '<td>' . number_format($item['price'], 2) . ' EUR</td>';
                $output .= '<td>' . number_format($item['qty'] * $item['price'], 2) . ' EUR</td>';
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
        } elseif ($format == 'json') {
            $output = json_encode([
                'invoice_number' => $invoiceNumber,
                'customer' => $customer,
                'items' => $items,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'shipping' => $shipping,
                'total' => $total
            ]);
        } elseif ($format == 'text') {
            $output = "INVOICE: $invoiceNumber\n";
            $output .= "========================\n";
            $output .= "Customer: " . $customer['name'] . "\n";
            $output .= "Email: " . $customer['email'] . "\n\n";
            $output .= "Items:\n";
            foreach ($items as $item) {
                $output .= "- " . $item['name'] . " x" . $item['qty'] . " @ " . $item['price'] . " = " . ($item['qty'] * $item['price']) . " EUR\n";
            }
            $output .= "\nSubtotal: $subtotal EUR\n";
            $output .= "Tax: $tax EUR\n";
            if ($shipping > 0) {
                $output .= "Shipping: $shipping EUR\n";
            }
            $output .= "========================\n";
            $output .= "TOTAL: $total EUR\n";
        } else {
            $output = '';
        }

        return [
            'success' => true,
            'invoice_number' => $invoiceNumber,
            'invoice_id' => $invoiceId,
            'total' => $total,
            'output' => $output,
        ];
    }

    /**
     * @param array{id: int, name: string, email: string, address: array{street: string, city: string, zip: string}} $customer
     * @param list<array{name: string, qty: int, price: float}> $items
     */
    private function validate(array $customer, array $items): void
    {
        if ($customer == null) {
            throw new InvalidArgumentException('No customer');
        }

        if (!isset($customer['email']) || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }

        if ($items == null || count($items) == 0) {
            throw new InvalidArgumentException('No items');
        }
    }

    /**
     * @param list<array{name: string, qty: int, price: float}> $items
     * @return array{float, float, float, float}
     */
    private function calculate(array $items): array
    {
        $subtotal = $this->calculateSubtotal($items);
        $itemCount = $this->totalQuantity($items);
        $tax = $this->calculateTax($subtotal);
        $shipping = $this->calculateShipping($subtotal, $itemCount);

        $total = $subtotal + $tax + $shipping;

        return [$subtotal, $tax, $shipping, $total];
    }

    /**
     * @param list<array{name: string, qty: int, price: float}> $items
     */
    public function calculateSubtotal(array $items): float
    {
        return array_reduce(
            array: $items,
            callback: fn(float $sum, array $item) => $sum + ($item['qty'] * $item['price']),
            initial: 0.0,
        );
    }

    /**
     * @param list<array{name: string, qty: int, price: float}> $items
     */
    public function totalQuantity(array $items): int
    {
        return array_reduce(
            array: $items,
            callback: fn(int $count, array $item) => $count + $item['qty'],
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
}
