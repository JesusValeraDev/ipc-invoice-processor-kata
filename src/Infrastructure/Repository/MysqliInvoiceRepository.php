<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItemCollection;
use App\Domain\Port\InvoiceNumberGenerator;
use App\Domain\Port\InvoiceRepository;
use mysqli;

final readonly class MysqliInvoiceRepository implements InvoiceRepository
{
    public function __construct(
        private mysqli $connection,
        private InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {
    }

    public function save(Customer $customer, InvoiceTotals $invoiceTotals, LineItemCollection $items): Invoice
    {
        $invoiceNumber = $this->invoiceNumberGenerator->generate();
        $invoiceId = $this->insertInvoice($invoiceNumber, $customer, $invoiceTotals);
        $this->saveLineItems($invoiceId, $items);

        return new Invoice($invoiceId, $invoiceNumber, $customer, $items, $invoiceTotals);
    }

    private function insertInvoice(string $invoiceNumber, Customer $customer, InvoiceTotals $invoiceTotals): int
    {
        $sql = <<<SQL
INSERT INTO invoices (invoice_number, customer_id, subtotal, tax, shipping, total, created_at)
VALUES ('$invoiceNumber', $customer->id, {$invoiceTotals->subtotal}, {$invoiceTotals->tax}, {$invoiceTotals->shipping}, {$invoiceTotals->total}, NOW())
SQL;
        mysqli_query($this->connection, $sql);

        return mysqli_insert_id($this->connection);
    }

    private function saveLineItems(int $invoiceId, LineItemCollection $items): void
    {
        foreach ($items as $item) {
            $name = mysqli_real_escape_string($this->connection, $item->name);
            $quantity = $item->quantity;
            $price = $item->unitPrice;

            $sql = <<<SQL
INSERT INTO invoice_items (invoice_id, product_name, quantity, unit_price, line_total)
VALUES ($invoiceId, '$name', $quantity, $price, {$item->lineTotal()})
SQL;

            mysqli_query($this->connection, $sql);
        }
    }
}
