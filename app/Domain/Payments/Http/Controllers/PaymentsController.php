<?php

namespace App\Domain\Payments\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Settings\Services\SettingsService;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class PaymentsController
{
    public function indexIn(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $rows = Database::fetchAll(
            'SELECT p.*, COALESCE(SUM(a.amount), 0) AS allocated
             FROM payments_in p
             LEFT JOIN payment_in_allocations a ON a.payment_in_id = p.id
             GROUP BY p.id
             ORDER BY p.paid_at DESC, p.id DESC'
        );

        Response::view('admin/payments/in/index', [
            'payments' => $rows,
        ]);
    }

    public function createIn(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $clientCui = preg_replace('/\D+/', '', (string) ($_GET['client_cui'] ?? ''));
        $clients = $this->availableClients();
        $invoices = [];

        if ($clientCui !== '') {
            $invoices = $this->clientInvoicesWithBalances($clientCui);
        }

        Response::view('admin/payments/in/create', [
            'clients' => $clients,
            'clientCui' => $clientCui,
            'invoices' => $invoices,
        ]);
    }

    public function storeIn(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru incasari.');
            Response::redirect('/admin/incasari');
        }

        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));
        $amountInput = $this->parseNumber($_POST['amount'] ?? null);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $allocationsInput = $_POST['allocations'] ?? [];

        if ($clientCui === '' || $paidAt === '') {
            Session::flash('error', 'Completeaza clientul si data incasarii.');
            Response::redirect('/admin/incasari/adauga');
        }

        $client = Partner::findByCui($clientCui);
        $clientName = $client ? $client->denumire : $clientCui;

        $allocations = [];
        $allocatedTotal = 0.0;

        foreach ((array) $allocationsInput as $invoiceId => $value) {
            $amount = $this->parseNumber($value);
            if ($amount === null || $amount <= 0) {
                continue;
            }
            $allocations[] = [
                'invoice_id' => (int) $invoiceId,
                'amount' => $amount,
            ];
            $allocatedTotal += $amount;
        }

        $amount = $amountInput ?? $allocatedTotal;
        if ($amount <= 0) {
            Session::flash('error', 'Completeaza suma incasata.');
            Response::redirect('/admin/incasari/adauga?client_cui=' . $clientCui);
        }

        if ($allocatedTotal > $amount) {
            Session::flash('error', 'Suma alocata depaseste suma incasata.');
            Response::redirect('/admin/incasari/adauga?client_cui=' . $clientCui);
        }

        Database::execute(
            'INSERT INTO payments_in (client_cui, client_name, amount, paid_at, notes, created_at)
             VALUES (:client_cui, :client_name, :amount, :paid_at, :notes, :created_at)',
            [
                'client_cui' => $clientCui,
                'client_name' => $clientName,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $paymentId = (int) Database::lastInsertId();

        foreach ($allocations as $allocation) {
            Database::execute(
                'INSERT INTO payment_in_allocations (payment_in_id, invoice_in_id, amount, created_at)
                 VALUES (:payment, :invoice, :amount, :created_at)',
                [
                    'payment' => $paymentId,
                    'invoice' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        Session::flash('status', 'Incasarea a fost salvata.');
        Response::redirect('/admin/incasari');
    }

    public function indexOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $suppliers = $this->supplierSummary();

        Response::view('admin/payments/out/index', [
            'suppliers' => $suppliers,
        ]);
    }

    public function createOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $supplierCui = preg_replace('/\D+/', '', (string) ($_GET['supplier_cui'] ?? ''));
        $suppliers = $this->availableSuppliers();
        $invoices = [];

        if ($supplierCui !== '') {
            $invoices = $this->supplierInvoicesWithBalances($supplierCui);
        }

        $supplierSummary = null;
        if ($supplierCui !== '') {
            foreach ($this->supplierSummary() as $row) {
                if ($row['supplier_cui'] === $supplierCui) {
                    $supplierSummary = $row;
                    break;
                }
            }
        }

        Response::view('admin/payments/out/create', [
            'suppliers' => $suppliers,
            'supplierCui' => $supplierCui,
            'invoices' => $invoices,
            'supplierSummary' => $supplierSummary,
        ]);
    }

    public function storeOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru plati.');
            Response::redirect('/admin/plati');
        }

        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $paidAt = trim((string) ($_POST['paid_at'] ?? ''));
        $amountInput = $this->parseNumber($_POST['amount'] ?? null);
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $allocationsInput = $_POST['allocations'] ?? [];

        if ($supplierCui === '' || $paidAt === '') {
            Session::flash('error', 'Completeaza furnizorul si data platii.');
            Response::redirect('/admin/plati/adauga');
        }

        $supplier = Partner::findByCui($supplierCui);
        $supplierName = $supplier ? $supplier->denumire : $supplierCui;

        $allocations = [];
        $allocatedTotal = 0.0;

        foreach ((array) $allocationsInput as $invoiceId => $value) {
            $amount = $this->parseNumber($value);
            if ($amount === null || $amount <= 0) {
                continue;
            }
            $allocations[] = [
                'invoice_id' => (int) $invoiceId,
                'amount' => $amount,
            ];
            $allocatedTotal += $amount;
        }

        if (empty($allocations)) {
            Session::flash('error', 'Selecteaza cel putin o factura pentru plata.');
            Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
        }

        $amount = $allocatedTotal;
        if ($amount <= 0) {
            Session::flash('error', 'Suma alocata este invalida.');
            Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
        }

        if ($allocatedTotal > $amount) {
            Session::flash('error', 'Suma alocata depaseste suma platita.');
            Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
        }

        Database::execute(
            'INSERT INTO payments_out (supplier_cui, supplier_name, amount, paid_at, notes, created_at)
             VALUES (:supplier_cui, :supplier_name, :amount, :paid_at, :notes, :created_at)',
            [
                'supplier_cui' => $supplierCui,
                'supplier_name' => $supplierName,
                'amount' => $amount,
                'paid_at' => $paidAt,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $paymentId = (int) Database::lastInsertId();

        foreach ($allocations as $allocation) {
            Database::execute(
                'INSERT INTO payment_out_allocations (payment_out_id, invoice_in_id, amount, created_at)
                 VALUES (:payment, :invoice, :amount, :created_at)',
                [
                    'payment' => $paymentId,
                    'invoice' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        Session::flash('status', 'Plata a fost salvata.');
        Response::redirect('/admin/plati');
    }

    public function sendDailyEmails(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru plati.');
            Response::redirect('/admin/plati');
        }

        $today = date('Y-m-d');
        $rows = Database::fetchAll(
            'SELECT o.*, a.invoice_in_id, a.amount AS alloc_amount, i.invoice_number
             FROM payments_out o
             LEFT JOIN payment_out_allocations a ON a.payment_out_id = o.id
             LEFT JOIN invoices_in i ON i.id = a.invoice_in_id
             WHERE o.paid_at = :date AND (o.email_sent_at IS NULL OR o.email_sent_at = "")
             ORDER BY o.supplier_cui ASC, o.id ASC',
            ['date' => $today]
        );

        if (empty($rows)) {
            Session::flash('status', 'Nu exista plati de trimis azi.');
            Response::redirect('/admin/plati');
        }

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['supplier_cui']][] = $row;
        }

        $settings = new SettingsService();
        $fromEmail = (string) $settings->get('company.email', '');

        foreach ($grouped as $supplierCui => $items) {
            $company = Company::findByCui($supplierCui);
            $to = $company?->email ?? '';
            if ($to === '') {
                Database::execute(
                    'UPDATE payments_out SET email_status = :status, email_message = :message, updated_at = :now
                     WHERE supplier_cui = :supplier AND paid_at = :date AND (email_sent_at IS NULL OR email_sent_at = "")',
                    [
                        'status' => 'missing_email',
                        'message' => 'Lipsa email furnizor.',
                        'now' => date('Y-m-d H:i:s'),
                        'supplier' => $supplierCui,
                        'date' => $today,
                    ]
                );
                continue;
            }

            $subject = 'Plati efectuate - ' . $today;
            $lines = ['Buna ziua,', '', 'Au fost efectuate plati catre compania dvs:', ''];

            $byPayment = [];
            foreach ($items as $row) {
                $byPayment[$row['id']][] = $row;
            }

            foreach ($byPayment as $paymentId => $allocs) {
                $payment = $allocs[0];
                $lines[] = 'Plata ' . $payment['id'] . ' | Data: ' . $payment['paid_at'] . ' | Suma: ' . number_format((float) $payment['amount'], 2, '.', ' ') . ' RON';
                foreach ($allocs as $alloc) {
                    if ($alloc['invoice_in_id']) {
                        $lines[] = ' - Factura ' . $alloc['invoice_number'] . ': ' . number_format((float) $alloc['alloc_amount'], 2, '.', ' ') . ' RON';
                    }
                }
                $lines[] = '';
            }

            $lines[] = 'Cu respect,';
            $lines[] = $settings->get('company.denumire', 'ERP');

            $headers = [];
            if ($fromEmail !== '') {
                $headers[] = 'From: ' . $fromEmail;
                $headers[] = 'Reply-To: ' . $fromEmail;
            }
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';

            $sent = @mail($to, $subject, implode("\n", $lines), implode("\r\n", $headers));
            $status = $sent ? 'sent' : 'failed';
            $message = $sent ? 'Email trimis.' : 'Eroare trimitere.';

            Database::execute(
                'UPDATE payments_out SET email_sent_at = :sent_at, email_status = :status, email_message = :message, updated_at = :now
                 WHERE supplier_cui = :supplier AND paid_at = :date AND (email_sent_at IS NULL OR email_sent_at = "")',
                [
                    'sent_at' => $sent ? date('Y-m-d H:i:s') : null,
                    'status' => $status,
                    'message' => $message,
                    'now' => date('Y-m-d H:i:s'),
                    'supplier' => $supplierCui,
                    'date' => $today,
                ]
            );
        }

        Session::flash('status', 'Emailurile pentru platile de azi au fost procesate.');
        Response::redirect('/admin/plati');
    }

    private function availableClients(): array
    {
        $rows = Commission::allWithPartners();
        $clients = [];

        foreach ($rows as $row) {
            $cui = (string) ($row['client_cui'] ?? '');
            if ($cui === '') {
                continue;
            }
            $clients[$cui] = [
                'cui' => $cui,
                'name' => (string) ($row['client_name'] ?? $cui),
            ];
        }

        return array_values($clients);
    }

    private function availableSuppliers(): array
    {
        if (!Database::tableExists('invoices_in')) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT DISTINCT supplier_cui, supplier_name
             FROM invoices_in
             WHERE supplier_cui IS NOT NULL AND supplier_cui <> ""
             ORDER BY supplier_name ASC'
        );

        return array_map(static function (array $row): array {
            return [
                'cui' => (string) $row['supplier_cui'],
                'name' => (string) ($row['supplier_name'] ?? $row['supplier_cui']),
            ];
        }, $rows);
    }

    private function clientInvoicesWithBalances(string $clientCui): array
    {
        $rows = Database::fetchAll(
            'SELECT i.*, COALESCE(SUM(a.amount), 0) AS collected
             FROM invoices_in i
             LEFT JOIN payment_in_allocations a ON a.invoice_in_id = i.id
             WHERE i.selected_client_cui = :client
             GROUP BY i.id
             ORDER BY i.issue_date DESC, i.id DESC',
            ['client' => $clientCui]
        );

        $invoices = [];

        foreach ($rows as $row) {
            $commission = $row['commission_percent'] ?? null;
            if ($commission === null) {
                $assoc = Commission::forSupplierClient($row['supplier_cui'], $clientCui);
                $commission = $assoc ? $assoc->commission : 0.0;
            }

            $totalClient = $this->applyCommission((float) $row['total_with_vat'], (float) $commission);
            $collected = (float) $row['collected'];

            $invoices[] = [
                'id' => (int) $row['id'],
                'invoice_number' => (string) $row['invoice_number'],
                'issue_date' => (string) $row['issue_date'],
                'total_client' => $totalClient,
                'collected' => $collected,
                'balance' => max(0, $totalClient - $collected),
            ];
        }

        return $invoices;
    }

    private function supplierInvoicesWithBalances(string $supplierCui): array
    {
        $rows = Database::fetchAll(
            'SELECT i.*, COALESCE(SUM(o.amount), 0) AS paid, COALESCE(SUM(a.amount), 0) AS collected
             FROM invoices_in i
             LEFT JOIN payment_out_allocations o ON o.invoice_in_id = i.id
             LEFT JOIN payment_in_allocations a ON a.invoice_in_id = i.id
             WHERE i.supplier_cui = :supplier
             GROUP BY i.id
             ORDER BY i.issue_date DESC, i.id DESC',
            ['supplier' => $supplierCui]
        );

        $invoices = [];

        foreach ($rows as $row) {
            $commission = $row['commission_percent'] ?? null;
            if ($commission === null && !empty($row['selected_client_cui'])) {
                $assoc = Commission::forSupplierClient($supplierCui, $row['selected_client_cui']);
                $commission = $assoc ? $assoc->commission : 0.0;
            }

            $totalSupplier = (float) $row['total_with_vat'];
            $paid = (float) $row['paid'];
            $collected = (float) $row['collected'];
            $collectedNet = $commission !== null && $commission !== 0.0
                ? $this->applyCommission($collected, -abs($commission))
                : $collected;
            $available = max(0, $collectedNet - $paid);

            $invoices[] = [
                'id' => (int) $row['id'],
                'invoice_number' => (string) $row['invoice_number'],
                'issue_date' => (string) $row['issue_date'],
                'total_supplier' => $totalSupplier,
                'paid' => $paid,
                'balance' => max(0, $totalSupplier - $paid),
                'available' => max(0, $available),
            ];
        }

        return $invoices;
    }

    private function supplierSummary(): array
    {
        if (!Database::tableExists('invoices_in')) {
            return [];
        }

        $invoices = Database::fetchAll(
            'SELECT id, supplier_cui, supplier_name, selected_client_cui, commission_percent, total_with_vat
             FROM invoices_in
             WHERE supplier_cui IS NOT NULL AND supplier_cui <> ""
             ORDER BY supplier_name ASC'
        );

        $collectedRows = Database::fetchAll(
            'SELECT invoice_in_id, COALESCE(SUM(amount), 0) AS collected
             FROM payment_in_allocations
             GROUP BY invoice_in_id'
        );
        $paidRows = Database::fetchAll(
            'SELECT invoice_in_id, COALESCE(SUM(amount), 0) AS paid
             FROM payment_out_allocations
             GROUP BY invoice_in_id'
        );

        $collectedMap = [];
        foreach ($collectedRows as $row) {
            $collectedMap[(int) $row['invoice_in_id']] = (float) $row['collected'];
        }

        $paidMap = [];
        foreach ($paidRows as $row) {
            $paidMap[(int) $row['invoice_in_id']] = (float) $row['paid'];
        }

        $summary = [];

        foreach ($invoices as $row) {
            $invoiceId = (int) $row['id'];
            $supplierCui = (string) $row['supplier_cui'];
            $supplierName = (string) ($row['supplier_name'] ?? $supplierCui);
            $commission = $row['commission_percent'];

            if ($commission === null && !empty($row['selected_client_cui'])) {
                $assoc = Commission::forSupplierClient($supplierCui, (string) $row['selected_client_cui']);
                $commission = $assoc ? $assoc->commission : 0.0;
            }

            $commission = (float) ($commission ?? 0.0);
            $collected = (float) ($collectedMap[$invoiceId] ?? 0.0);
            $paid = (float) ($paidMap[$invoiceId] ?? 0.0);

            $collectedNet = $commission !== 0.0
                ? $this->applyCommission($collected, -abs($commission))
                : $collected;

            if (!isset($summary[$supplierCui])) {
                $summary[$supplierCui] = [
                    'supplier_cui' => $supplierCui,
                    'supplier_name' => $supplierName,
                    'collected_net' => 0.0,
                    'paid' => 0.0,
                ];
            }

            $summary[$supplierCui]['collected_net'] += $collectedNet;
            $summary[$supplierCui]['paid'] += $paid;
        }

        $result = [];
        foreach ($summary as $row) {
            $due = $row['collected_net'] - $row['paid'];
            if ($due <= 0) {
                continue;
            }
            $row['due'] = $due;
            $result[] = $row;
        }

        usort($result, function (array $a, array $b): int {
            return $b['due'] <=> $a['due'];
        });

        return $result;
    }

    private function applyCommission(float $amount, float $percent): float
    {
        $factor = 1 + (abs($percent) / 100);
        if ($percent >= 0) {
            return round($amount * $factor, 2);
        }

        return round($amount / $factor, 2);
    }

    private function parseNumber(mixed $value): ?float
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $raw);
        if (!is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
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
