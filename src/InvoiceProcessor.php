<?php

declare(strict_types=1);

namespace App;

final class InvoiceProcessor
{
    public function processInvoice($invoiceData, $conn, $format = 'html')
    {
        $customer = $invoiceData['customer'];
        $items = $invoiceData['items'];

        // Validate
        if ($customer != null) {
            if (isset($customer['email'])) {
                if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
                    return ['error' => 'Invalid email'];
                }
            } else {
                return ['error' => 'Invalid email'];
            }
        } else {
            return ['error' => 'No customer'];
        }

        // Check items
        if ($items == null || count($items) == 0) {
            return ['error' => 'No items'];
        }

        // Calculate subtotal
        $subtotal = 0;
        $itemCount = 0;
        foreach ($items as $item) {
            $lineTotal = $item['qty'] * $item['price'];
            $subtotal += $lineTotal;
            $itemCount += $item['qty'];
        }

        $tax = $subtotal * 0.21; // tax_rate

        // Shipping - free over 50 euros
        $freeShippingThreshold = 50;
        if ($subtotal >= $freeShippingThreshold) {
            $shipping = 0;
        } else {
            $shipping = match (true) {
                $itemCount <= 2 => 4.95, // SHIPPING_SMALL
                $itemCount <= 5 => 6.95, // SHIPPING_MEDIUM
                default => 9.95, // SHIPPING_LARGE
            };
        }

        $customerId = $customer['id'];
        $total = $subtotal + $tax + $shipping;

        // Round to 2 decimals
        $total = round($total, 2);
        $tax = round($tax, 2);
        $subtotal = round($subtotal, 2);
        $shipping = round($shipping, 2);

        // Generate invoice number
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . rand(1000, 9999);

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
}
