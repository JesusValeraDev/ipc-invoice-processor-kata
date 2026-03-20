<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Model\Customer;
use App\Domain\Model\Invoice;
use App\Domain\Model\InvoiceTotals;
use App\Domain\Model\LineItem;
use App\Domain\Model\LineItemCollection;
use mysqli;

final class MysqliInvoiceRepository
{
    public function save(mysqli $connection, Customer $customer, InvoiceTotals $invoiceTotals, LineItemCollection $items): Invoice
    {
        $invoiceNumber = new DateBasedInvoiceNumberGenerator()->generate();
        $invoiceId = $this->insertInvoice($connection, $invoiceNumber, $customer, $invoiceTotals);
        $this->saveLineItems($connection, $invoiceId, $items);

        return new Invoice($invoiceId, $invoiceNumber, $customer, $items, $invoiceTotals);
    }

    private function insertInvoice(mysqli $connection, string $invoiceNumber, Customer $customer, InvoiceTotals $invoiceTotals): int {
        $sql = <<<SQL
INSERT INTO invoices (invoice_number, customer_id, subtotal, tax, shipping, total, created_at)
VALUES ('$invoiceNumber', $customer->id, {$invoiceTotals->subtotal}, {$invoiceTotals->tax}, {$invoiceTotals->shipping}, {$invoiceTotals->total}, NOW())
SQL;
        mysqli_query($connection, $sql);

        return mysqli_insert_id($connection);
    }

    private function saveLineItems(\mysqli $conn, int $invoiceId, LineItemCollection $items): void
    {
        foreach ($items as $item) {
            $name = mysqli_real_escape_string($conn, $item->name);
            $quantity = $item->quantity;
            $price = $item->unitPrice;

            $sql = <<<SQL
INSERT INTO invoice_items (invoice_id, product_name, quantity, unit_price, line_total)
VALUES ($invoiceId, '$name', $quantity, $price, {$item->lineTotal()})
SQL;

            mysqli_query($conn, $sql);
        }
    }
}
