<?php

namespace App\Domain\Reports\Http\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class ReportsController
{
    public function cashflow(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $period = $this->normalizeMonth((string) ($_GET['month'] ?? ''));
        $paymentsIn = $this->fetchPayments('payments_in', $period['start'], $period['end']);
        $paymentsOut = $this->fetchPayments('payments_out', $period['start'], $period['end']);
        $totalIn = $this->sumPayments($paymentsIn);
        $totalOut = $this->sumPayments($paymentsOut);

        Response::view('admin/reports/cashflow', [
            'month' => $period['month'],
            'start' => $period['start'],
            'end' => $period['end'],
            'paymentsIn' => $paymentsIn,
            'paymentsOut' => $paymentsOut,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => $totalIn - $totalOut,
        ]);
    }

    public function cashflowPdf(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $period = $this->normalizeMonth((string) ($_GET['month'] ?? ''));
        $paymentsIn = $this->fetchPayments('payments_in', $period['start'], $period['end']);
        $paymentsOut = $this->fetchPayments('payments_out', $period['start'], $period['end']);
        $totalIn = $this->sumPayments($paymentsIn);
        $totalOut = $this->sumPayments($paymentsOut);

        Response::view('admin/reports/cashflow_print', [
            'month' => $period['month'],
            'start' => $period['start'],
            'end' => $period['end'],
            'paymentsIn' => $paymentsIn,
            'paymentsOut' => $paymentsOut,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'net' => $totalIn - $totalOut,
        ], 'layouts/print');
    }

