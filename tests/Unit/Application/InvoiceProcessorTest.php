<?php

declare(strict_types=1);

namespace Tests\Unit\Application;

use App\Application\DTO\InvoiceRequest;
use App\Application\InvoiceProcessor;
use App\Domain\Model\Address;
use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use App\Domain\Port\InvoiceRendererStrategy;
use App\Domain\Port\InvoiceRepository;
use App\Domain\Service\InvoiceTotalsCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class InvoiceProcessorTest extends TestCase
{
    private InvoiceTotalsCalculator $calculator;
    private InvoiceRepository $repository;
    private InvoiceRendererStrategy $renderer;

    protected function setUp(): void
    {
        $this->calculator = new InvoiceTotalsCalculator();
        $this->repository = $this->createMock(InvoiceRepository::class);
        $this->renderer = $this->createMock(InvoiceRendererStrategy::class);
    }

    private function processor(): InvoiceProcessor
    {
        return new InvoiceProcessor($this->calculator, $this->repository, $this->renderer);
    }

    private function baseRequest(): InvoiceRequest
    {
        return new InvoiceRequest(
            customer: new Customer(
                1, 'Jane Smith', 'jane@example.com', new Address('Street 1', 'Amsterdam', '1015 DT')
            ),
            items: new LineItemCollection([
                new LineItem('Widget', 2, 30.00),
            ]),
        );
    }

    // ---- Validation ----

    public function test_throws_when_email_is_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        $request = new InvoiceRequest(
            customer: new Customer(1, 'Jane', 'not-an-email'),
            items: new LineItemCollection([new LineItem('A', 1, 10.00)]),
        );

        $this->processor()->processInvoice($request);
    }

    public function test_throws_when_items_are_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No items');

        $request = new InvoiceRequest(
            customer: new Customer(1, 'Jane', 'jane@example.com'),
            items: new LineItemCollection([]),
        );

        $this->processor()->processInvoice($request);
    }

    // ---- Orchestration ----

    public function test_returns_success_with_invoice_data(): void
    {
        $request = $this->baseRequest();
        $totals = new InvoiceTotals(60.00, 12.60, 0.00, 72.60, 2);
        $invoice = new Invoice(1, 'INV-20260320-1234', $request->customer, $request->items, $totals);

        $this->repository
            ->method('save')
            ->willReturn($invoice);

        $this->renderer
            ->method('render')
            ->willReturn('<html>invoice</html>');

        $result = $this->processor()->processInvoice($request);

        $this->assertTrue($result['success']);
        $this->assertEquals('INV-20260320-1234', $result['invoice_number']);
        $this->assertEquals(1, $result['invoice_id']);
        $this->assertEquals(72.60, $result['total']);
        $this->assertEquals('<html>invoice</html>', $result['output']);
    }

    public function test_delegates_to_repository(): void
    {
        $request = $this->baseRequest();
        $totals = new InvoiceTotals(60.00, 12.60, 0.00, 72.60, 2);
        $invoice = new Invoice(1, 'INV-001', $request->customer, $request->items, $totals);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->with($request->customer, $this->isInstanceOf(InvoiceTotals::class), $request->items)
            ->willReturn($invoice);

        $this->renderer->method('render')->willReturn('');

        $this->processor()->processInvoice($request);
    }

    public function test_delegates_to_renderer(): void
    {
        $request = $this->baseRequest();
        $totals = new InvoiceTotals(60.00, 12.60, 0.00, 72.60, 2);
        $invoice = new Invoice(1, 'INV-001', $request->customer, $request->items, $totals);

        $this->repository->method('save')->willReturn($invoice);

        $this->renderer
            ->expects($this->once())
            ->method('render')
            ->with($this->isInstanceOf(Invoice::class))
            ->willReturn('rendered');

        $this->processor()->processInvoice($request);
    }
}
