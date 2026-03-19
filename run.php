<?php

require 'vendor/autoload.php';

$conn = mysqli_connect("127.0.0.1", "root", "password123", "shop_db", 3307);
if (!$conn) {
    die("DB connection failed: " . mysqli_connect_error() . "\n");
}

// Invoice data
$invoiceData = [
    'customer' => [
        'id' => 42,
        'name' => 'Jane Smith',
        'email' => 'jane.smith@example.com',
        'address' => new \App\Domain\Model\Address(street: 'Prinsengracht 123', city: 'Amsterdam', zipCode: '1015 DT'),
    ],
    'items' => [
        new \App\Domain\Model\LineItem('Mechanical Keyboard', 1, 149.99),
        new \App\Domain\Model\LineItem('USB-C Cable', 3, 12.50),
        new \App\Domain\Model\LineItem('Mouse Pad XL', 1, 24.95),
    ],
];

// Run the demo
echo "=== Invoice Processing Demo ===\n\n";

echo "Customer: {$invoiceData['customer']['name']}\n";
echo "Email: {$invoiceData['customer']['email']}\n\n";

echo "Items:\n";
/** @var \App\Domain\Model\LineItem $item */
foreach ($invoiceData['items'] as $item) {
    $lineTotal = $item->quantity * $item->unitPrice;
    printf("  - %s x%d @ %.2f = %.2f EUR\n", $item->name, $item->quantity, $item->unitPrice, $lineTotal);
}
echo "\n";

$invoiceProcessor = new \App\InvoiceProcessor();
$result = $invoiceProcessor->processInvoice($invoiceData, $conn, 'json');
$details = json_decode($result['output'], true);

echo "Totals:\n";
printf("  Subtotal:      %.2f EUR\n", $details['subtotal']);
printf("  Tax (21%%):     %.2f EUR\n", $details['tax']);
printf("  Shipping:      %.2f EUR", $details['shipping']);
if ($details['shipping'] === 0) {
    echo ' (free)';
}
echo "\n  -------------------------\n";
printf("  TOTAL:         %.2f EUR\n", $result['total']);
echo "\n";

mysqli_close($conn);

// Expected output for verification:
//
// Subtotal: 149.99 + 37.50 + 24.95 = 212.44
// Tax (21%): 44.61
// Shipping: 0 (free over 50 EUR)
// Total: 257.05
