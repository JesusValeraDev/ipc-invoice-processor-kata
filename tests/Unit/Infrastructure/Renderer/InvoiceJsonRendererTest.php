<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Renderer;

use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use App\Infrastructure\Renderer\InvoiceJsonRenderer;
use PHPUnit\Framework\TestCase;

final class InvoiceJsonRendererTest extends TestCase
{
    private InvoiceJsonRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new InvoiceJsonRenderer();
    }

    private function invoice(): Invoice
    {
        return new Invoice(
            invoiceId: 1,
            invoiceNumber: 'INV-20260320-1234',
            customer: new Customer(1, 'Jane Smith', 'jane@example.com'),
            items: new LineItemCollection([
                new LineItem('Widget', 2, 15.00),
            ]),
            totals: new InvoiceTotals(30.00, 6.30, 0.00, 36.30, 2),
        );
    }

    public function test_renders_valid_json(): void
    {
        $json = $this->renderer->render($this->invoice());

        $this->assertJson($json);
    }

    public function test_contains_invoice_number(): void
    {
        $decoded = json_decode($this->renderer->render($this->invoice()), true);

        $this->assertEquals('INV-20260320-1234', $decoded['invoice_number']);
    }

    public function test_contains_totals(): void
    {
        $decoded = json_decode($this->renderer->render($this->invoice()), true);

        $this->assertEquals(30.00, $decoded['subtotal']);
        $this->assertEquals(6.30, $decoded['tax']);
        $this->assertEquals(0.00, $decoded['shipping']);
        $this->assertEquals(36.30, $decoded['total']);
    }

    public function test_contains_customer_data(): void
    {
        $decoded = json_decode($this->renderer->render($this->invoice()), true);

        $this->assertEquals('Jane Smith', $decoded['customer']['name']);
        $this->assertEquals('jane@example.com', $decoded['customer']['email']);
    }
}
