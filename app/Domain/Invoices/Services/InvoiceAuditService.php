<?php

namespace App\Domain\Invoices\Services;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Support\Audit;

class InvoiceAuditService
{
    public function recordImportXml(InvoiceIn $invoice): void
    {
        Audit::record('invoice.import_xml', 'invoice_in', $invoice->id, [
            'supplier_cui' => $invoice->supplier_cui,
            'invoice_number' => $invoice->invoice_number,
            'total_without_vat' => $invoice->total_without_vat,
            'total_vat' => $invoice->total_vat,
            'total_with_vat' => $invoice->total_with_vat,
        ]);
    }

    public function recordManualCreate(InvoiceIn $invoice): void
    {
        Audit::record('invoice.create_manual', 'invoice_in', $invoice->id, [
            'supplier_cui' => $invoice->supplier_cui,
            'invoice_number' => $invoice->invoice_number,
            'total_without_vat' => $invoice->total_without_vat,
            'total_vat' => $invoice->total_vat,
            'total_with_vat' => $invoice->total_with_vat,
        ]);
    }

    public function recordPackagesConfirmed(int $invoiceId, int $packagesCount): void
    {
        Audit::record('invoice.packages_confirm', 'invoice_in', $invoiceId, [
            'invoice_id' => $invoiceId,
            'packages_count' => $packagesCount,
        ]);
    }
}
