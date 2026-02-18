<?php

namespace App\Domain\Dashboard\Http\Controllers;

use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class DashboardController
{
    private array $invoiceSalesGrossCache = [];
    private array $invoicePackageSalesGrossCache = [];

    public function index(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }

        $isPlatform = $user->isPlatformUser();
        $isOperator = $user->isOperator();
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
        $showEnrollmentPendingCard = $isPlatform;
        $pendingEnrollmentSummary = $showEnrollmentPendingCard
            ? $this->pendingEnrollmentSummary()
            : [
                'total' => 0,
                'suppliers' => 0,
                'clients' => 0,
                'submitted_today' => 0,
                'association_pending' => 0,
            ];
        $showCommissionDailyChart = $isPlatform && $user->hasRole(['super_admin', 'admin']);
        $commissionDailyChart = [
            'days' => [],
            'max' => 0.0,
            'total' => 0.0,
            'month_label' => date('m.Y', strtotime($monthStart)),
            'has_data' => false,
        ];
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
                    'isOperator' => $isOperator,
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
                    'showEnrollmentPendingCard' => $showEnrollmentPendingCard,
                    'pendingEnrollmentSummary' => $pendingEnrollmentSummary,
                    'showCommissionDailyChart' => $showCommissionDailyChart,
                    'commissionDailyChart' => $commissionDailyChart,
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
                            i.commission_percent, i.total_with_vat,
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
                $totalSupplier = (float) ($row['total_with_vat'] ?? 0.0);
                $totalClient = $this->invoiceClientTotalForRow($row, $commission);
                $collectedNet = $this->collectedNetForSupplier($collected, $totalClient, $totalSupplier, $commission);
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
            if ($showCommissionDailyChart) {
                $commissionDailyChart = $this->buildDailyCommissionChart($monthIssuedRows, $commissionMap, $monthStart);
            }

            foreach ($monthIssuedRows as $row) {
                $commission = $this->resolveCommission($row, $commissionMap);
                $clientTotal = $this->invoiceClientTotalForRow($row, $commission);
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
                $clientTotal = $this->invoiceClientTotalForRow($row, $commission);
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
            'isOperator' => $isOperator,
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
            'showEnrollmentPendingCard' => $showEnrollmentPendingCard,
            'pendingEnrollmentSummary' => $pendingEnrollmentSummary,
            'showCommissionDailyChart' => $showCommissionDailyChart,
            'commissionDailyChart' => $commissionDailyChart,
        ]);
    }

    private function pendingEnrollmentSummary(): array
    {
        $summary = [
            'total' => 0,
            'suppliers' => 0,
            'clients' => 0,
            'submitted_today' => 0,
            'association_pending' => 0,
        ];
        if (!Database::tableExists('enrollment_links') || !Database::columnExists('enrollment_links', 'onboarding_status')) {
            return $summary;
        }

        $whereParts = ['onboarding_status = :submitted'];
        $params = ['submitted' => 'submitted'];
        if (Database::columnExists('enrollment_links', 'status')) {
            $whereParts[] = '(status IS NULL OR status = "" OR status = :active)';
            $params['active'] = 'active';
        }
        $whereSql = implode(' AND ', $whereParts);
        $typeSelect = Database::columnExists('enrollment_links', 'type') ? 'type' : "''";

        $rows = Database::fetchAll(
            'SELECT ' . $typeSelect . ' AS link_type, COUNT(*) AS total
             FROM enrollment_links
             WHERE ' . $whereSql . '
             GROUP BY link_type',
            $params
        );

        foreach ($rows as $row) {
            $count = (int) ($row['total'] ?? 0);
            $type = trim((string) ($row['link_type'] ?? ''));
            $summary['total'] += $count;
            if ($type === 'supplier') {
                $summary['suppliers'] += $count;
            } elseif ($type === 'client') {
                $summary['clients'] += $count;
            }
        }

        if (Database::columnExists('enrollment_links', 'submitted_at')) {
            $summary['submitted_today'] = (int) (Database::fetchValue(
                'SELECT COUNT(*)
                 FROM enrollment_links
                 WHERE ' . $whereSql . '
                   AND DATE(submitted_at) = :today',
                array_merge($params, ['today' => date('Y-m-d')])
            ) ?? 0);
        }

        if (Database::tableExists('association_requests') && Database::columnExists('association_requests', 'status')) {
            $summary['association_pending'] = (int) (Database::fetchValue(
                'SELECT COUNT(*) FROM association_requests WHERE status = :status',
                ['status' => 'pending']
            ) ?? 0);
        }

        return $summary;
    }

    private function buildDailyCommissionChart(array $monthIssuedRows, array $commissionMap, string $monthStart): array
    {
        $daysInMonth = (int) date('t', strtotime($monthStart));
        if ($daysInMonth <= 0) {
            $daysInMonth = (int) date('t');
        }

        $dailyTotals = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dailyTotals[$day] = 0.0;
        }

        foreach ($monthIssuedRows as $row) {
            $invoiceDate = trim((string) ($row['invoice_date'] ?? $row['issue_date'] ?? ''));
            if ($invoiceDate === '') {
                continue;
            }
            $timestamp = strtotime($invoiceDate);
            if ($timestamp === false) {
                continue;
            }
            $dayNo = (int) date('j', $timestamp);
            if ($dayNo < 1 || $dayNo > $daysInMonth) {
                continue;
            }

            $commission = $this->resolveCommission($row, $commissionMap);
            $clientTotal = $this->invoiceClientTotalForRow($row, $commission);
            $supplierTotal = (float) ($row['total_with_vat'] ?? 0.0);
            $commissionAmount = round($clientTotal - $supplierTotal, 2);
            if ($commissionAmount < 0.0) {
                $commissionAmount = 0.0;
            }
            $dailyTotals[$dayNo] += $commissionAmount;
        }

        $days = [];
        $maxValue = 0.0;
        $total = 0.0;
        foreach ($dailyTotals as $dayNo => $amount) {
            $value = round((float) $amount, 2);
            if (abs($value) < 0.005) {
                $value = 0.0;
            }
            if ($value > $maxValue) {
                $maxValue = $value;
            }
            $total += $value;
            $days[] = [
                'day' => (int) $dayNo,
                'value' => $value,
            ];
        }

        return [
            'days' => $days,
            'max' => round($maxValue, 2),
            'total' => round($total, 2),
            'month_label' => date('m.Y', strtotime($monthStart)),
            'has_data' => $maxValue > 0.0,
        ];
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

    private function invoiceClientTotalForRow(array $row, float $commission): float
    {
        $invoiceId = (int) ($row['id'] ?? 0);
        $invoiceGross = (float) ($row['total_with_vat'] ?? 0.0);
        if ($invoiceId <= 0) {
            return $this->applyCommission($invoiceGross, $commission);
        }

        $salesGross = $this->invoiceSalesGrossTotal($invoiceId);
        if ($invoiceGross > 0.0 && $salesGross > ($invoiceGross + 0.009)) {
            return round($salesGross, 2);
        }

        $packageTotals = $this->invoicePackageSalesGrossTotals($invoiceId);
        if (empty($packageTotals)) {
            return $this->applyCommission($invoiceGross, $commission);
        }

        $total = 0.0;
        foreach ($packageTotals as $packageGross) {
            if (abs((float) $packageGross) < 0.0001) {
                continue;
            }
            $total += $this->applyCommission((float) $packageGross, $commission);
        }

        return round($total, 2);
    }

    private function collectedNetForSupplier(float $collected, float $totalClient, float $totalSupplier, float $commission): float
    {
        if ($totalClient > 0.0 && $collected + 0.01 >= $totalClient) {
            return round($totalSupplier, 2);
        }

        if ($commission !== 0.0) {
            return $this->applyCommission($collected, -abs($commission));
        }

        return round($collected, 2);
    }

    private function discountCommissionForInvoiceRow(array $row): ?float
    {
        $invoiceId = (int) ($row['id'] ?? 0);
        if ($invoiceId <= 0 || !Database::tableExists('invoice_in_lines')) {
            return null;
        }

        $adjustmentCommission = $this->latestAdjustmentCommissionPercent($invoiceId);
        if ($adjustmentCommission !== null && $adjustmentCommission > 0.0) {
            return $adjustmentCommission;
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

    private function latestAdjustmentCommissionPercent(int $invoiceId): ?float
    {
        if ($invoiceId <= 0 || !Database::tableExists('invoice_adjustments')) {
            return null;
        }

        $value = Database::fetchValue(
            'SELECT commission_percent
             FROM invoice_adjustments
             WHERE invoice_in_id = :invoice
               AND commission_percent IS NOT NULL
             ORDER BY id DESC
             LIMIT 1',
            ['invoice' => $invoiceId]
        );
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
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

    private function invoicePackageSalesGrossTotals(int $invoiceId): array
    {
        if (isset($this->invoicePackageSalesGrossCache[$invoiceId])) {
            return $this->invoicePackageSalesGrossCache[$invoiceId];
        }
        if (
            $invoiceId <= 0
            || !Database::tableExists('invoice_in_lines')
            || !Database::columnExists('invoice_in_lines', 'package_id')
        ) {
            $this->invoicePackageSalesGrossCache[$invoiceId] = [];
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT package_id, COALESCE(SUM(line_total_vat), 0) AS total_vat
             FROM invoice_in_lines
             WHERE invoice_in_id = :invoice
               AND package_id IS NOT NULL
               AND package_id > 0
             GROUP BY package_id
             ORDER BY package_id ASC',
            ['invoice' => $invoiceId]
        );

        $totals = [];
        foreach ($rows as $row) {
            $packageId = (int) ($row['package_id'] ?? 0);
            if ($packageId <= 0) {
                continue;
            }
            $totals[$packageId] = (float) ($row['total_vat'] ?? 0.0);
        }
        $this->invoicePackageSalesGrossCache[$invoiceId] = $totals;

        return $totals;
    }
}
