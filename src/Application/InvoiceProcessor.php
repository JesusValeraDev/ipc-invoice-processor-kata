<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\DTO\InvoiceRequest;
use App\Domain\Model\Customer;
use App\Domain\Model\LineItemCollection;
use App\Domain\Port\InvoiceRendererStrategy;
use App\Domain\Port\InvoiceRepository;
use App\Domain\Service\InvoiceTotalsCalculator;
use InvalidArgumentException;

final readonly class InvoiceProcessor
{
    public function __construct(
        private InvoiceTotalsCalculator $invoiceTotalsCalculator,
        private InvoiceRepository $invoiceRepository,
        private InvoiceRendererStrategy $invoiceRenderer,
    ) {
    }

    /**
     * @return array{success: true, invoice_number: string, invoice_id: int, total: float, output: string}
     */
    public function processInvoice(InvoiceRequest $invoiceData): array
    {
        $customer = $invoiceData->customer;
        $items = $invoiceData->items;

        $this->validate($customer, $items);

        $invoiceTotals = $this->invoiceTotalsCalculator->calculate($items);
        $invoice = $this->invoiceRepository->save($customer, $invoiceTotals, $items);

        $output = $this->invoiceRenderer->render($invoice);

        return [
            'success' => true,
            'invoice_number' => $invoice->invoiceNumber,
            'invoice_id' => $invoice->invoiceId,
            'total' => $invoiceTotals->total,
            'output' => $output,
        ];
    }

    private function validate(Customer $customer, LineItemCollection $items): void
    {
        if (!isset($customer->email) || !filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email');
        }

        if (count($items) == 0) {
            throw new InvalidArgumentException('No items');
        }
    }
}
