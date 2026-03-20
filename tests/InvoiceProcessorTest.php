<?php

declare(strict_types=1);

namespace Tests;

use App\Application\DTO\InvoiceRequest;
use App\Application\InvoiceProcessor;
use App\Domain\Model\Address;
use App\Domain\Model\Customer;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use mysqli;
use PHPUnit\Framework\TestCase;

final class InvoiceProcessorTest extends TestCase
{
    private InvoiceProcessor $invoiceProcessor;

    private static ?mysqli $conn = null;

    public static function setUpBeforeClass(): void
    {
        self::$conn = mysqli_connect("127.0.0.1", "root", "password123", "shop_db", 3307);
        if (!self::$conn) {
            self::fail("DB connection failed: " . mysqli_connect_error());
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$conn) {
            mysqli_close(self::$conn);
            self::$conn = null;
        }
    }

    protected function setUp(): void
    {
        $this->invoiceProcessor = new InvoiceProcessor();

        mysqli_query(self::$conn, "DELETE FROM invoice_items");
        mysqli_query(self::$conn, "DELETE FROM invoices");
    }

    private function baseInvoiceData(): InvoiceRequest
    {
        return new InvoiceRequest(
            customer: new Customer(
                id: 42,
                name: 'Jane Smith',
                email: 'jane.smith@example.com',
                address: new Address(street: 'Prinsengracht 123', city:  'Amsterdam', zipCode:  '1015 DT'),
            ),
            items: new LineItemCollection([
                new LineItem('Mechanical Keyboard', 1, 149.99),
                new LineItem('USB-C Cable', 3, 12.50),
                new LineItem('Mouse Pad XL', 1, 24.95),
            ]),
        );
    }

    private function processAndDecode(InvoiceRequest $invoiceData): array
    {
        $result = $this->invoiceProcessor->processInvoice($invoiceData, self::$conn, 'json');
        $result['decoded'] = json_decode($result['output'], true);
        return $result;
    }

    // ---- Validation tests ----

    public function test_returns_error_when_invalid_email(): void
    {
        $this->expectExceptionMessage('Invalid email');

        $data = $this->baseInvoiceData();
        $data->customer = new Customer(id: 42, name: 'John', email: 'not-an-email');

        $this->invoiceProcessor->processInvoice($data, self::$conn);
    }

    public function test_returns_error_when_no_items(): void
    {
        $this->expectExceptionMessage('No items');

        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([]);

        $this->invoiceProcessor->processInvoice($data, self::$conn);
    }

    public function test_returns_error_when_email_missing(): void
    {
        $this->expectExceptionMessage('Invalid email');

        $data = $this->baseInvoiceData();
        unset($data->customer->email);

        $this->invoiceProcessor->processInvoice($data, self::$conn);
    }

    // ---- Basic invoice ----

    public function test_basic_invoice(): void
    {
        $data = $this->baseInvoiceData();
        // subtotal = 149.99 + 37.50 + 24.95 = 212.44
        // no discount -> after_discount = 212.44
        // tax = 212.44 * 0.21 = 44.61
        // shipping = 0 (over 50)
        // total = 212.44 + 44.61 = 257.05

        $result = $this->processAndDecode($data);

        $this->assertTrue($result['success']);
        $this->assertEquals(212.44, $result['decoded']['subtotal']);
        $this->assertEquals(44.61, $result['decoded']['tax']);
        $this->assertEquals(0, $result['decoded']['shipping']);
        $this->assertEquals(257.05, $result['total']);
    }

    // ---- Shipping tiers ----

    public function test_free_shipping_when_over_50(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $this->assertEquals(0, $result['decoded']['shipping']);
    }

