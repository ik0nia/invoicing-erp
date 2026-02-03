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

    public function deleteIn(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru incasari.');
            Response::redirect('/admin/incasari');
        }

        $paymentId = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
        if (!$paymentId) {
            Response::redirect('/admin/incasari');
        }

        Database::execute('DELETE FROM payment_in_allocations WHERE payment_in_id = :id', ['id' => $paymentId]);
        Database::execute('DELETE FROM payments_in WHERE id = :id', ['id' => $paymentId]);

        Session::flash('status', 'Incasarea a fost stearsa.');
        Response::redirect('/admin/incasari/istoric');
    }

    public function historyIn(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;

        $where = [];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'p.paid_at >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'p.paid_at <= :to';
            $params['to'] = $dateTo;
        }
        if ($paymentId) {
            $where[] = 'p.id = :payment_id';
            $params['payment_id'] = $paymentId;
        }

        $sql = 'SELECT p.* FROM payments_in p';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.paid_at DESC, p.id DESC';

        $payments = Database::fetchAll($sql, $params);
        $allocations = $this->fetchAllocations('payment_in_allocations', 'payment_in_id', $payments, 'invoice_in_id');

        Response::view('admin/payments/in/history', [
            'payments' => $payments,
            'allocations' => $allocations,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'paymentId' => $paymentId,
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
            $stornoRow = Database::fetchOne(
                'SELECT id FROM invoices_in WHERE id = :id
                 AND (fgo_storno_number IS NOT NULL AND fgo_storno_number <> ""
                      OR fgo_storno_series IS NOT NULL AND fgo_storno_series <> ""
                      OR fgo_storno_link IS NOT NULL AND fgo_storno_link <> "")',
                ['id' => (int) $invoiceId]
            );
            if ($stornoRow) {
                Session::flash('error', 'Nu poti incasa o factura stornata.');
                Response::redirect('/admin/incasari/adauga?client_cui=' . $clientCui);
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

    public function deleteOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru plati.');
            Response::redirect('/admin/plati');
        }

        $paymentId = isset($_POST['payment_id']) ? (int) $_POST['payment_id'] : 0;
        if (!$paymentId) {
            Response::redirect('/admin/plati');
        }

        Database::execute('DELETE FROM payment_out_allocations WHERE payment_out_id = :id', ['id' => $paymentId]);
        Database::execute('DELETE FROM payments_out WHERE id = :id', ['id' => $paymentId]);

        Session::flash('status', 'Plata a fost stearsa.');
        Response::redirect('/admin/plati/istoric');
    }

    public function historyOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_GET['supplier_cui'] ?? ''));
        $paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;

        $where = [];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'p.paid_at >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'p.paid_at <= :to';
            $params['to'] = $dateTo;
        }
        if ($supplierCui !== '') {
            $where[] = 'p.supplier_cui = :supplier';
            $params['supplier'] = $supplierCui;
        }
        if ($paymentId) {
            $where[] = 'p.id = :payment_id';
            $params['payment_id'] = $paymentId;
        }

        $sql = 'SELECT p.* FROM payments_out p';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.paid_at DESC, p.id DESC';

        $payments = Database::fetchAll($sql, $params);
        $allocations = $this->fetchAllocations('payment_out_allocations', 'payment_out_id', $payments, 'invoice_in_id');
        $suppliers = $this->paymentSuppliers();
        $orderMarks = $this->paymentOrderMarks();

        Response::view('admin/payments/out/history', [
            'payments' => $payments,
            'allocations' => $allocations,
            'suppliers' => $suppliers,
            'supplierCui' => $supplierCui,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'orderMarks' => $orderMarks,
            'paymentId' => $paymentId,
        ]);
    }

    public function printOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $paymentId = isset($_GET['payment_id']) ? (int) $_GET['payment_id'] : 0;
        if (!$paymentId) {
            Response::redirect('/admin/plati/istoric');
        }

        $payment = Database::fetchOne(
            'SELECT * FROM payments_out WHERE id = :id LIMIT 1',
            ['id' => $paymentId]
        );

        if (!$payment) {
            Session::flash('error', 'Plata nu a fost gasita.');
            Response::redirect('/admin/plati/istoric');
        }

        $hasPartners = Database::tableExists('partners');
        $rows = Database::fetchAll(
            'SELECT a.amount, i.invoice_number, i.selected_client_cui, i.fgo_series, i.fgo_number'
                . ($hasPartners ? ', c.denumire AS selected_client_name' : '')
                . ' FROM payment_out_allocations a
             LEFT JOIN invoices_in i ON i.id = a.invoice_in_id'
                . ($hasPartners ? ' LEFT JOIN partners c ON c.cui = i.selected_client_cui' : '')
                . ' WHERE a.payment_out_id = :payment
             ORDER BY a.id ASC',
            ['payment' => $paymentId]
        );

        $totalAllocated = 0.0;
        foreach ($rows as $row) {
            $totalAllocated += (float) $row['amount'];
        }

        Response::view('admin/payments/out/print', [
            'payment' => $payment,
            'allocations' => $rows,
            'totalAllocated' => $totalAllocated,
        ], 'layouts/print');
    }

    public function exportOut(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Response::view('errors/schema', [], 'layouts/app');
        }

        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_GET['supplier_cui'] ?? ''));

        $where = [];
        $params = [];

        if ($dateFrom !== '') {
            $where[] = 'p.paid_at >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'p.paid_at <= :to';
            $params['to'] = $dateTo;
        }
        if ($supplierCui !== '') {
            $where[] = 'p.supplier_cui = :supplier';
            $params['supplier'] = $supplierCui;
        }

        $sql = 'SELECT p.* FROM payments_out p';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY p.paid_at DESC, p.id DESC';

        $payments = Database::fetchAll($sql, $params);
        $allocations = $this->fetchAllocations('payment_out_allocations', 'payment_out_id', $payments, 'invoice_in_id');

        $filename = 'plati_furnizori_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Data', 'Furnizor', 'CUI', 'Suma plata', 'Factura', 'Suma alocata']);

        foreach ($payments as $payment) {
            $rows = $allocations[$payment['id']] ?? [];
            if (empty($rows)) {
                fputcsv($out, [
                    $payment['paid_at'],
                    $payment['supplier_name'],
                    $payment['supplier_cui'],
                    number_format((float) $payment['amount'], 2, '.', ''),
                    '',
                    '',
                ]);
                continue;
            }
            foreach ($rows as $alloc) {
                fputcsv($out, [
                    $payment['paid_at'],
                    $payment['supplier_name'],
                    $payment['supplier_cui'],
                    number_format((float) $payment['amount'], 2, '.', ''),
                    $alloc['invoice_number'] ?? '',
                    number_format((float) $alloc['amount'], 2, '.', ''),
                ]);
            }
        }

        fclose($out);
        exit;
    }

    public function exportPaymentOrder(): void
    {
        Auth::requireAdmin();

        if (!$this->ensurePaymentTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru plati.');
            Response::redirect('/admin/plati/istoric');
        }

        $supplierCuis = array_values(array_filter(array_map('strval', (array) ($_POST['supplier_cuis'] ?? []))));
        $dateFrom = trim((string) ($_POST['date_from'] ?? ''));
        $dateTo = trim((string) ($_POST['date_to'] ?? ''));

        if (empty($supplierCuis)) {
            Session::flash('error', 'Selecteaza cel putin un furnizor.');
            Response::redirect('/admin/plati/istoric' . $this->historyQuery($dateFrom, $dateTo));
        }

        $settings = new SettingsService();
        $platformIban = trim((string) $settings->get('company.iban', ''));
        $platformIban = preg_replace('/\s+/', '', $platformIban);
        if ($platformIban === '') {
            Session::flash('error', 'Completeaza IBAN-ul platformei in setari.');
            Response::redirect('/admin/plati/istoric' . $this->historyQuery($dateFrom, $dateTo));
        }

        $suppliers = $this->fetchSuppliersForOrders($supplierCuis);
        $missingIban = [];
        foreach ($suppliers as $supplier) {
            if (trim((string) $supplier['iban']) === '') {
                $missingIban[] = $supplier['name'] ?: $supplier['cui'];
            }
        }
        if (!empty($missingIban)) {
            Session::flash('error', 'Lipseste IBAN pentru: ' . implode(', ', $missingIban));
            Response::redirect('/admin/plati/istoric' . $this->historyQuery($dateFrom, $dateTo));
        }

        $orderData = $this->buildPaymentOrders($supplierCuis, $dateFrom, $dateTo);
        if (empty($orderData)) {
            Session::flash('error', 'Nu exista plati alocate pentru furnizorii selectati.');
            Response::redirect('/admin/plati/istoric' . $this->historyQuery($dateFrom, $dateTo));
        }

        $now = date('Y-m-d H:i:s');
        foreach ($orderData as $supplierCui => $row) {
            Database::execute(
                'INSERT INTO payment_orders (supplier_cui, supplier_name, date_from, date_to, total_amount, invoice_numbers, generated_at, created_at)
                 VALUES (:supplier_cui, :supplier_name, :date_from, :date_to, :total_amount, :invoice_numbers, :generated_at, :created_at)',
                [
                    'supplier_cui' => $supplierCui,
                    'supplier_name' => $row['supplier_name'],
                    'date_from' => $dateFrom !== '' ? $dateFrom : null,
                    'date_to' => $dateTo !== '' ? $dateTo : null,
                    'total_amount' => $row['total'],
                    'invoice_numbers' => $row['invoices'],
                    'generated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $filename = 'ordine_plata_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        foreach ($orderData as $supplierCui => $row) {
            $supplier = $suppliers[$supplierCui] ?? null;
            if (!$supplier) {
                continue;
            }
            $iban = preg_replace('/\s+/', '', (string) ($supplier['iban'] ?? ''));
            $details = '';
            if (!empty($row['payment_codes'])) {
                $codes = array_map(static fn (int $id): string => 'Nr ordin de plata: ' . $id, $row['payment_codes']);
                $details = implode(', ', $codes);
            }
            $line = [
                $this->csvQuote($platformIban),
                $this->csvQuote($supplier['name']),
                $this->csvQuote($supplierCui),
                $this->csvQuote($iban),
                $this->csvQuote(number_format($row['total'], 2, '.', '')),
                $this->csvQuote($details),
            ];
            fwrite($out, implode(',', $line) . "\n");
        }
        fclose($out);
        exit;
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

        $invoiceMap = [];
        foreach ($this->supplierInvoicesWithBalances($supplierCui) as $invoice) {
            $invoiceMap[$invoice['id']] = $invoice['available'];
        }

        foreach ($allocations as $allocation) {
            $max = $invoiceMap[$allocation['invoice_id']] ?? 0.0;
            if ($allocation['amount'] > $max + 0.01) {
                Session::flash('error', 'Suma alocata depaseste disponibilul pentru o factura.');
                Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
            }

            $stornoRow = Database::fetchOne(
                'SELECT id FROM invoices_in WHERE id = :id
                 AND (fgo_storno_number IS NOT NULL AND fgo_storno_number <> ""
                      OR fgo_storno_series IS NOT NULL AND fgo_storno_series <> ""
                      OR fgo_storno_link IS NOT NULL AND fgo_storno_link <> "")',
                ['id' => $allocation['invoice_id']]
            );
            if ($stornoRow) {
                Session::flash('error', 'Nu poti aloca plata pentru o factura stornata.');
                Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
            }
        }

        $amount = $allocatedTotal;
        if ($amount <= 0) {
            Session::flash('error', 'Suma alocata este invalida.');
            Response::redirect('/admin/plati/adauga?supplier_cui=' . $supplierCui);
        }

        $availableTotal = 0.0;
        foreach ($this->supplierSummary() as $row) {
            if ($row['supplier_cui'] === $supplierCui) {
                $availableTotal = (float) $row['due'];
                break;
            }
        }
        if ($allocatedTotal > $availableTotal + 0.01) {
            Session::flash('error', 'Suma alocata depaseste suma disponibila.');
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
               AND (i.fgo_storno_number IS NULL OR i.fgo_storno_number = "")
               AND (i.fgo_storno_series IS NULL OR i.fgo_storno_series = "")
               AND (i.fgo_storno_link IS NULL OR i.fgo_storno_link = "")
             GROUP BY i.id
             ORDER BY i.issue_date DESC, i.id DESC',
            ['client' => $clientCui]
        );

        $invoices = [];

        foreach ($rows as $row) {
            if ($this->hasStorno($row)) {
                continue;
            }
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
               AND (i.fgo_storno_number IS NULL OR i.fgo_storno_number = "")
               AND (i.fgo_storno_series IS NULL OR i.fgo_storno_series = "")
               AND (i.fgo_storno_link IS NULL OR i.fgo_storno_link = "")
             GROUP BY i.id
             ORDER BY i.issue_date DESC, i.id DESC',
            ['supplier' => $supplierCui]
        );

        $invoices = [];

        foreach ($rows as $row) {
            if ($this->hasStorno($row)) {
                continue;
            }
            $commission = $row['commission_percent'] ?? null;
            if ($commission === null && !empty($row['selected_client_cui'])) {
                $assoc = Commission::forSupplierClient($supplierCui, $row['selected_client_cui']);
                $commission = $assoc ? $assoc->commission : 0.0;
            }

            $totalSupplier = (float) $row['total_with_vat'];
            $paid = (float) $row['paid'];
            $collected = (float) $row['collected'];
            $commissionValue = (float) ($commission ?? 0.0);
            $collectedNet = $commissionValue !== 0.0
                ? $this->applyCommission($collected, -abs($commissionValue))
                : $collected;
            $available = max(0, $collectedNet - $paid);
            $balance = max(0, $totalSupplier - $paid);
            if ($available > $balance) {
                $available = $balance;
            }

            $invoices[] = [
                'id' => (int) $row['id'],
                'invoice_number' => (string) $row['invoice_number'],
                'issue_date' => (string) $row['issue_date'],
                'total_supplier' => $totalSupplier,
                'paid' => $paid,
                'balance' => $balance,
                'collected' => $collected,
                'collected_net' => $collectedNet,
                'commission' => $commissionValue,
                'available' => max(0, $available),
            ];
        }

        return $invoices;
    }

    private function hasStorno(array $row): bool
    {
        return trim((string) ($row['fgo_storno_number'] ?? '')) !== ''
            || trim((string) ($row['fgo_storno_series'] ?? '')) !== ''
            || trim((string) ($row['fgo_storno_link'] ?? '')) !== '';
    }

    private function paymentSuppliers(): array
    {
        if (!Database::tableExists('payments_out')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT DISTINCT supplier_cui, supplier_name
             FROM payments_out
             ORDER BY supplier_name ASC'
        );
    }

    private function fetchAllocations(string $table, string $paymentKey, array $payments, string $invoiceKey): array
    {
        $ids = array_map(static fn (array $row) => (int) $row['id'], $payments);
        if (empty($ids)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($ids as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $hasPartners = Database::tableExists('partners');
        $rows = Database::fetchAll(
            'SELECT a.*, i.invoice_number, i.selected_client_cui, i.fgo_series, i.fgo_number'
                . ($hasPartners ? ', c.denumire AS selected_client_name' : '')
                . ' FROM ' . $table . ' a
             LEFT JOIN invoices_in i ON i.id = a.' . $invoiceKey
                . ($hasPartners ? ' LEFT JOIN partners c ON c.cui = i.selected_client_cui' : '')
                . ' WHERE a.' . $paymentKey . ' IN (' . implode(',', $placeholders) . ')
             ORDER BY a.' . $paymentKey . ' ASC, a.id ASC',
            $params
        );

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row[$paymentKey]][] = $row;
        }

        return $grouped;
    }

    private function paymentOrderMarks(): array
    {
        if (!Database::tableExists('payment_orders')) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT supplier_cui, MAX(generated_at) AS last_generated_at
             FROM payment_orders
             GROUP BY supplier_cui'
        );
        $marks = [];
        foreach ($rows as $row) {
            $marks[(string) $row['supplier_cui']] = (string) $row['last_generated_at'];
        }

        return $marks;
    }

    private function fetchSuppliersForOrders(array $supplierCuis): array
    {
        $placeholders = [];
        $params = [];
        foreach (array_values($supplierCuis) as $index => $cui) {
            $key = 's' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }

        $suppliers = [];

        if (Database::tableExists('companies')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire, iban
                 FROM companies
                 WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $suppliers[(string) $row['cui']] = [
                    'cui' => (string) $row['cui'],
                    'name' => (string) ($row['denumire'] ?? $row['cui']),
                    'iban' => (string) ($row['iban'] ?? ''),
                ];
            }
        }

        if (Database::tableExists('partners')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM partners WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            foreach ($rows as $row) {
                $cui = (string) $row['cui'];
                if (!isset($suppliers[$cui])) {
                    $suppliers[$cui] = [
                        'cui' => $cui,
                        'name' => (string) ($row['denumire'] ?? $cui),
                        'iban' => '',
                    ];
                }
            }
        }

        return $suppliers;
    }

    private function buildPaymentOrders(array $supplierCuis, string $dateFrom, string $dateTo): array
    {
        if (!Database::tableExists('payment_out_allocations')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($supplierCuis) as $index => $cui) {
            $key = 's' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }

        $where = ['o.supplier_cui IN (' . implode(',', $placeholders) . ')'];
        if ($dateFrom !== '') {
            $where[] = 'o.paid_at >= :from';
            $params['from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $where[] = 'o.paid_at <= :to';
            $params['to'] = $dateTo;
        }

        $rows = Database::fetchAll(
            'SELECT o.id AS payment_id, o.supplier_cui, o.supplier_name, a.amount, i.invoice_number,
                    i.fgo_storno_number, i.fgo_storno_series, i.fgo_storno_link
             FROM payment_out_allocations a
             JOIN payments_out o ON o.id = a.payment_out_id
             JOIN invoices_in i ON i.id = a.invoice_in_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY o.supplier_cui ASC, o.id ASC',
            $params
        );

        $data = [];
        foreach ($rows as $row) {
            if ($this->hasStorno($row)) {
                continue;
            }
            $cui = (string) $row['supplier_cui'];
            if (!isset($data[$cui])) {
                $data[$cui] = [
                    'supplier_name' => (string) ($row['supplier_name'] ?? $cui),
                    'total' => 0.0,
                    'invoices' => [],
                    'payments' => [],
                ];
            }
            $data[$cui]['total'] += (float) $row['amount'];
            if (!empty($row['invoice_number'])) {
                $data[$cui]['invoices'][$row['invoice_number']] = true;
            }
            if (!empty($row['payment_id'])) {
                $data[$cui]['payments'][(int) $row['payment_id']] = true;
            }
        }

        foreach ($data as $cui => $row) {
            $data[$cui]['total'] = round((float) $row['total'], 2);
            $data[$cui]['invoices'] = implode(', ', array_keys($row['invoices']));
            $paymentCodes = array_keys($row['payments']);
            sort($paymentCodes);
            $data[$cui]['payment_codes'] = $paymentCodes;
        }

        return $data;
    }

    private function csvQuote(string $value): string
    {
        $escaped = str_replace('"', '""', $value);

        return '"' . $escaped . '"';
    }

    private function historyQuery(string $dateFrom, string $dateTo): string
    {
        $query = [];
        if ($dateFrom !== '') {
            $query['date_from'] = $dateFrom;
        }
        if ($dateTo !== '') {
            $query['date_to'] = $dateTo;
        }

        return $query ? ('?' . http_build_query($query)) : '';
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
               AND (fgo_storno_number IS NULL OR fgo_storno_number = "")
               AND (fgo_storno_series IS NULL OR fgo_storno_series = "")
               AND (fgo_storno_link IS NULL OR fgo_storno_link = "")
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
            Database::execute(
                'CREATE TABLE IF NOT EXISTS payment_orders (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    supplier_cui VARCHAR(32) NOT NULL,
                    supplier_name VARCHAR(255) NOT NULL,
                    date_from DATE NULL,
                    date_to DATE NULL,
                    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                    invoice_numbers TEXT NULL,
                    generated_at DATETIME NULL,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
