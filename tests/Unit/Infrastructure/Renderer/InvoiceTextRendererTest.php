<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Renderer;

use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use App\Infrastructure\Renderer\InvoiceTextRenderer;
use PHPUnit\Framework\TestCase;

final class InvoiceTextRendererTest extends TestCase
{
    private InvoiceTextRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new InvoiceTextRenderer();
    }

    private function invoice(float $shipping = 0.00): Invoice
    {
        return new Invoice(
            invoiceId: 1,
            invoiceNumber: 'INV-20260320-1234',
            customer: new Customer(1, 'Jane Smith', 'jane@example.com'),
            items: new LineItemCollection([
                new LineItem('Widget', 2, 15.00),
            ]),
            totals: new InvoiceTotals(30.00, 6.30, $shipping, 30.00 + 6.30 + $shipping, 2),
        );
    }

    public function test_renders_invoice_header(): void
    {
        $output = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('INVOICE: INV-20260320-1234', $output);
        $this->assertStringContainsString('========================', $output);
    }

    public function test_renders_customer_info(): void
    {
        $output = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('Customer: Jane Smith', $output);
        $this->assertStringContainsString('Email: jane@example.com', $output);
    }

    public function test_renders_line_items(): void
    {
        $output = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('- Widget x2 @ 15 = 30 EUR', $output);
    }

    public function test_renders_totals(): void
    {
        $output = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('Subtotal: 30 EUR', $output);
        $this->assertStringContainsString('Tax: 6.3 EUR', $output);
        $this->assertStringContainsString('TOTAL: 36.3 EUR', $output);
    }

    public function test_shows_shipping_when_greater_than_zero(): void
    {
        $output = $this->renderer->render($this->invoice(shipping: 4.95));

        $this->assertStringContainsString('Shipping: 4.95 EUR', $output);
    }

    public function test_hides_shipping_when_zero(): void
    {
        $output = $this->renderer->render($this->invoice(shipping: 0.00));

        $this->assertStringNotContainsString('Shipping:', $output);
    }
}