    public function exportCashflow(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $period = $this->normalizeMonth((string) ($_GET['month'] ?? ''));
        $paymentsIn = $this->fetchPayments('payments_in', $period['start'], $period['end']);
        $paymentsOut = $this->fetchPayments('payments_out', $period['start'], $period['end']);
        $totalIn = $this->sumPayments($paymentsIn);
        $totalOut = $this->sumPayments($paymentsOut);

        $filename = 'cashflow_' . $period['month'] . '_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Tip', 'Data', 'Partener', 'CUI', 'Suma', 'Observatii']);

        foreach ($paymentsIn as $row) {
            fputcsv($out, [
                'Incasare',
                $row['paid_at'] ?? '',
                $row['partner_name'] ?? '',
                $row['partner_cui'] ?? '',
                number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                $row['notes'] ?? '',
            ]);
        }

        foreach ($paymentsOut as $row) {
            fputcsv($out, [
                'Plata',
                $row['paid_at'] ?? '',
                $row['partner_name'] ?? '',
                $row['partner_cui'] ?? '',
                number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
                $row['notes'] ?? '',
            ]);
        }

        fputcsv($out, []);
        fputcsv($out, ['TOTAL', '', '', '', number_format($totalIn, 2, '.', ''), 'Incasari']);
        fputcsv($out, ['TOTAL', '', '', '', number_format($totalOut, 2, '.', ''), 'Plati']);
        fputcsv($out, ['TOTAL', '', '', '', number_format($totalIn - $totalOut, 2, '.', ''), 'Net']);

        fclose($out);
        exit;
    }

    public function supplierReport(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $suppliers = Database::tableExists('invoices_in')
            ? Database::fetchAll('SELECT DISTINCT supplier_cui, supplier_name FROM invoices_in ORDER BY supplier_name ASC')
            : [];

        $supplierCui  = trim((string) ($_GET['supplier_cui'] ?? ''));
        $dateStart    = trim((string) ($_GET['date_start'] ?? ''));
        $dateEnd      = trim((string) ($_GET['date_end'] ?? ''));

        if ($dateStart && !strtotime($dateStart)) {
            $dateStart = '';
        }
        if ($dateEnd && !strtotime($dateEnd)) {
            $dateEnd = '';
        }

        $supplierName  = '';
        $invoices      = [];
        $paymentsIn    = [];
        $paymentsOut   = [];
        $totalFurnizor = 0.0;
        $totalIncasat  = 0.0;
        $totalPlatit   = 0.0;

        $totalCuvenitFurnizorDinFacturi  = 0.0;
        $totalCuvenitFurnizorDinIncasat = 0.0;

        if ($supplierCui !== '') {
            foreach ($suppliers as $s) {
                if ((string) $s['supplier_cui'] === $supplierCui) {
                    $supplierName = (string) $s['supplier_name'];
                    break;
                }
            }

            [$invoices, $paymentsIn, $paymentsOut, $totalCuvenitFurnizorDinIncasat] = $this->fetchSupplierData(
                $supplierCui,
                $dateStart,
                $dateEnd
            );

            foreach ($invoices as $inv) {
                $totalFurnizor += (float) ($inv['total_with_vat'] ?? 0);
                $commission = (float) ($inv['commission_percent'] ?? 0);
                $totalCuvenitFurnizorDinFacturi += (float) ($inv['total_with_vat'] ?? 0) * (1 - $commission / 100);
            }
            foreach ($paymentsIn as $p) {
                $totalIncasat += (float) ($p['allocated_amount'] ?? 0);
            }
            foreach ($paymentsOut as $p) {
                $totalPlatit += (float) ($p['amount'] ?? 0);
            }
        }

        Response::view('admin/reports/supplier_report', [
            'suppliers'                      => $suppliers,
            'supplierCui'                    => $supplierCui,
            'supplierName'                   => $supplierName,
            'dateStart'                      => $dateStart,
            'dateEnd'                        => $dateEnd,
            'invoices'                       => $invoices,
            'paymentsIn'                     => $paymentsIn,
            'paymentsOut'                    => $paymentsOut,
            'totalFurnizor'                  => $totalFurnizor,
            'totalIncasat'                   => $totalIncasat,
            'totalPlatit'                    => $totalPlatit,
            'totalCuvenitFurnizorDinFacturi' => $totalCuvenitFurnizorDinFacturi,
            'totalCuvenitFurnizorDinIncasat' => $totalCuvenitFurnizorDinIncasat,
        ]);
    }

    public function supplierReportPrint(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $suppliers = Database::tableExists('invoices_in')
            ? Database::fetchAll('SELECT DISTINCT supplier_cui, supplier_name FROM invoices_in ORDER BY supplier_name ASC')
            : [];

        $supplierCui = trim((string) ($_GET['supplier_cui'] ?? ''));
        $dateStart   = trim((string) ($_GET['date_start'] ?? ''));
        $dateEnd     = trim((string) ($_GET['date_end'] ?? ''));

        if ($dateStart && !strtotime($dateStart)) {
            $dateStart = '';
        }
        if ($dateEnd && !strtotime($dateEnd)) {
            $dateEnd = '';
        }

        $supplierName  = '';
        $invoices      = [];
        $paymentsIn    = [];
        $paymentsOut   = [];
        $totalFurnizor = 0.0;
        $totalIncasat  = 0.0;
        $totalPlatit   = 0.0;

        $totalCuvenitFurnizorDinFacturi  = 0.0;
        $totalCuvenitFurnizorDinIncasat = 0.0;

        if ($supplierCui !== '') {
            foreach ($suppliers as $s) {
                if ((string) $s['supplier_cui'] === $supplierCui) {
                    $supplierName = (string) $s['supplier_name'];
                    break;
                }
            }

            [$invoices, $paymentsIn, $paymentsOut, $totalCuvenitFurnizorDinIncasat] = $this->fetchSupplierData(
                $supplierCui,
                $dateStart,
                $dateEnd
            );

            foreach ($invoices as $inv) {
                $totalFurnizor += (float) ($inv['total_with_vat'] ?? 0);
                $commission = (float) ($inv['commission_percent'] ?? 0);
                $totalCuvenitFurnizorDinFacturi += (float) ($inv['total_with_vat'] ?? 0) * (1 - $commission / 100);
            }
            foreach ($paymentsIn as $p) {
                $totalIncasat += (float) ($p['allocated_amount'] ?? 0);
            }
            foreach ($paymentsOut as $p) {
                $totalPlatit += (float) ($p['amount'] ?? 0);
            }
        }

        Response::view('admin/reports/supplier_report_print', [
            'supplierCui'                    => $supplierCui,
            'supplierName'                   => $supplierName,
            'dateStart'                      => $dateStart,
            'dateEnd'                        => $dateEnd,
            'invoices'                       => $invoices,
            'paymentsIn'                     => $paymentsIn,
            'paymentsOut'                    => $paymentsOut,
            'totalFurnizor'                  => $totalFurnizor,
            'totalIncasat'                   => $totalIncasat,
            'totalPlatit'                    => $totalPlatit,
            'totalCuvenitFurnizorDinFacturi' => $totalCuvenitFurnizorDinFacturi,
            'totalCuvenitFurnizorDinIncasat' => $totalCuvenitFurnizorDinIncasat,
        ], 'layouts/print');
    }

    private function fetchSupplierData(string $supplierCui, string $dateStart, string $dateEnd): array
    {
        // --- Invoices filtered by fgo_date ---
        $invWhere  = 'WHERE supplier_cui = :supplier_cui AND fgo_number IS NOT NULL';
        $invParams = ['supplier_cui' => $supplierCui];
        if ($dateStart) {
            $invWhere              .= ' AND fgo_date >= :date_start';
            $invParams['date_start'] = $dateStart;
        }
        if ($dateEnd) {
            $invWhere            .= ' AND fgo_date <= :date_end';
            $invParams['date_end'] = $dateEnd;
        }

        $invoices = Database::tableExists('invoices_in')
            ? Database::fetchAll(
                "SELECT id, fgo_series, fgo_number, fgo_date, invoice_number, issue_date,
                        selected_client_cui, total_with_vat, commission_percent
                 FROM invoices_in
                 $invWhere
                 ORDER BY fgo_date ASC, fgo_number ASC",
                $invParams
            )
            : [];

        // Build client name map from commissions/partners table
        $clientNames = [];
        if (Database::tableExists('commissions')) {
            $partners = Database::fetchAll(
                'SELECT c.client_cui, cp.denumire AS client_name
                 FROM commissions c
                 LEFT JOIN partners cp ON cp.cui = c.client_cui
                 WHERE c.supplier_cui = :supplier',
                ['supplier' => $supplierCui]
            );
            foreach ($partners as $p) {
                if (!empty($p['client_name'])) {
                    $clientNames[(string) $p['client_cui']] = (string) $p['client_name'];
                }
            }
        }
        // Also supplement from payments_in client names
        if (Database::tableExists('payments_in')) {
            $payClients = Database::fetchAll(
                'SELECT DISTINCT p.client_cui, p.client_name
                 FROM payments_in p
                 WHERE p.client_name != "" AND p.client_name IS NOT NULL'
            );
            foreach ($payClients as $pc) {
                $clientNames[(string) $pc['client_cui']] ??= (string) $pc['client_name'];
            }
        }

        foreach ($invoices as &$inv) {
            $cui              = (string) ($inv['selected_client_cui'] ?? '');
            $inv['client_name'] = $clientNames[$cui] ?? $cui;
        }
        unset($inv);

        // Build a shared IN clause for invoice IDs.
        // Payments and commission calculation are all anchored to the same
        // set of invoices, so totals remain consistent with what is displayed.
        $invoiceIds     = array_column($invoices, 'id');
        $inPlaceholders = [];
        $inParams       = [];
        foreach ($invoiceIds as $k => $id) {
            $inPlaceholders[]    = ':inv' . $k;
            $inParams['inv' . $k] = $id;
        }
        $inClause = implode(',', $inPlaceholders);

        // --- Payments IN allocated to the filtered invoices ---
        $paymentsIn = [];
        if (
            !empty($invoiceIds)
            && Database::tableExists('payments_in')
            && Database::tableExists('payment_in_allocations')
        ) {
            $paymentsIn = Database::fetchAll(
                "SELECT p.id, p.paid_at, p.client_cui, p.client_name,
                        SUM(a.amount) AS allocated_amount, p.notes
                 FROM payments_in p
                 JOIN payment_in_allocations a ON a.payment_in_id = p.id
                 WHERE a.invoice_in_id IN ($inClause)
                 GROUP BY p.id, p.paid_at, p.client_cui, p.client_name, p.notes
                 ORDER BY p.paid_at ASC, p.id ASC",
                $inParams
            );
        }

        // --- Supplier's net share from collected amounts (after deducting commission) ---
        // This is what the user actually owes the supplier from client payments.
        $totalCuvenitFurnizorDinIncasat = 0.0;
        if (
            !empty($invoiceIds)
            && Database::tableExists('payment_in_allocations')
            && Database::tableExists('invoices_in')
        ) {
            $allocRows = Database::fetchAll(
                "SELECT a.amount AS allocated_amount, i.commission_percent
                 FROM payment_in_allocations a
                 JOIN invoices_in i ON i.id = a.invoice_in_id
                 WHERE a.invoice_in_id IN ($inClause)",
                $inParams
            );
            foreach ($allocRows as $row) {
                $commission = (float) ($row['commission_percent'] ?? 0);
                $totalCuvenitFurnizorDinIncasat += (float) ($row['allocated_amount'] ?? 0) * (1 - $commission / 100);
            }
        }

        // --- Payments OUT allocated to the filtered invoices ---
        $paymentsOut = [];
        if (
            !empty($invoiceIds)
            && Database::tableExists('payments_out')
            && Database::tableExists('payment_out_allocations')
        ) {
            $paymentsOut = Database::fetchAll(
                "SELECT p.id, p.paid_at, p.amount, p.notes
                 FROM payments_out p
                 JOIN payment_out_allocations a ON a.payment_out_id = p.id
                 WHERE a.invoice_in_id IN ($inClause)
                 GROUP BY p.id, p.paid_at, p.amount, p.notes
                 ORDER BY p.paid_at ASC, p.id ASC",
                $inParams
            );
        }

        return [$invoices, $paymentsIn, $paymentsOut, $totalCuvenitFurnizorDinIncasat];
    }

    private function fetchPayments(string $table, string $start, string $end): array
    {
        if (!Database::tableExists($table)) {
            return [];
        }

        if ($table === 'payments_in') {
            $sql = 'SELECT id, client_cui AS partner_cui, client_name AS partner_name, amount, paid_at, notes
                    FROM payments_in
                    WHERE paid_at BETWEEN :start AND :end
                    ORDER BY paid_at ASC, id ASC';
        } else {
            $sql = 'SELECT id, supplier_cui AS partner_cui, supplier_name AS partner_name, amount, paid_at, notes
                    FROM payments_out
                    WHERE paid_at BETWEEN :start AND :end
                    ORDER BY paid_at ASC, id ASC';
        }

        return Database::fetchAll($sql, ['start' => $start, 'end' => $end]);
    }

    private function sumPayments(array $rows): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += (float) ($row['amount'] ?? 0);
        }

        return $total;
    }

    private function normalizeMonth(string $month): array
    {
        $value = trim($month);
        $current = date('Y-m');

        if (!preg_match('/^(\\d{4})-(\\d{2})$/', $value, $matches)) {
            $value = $current;
            $matches = [null, substr($current, 0, 4), substr($current, 5, 2)];
        }

        $year = (int) $matches[1];
        $monthNum = (int) $matches[2];

        if ($year <= 0 || $monthNum < 1 || $monthNum > 12) {
            $value = $current;
        }

        $start = $value . '-01';
        $startDate = new \DateTime($start);
        $endDate = (clone $startDate)->modify('last day of this month');

        return [
            'month' => $value,
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
        ];
    }

    private function ensurePaymentTables(): bool
    {
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS payments_in (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    client_cui VARCHAR(32) NOT NULL,
                    client_name VARCHAR(255) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    paid_at DATE NOT NULL,
                    notes TEXT NULL,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            Database::execute(
                'CREATE TABLE IF NOT EXISTS payment_in_allocations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    payment_in_id BIGINT UNSIGNED NOT NULL,
                    invoice_in_id BIGINT UNSIGNED NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            Database::execute(
                'CREATE TABLE IF NOT EXISTS payments_out (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    supplier_cui VARCHAR(32) NOT NULL,
                    supplier_name VARCHAR(255) NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    paid_at DATE NOT NULL,
                    notes TEXT NULL,
                    email_sent_at DATETIME NULL,
                    email_status VARCHAR(32) NULL,
                    email_message TEXT NULL,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            Database::execute(
                'CREATE TABLE IF NOT EXISTS payment_out_allocations (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    payment_out_id BIGINT UNSIGNED NOT NULL,
                    invoice_in_id BIGINT UNSIGNED NOT NULL,
                    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
