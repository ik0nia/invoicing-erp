<?php

namespace App\Domain\Invoices\Services;

use App\Support\Database;

class PackageTotalsService
{
    public function calculatePackageTotals(int $packageId): array
    {
        if (!Database::tableExists('invoice_in_lines')) {
            return [
                'sum_net' => 0.0,
                'sum_gross' => 0.0,
                'vat_percent' => 0.0,
                'line_count' => 0,
            ];
        }

        $row = Database::fetchOne(
            'SELECT p.vat_percent,
                    COUNT(l.id) AS line_count,
                    COALESCE(SUM(l.line_total), 0) AS sum_net,
                    COALESCE(SUM(l.line_total_vat), 0) AS sum_gross
             FROM packages p
             LEFT JOIN invoice_in_lines l ON l.package_id = p.id
             WHERE p.id = :id
             GROUP BY p.id',
            ['id' => $packageId]
        );

        if (!$row) {
            return [
                'sum_net' => 0.0,
                'sum_gross' => 0.0,
                'vat_percent' => 0.0,
                'line_count' => 0,
            ];
        }

        return [
            'sum_net' => (float) ($row['sum_net'] ?? 0),
            'sum_gross' => (float) ($row['sum_gross'] ?? 0),
            'vat_percent' => (float) ($row['vat_percent'] ?? 0),
            'line_count' => (int) ($row['line_count'] ?? 0),
        ];
    }

    public function calculateInvoiceTotals(int $invoiceId): array
    {
        if (!Database::tableExists('invoice_in_lines')) {
            return [
                'sum_net' => 0.0,
                'sum_gross' => 0.0,
                'vat_percent' => 0.0,
                'line_count' => 0,
            ];
        }

        $row = Database::fetchOne(
            'SELECT COUNT(*) AS line_count,
                    COALESCE(SUM(line_total), 0) AS sum_net,
                    COALESCE(SUM(line_total_vat), 0) AS sum_gross
             FROM invoice_in_lines
             WHERE invoice_in_id = :id',
            ['id' => $invoiceId]
        );

        return [
            'sum_net' => (float) ($row['sum_net'] ?? 0),
            'sum_gross' => (float) ($row['sum_gross'] ?? 0),
            'vat_percent' => 0.0,
            'line_count' => (int) ($row['line_count'] ?? 0),
        ];
    }
}
