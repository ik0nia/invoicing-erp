<?php

namespace App\Domain\Invoices\Services;

use App\Support\Database;

class PackageTotalsService
{
    public function calculatePackageTotals(int $packageId): array
    {
        if (!Database::tableExists('invoice_in_lines') || !Database::tableExists('packages')) {
            return [
                'sum_net' => 0.0,
                'sum_gross' => 0.0,
                'vat_percent' => 0.0,
                'line_count' => 0,
            ];
        }

        $package = Database::fetchOne(
            'SELECT vat_percent FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        if (!$package) {
            return [
                'sum_net' => 0.0,
                'sum_gross' => 0.0,
                'vat_percent' => 0.0,
                'line_count' => 0,
            ];
        }

        $hasCostNet = Database::columnExists('invoice_in_lines', 'cost_line_total');
        $hasCostGross = Database::columnExists('invoice_in_lines', 'cost_line_total_vat');
        $costNetSelect = $hasCostNet ? ', cost_line_total' : '';
        $costGrossSelect = $hasCostGross ? ', cost_line_total_vat' : '';

        $rows = Database::fetchAll(
            'SELECT product_name, line_total, line_total_vat' . $costNetSelect . $costGrossSelect . '
             FROM invoice_in_lines
             WHERE package_id = :id',
            ['id' => $packageId]
        );

        $sumNet = 0.0;
        $sumGross = 0.0;
        $lineCount = 0;
        foreach ($rows as $row) {
            if ($this->isDiscountLine($row)) {
                continue;
            }

            $effectiveNet = $this->effectiveNet($row);
            $effectiveGross = $this->effectiveGross($row);
            $lineCount++;
            $sumNet += $effectiveNet;
            $sumGross += $effectiveGross;
        }

        return [
            'sum_net' => round($sumNet, 2),
            'sum_gross' => round($sumGross, 2),
            'vat_percent' => (float) ($package['vat_percent'] ?? 0),
            'line_count' => $lineCount,
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

        $hasCostNet = Database::columnExists('invoice_in_lines', 'cost_line_total');
        $hasCostGross = Database::columnExists('invoice_in_lines', 'cost_line_total_vat');
        $costNetSelect = $hasCostNet ? ', cost_line_total' : '';
        $costGrossSelect = $hasCostGross ? ', cost_line_total_vat' : '';

        $rows = Database::fetchAll(
            'SELECT product_name, line_total, line_total_vat' . $costNetSelect . $costGrossSelect . '
             FROM invoice_in_lines
             WHERE invoice_in_id = :id',
            ['id' => $invoiceId]
        );

        $sumNet = 0.0;
        $sumGross = 0.0;
        $lineCount = 0;
        foreach ($rows as $row) {
            if ($this->isDiscountLine($row)) {
                continue;
            }

            $effectiveNet = $this->effectiveNet($row);
            $effectiveGross = $this->effectiveGross($row);
            $lineCount++;
            $sumNet += $effectiveNet;
            $sumGross += $effectiveGross;
        }

        return [
            'sum_net' => round($sumNet, 2),
            'sum_gross' => round($sumGross, 2),
            'vat_percent' => 0.0,
            'line_count' => $lineCount,
        ];
    }

    private function effectiveNet(array $row): float
    {
        if (array_key_exists('cost_line_total', $row) && $row['cost_line_total'] !== null) {
            return (float) $row['cost_line_total'];
        }

        return (float) ($row['line_total'] ?? 0.0);
    }

    private function effectiveGross(array $row): float
    {
        if (array_key_exists('cost_line_total_vat', $row) && $row['cost_line_total_vat'] !== null) {
            return (float) $row['cost_line_total_vat'];
        }

        return (float) ($row['line_total_vat'] ?? 0.0);
    }

    private function isDiscountLine(array $line): bool
    {
        $lineTotal = (float) ($line['line_total'] ?? 0.0);
        $lineTotalVat = (float) ($line['line_total_vat'] ?? 0.0);
        if ($lineTotal >= -0.00001 && $lineTotalVat >= -0.00001) {
            return false;
        }

        $name = $this->normalizeDiscountText((string) ($line['product_name'] ?? ''));
        if ($name === '') {
            return false;
        }

        if (str_contains($name, 'discount') || str_contains($name, 'reduc')) {
            return true;
        }

        return preg_match('/\bdisc[a-z]*/', $name) === 1;
    }

    private function normalizeDiscountText(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }
}
