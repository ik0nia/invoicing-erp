<?php

namespace App\Domain\Dashboard\Http\Controllers;

use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class DashboardController
{
    private array $invoiceSalesGrossCache = [];

    public function index(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }

        $isPlatform = $user->isPlatformUser();
        $isSupplierUser = $user->isSupplierUser();
        $canAccessSaga = $user->hasRole(['super_admin', 'contabil']);
        if (!$isPlatform && !$isSupplierUser) {
            Response::abort(403, 'Acces interzis.');
        }

        $latestInvoices = [];
        $pendingPackages = [];
        $supplierLatestInvoices = [];
        $supplierMonthCount = 0;
        $supplierDueInvoices = [];
        $monthIssuedTotal = 0.0;
        $monthIssuedCount = 0;
        $monthCollectedTotal = 0.0;
        $monthPaidTotal = 0.0;
        $uncollectedInvoices = [];
        $uncollectedCount = 0;
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        $supplierFilter = '';
        $supplierPlaceholders = '';
        $params = [];

        if ($isSupplierUser) {
            UserSupplierAccess::ensureTable();
            $suppliers = UserSupplierAccess::suppliersForUser($user->id);
            if (empty($suppliers)) {
                Response::view('admin/dashboard/index', [
                    'user' => $user,
                    'isPlatform' => $isPlatform,
                    'isSupplierUser' => $isSupplierUser,
                    'latestInvoices' => $latestInvoices,
                    'supplierLatestInvoices' => $supplierLatestInvoices,
                    'supplierMonthCount' => $supplierMonthCount,
                    'supplierDueInvoices' => $supplierDueInvoices,
                    'pendingPackages' => $pendingPackages,
                    'monthIssuedTotal' => $monthIssuedTotal,
                    'monthIssuedCount' => $monthIssuedCount,
                    'monthCollectedTotal' => $monthCollectedTotal,
                    'monthPaidTotal' => $monthPaidTotal,
                    'uncollectedInvoices' => $uncollectedInvoices,
                    'uncollectedCount' => $uncollectedCount,
                ]);
                return;
            }
            $placeholders = [];
            foreach (array_values($suppliers) as $index => $supplier) {
                $key = 's' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $supplier;
            }
            $supplierPlaceholders = implode(',', $placeholders);
            $supplierFilter = ' AND supplier_cui IN (' . implode(',', $placeholders) . ')';
        }

        if ($isSupplierUser && Database::tableExists('invoices_in')) {
            try {
                $supplierLatestInvoices = Database::fetchAll(
                    'SELECT id, invoice_number, issue_date, customer_name, selected_client_cui
                     FROM invoices_in
                     WHERE 1=1' . $supplierFilter . '
                     ORDER BY issue_date DESC, id DESC
                     LIMIT 5',
                    $params
                );
            } catch (\Throwable $exception) {
                $supplierLatestInvoices = [];
            }

            $supplierMonthCount = (int) (Database::fetchValue(
                'SELECT COUNT(*) FROM invoices_in
                 WHERE issue_date BETWEEN :start AND :end' . $supplierFilter,
                array_merge($params, ['start' => $monthStart, 'end' => $monthEnd])
            ) ?? 0);

            $supplierDueRows = [];
            if (Database::tableExists('payment_in_allocations') && Database::tableExists('payment_out_allocations')) {
                $supplierDueRows = Database::fetchAll(
                    'SELECT i.id, i.invoice_number, i.issue_date, i.supplier_cui, i.selected_client_cui, i.customer_name,
                            i.commission_percent,
                            COALESCE(SUM(a.amount), 0) AS collected,
                            COALESCE(SUM(o.amount), 0) AS paid
                     FROM invoices_in i
                     LEFT JOIN payment_in_allocations a ON a.invoice_in_id = i.id
                     LEFT JOIN payment_out_allocations o ON o.invoice_in_id = i.id
                     WHERE 1=1' . $supplierFilter . '
                       AND (i.fgo_storno_number IS NULL OR i.fgo_storno_number = "")
                       AND (i.fgo_storno_series IS NULL OR i.fgo_storno_series = "")
                       AND (i.fgo_storno_link IS NULL OR i.fgo_storno_link = "")
                     GROUP BY i.id
                     ORDER BY i.issue_date DESC, i.id DESC',
                    $params
                );
            }

            $clientMap = $this->partnerMap($this->collectClientCuis($supplierLatestInvoices, $supplierDueRows));
            $commissionMap = $this->commissionMap($supplierDueRows);

            foreach ($supplierLatestInvoices as &$invoice) {
                $clientCui = preg_replace('/\D+/', '', (string) ($invoice['selected_client_cui'] ?? ''));
                $clientName = $clientCui !== '' ? ($clientMap[$clientCui] ?? '') : '';
                if ($clientName === '') {
                    $clientName = (string) ($invoice['customer_name'] ?? '');
                }
                $invoice['client_label'] = $clientName !== '' ? $clientName : ($clientCui !== '' ? $clientCui : '—');
            }
            unset($invoice);

            foreach ($supplierDueRows as $row) {
                $collected = (float) $row['collected'];
                if ($collected <= 0) {
                    continue;
                }
                $commission = $this->resolveCommission($row, $commissionMap);
                $collectedNet = $commission !== 0.0
                    ? $this->applyCommission($collected, -abs($commission))
                    : $collected;
                $paid = (float) $row['paid'];
                $available = max(0, $collectedNet - $paid);
                if ($available <= 0) {
                    continue;
                }

                $clientCui = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
                $clientName = $clientCui !== '' ? ($clientMap[$clientCui] ?? '') : '';
                if ($clientName === '') {
                    $clientName = (string) ($row['customer_name'] ?? '');
                }

                $supplierDueInvoices[] = [
                    'invoice_number' => (string) $row['invoice_number'],
                    'issue_date' => (string) $row['issue_date'],
                    'client_label' => $clientName !== '' ? $clientName : ($clientCui !== '' ? $clientCui : '—'),
                ];
            }
        }

        if ($isPlatform && Database::tableExists('invoices_in')) {
            try {
                $latestInvoices = Database::fetchAll(
                    'SELECT id, invoice_number, supplier_name, issue_date, total_with_vat
                     FROM invoices_in
                     ORDER BY created_at DESC, id DESC
                     LIMIT 10'
                );
            } catch (\Throwable $exception) {
                $latestInvoices = [];
            }

            $monthIssuedRows = Database::fetchAll(
                'SELECT id, supplier_cui, selected_client_cui, commission_percent, total_with_vat,
                        COALESCE(fgo_date, issue_date) AS invoice_date, fgo_number
                 FROM invoices_in
                 WHERE COALESCE(fgo_date, issue_date) BETWEEN :start AND :end
                   AND (fgo_number IS NOT NULL AND fgo_number <> "")',
                ['start' => $monthStart, 'end' => $monthEnd]
            );

            $commissionMap = $this->commissionMap($monthIssuedRows);

            foreach ($monthIssuedRows as $row) {
                $commission = $this->resolveCommission($row, $commissionMap);
                $clientTotal = $commission !== 0.0
                    ? $this->applyCommission((float) $row['total_with_vat'], $commission)
                    : (float) $row['total_with_vat'];
                $monthIssuedTotal += $clientTotal;
                $monthIssuedCount++;
            }

            if (Database::tableExists('payments_in')) {
                $monthCollectedTotal = (float) (Database::fetchValue(
                    'SELECT COALESCE(SUM(amount), 0) FROM payments_in WHERE paid_at BETWEEN :start AND :end',
                    ['start' => $monthStart, 'end' => $monthEnd]
                ) ?? 0.0);
            }

            if (Database::tableExists('payments_out')) {
                $monthPaidTotal = (float) (Database::fetchValue(
                    'SELECT COALESCE(SUM(amount), 0) FROM payments_out WHERE paid_at BETWEEN :start AND :end',
                    ['start' => $monthStart, 'end' => $monthEnd]
                ) ?? 0.0);
            }

            $uncollectedRows = [];
            if (Database::tableExists('payment_in_allocations')) {
                $uncollectedRows = Database::fetchAll(
                    'SELECT i.id, i.invoice_number, i.issue_date, i.supplier_name, i.customer_name,
                            i.supplier_cui, i.selected_client_cui, i.commission_percent, i.total_with_vat,
                            COALESCE(SUM(a.amount), 0) AS collected
                     FROM invoices_in i
                     LEFT JOIN payment_in_allocations a ON a.invoice_in_id = i.id
                     WHERE (i.fgo_number IS NOT NULL AND i.fgo_number <> "")
                     GROUP BY i.id
                     ORDER BY i.issue_date DESC, i.id DESC'
                );
            }

            $clientMap = $this->partnerMap($this->collectClientCuis($uncollectedRows));
            $commissionMap = $this->commissionMap($uncollectedRows);

            foreach ($uncollectedRows as $row) {
                $commission = $this->resolveCommission($row, $commissionMap);
                $clientTotal = $commission !== 0.0
                    ? $this->applyCommission((float) $row['total_with_vat'], $commission)
                    : (float) $row['total_with_vat'];
                $collected = (float) $row['collected'];
                if ($collected + 0.01 >= $clientTotal) {
                    continue;
                }

                $clientCui = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
                $clientName = $clientCui !== '' ? ($clientMap[$clientCui] ?? '') : '';
                if ($clientName === '') {
                    $clientName = (string) ($row['customer_name'] ?? '');
                }

                $uncollectedInvoices[] = [
                    'invoice_number' => (string) $row['invoice_number'],
                    'issue_date' => (string) $row['issue_date'],
                    'supplier_name' => (string) ($row['supplier_name'] ?? ''),
                    'client_name' => $clientName !== '' ? $clientName : ($clientCui !== '' ? $clientCui : '—'),
                    'remaining' => max(0, $clientTotal - $collected),
                ];
            }
            $uncollectedCount = count($uncollectedInvoices);
        }

        $hasPackages = Database::tableExists('packages') && Database::tableExists('invoices_in');
        $hasConfirmed = $hasPackages && Database::columnExists('invoices_in', 'packages_confirmed');
        $hasFgoNumber = $hasPackages && Database::columnExists('invoices_in', 'fgo_number');
        $hasConfirmedAt = $hasPackages && Database::columnExists('invoices_in', 'packages_confirmed_at');

        if ($canAccessSaga && $hasConfirmed && $hasFgoNumber) {
            $orderBy = $hasConfirmedAt ? 'i.packages_confirmed_at' : 'i.issue_date';

            try {
                $pendingPackages = Database::fetchAll(
                    'SELECT p.id, p.package_no, p.label, p.invoice_in_id,
                            i.invoice_number, i.supplier_name, i.packages_confirmed_at
                     FROM packages p
                     JOIN invoices_in i ON i.id = p.invoice_in_id
                     WHERE i.packages_confirmed = 1
                       AND (i.fgo_number IS NULL OR i.fgo_number = "")' . ($supplierPlaceholders !== '' ? ' AND i.supplier_cui IN (' . $supplierPlaceholders . ')' : '') . '
                     ORDER BY ' . $orderBy . ' DESC, p.package_no ASC, p.id ASC
                     LIMIT 10',
                    $params
                );
            } catch (\Throwable $exception) {
                $pendingPackages = [];
            }
        }

        Response::view('admin/dashboard/index', [
            'user' => $user,
            'isPlatform' => $isPlatform,
            'isSupplierUser' => $isSupplierUser,
            'canAccessSaga' => $canAccessSaga,
            'latestInvoices' => $latestInvoices,
            'supplierLatestInvoices' => $supplierLatestInvoices,
            'supplierMonthCount' => $supplierMonthCount,
            'supplierDueInvoices' => $supplierDueInvoices,
            'pendingPackages' => $pendingPackages,
            'monthIssuedTotal' => $monthIssuedTotal,
            'monthIssuedCount' => $monthIssuedCount,
            'monthCollectedTotal' => $monthCollectedTotal,
            'monthPaidTotal' => $monthPaidTotal,
            'uncollectedInvoices' => $uncollectedInvoices,
            'uncollectedCount' => $uncollectedCount,
        ]);
    }

    private function collectClientCuis(array ...$rows): array
    {
        $cuis = [];
        foreach ($rows as $list) {
            foreach ($list as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
                if ($cui !== '') {
                    $cuis[$cui] = true;
                }
            }
        }

        return array_keys($cuis);
    }

    private function partnerMap(array $cuis): array
    {
        if (empty($cuis)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($cuis) as $index => $cui) {
            $key = 'c' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }

        $map = [];
        if (Database::tableExists('companies')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM companies WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $map[(string) $row['cui']] = (string) $row['denumire'];
            }
        }

        if (Database::tableExists('partners')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM partners WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $cui = (string) $row['cui'];
                if (!isset($map[$cui]) || $map[$cui] === '') {
                    $map[$cui] = (string) $row['denumire'];
                }
            }
        }

        return $map;
    }

    private function commissionMap(array $rows): array
    {
        if (empty($rows) || !Database::tableExists('commissions')) {
            return [];
        }

        $supplierSet = [];
        $clientSet = [];

        foreach ($rows as $row) {
            $supplier = preg_replace('/\D+/', '', (string) ($row['supplier_cui'] ?? ''));
            $client = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
            if ($supplier !== '' && $client !== '') {
                $supplierSet[$supplier] = true;
                $clientSet[$client] = true;
            }
        }

        if (empty($supplierSet) || empty($clientSet)) {
            return [];
        }

        $supplierPlaceholders = [];
        $clientPlaceholders = [];
        $params = [];

        foreach (array_keys($supplierSet) as $index => $supplier) {
            $key = 's' . $index;
            $supplierPlaceholders[] = ':' . $key;
            $params[$key] = $supplier;
        }

        foreach (array_keys($clientSet) as $index => $client) {
            $key = 'c' . $index;
            $clientPlaceholders[] = ':' . $key;
            $params[$key] = $client;
        }

        $rows = Database::fetchAll(
            'SELECT supplier_cui, client_cui, commission
             FROM commissions
             WHERE supplier_cui IN (' . implode(',', $supplierPlaceholders) . ')
               AND client_cui IN (' . implode(',', $clientPlaceholders) . ')',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $supplier = preg_replace('/\D+/', '', (string) $row['supplier_cui']);
            $client = preg_replace('/\D+/', '', (string) $row['client_cui']);
            $map[$supplier][$client] = (float) $row['commission'];
        }

        return $map;
    }

    private function resolveCommission(array $row, array $commissionMap): float
    {
        $commission = isset($row['commission_percent']) && $row['commission_percent'] !== null
            ? (float) $row['commission_percent']
            : null;

        if ($commission === null) {
            $supplier = preg_replace('/\D+/', '', (string) ($row['supplier_cui'] ?? ''));
            $client = preg_replace('/\D+/', '', (string) ($row['selected_client_cui'] ?? ''));
            if ($supplier !== '' && $client !== '' && isset($commissionMap[$supplier][$client])) {
                $commission = (float) $commissionMap[$supplier][$client];
            }
        }

        $discountCommission = $this->discountCommissionForInvoiceRow($row);
        if ($discountCommission !== null) {
            $invoiceId = (int) ($row['id'] ?? 0);
            if ($invoiceId > 0 && ($commission === null || abs($commission - $discountCommission) >= 0.000001)) {
                Database::execute(
                    'UPDATE invoices_in SET commission_percent = :commission, updated_at = :now WHERE id = :id',
                    [
                        'commission' => $discountCommission,
                        'now' => date('Y-m-d H:i:s'),
                        'id' => $invoiceId,
                    ]
                );
            }

            return $discountCommission;
        }

        return (float) ($commission ?? 0.0);
    }

    private function applyCommission(float $amount, float $percent): float
    {
        $factor = 1 + (abs($percent) / 100);
        if ($percent >= 0) {
            return round($amount * $factor, 2);
        }

        return round($amount / $factor, 2);
    }

    private function discountCommissionForInvoiceRow(array $row): ?float
    {
        $invoiceId = (int) ($row['id'] ?? 0);
        if ($invoiceId <= 0 || !Database::tableExists('invoice_in_lines')) {
            return null;
        }

        $invoiceGross = (float) ($row['total_with_vat'] ?? 0.0);
        if ($invoiceGross <= 0.0) {
            return null;
        }

        $salesGross = $this->invoiceSalesGrossTotal($invoiceId);
        if ($salesGross <= ($invoiceGross + 0.009)) {
            return null;
        }

        $percent = (($salesGross / $invoiceGross) - 1.0) * 100.0;
        if ($percent <= 0.0) {
            return null;
        }

        return round($percent, 6);
    }

    private function invoiceSalesGrossTotal(int $invoiceId): float
    {
        if (isset($this->invoiceSalesGrossCache[$invoiceId])) {
            return (float) $this->invoiceSalesGrossCache[$invoiceId];
        }

        $value = (float) (Database::fetchValue(
            'SELECT COALESCE(SUM(line_total_vat), 0) FROM invoice_in_lines WHERE invoice_in_id = :invoice',
            ['invoice' => $invoiceId]
        ) ?? 0.0);
        $this->invoiceSalesGrossCache[$invoiceId] = $value;

        return $value;
    }
}
