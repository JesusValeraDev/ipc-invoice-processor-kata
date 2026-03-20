<?php

declare(strict_types=1);

namespace App\Application;

use App\Application\DTO\InvoiceRequest;
use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\LineItemCollection;
use App\Domain\Port\InvoiceRendererStrategy;
use App\Domain\Service\InvoiceTotalsCalculator;
use App\Infrastructure\Renderer\InvoiceHtmlRenderer;
use App\Infrastructure\Renderer\InvoiceJsonRenderer;
use App\Infrastructure\Renderer\InvoiceTextRenderer;
use App\Infrastructure\Repository\MysqliInvoiceRepository;
use InvalidArgumentException;

final class InvoiceProcessor
{
    /**
     * @return array{success: true, invoice_number: string, invoice_id: int, total: float, output: string}
     */
    public function processInvoice(InvoiceRequest $invoiceData, \mysqli $conn, string $format = 'html'): array
    {
        $customer = $invoiceData->customer;
        $items = $invoiceData->items;

        $this->validate($customer, $items);

        $invoiceTotals = new InvoiceTotalsCalculator()->calculate($items);
        $invoice = new MysqliInvoiceRepository()->save($conn, $customer, $invoiceTotals, $items);

        $output = $this->render($format, $invoice);

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

    private function render(string $format, Invoice $invoice): string {

        /** @var InvoiceRendererStrategy $render */
        $render = match ($format) {
            'html' => new InvoiceHtmlRenderer(),
            'json' => new InvoiceJsonRenderer(),
            'text' => new InvoiceTextRenderer(),
            default => throw new InvalidArgumentException('Invalid format'),
        };

        return $render->render($invoice);
    }
}
