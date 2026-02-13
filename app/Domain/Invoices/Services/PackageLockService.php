<?php

namespace App\Domain\Invoices\Services;

use App\Support\Response;

class PackageLockService
{
    public function isInvoiceLocked(array $invoiceRow): bool
    {
        return !empty($invoiceRow['packages_confirmed']);
    }

    public function assertInvoiceEditable(array $invoiceRow): void
    {
        if ($this->isInvoiceLocked($invoiceRow)) {
            Response::abort(403, 'Pachetele sunt confirmate.');
        }
    }
}
