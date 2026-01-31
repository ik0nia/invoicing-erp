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
