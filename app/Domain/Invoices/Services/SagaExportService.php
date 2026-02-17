<?php

namespace App\Domain\Invoices\Services;

use App\Domain\Invoices\Rules\PackageSagaRules;
use App\Domain\Partners\Services\CommissionService;
use App\Support\Database;
use App\Support\Logger;

class SagaExportService
{
    private CommissionService $commissionService;
    private SagaStatusService $sagaStatusService;
    private array $invoiceDiscountPricingCache = [];

    public function __construct(?CommissionService $commissionService = null, ?SagaStatusService $sagaStatusService = null)
    {
        $this->commissionService = $commissionService ?? new CommissionService();
        $this->sagaStatusService = $sagaStatusService ?? new SagaStatusService();
    }

    public function buildPackagePayload(int $packageId, ?array $packageRow = null, bool $debug = false): array
    {
        if (!Database::columnExists('invoice_in_lines', 'cod_saga')) {
            throw new \RuntimeException('Nu exista coloana cod_saga in linii facturi.');
        }

        $validation = PackageSagaRules::validateForSaga($packageId);
        if (!empty($validation['errors']) || !empty($validation['warnings'])) {
            Logger::logWarning('saga_package_validation', [
                'package_id' => $packageId,
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
            ]);
        }

        $this->sagaStatusService->ensureSagaStatusColumn();

        $needsLookup = $packageRow === null
            || !array_key_exists('selected_client_cui', $packageRow)
            || !array_key_exists('supplier_cui', $packageRow)
            || !array_key_exists('commission_percent', $packageRow)
            || !array_key_exists('invoice_number', $packageRow)
            || !array_key_exists('invoice_in_id', $packageRow)
            || trim((string) ($packageRow['supplier_cui'] ?? '')) === ''
            || trim((string) ($packageRow['selected_client_cui'] ?? '')) === '';

        if ($needsLookup) {
            $hasSagaStatus = Database::columnExists('packages', 'saga_status');
            $statusSelect = $hasSagaStatus ? 'p.saga_status' : 'NULL AS saga_status';
            $packageRow = Database::fetchOne(
                'SELECT p.id, p.package_no, p.label, p.vat_percent, ' . $statusSelect . ',
                        i.id AS invoice_in_id, i.invoice_number, i.issue_date, i.total_with_vat,
                        i.selected_client_cui, i.supplier_cui, i.commission_percent
                 FROM packages p
                 JOIN invoices_in i ON i.id = p.invoice_in_id
                 WHERE p.id = :id
                 LIMIT 1',
                ['id' => $packageId]
            );
        }

        if (!$packageRow) {
            throw new \RuntimeException('Pachetul nu a fost gasit.');
        }

        $lines = Database::fetchAll(
            'SELECT id, product_name, quantity, unit_price, line_total, line_total_vat, cod_saga
             FROM invoice_in_lines
             WHERE package_id = :id
             ORDER BY id ASC',
            ['id' => $packageId]
        );

        if (empty($lines)) {
            throw new \RuntimeException('Pachetul nu are produse.');
        }

        $products = [];
        $sumValues = 0.0;
        $sumGross = 0.0;
        $hasNegativeQty = false;
        $hasPositiveQty = false;
        foreach ($lines as $line) {
            $code = trim((string) ($line['cod_saga'] ?? ''));
            if ($code === '') {
                throw new \RuntimeException('Exista produse fara cod SAGA asociat.');
            }

            $quantity = (float) ($line['quantity'] ?? 0);
            if ($quantity < -0.0001) {
                $hasNegativeQty = true;
            } elseif ($quantity > 0.0001) {
                $hasPositiveQty = true;
            }
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            $lineTotal = $unitPrice > 0 ? round($unitPrice * $quantity, 4) : (float) ($line['line_total'] ?? 0);
            $lineTotalVat = (float) ($line['line_total_vat'] ?? 0);
            $sumValues += $lineTotal;
            $sumGross += $lineTotalVat;
            $products[] = [
                'cod_articol' => $code,
                'cantitate' => $quantity,
                'val_produse' => round($lineTotal, 2),
            ];
        }

        $isStorno = $sumValues < -0.009 || $sumGross < -0.009 || ($hasNegativeQty && !$hasPositiveQty);
        if ($isStorno) {
            foreach ($products as &$product) {
                $product['val_produse'] = abs((float) ($product['val_produse'] ?? 0));
            }
            unset($product);
        }

        $dbTotal = (float) Database::fetchValue(
            'SELECT COALESCE(SUM(line_total), 0) FROM invoice_in_lines WHERE package_id = :id',
            ['id' => $packageId]
        );
        if (abs($dbTotal - $sumValues) > 0.01 && $dbTotal > 0) {
            $sumValues = $dbTotal;
        }

        $labelText = trim((string) ($packageRow['label'] ?? ''));
        if ($labelText === '') {
            $labelText = 'Pachet de produse';
        }
        $packageNo = (int) ($packageRow['package_no'] ?? $packageId);
        $label = $labelText . ' #' . $packageNo;

        $commissionPercent = null;
        if ($packageRow['commission_percent'] !== null) {
            $commissionPercent = (float) $packageRow['commission_percent'];
        }
        $clientCui = preg_replace('/\D+/', '', (string) ($packageRow['selected_client_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($packageRow['supplier_cui'] ?? ''));
        if ($commissionPercent === null && !empty($packageRow['invoice_in_id'])) {
            $invoiceRow = Database::fetchOne(
                'SELECT invoice_number, selected_client_cui, supplier_cui, commission_percent, total_with_vat
                 FROM invoices_in
                 WHERE id = :id
                 LIMIT 1',
                ['id' => (int) $packageRow['invoice_in_id']]
            );
            if ($invoiceRow) {
                $packageRow['invoice_number'] = $packageRow['invoice_number'] ?? $invoiceRow['invoice_number'];
                $packageRow['selected_client_cui'] = $packageRow['selected_client_cui'] ?? $invoiceRow['selected_client_cui'];
                $packageRow['supplier_cui'] = $packageRow['supplier_cui'] ?? $invoiceRow['supplier_cui'];
                $commissionPercent = $this->commissionService->resolveCommissionPercent(
                    $invoiceRow['commission_percent'] !== null ? (float) $invoiceRow['commission_percent'] : null,
                    (string) $packageRow['supplier_cui'],
                    (string) $packageRow['selected_client_cui']
                );
                $clientCui = preg_replace('/\D+/', '', (string) ($packageRow['selected_client_cui'] ?? ''));
                $supplierCui = preg_replace('/\D+/', '', (string) ($packageRow['supplier_cui'] ?? ''));
            }
        }
        if ($commissionPercent === null) {
            $commissionPercent = $this->commissionService->resolveCommissionPercent(
                null,
                $supplierCui,
                $clientCui
            );
        }

        $invoiceId = (int) ($packageRow['invoice_in_id'] ?? 0);
        $invoiceGrossTotal = isset($packageRow['total_with_vat']) ? (float) $packageRow['total_with_vat'] : null;
        $hasDiscountPricing = $this->invoiceHasDiscountPricing($invoiceId, $invoiceGrossTotal);

        $sellGross = $sumGross;
        if ($commissionPercent !== null && !$hasDiscountPricing) {
            $sellGross = $this->commissionService->applyCommission($sellGross, $commissionPercent);
        }
        $vatPercent = (float) ($packageRow['vat_percent'] ?? 0);
        $vatFactor = $vatPercent > 0 ? (1 + ($vatPercent / 100)) : 1;
        $sellTotal = $vatFactor > 0 ? ($sellGross / $vatFactor) : $sellGross;

        $status = (string) ($packageRow['saga_status'] ?? '');
        if ($status === '' || $status === 'pending') {
            $status = 'processing';
            $this->sagaStatusService->markProcessing($packageId);
        }

        $pretVanz = number_format($isStorno ? abs($sellTotal) : $sellTotal, 4, '.', '');
        $payload = [
            'pachet' => [
                'id_doc' => $packageNo,
                'data' => !empty($packageRow['issue_date'])
                    ? date('Y-m-d', strtotime((string) $packageRow['issue_date']))
                    : '',
                'denumire' => $this->normalizeName($label),
                'pret_vanz' => $pretVanz,
                'cota_tva' => round($vatPercent, 2),
                'cost_total' => $isStorno ? abs(round($sumValues, 2)) : round($sumValues, 2),
                'gestiune' => '0001',
                'cantitate_produsa' => $isStorno ? -1.0 : 1.0,
                'status' => $status,
            ],
            'produse' => $products,
        ];

        if ($debug) {
            $payload['debug'] = [
                'invoice_id' => (int) ($packageRow['invoice_in_id'] ?? 0),
                'invoice_number' => (string) ($packageRow['invoice_number'] ?? ''),
                'supplier_cui' => (string) ($packageRow['supplier_cui'] ?? ''),
                'selected_client_cui' => (string) ($packageRow['selected_client_cui'] ?? ''),
                'commission_percent' => $commissionPercent,
                'sum_net' => round($sumValues, 4),
                'sum_gross' => round($sumGross, 4),
                'sell_gross' => round($sellGross, 4),
                'vat_percent' => $vatPercent,
                'pret_vanz_calc' => $pretVanz,
                'is_storno' => $isStorno,
                'has_discount_pricing' => $hasDiscountPricing,
            ];
        }

        return $payload;
    }

    private function invoiceHasDiscountPricing(int $invoiceId, ?float $invoiceGrossTotal = null): bool
    {
        if ($invoiceId <= 0 || !Database::tableExists('invoice_in_lines')) {
            return false;
        }
        if (isset($this->invoiceDiscountPricingCache[$invoiceId])) {
            return (bool) $this->invoiceDiscountPricingCache[$invoiceId];
        }

        if ($invoiceGrossTotal === null && Database::tableExists('invoices_in')) {
            $invoiceGrossTotal = (float) (Database::fetchValue(
                'SELECT total_with_vat FROM invoices_in WHERE id = :id LIMIT 1',
                ['id' => $invoiceId]
            ) ?? 0.0);
        }
        if ($invoiceGrossTotal === null) {
            $invoiceGrossTotal = 0.0;
        }

        $salesGrossTotal = (float) (Database::fetchValue(
            'SELECT COALESCE(SUM(line_total_vat), 0) FROM invoice_in_lines WHERE invoice_in_id = :invoice',
            ['invoice' => $invoiceId]
        ) ?? 0.0);

        $hasDiscountPricing = $salesGrossTotal > ($invoiceGrossTotal + 0.009);
        $this->invoiceDiscountPricingCache[$invoiceId] = $hasDiscountPricing;

        return $hasDiscountPricing;
    }

    private function normalizeName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }
        return strtoupper($value);
    }
}
