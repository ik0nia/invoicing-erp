<?php

namespace App\Domain\Invoices\Services;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Support\Audit;

class InvoiceAuditService
{
    public function recordImportXml(InvoiceIn $invoice): void
    {
        Audit::record('invoice.import_xml', 'invoice_in', $invoice->id, [
            'invoice_id' => $invoice->id,
            'supplier_cui' => $invoice->supplier_cui,
            'selected_client_cui' => $invoice->selected_client_cui,
            'totals' => [
                'net' => $invoice->total_without_vat,
                'vat' => $invoice->total_vat,
                'gross' => $invoice->total_with_vat,
            ],
        ]);
    }

    public function recordManualCreate(InvoiceIn $invoice): void
    {
        Audit::record('invoice.create_manual', 'invoice_in', $invoice->id, [
            'invoice_id' => $invoice->id,
            'supplier_cui' => $invoice->supplier_cui,
            'selected_client_cui' => $invoice->selected_client_cui,
            'totals' => [
                'net' => $invoice->total_without_vat,
                'vat' => $invoice->total_vat,
                'gross' => $invoice->total_with_vat,
            ],
        ]);
    }

    public function recordPackagesConfirmed(int $invoiceId, int $packagesCount): void
    {
        Audit::record('invoice.packages_confirm', 'invoice_in', $invoiceId, [
            'invoice_id' => $invoiceId,
            'rows_count' => $packagesCount,
        ]);
    }
}