    public function test_shipping_495_when_under_50_and_2_or_fewer_items(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 1, 3.00),
        ]);
        // subtotal = 3.00, no discount, after_discount = 3.00
        // tax = 3.00 * 0.21 = 0.63
        // item_count = 1 (<=2), shipping = 4.95
        // total = 3.00 + 0.63 + 4.95 = 8.58

        $result = $this->processAndDecode($data);

        $this->assertEquals(4.95, $result['decoded']['shipping']);
        $this->assertEquals(8.58, $result['total']);
    }

    public function test_shipping_695_when_under_50_and_3_to_5_items(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker A', 3, 2.00),
            new LineItem('Sticker B', 1, 1.00),
        ]);
        // subtotal = 6.00 + 1.00 = 7.00
        // item_count = 4 (3-5), shipping = 6.95
        // tax = 7.00 * 0.21 = 1.47
        // total = 7.00 + 1.47 + 6.95 = 15.42

        $result = $this->processAndDecode($data);

        $this->assertEquals(6.95, $result['decoded']['shipping']);
        $this->assertEquals(15.42, $result['total']);
    }

    public function test_shipping_995_when_under_50_and_more_than_5_items(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 7, 1.00),
        ]);
        // subtotal = 7.00, item_count = 7 (>5), shipping = 9.95
        // tax = 7.00 * 0.21 = 1.47
        // total = 7.00 + 1.47 + 9.95 = 18.42

        $result = $this->processAndDecode($data);

        $this->assertEquals(9.95, $result['decoded']['shipping']);
        $this->assertEquals(18.42, $result['total']);
    }

    // ---- Database persistence ----

    public function test_invoice_is_saved_to_database(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $this->assertGreaterThan(0, $result['invoice_id']);

        $row = mysqli_fetch_assoc(
            mysqli_query(self::$conn, "SELECT * FROM invoices WHERE id = " . $result['invoice_id'])
        );
        $this->assertNotNull($row);
        $this->assertEquals($result['invoice_number'], $row['invoice_number']);
        $this->assertEquals(42, (int) $row['customer_id']);
        $this->assertEquals(212.44, (float) $row['subtotal']);
        $this->assertEquals($result['total'], (float) $row['total']);
    }

    public function test_line_items_are_saved_to_database(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $rows = mysqli_fetch_all(
            mysqli_query(self::$conn, "SELECT * FROM invoice_items WHERE invoice_id = " . $result['invoice_id']),
            MYSQLI_ASSOC
        );
        $this->assertCount(3, $rows);

        $names = array_column($rows, 'product_name');
        $this->assertContains('Mechanical Keyboard', $names);
        $this->assertContains('USB-C Cable', $names);
        $this->assertContains('Mouse Pad XL', $names);
    }

    // ---- Output formats ----

    public function test_html_output_contains_invoice_structure(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'html');

        $this->assertStringContainsString('<div class="invoice">', $result['output']);
        $this->assertStringContainsString('Jane Smith', $result['output']);
        $this->assertStringContainsString('Mechanical Keyboard', $result['output']);
        $this->assertStringContainsString('<table class="items">', $result['output']);
    }

    public function test_json_output_is_valid_json(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'json');
        $decoded = json_decode($result['output'], true);

        $this->assertNotNull($decoded);
        $this->assertArrayHasKey('invoice_number', $decoded);
        $this->assertArrayHasKey('subtotal', $decoded);
        $this->assertArrayHasKey('total', $decoded);
    }

    public function test_text_output_contains_invoice_details(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'text');

        $this->assertStringContainsString('INVOICE:', $result['output']);
        $this->assertStringContainsString('Jane Smith', $result['output']);
        $this->assertStringContainsString('Mechanical Keyboard', $result['output']);
        $this->assertStringContainsString('TOTAL:', $result['output']);
    }

    // ---- Invoice number format ----

    public function test_invoice_number_format(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $this->assertMatchesRegularExpression('/^INV-\d{8}-\d{4}$/', $result['invoice_number']);
    }

    // ---- Unknown output format ----

    public function test_unknown_format_returns_empty_output(): void
    {
        $this->expectExceptionMessage('Invalid format');
        $data = $this->baseInvoiceData();

        $this->invoiceProcessor->processInvoice($data, self::$conn, 'xml');
    }

    // ---- Free shipping  ----

    public function test_free_shipping_when_exactly_50(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Widget', 1, 50.00),
        ]);
        // after_discount = 50.00, which is NOT < 50, so shipping = 0

        $result = $this->processAndDecode($data);

        $this->assertEquals(0, $result['decoded']['shipping']);
    }

    // ---- HTML output sub-branches ----

    public function test_html_output_without_address(): void
    {
        $data = $this->baseInvoiceData();
        unset($data->customer->address);

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'html');

        $this->assertStringContainsString('Jane Smith', $result['output']);
        $this->assertStringNotContainsString('Prinsengracht', $result['output']);
    }

    public function test_html_output_shows_shipping_line(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 1, 3.00),
        ]);

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'html');

        $this->assertStringContainsString('Shipping:', $result['output']);
        $this->assertStringContainsString('4.95', $result['output']);
    }

    // ---- Text output sub-branches ----

    public function test_text_output_shows_shipping_line(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 1, 3.00),
        ]);

        $result = $this->invoiceProcessor->processInvoice($data, self::$conn, 'text');

        $this->assertStringContainsString('Shipping: ', $result['output']);
    }

    // ---- DB: line item values persisted correctly ----

    public function test_invoice_persisted_in_database(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $row = mysqli_fetch_assoc(
            mysqli_query(self::$conn, "SELECT * FROM invoices WHERE id = " . $result['invoice_id'])
        );
        $this->assertEquals($result['decoded']['tax'], (float) $row['tax']);
        $this->assertEquals(0.00, (float) $row['shipping']);
    }

    public function test_line_item_values_persisted_correctly(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Widget', 2, 15.00),
        ]);

        $result = $this->processAndDecode($data);

        $row = mysqli_fetch_assoc(
            mysqli_query(self::$conn, "SELECT * FROM invoice_items WHERE invoice_id = " . $result['invoice_id'])
        );
        $this->assertEquals('Widget', $row['product_name']);
        $this->assertEquals(2, (int) $row['quantity']);
        $this->assertEquals(15.00, (float) $row['unit_price']);
        $this->assertEquals(30.00, (float) $row['line_total']);
    }

    // ---- Shipping boundary: item_count exactly 2 and exactly 5 ----

    public function test_shipping_495_when_exactly_2_items(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 2, 1.00),
        ]);
        // subtotal = 2.00, item_count = 2 (<=2), shipping = 4.95

        $result = $this->processAndDecode($data);

        $this->assertEquals(4.95, $result['decoded']['shipping']);
    }

    public function test_shipping_695_when_exactly_5_items(): void
    {
        $data = $this->baseInvoiceData();
        $data->items = new LineItemCollection([
            new LineItem('Sticker', 5, 1.00),
        ]);
        // subtotal = 5.00, item_count = 5 (<=5), shipping = 6.95

        $result = $this->processAndDecode($data);

        $this->assertEquals(6.95, $result['decoded']['shipping']);
    }

    // ---- Full scenario matching run.php expected output ----

    public function test_full_scenario_from_run_php(): void
    {
        $data = $this->baseInvoiceData();

        $result = $this->processAndDecode($data);

        $this->assertTrue($result['success']);
        $this->assertEquals(212.44, $result['decoded']['subtotal']);
        $this->assertEquals(44.61, $result['decoded']['tax']);
        $this->assertEquals(0, $result['decoded']['shipping']);
        $this->assertEquals(257.05, $result['total']);
    }
}
