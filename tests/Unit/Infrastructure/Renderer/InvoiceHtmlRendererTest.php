<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Renderer;

use App\Domain\Model\Address;
use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use App\Infrastructure\Renderer\InvoiceHtmlRenderer;
use PHPUnit\Framework\TestCase;

final class InvoiceHtmlRendererTest extends TestCase
{
    private InvoiceHtmlRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new InvoiceHtmlRenderer();
    }

    private function invoice(?Address $address = null, float $shipping = 0.00): Invoice
    {
        return new Invoice(
            invoiceId: 1,
            invoiceNumber: 'INV-20260320-1234',
            customer: new Customer(1, 'Jane Smith', 'jane@example.com', $address),
            items: new LineItemCollection([
                new LineItem('Widget', 2, 15.00),
            ]),
            totals: new InvoiceTotals(30.00, 6.30, $shipping, 30.00 + 6.30 + $shipping, 2),
        );
    }

    public function test_renders_invoice_structure(): void
    {
        $html = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('<div class="invoice">', $html);
        $this->assertStringContainsString('<h1>Invoice INV-20260320-1234</h1>', $html);
        $this->assertStringContainsString('<table class="items">', $html);
    }

    public function test_renders_customer_info(): void
    {
        $html = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('jane@example.com', $html);
    }

    public function test_renders_address_when_present(): void
    {
        $address = new Address('Prinsengracht 123', 'Amsterdam', '1015 DT');

        $html = $this->renderer->render($this->invoice(address: $address));

        $this->assertStringContainsString('Prinsengracht 123', $html);
        $this->assertStringContainsString('Amsterdam', $html);
    }

    public function test_omits_address_when_null(): void
    {
        $html = $this->renderer->render($this->invoice());

        $this->assertStringNotContainsString('Prinsengracht', $html);
    }

    public function test_renders_line_items(): void
    {
        $html = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('Widget', $html);
        $this->assertStringContainsString('15.00 EUR', $html);
        $this->assertStringContainsString('30.00 EUR', $html);
    }

    public function test_renders_totals(): void
    {
        $html = $this->renderer->render($this->invoice());

        $this->assertStringContainsString('Subtotal: 30.00 EUR', $html);
        $this->assertStringContainsString('Tax (21%): 6.30 EUR', $html);
        $this->assertStringContainsString('Total: 36.30 EUR', $html);
    }

    public function test_shows_shipping_when_greater_than_zero(): void
    {
        $html = $this->renderer->render($this->invoice(shipping: 4.95));

        $this->assertStringContainsString('Shipping: 4.95 EUR', $html);
    }

    public function test_hides_shipping_when_zero(): void
    {
        $html = $this->renderer->render($this->invoice(shipping: 0.00));

        $this->assertStringNotContainsString('Shipping:', $html);
    }

    public function test_escapes_html_in_customer_name(): void
    {
        $invoice = new Invoice(
            invoiceId: 1,
            invoiceNumber: 'INV-001',
            customer: new Customer(1, '<script>alert("xss")</script>', 'a@b.com'),
            items: new LineItemCollection([new LineItem('Item', 1, 10.00)]),
            totals: new InvoiceTotals(10.00, 2.10, 0.00, 12.10, 1),
        );

        $html = $this->renderer->render($invoice);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }
}
