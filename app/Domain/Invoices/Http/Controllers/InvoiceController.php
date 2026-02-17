<?php

namespace App\Domain\Invoices\Http\Controllers;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Invoices\Services\FgoClient;
use App\Domain\Invoices\Services\InvoiceAuditService;
use App\Domain\Invoices\Services\InvoiceXmlParser;
use App\Domain\Invoices\Services\SagaExportService;
use App\Domain\Invoices\Services\PackageLockService;
use App\Domain\Invoices\Services\PackageTotalsService;
use App\Domain\Invoices\Services\SagaStatusService;
use App\Domain\Companies\Models\Company;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Partners\Services\CommissionService;
use App\Domain\Settings\Services\SettingsService;
use App\Domain\Users\Models\UserPermission;
use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\Env;
use App\Support\Response;
use App\Support\Session;

class InvoiceController
{
    private CommissionService $commissionService;
    private SagaExportService $sagaExportService;
    private InvoiceAuditService $invoiceAuditService;
    private PackageLockService $packageLockService;
    private SagaStatusService $sagaStatusService;
    private PackageTotalsService $packageTotalsService;

    public function __construct()
    {
        $this->commissionService = new CommissionService();
        $this->sagaStatusService = new SagaStatusService();
        $this->packageLockService = new PackageLockService();
        $this->packageTotalsService = new PackageTotalsService();
        $this->invoiceAuditService = new InvoiceAuditService();
        $this->sagaExportService = new SagaExportService($this->commissionService, $this->sagaStatusService);
    }

    public function index(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        $canImportSaga = $user ? $user->hasRole(['super_admin', 'contabil']) : false;
        $sagaToken = (string) Env::get('SAGA_EXPORT_TOKEN', '');
        if ($sagaToken === '') {
            $sagaToken = (string) Env::get('STOCK_IMPORT_TOKEN', '');
        }
        $isSupplierUser = $user ? $user->isSupplierUser() : false;
        $isOperator = $user ? $user->isOperator() : false;
        $canViewPaymentDetails = $user ? $user->canViewPaymentDetails() : false;
        $canShowRequestAlert = $user ? $user->hasRole(['super_admin', 'admin', 'contabil', 'operator', 'staff', 'supplier_user']) : false;
        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : null;
        $selectedClientCui = preg_replace('/\D+/', '', (string) ($_GET['client_cui'] ?? ''));

        if ($invoiceId) {
            $invoice = $this->guardInvoice($invoiceId);

            $lines = InvoiceInLine::forInvoice($invoiceId);
            $packages = Package::forInvoice($invoiceId);
            $packageStats = $this->packageStats($lines, $packages);
            $vatRates = $this->vatRates($lines);
            $packageDefaults = $this->packageDefaults($packages, $vatRates);
            $linesByPackage = $this->groupLinesByPackage($lines, $packages);
            $clients = Commission::forSupplierWithPartners($invoice->supplier_cui);
            $commissionPercent = null;
            $selectedClientName = '';
            $isAdmin = $user ? $user->isAdmin() : false;
            $canRenamePackages = $this->canRenamePackages($user) && empty($invoice->packages_confirmed);
            $canUnconfirmPackages = $this->canUnconfirmPackages($user);
            $canDownloadSaga = $user ? $user->hasRole(['super_admin', 'contabil']) : false;
            $hasFgoInvoice = $this->hasFgoInvoice($invoice);
            $invoiceLocked = $this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed]);
            $clientLocked = $invoiceLocked && (!$isAdmin || !$this->isClientUnlocked($invoice->id));
            $storedClientCui = $invoice->selected_client_cui ?? '';
            $settings = new SettingsService();
            $fgoSeriesOptions = $settings->get('fgo.series_list', []);
            if (!is_array($fgoSeriesOptions)) {
                $fgoSeriesOptions = [];
            }
            $fgoSeriesSelected = (string) $settings->get('fgo.series', '');
            if (!empty($invoice->fgo_series)) {
                $fgoSeriesSelected = $invoice->fgo_series;
            }

            if ($clientLocked) {
                $selectedClientCui = $storedClientCui;
            } elseif ($selectedClientCui === '' && $storedClientCui !== '') {
                $selectedClientCui = $storedClientCui;
            } elseif ($selectedClientCui !== '' && $selectedClientCui !== $storedClientCui) {
                Database::execute(
                    'UPDATE invoices_in SET selected_client_cui = :client, updated_at = :now WHERE id = :id',
                    [
                        'client' => $selectedClientCui,
                        'now' => date('Y-m-d H:i:s'),
                        'id' => $invoice->id,
                    ]
                );
                $invoice->selected_client_cui = $selectedClientCui;
            }

            if ($selectedClientCui !== '') {
                foreach ($clients as $client) {
                    if ((string) $client['client_cui'] === $selectedClientCui) {
                        $commissionPercent = (float) $client['commission'];
                        $selectedClientName = (string) ($client['client_name'] ?? '');
                        break;
                    }
                }
            }

            $packageTotalsWithCommission = $this->packageTotalsWithCommission($packageStats, $commissionPercent);
            $collectedTotal = 0.0;
            $paidTotal = 0.0;
            $clientTotal = null;
            $paymentInRows = [];
            $paymentOutRows = [];

            if (Database::tableExists('payment_in_allocations')) {
                $collectedTotal = (float) Database::fetchValue(
                    'SELECT COALESCE(SUM(amount), 0) FROM payment_in_allocations WHERE invoice_in_id = :invoice',
                    ['invoice' => $invoiceId]
                );
            }
            if (Database::tableExists('payment_out_allocations')) {
                $paidTotal = (float) Database::fetchValue(
                    'SELECT COALESCE(SUM(amount), 0) FROM payment_out_allocations WHERE invoice_in_id = :invoice',
                    ['invoice' => $invoiceId]
                );
            }

            if (Database::tableExists('payment_in_allocations') && Database::tableExists('payments_in')) {
                $paymentInRows = Database::fetchAll(
                    'SELECT p.id, p.paid_at, p.amount AS payment_amount, p.client_name, p.client_cui,
                            a.amount AS alloc_amount
                     FROM payment_in_allocations a
                     JOIN payments_in p ON p.id = a.payment_in_id
                     WHERE a.invoice_in_id = :invoice
                     ORDER BY p.paid_at DESC, p.id DESC',
                    ['invoice' => $invoiceId]
                );
            }

            if (Database::tableExists('payment_out_allocations') && Database::tableExists('payments_out')) {
                $paymentOutRows = Database::fetchAll(
                    'SELECT p.id, p.paid_at, p.amount AS payment_amount, p.supplier_name, p.supplier_cui,
                            a.amount AS alloc_amount
                     FROM payment_out_allocations a
                     JOIN payments_out p ON p.id = a.payment_out_id
                     WHERE a.invoice_in_id = :invoice
                     ORDER BY p.paid_at DESC, p.id DESC',
                    ['invoice' => $invoiceId]
                );
            }

            $commissionBase = $invoice->commission_percent ?? $commissionPercent;
            if ($commissionBase !== null) {
                $clientTotal = $this->commissionService->applyCommission($invoice->total_with_vat, (float) $commissionBase);
            }
            $canRefacereInvoice = $this->canRefacereInvoice($user);
            $refacerePackages = $canRefacereInvoice
                ? $this->buildInvoiceAdjustmentCandidates($invoice->id, $packages, $linesByPackage, $packageStats)
                : [];
            $invoiceAdjustments = $this->loadInvoiceAdjustments($invoice->id, 10);

            Response::view('admin/invoices/show', [
                'invoice' => $invoice,
                'lines' => $lines,
                'packages' => $packages,
                'packageStats' => $packageStats,
                'vatRates' => $vatRates,
                'packageDefaults' => $packageDefaults,
                'linesByPackage' => $linesByPackage,
                'isConfirmed' => $invoice->packages_confirmed,
                'clients' => $clients,
                'selectedClientCui' => $selectedClientCui,
                'selectedClientName' => $selectedClientName,
                'commissionPercent' => $commissionPercent,
                'packageTotalsWithCommission' => $packageTotalsWithCommission,
                'isAdmin' => $isAdmin,
                'canRenamePackages' => $canRenamePackages,
                'canUnconfirmPackages' => $canUnconfirmPackages,
                'hasFgoInvoice' => $hasFgoInvoice,
                'clientLocked' => $clientLocked,
                'canDownloadSaga' => $canDownloadSaga,
                'isPlatform' => $isPlatform,
                'isOperator' => $isOperator,
                'isSupplierUser' => $isSupplierUser,
                'canShowRequestAlert' => $canShowRequestAlert,
                'fgoSeriesOptions' => $fgoSeriesOptions,
                'fgoSeriesSelected' => $fgoSeriesSelected,
                'collectedTotal' => $collectedTotal,
                'paidTotal' => $paidTotal,
                'clientTotal' => $clientTotal,
                'paymentInRows' => $paymentInRows,
                'paymentOutRows' => $paymentOutRows,
                'canViewPaymentDetails' => $canViewPaymentDetails,
                'canRefacereInvoice' => $canRefacereInvoice,
                'refacerePackages' => $refacerePackages,
                'invoiceAdjustments' => $invoiceAdjustments,
            ]);
        }

        $filters = $this->invoiceFiltersFromRequest();

        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }
        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        $supplierFilterLabel = $this->resolveSupplierLabel((string) $filters['supplier_cui'], $invoices);
        $clientFilterLabel = $this->resolveClientLabel((string) $filters['client_cui'], $clientNameMap);
        $hasEmptyClients = $this->hasEmptySelectedClient($invoices);

        $filteredInvoices = $this->applyInvoiceFilters($invoices, $filters, $clientNameMap, $invoiceStatuses);
        $pagination = $this->paginateInvoices($filteredInvoices, $filters['page'], $filters['per_page']);
        $pagedInvoices = $pagination['items'];
        $clientFinals = $this->clientFinals($pagedInvoices, $clientNameMap);

        Response::view('admin/invoices/index', [
            'invoices' => $pagedInvoices,
            'invoiceStatuses' => $invoiceStatuses,
            'clientFinals' => $clientFinals,
            'isPlatform' => $isPlatform,
            'canShowRequestAlert' => $canShowRequestAlert,
            'filters' => $filters,
            'pagination' => $pagination,
            'supplierFilterLabel' => $supplierFilterLabel,
            'clientFilterLabel' => $clientFilterLabel,
            'hasEmptyClients' => $hasEmptyClients,
            'clientStatusOptions' => $this->clientStatusOptions(),
            'supplierStatusOptions' => $this->supplierStatusOptions(),
            'canViewPaymentDetails' => $canViewPaymentDetails,
        ]);
    }

    public function search(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        $canShowRequestAlert = $user ? $user->hasRole(['super_admin', 'admin', 'contabil', 'operator', 'staff', 'supplier_user']) : false;
        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }

        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        if ($query !== '') {
            $invoices = $this->filterInvoices($invoices, $query, $clientNameMap, $invoiceStatuses);
        }

        $clientFinals = $this->clientFinals($invoices, $clientNameMap);

        Response::view('admin/invoices/rows', [
            'invoices' => $invoices,
            'invoiceStatuses' => $invoiceStatuses,
            'clientFinals' => $clientFinals,
            'isPlatform' => $isPlatform,
            'canShowRequestAlert' => $canShowRequestAlert,
        ], null);
    }

    public function lookupSuppliers(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $filters = $this->invoiceFiltersFromRequest();
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        $filters['supplier_cui'] = '';

        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }
        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        $filtered = $this->applyInvoiceFilters($invoices, $filters, $clientNameMap, $invoiceStatuses);
        $items = $this->supplierOptionsFromInvoices($filtered);
        if ($term !== '') {
            $items = $this->filterLookupOptions($items, $term);
        }
        $items = array_slice($items, 0, $limit);

        $this->jsonResponse(['items' => $items]);
    }

    public function lookupClients(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $filters = $this->invoiceFiltersFromRequest();
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        $filters['client_cui'] = '';

        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }
        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        $filtered = $this->applyInvoiceFilters($invoices, $filters, $clientNameMap, $invoiceStatuses);
        [$items, $hasEmpty] = $this->clientOptionsFromInvoices($filtered, $clientNameMap);
        if ($term !== '') {
            $items = $this->filterLookupOptions($items, $term);
        }
        $items = array_slice($items, 0, $limit);

        $this->jsonResponse(['items' => $items, 'allow_empty' => $hasEmpty]);
    }

    public function manualSupplierSearch(): void
    {
        $this->requireInvoiceRole();

        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $user = Auth::user();
        $allowedSuppliers = [];
        if ($user && $user->isSupplierUser()) {
            $allowedSuppliers = $this->allowedSuppliers($user);
            if (empty($allowedSuppliers)) {
                $this->jsonResponse(['success' => true, 'items' => []]);
            }
        }

        $items = $this->manualSupplierOptions($allowedSuppliers);
        if ($term !== '') {
            $items = $this->filterLookupOptions($items, $term);
        }
        $items = array_slice($items, 0, $limit);

        $this->jsonResponse(['success' => true, 'items' => $items]);
    }

    public function manualClientSearch(): void
    {
        $this->requireInvoiceRole();

        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_GET['supplier_cui'] ?? ''));
        $user = Auth::user();
        $allowedSuppliers = [];
        if ($user && $user->isSupplierUser()) {
            $allowedSuppliers = $this->allowedSuppliers($user);
            if (empty($allowedSuppliers)) {
                $this->jsonResponse(['success' => true, 'items' => []]);
            }
            if ($supplierCui !== '' && !in_array($supplierCui, $allowedSuppliers, true)) {
                $this->jsonResponse(['success' => true, 'items' => []]);
            }
        }

        $items = $this->manualClientOptions($supplierCui, $allowedSuppliers);
        if ($term !== '') {
            $items = $this->filterLookupOptions($items, $term);
        }
        $items = array_slice($items, 0, $limit);

        $this->jsonResponse(['success' => true, 'items' => $items]);
    }

    public function export(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $filters = $this->invoiceFiltersFromRequest();
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }
        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        $invoices = $this->applyInvoiceFilters($invoices, $filters, $clientNameMap, $invoiceStatuses);
        $clientFinals = $this->clientFinals($invoices, $clientNameMap);

        $filename = 'facturi_intrare_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'Furnizor',
            'Factura furnizor',
            'Data factura furnizor',
            'Total factura furnizor',
            'Client final',
            'Factura client',
            'Data factura client',
            'Total factura client',
            'Incasare client',
            'Plata furnizor',
        ]);

        foreach ($invoices as $invoice) {
            $status = $invoiceStatuses[$invoice->id] ?? $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
            $supplierInvoice = trim((string) ($invoice->invoice_series ?? '') . ' ' . (string) ($invoice->invoice_no ?? ''));
            if ($supplierInvoice === '') {
                $supplierInvoice = (string) ($invoice->invoice_number ?? '');
            }
            $fgoNumber = trim((string) ($invoice->fgo_series ?? '') . ' ' . (string) ($invoice->fgo_number ?? ''));
            $clientDate = (string) ($invoice->fgo_date ?? '');
            if ($clientDate === '' && !empty($invoice->fgo_number) && !empty($invoice->packages_confirmed_at)) {
                $clientDate = date('Y-m-d', strtotime((string) $invoice->packages_confirmed_at));
            }
            $clientFinal = $clientFinals[$invoice->id] ?? ['name' => '', 'cui' => ''];
            $clientLabel = $clientFinal['name'] !== '' ? $clientFinal['name'] : '—';
            $clientTotal = $status['client_total'] ?? null;
            $clientTotalText = $clientTotal !== null ? number_format($clientTotal, 2, '.', '') : '';
            $collectedText = $clientTotal !== null
                ? number_format($status['collected'], 2, '.', '') . ' / ' . $clientTotalText . ' (' . $status['client_label'] . ')'
                : $status['client_label'];
            $paidText = number_format($status['paid'], 2, '.', '') . ' / ' . number_format((float) $invoice->total_with_vat, 2, '.', '') .
                ' (' . $status['supplier_label'] . ')';

            fputcsv($out, [
                $invoice->supplier_name,
                $supplierInvoice !== '' ? $supplierInvoice : '—',
                $invoice->issue_date,
                number_format((float) $invoice->total_with_vat, 2, '.', ''),
                $clientLabel,
                $fgoNumber !== '' ? $fgoNumber : '—',
                $clientDate !== '' ? $clientDate : '—',
                $clientTotal !== null ? number_format($clientTotal, 2, '.', '') : '—',
                $collectedText,
                $paidText,
            ]);
        }

        fclose($out);
        exit;
    }

    public function showSupplierFile(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $invoice = $this->guardInvoice($invoiceId);
        $invoiceLock = $this->packageLockService->isInvoiceLocked([
            'packages_confirmed' => $invoice->packages_confirmed,
        ]);
        $filePath = $this->invoiceFilePath($invoice);
        if (!$filePath || !file_exists($filePath)) {
            Response::abort(404, 'Fisierul nu a fost gasit.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === 'xml') {
            $content = file_get_contents($filePath) ?: '';
            $parseError = null;
            $dom = $this->loadXmlDocument($content, $parseError);
            $tree = $dom ? $this->xmlNodeToTree($dom->documentElement) : null;
            $display = $dom ? $this->buildXmlDisplayData($dom) : null;
            $formatted = $dom ? $dom->saveXML() : $this->formatXmlForDisplay($content);

            Response::view('admin/invoices/xml_view', [
                'invoice' => $invoice,
                'content' => $formatted,
                'tree' => $tree,
                'display' => $display,
                'error' => $parseError,
            ], null);
            return;
        }

        $mime = $this->detectMimeType($filePath);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
        header('Content-Length: ' . filesize($filePath));
        readfile($filePath);
        exit;
    }

    public function uploadSupplierFile(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $invoice = $this->guardInvoice($invoiceId);

        if (empty($_FILES['supplier_file']) || $_FILES['supplier_file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Te rog incarca un fisier valid.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
        }

        $file = $_FILES['supplier_file'];
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['xml', 'pdf'], true)) {
            Session::flash('error', 'Acceptam doar fisiere XML sau PDF.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
        }

        $stored = $this->storeSupplierFile($file['tmp_name'], $invoice->invoice_number ?: (string) $invoice->id, $ext);
        if (!$stored) {
            Session::flash('error', 'Nu am putut salva fisierul incarcat.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
        }

        Database::execute(
            'UPDATE invoices_in SET xml_path = :path, supplier_request_at = :requested_at, updated_at = :now WHERE id = :id',
            [
                'path' => $stored,
                'requested_at' => date('Y-m-d H:i:s'),
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoiceId,
            ]
        );

        Session::flash('status', 'Fisierul a fost incarcat.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
    }

    public function unlockClient(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $invoice = $this->guardInvoice($invoiceId);
        if (!$this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed])) {
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
        }

        Session::put($this->clientUnlockKey($invoiceId), true);
        Session::flash('status', 'Clientul a fost deblocat pentru modificare.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
    }

    public function renamePackage(): void
    {
        $this->requireInvoiceRole();

        $user = Auth::user();
        if (!$this->canRenamePackages($user)) {
            Response::abort(403, 'Acces interzis.');
        }

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
        if (!$invoiceId || !$packageId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $invoice = $this->guardInvoice($invoiceId);
        if (!empty($invoice->packages_confirmed)) {
            Session::flash('error', 'Pachetele sunt confirmate si nu pot fi redenumite.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }
        $package = Package::find($packageId);
        if (!$package || $package->invoice_in_id !== $invoice->id) {
            Response::abort(404, 'Pachet inexistent.');
        }

        $label = trim((string) ($_POST['label'] ?? ''));
        if ($label !== '') {
            $label = preg_replace('/\s*#\s*\d+\s*$/', '', $label);
            $label = trim((string) $label);
        }
        if ($label !== '' && strlen($label) > 60) {
            $label = substr($label, 0, 60);
        }

        Package::updateLabel($package->id, $label !== '' ? $label : null);
        Session::flash('status', 'Pachetul a fost redenumit.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function printSituation(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $filters = $this->invoiceFiltersFromRequest();
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        if ($isPlatform) {
            $invoices = InvoiceIn::all();
        } else {
            $invoices = InvoiceIn::forSuppliers($this->allowedSuppliers($user));
        }
        $collectedMap = $this->invoiceAllocationTotals('payment_in_allocations');
        $paidMap = $this->invoiceAllocationTotals('payment_out_allocations');
        $commissionMap = $this->commissionMap();
        $invoiceStatuses = [];

        foreach ($invoices as $invoice) {
            $invoiceStatuses[$invoice->id] = $this->buildInvoiceStatus(
                $invoice,
                (float) ($collectedMap[$invoice->id] ?? 0.0),
                (float) ($paidMap[$invoice->id] ?? 0.0),
                $commissionMap
            );
        }
        $clientNameMap = $this->clientNameMap($this->collectSelectedClientCuis($invoices));
        $allInvoices = $invoices;
        $invoices = $this->applyInvoiceFilters($invoices, $filters, $clientNameMap, $invoiceStatuses);
        $clientFinals = $this->clientFinals($invoices, $clientNameMap);

        $titleParts = ['Situatie Facturi'];
        if (($filters['supplier_cui'] ?? '') !== '') {
            $titleParts[] = 'Furnizor: ' . $this->resolveSupplierName((string) $filters['supplier_cui'], $allInvoices);
        }
        if (($filters['client_cui'] ?? '') !== '') {
            $titleParts[] = 'Client: ' . $this->resolveClientName((string) $filters['client_cui'], $clientNameMap);
        }
        $periodLabel = $this->formatPeriodLabel((string) ($filters['date_from'] ?? ''), (string) ($filters['date_to'] ?? ''));
        if ($periodLabel !== '') {
            $titleParts[] = $periodLabel;
        }
        $titleText = implode(' - ', array_filter($titleParts, static fn ($part) => trim((string) $part) !== ''));

        $settings = new SettingsService();
        $logoPath = $settings->get('branding.logo_path');
        $logoUrl = null;
        if ($logoPath) {
            $absolutePath = BASE_PATH . '/' . ltrim($logoPath, '/');
            if (file_exists($absolutePath)) {
                $logoUrl = \App\Support\Url::asset($logoPath);
            }
        }
        $company = [
            'denumire' => (string) $settings->get('company.denumire', ''),
            'cui' => (string) $settings->get('company.cui', ''),
            'nr_reg_comertului' => (string) $settings->get('company.nr_reg_comertului', ''),
            'adresa' => (string) $settings->get('company.adresa', ''),
            'localitate' => (string) $settings->get('company.localitate', ''),
            'judet' => (string) $settings->get('company.judet', ''),
            'tara' => (string) $settings->get('company.tara', ''),
            'email' => (string) $settings->get('company.email', ''),
            'telefon' => (string) $settings->get('company.telefon', ''),
        ];

        Response::view('admin/invoices/print_situation', [
            'invoices' => $invoices,
            'invoiceStatuses' => $invoiceStatuses,
            'clientFinals' => $clientFinals,
            'logoUrl' => $logoUrl,
            'company' => $company,
            'printedAt' => date('d.m.Y H:i'),
            'titleText' => $titleText,
        ], null);
    }

    public function confirmedPackages(): void
    {
        Auth::requireSagaRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
        $canImportSaga = $user ? $user->hasRole(['super_admin', 'contabil']) : false;
        $sagaToken = (string) Env::get('SAGA_EXPORT_TOKEN', '');
        if ($sagaToken === '') {
            $sagaToken = (string) Env::get('STOCK_IMPORT_TOKEN', '');
        }
        $params = [];
        $whereSupplier = '';

        if (!$isPlatform) {
            $suppliers = $this->allowedSuppliers($user);
            if (empty($suppliers)) {
                $rows = [];
            } else {
                $placeholders = [];
                foreach ($suppliers as $index => $supplier) {
                    $key = 's' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $supplier;
                }
                $whereSupplier = ' AND i.supplier_cui IN (' . implode(',', $placeholders) . ')';
            }
        }

        if (!isset($rows)) {
            $rows = Database::fetchAll(
                'SELECT p.*, i.invoice_number, i.supplier_name, i.issue_date, i.packages_confirmed_at
                 FROM packages p
                 JOIN invoices_in i ON i.id = p.invoice_in_id
                 WHERE i.packages_confirmed = 1' . $whereSupplier . '
                 ORDER BY i.packages_confirmed_at DESC, p.package_no ASC, p.id ASC',
                $params
            );
        }

        $packageIds = array_map(static fn ($row) => (int) $row['id'], $rows);
        $totals = $this->packageTotalsForIds($packageIds);
        $sagaProducts = [];
        if (Database::tableExists('saga_products')) {
            $sagaRows = Database::fetchAll('SELECT name_key, cod_saga, stock_qty FROM saga_products');
            foreach ($sagaRows as $row) {
                $key = (string) ($row['name_key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $sagaProducts[$key] = [
                    'cod_saga' => (string) ($row['cod_saga'] ?? ''),
                    'stock_qty' => (float) ($row['stock_qty'] ?? 0),
                ];
            }
        }
        $packageQtyMap = [];
        $packageSagaMap = [];
        if (!empty($packageIds) && Database::tableExists('invoice_in_lines')) {
            $placeholders = [];
            $params = [];
            foreach ($packageIds as $index => $id) {
                $key = 'p' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $id;
            }
            $qtyRows = Database::fetchAll(
                'SELECT package_id, COALESCE(SUM(quantity), 0) AS qty
                 FROM invoice_in_lines
                 WHERE package_id IN (' . implode(',', $placeholders) . ')
                 GROUP BY package_id',
                $params
            );
            foreach ($qtyRows as $row) {
                $packageQtyMap[(int) $row['package_id']] = (float) ($row['qty'] ?? 0);
            }

            if (Database::columnExists('invoice_in_lines', 'cod_saga')) {
                $sagaRows = Database::fetchAll(
                    'SELECT package_id,
                            COUNT(*) AS line_count,
                            SUM(CASE WHEN cod_saga IS NOT NULL AND cod_saga <> \'\' THEN 1 ELSE 0 END) AS saga_count
                     FROM invoice_in_lines
                     WHERE package_id IN (' . implode(',', $placeholders) . ')
                     GROUP BY package_id',
                    $params
                );
                foreach ($sagaRows as $row) {
                    $packageSagaMap[(int) $row['package_id']] = [
                        'line_count' => (int) ($row['line_count'] ?? 0),
                        'saga_count' => (int) ($row['saga_count'] ?? 0),
                    ];
                }
            }
        }
        foreach ($rows as &$row) {
            $labelText = trim((string) ($row['label'] ?? ''));
            if ($labelText === '') {
                $labelText = 'Pachet de produse';
            }
            $packageTotalVat = (float) (($totals[(int) ($row['id'] ?? 0)]['total_vat'] ?? 0.0));
            $row['is_storno'] = $packageTotalVat < 0;
            $label = $labelText . ' #' . (int) ($row['package_no'] ?? 0);
            $key = $this->normalizeSagaName($label);
            $saga = $sagaProducts[$key] ?? null;
            $qty = $packageQtyMap[(int) ($row['id'] ?? 0)] ?? 0.0;
            $row['stock_ok'] = $saga && $saga['cod_saga'] !== '' && $saga['stock_qty'] > $qty;
            $sagaStats = $packageSagaMap[(int) ($row['id'] ?? 0)] ?? null;
            $row['all_saga'] = $sagaStats
                ? ($sagaStats['line_count'] > 0 && $sagaStats['saga_count'] >= $sagaStats['line_count'])
                : false;
        }
        unset($row);

        Response::view('admin/invoices/confirmed', [
            'packages' => $rows,
            'totals' => $totals,
            'canImportSaga' => $canImportSaga,
            'sagaDebug' => Session::pull('saga_debug'),
            'sagaToken' => $sagaToken,
        ]);
    }

    public function sagaPackageJson(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $packageId = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
        if (!$packageId) {
            Response::abort(400, 'Pachet invalid.');
        }

        try {
            $payload = $this->sagaExportService->buildPackagePayload($packageId, null, !empty($_GET['debug']));
        } catch (\Throwable $exception) {
            Response::abort(400, $exception->getMessage());
        }

        $filename = 'pachet_' . (int) ($payload['pachet']['id_doc'] ?? $packageId) . '.json';
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public function markSagaPending(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
        if (!$packageId) {
            Response::redirect('/admin/pachete-confirmate');
        }

        if (!$this->sagaStatusService->ensureSagaStatusColumn()) {
            Session::flash('error', 'Lipseste coloana saga_status.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $row = Database::fetchOne(
            'SELECT p.id, p.package_no, i.packages_confirmed
             FROM packages p
             JOIN invoices_in i ON i.id = p.invoice_in_id
             WHERE p.id = :id
             LIMIT 1',
            ['id' => $packageId]
        );
        if (!$row || !$this->packageLockService->isInvoiceLocked($row)) {
            Session::flash('error', 'Pachetul nu este confirmat.');
            Response::redirect('/admin/pachete-confirmate');
        }

        if (!Database::tableExists('invoice_in_lines') || !Database::columnExists('invoice_in_lines', 'cod_saga')) {
            Session::flash('error', 'Nu exista produse cu cod SAGA.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $stats = Database::fetchOne(
            'SELECT COUNT(*) AS line_count,
                    SUM(CASE WHEN cod_saga IS NOT NULL AND cod_saga <> \'\' THEN 1 ELSE 0 END) AS saga_count
             FROM invoice_in_lines
             WHERE package_id = :id',
            ['id' => $packageId]
        );
        $lineCount = (int) ($stats['line_count'] ?? 0);
        $sagaCount = (int) ($stats['saga_count'] ?? 0);
        if ($lineCount === 0 || $sagaCount < $lineCount) {
            Session::flash('error', 'Nu toate produsele au cod SAGA asociat.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $this->sagaStatusService->markProcessing($packageId);
        Session::flash('status', 'Pachet marcat ca processing pentru SAGA.');
        Response::redirect('/admin/pachete-confirmate');
    }

    public function apiSagaPackage(): void
    {
        $this->requireSagaToken();

        if (!$this->ensureInvoiceTables()) {
            $this->json(['success' => false, 'message' => 'Schema indisponibila.'], 500);
        }

        $packageId = isset($_GET['package_id']) ? (int) $_GET['package_id'] : 0;
        if (!$packageId) {
            $this->json(['success' => false, 'message' => 'Pachet invalid.'], 400);
        }

        try {
            $payload = $this->sagaExportService->buildPackagePayload($packageId, null, !empty($_GET['debug']));
        } catch (\Throwable $exception) {
            $this->json(['success' => false, 'message' => $exception->getMessage()], 400);
        }

        $this->json(['success' => true, 'data' => $payload]);
    }

    public function apiSagaPending(): void
    {
        $this->requireSagaToken();

        if (!$this->ensureInvoiceTables()) {
            $this->json(['success' => false, 'message' => 'Schema indisponibila.'], 500);
        }

        if (!Database::tableExists('invoice_in_lines')) {
            $this->json(['success' => true, 'count' => 0, 'data' => []]);
        }
        if (!Database::columnExists('invoice_in_lines', 'cod_saga')) {
            $this->json(['success' => true, 'count' => 0, 'data' => []]);
        }
        if (!$this->sagaStatusService->ensureSagaStatusColumn()) {
            $this->json(['success' => false, 'message' => 'Lipseste coloana saga_status.'], 500);
        }

        $hasSagaStatus = true;
        $statusSelect = ', p.saga_status';
        $statusFilter = " AND p.saga_status IN ('processing', 'pending')";

        $packages = Database::fetchAll(
            "SELECT p.id, p.package_no, p.label, p.vat_percent, p.invoice_in_id{$statusSelect},
                    i.invoice_number, i.issue_date, i.selected_client_cui, i.supplier_cui, i.commission_percent
             FROM packages p
             JOIN invoices_in i ON i.id = p.invoice_in_id
             JOIN (
                SELECT package_id,
                       COUNT(*) AS line_count,
                       SUM(CASE WHEN cod_saga IS NOT NULL AND cod_saga <> '' THEN 1 ELSE 0 END) AS saga_count
                FROM invoice_in_lines
                GROUP BY package_id
             ) l ON l.package_id = p.id
             WHERE i.packages_confirmed = 1
               AND l.line_count > 0
               AND l.saga_count = l.line_count{$statusFilter}
             ORDER BY i.packages_confirmed_at DESC, p.package_no ASC, p.id ASC"
        );

        $payloads = [];
        $debug = !empty($_GET['debug']);
        foreach ($packages as $package) {
            try {
                $payloads[] = $this->sagaExportService->buildPackagePayload((int) $package['id'], $package, $debug);
            } catch (\Throwable $exception) {
                continue;
            }
        }

        $this->json([
            'success' => true,
            'count' => count($payloads),
            'data' => $payloads,
        ]);
    }

    public function apiSagaExecuted(): void
    {
        $this->requireSagaToken();

        if (!$this->ensureInvoiceTables()) {
            $this->json(['success' => false, 'message' => 'Schema indisponibila.'], 500);
        }

        $payload = $this->readJsonBody();
        $packageNo = isset($_POST['id_doc']) ? (int) $_POST['id_doc'] : (int) ($payload['id_doc'] ?? 0);
        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : (int) ($payload['package_id'] ?? 0);

        if (!$packageNo && !$packageId) {
            $this->json(['success' => false, 'message' => 'Lipseste id_doc sau package_id.'], 400);
        }

        if (!$this->sagaStatusService->ensureSagaStatusColumn()) {
            $this->json(['success' => false, 'message' => 'Lipseste coloana saga_status.'], 500);
        }

        if ($packageId) {
            $this->sagaStatusService->markExecuted($packageId);
        } else {
            $this->sagaStatusService->markExecutedByPackageNo($packageNo);
        }

        $this->json(['success' => true, 'status' => 'executed']);
    }

    public function apiSagaImported(): void
    {
        $this->requireSagaToken();

        if (!$this->ensureInvoiceTables()) {
            $this->json(['success' => false, 'message' => 'Schema indisponibila.'], 500);
        }

        $payload = $this->readJsonBody();
        $packageNo = isset($_POST['id_pachet']) ? (int) $_POST['id_pachet'] : (int) ($payload['id_pachet'] ?? 0);
        if (!$packageNo) {
            $packageNo = isset($_GET['id_pachet']) ? (int) $_GET['id_pachet'] : 0;
        }
        if (!$packageNo) {
            $packageNo = isset($_POST['id_doc']) ? (int) $_POST['id_doc'] : (int) ($payload['id_doc'] ?? 0);
        }
        if (!$packageNo) {
            $packageNo = isset($_GET['id_doc']) ? (int) $_GET['id_doc'] : 0;
        }

        if (!$packageNo) {
            $this->json(['success' => false, 'message' => 'Lipseste id_pachet.'], 400);
        }

        if (!$this->sagaStatusService->ensureSagaStatusColumn()) {
            $this->json(['success' => false, 'message' => 'Lipseste coloana saga_status.'], 500);
        }

        $this->sagaStatusService->markImported($packageNo);

        $this->json([
            'success' => true,
            'status' => 'imported',
            'updated' => 1,
        ]);
    }

    public function importSagaCsv(): void
    {
        Auth::requireSagaRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        if (Database::tableExists('packages') && !Database::columnExists('packages', 'saga_value')) {
            Database::execute('ALTER TABLE packages ADD COLUMN saga_value DECIMAL(12,2) NULL AFTER vat_percent');
        }

        if (!isset($_FILES['saga_csv']) || $_FILES['saga_csv']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Incarca un fisier CSV valid.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $file = $_FILES['saga_csv'];
        if (!isset($file['tmp_name']) || !is_readable($file['tmp_name'])) {
            Session::flash('error', 'Fisier CSV indisponibil.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $rows = $this->readSagaRows($file['tmp_name']);
        if (empty($rows)) {
            Session::flash('error', 'Fisierul nu contine date valide.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $header = array_shift($rows);
        if (empty($header)) {
            Session::flash('error', 'Fisierul nu contine antet.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $columns = $this->mapSagaColumns($header);
        if ($columns['denumire'] === null || $columns['pret_vanz'] === null) {
            Session::flash('error', 'CSV trebuie sa contina coloanele: denumire, pret_vanz.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $sagaByName = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row[$columns['denumire']] ?? ''));
            if ($name === '') {
                continue;
            }
            $pret = $this->parseSagaNumber((string) ($row[$columns['pret_vanz']] ?? ''));
            $tva = $columns['tva'] !== null
                ? $this->parseSagaNumber((string) ($row[$columns['tva']] ?? ''))
                : null;
            if ($pret === null) {
                continue;
            }
            $normalized = $this->normalizeSagaName($name);
            if ($normalized === '') {
                continue;
            }
            $entry = [
                'pret' => $pret,
                'tva' => $tva,
            ];
            $sagaByName[$normalized] = $entry;
        }

        if (empty($sagaByName)) {
            Session::flash('error', 'Nu am gasit linii valide in CSV.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $packages = Database::fetchAll(
            'SELECT p.id, p.label, p.vat_percent, p.package_no
             FROM packages p
             JOIN invoices_in i ON i.id = p.invoice_in_id
             WHERE i.packages_confirmed = 1'
        );

        $debugPackages = [];
        foreach ($packages as $package) {
            $labelText = trim((string) ($package['label'] ?? ''));
            if ($labelText === '') {
                $labelText = 'Pachet de produse';
            }
            $packageNo = isset($package['package_no']) ? (int) $package['package_no'] : 0;
            $matchName = $packageNo > 0 ? ($labelText . ' #' . $packageNo) : $labelText;
            $matchKey = $this->normalizeSagaName($matchName);
            $debugPackages[] = [
                'label' => $labelText,
                'package_no' => $packageNo,
                'match_key' => $matchKey,
                'matched' => $matchKey !== '' && array_key_exists($matchKey, $sagaByName),
            ];
        }
        Session::flash('saga_debug', [
            'header' => $header,
            'saga_keys_count' => count($sagaByName),
            'saga_keys' => array_slice(array_keys($sagaByName), 0, 50),
            'packages' => array_slice($debugPackages, 0, 200),
        ]);

        Database::execute(
            'UPDATE packages p
             JOIN invoices_in i ON i.id = p.invoice_in_id
             SET p.saga_value = NULL
             WHERE i.packages_confirmed = 1'
        );

        $matched = 0;
        foreach ($packages as $package) {
            $labelText = trim((string) ($package['label'] ?? ''));
            if ($labelText === '') {
                $labelText = 'Pachet de produse';
            }
            $packageNo = isset($package['package_no']) ? (int) $package['package_no'] : 0;
            $matchName = $packageNo > 0 ? ($labelText . ' #' . $packageNo) : $labelText;
            $labelKey = $this->normalizeSagaName($matchName);
            if ($labelKey === '' || !array_key_exists($labelKey, $sagaByName)) {
                continue;
            }
            $entry = $sagaByName[$labelKey];
            $pret = (float) ($entry['pret'] ?? 0);
            $tva = $entry['tva'];
            if ($tva === null) {
                $tva = (float) ($package['vat_percent'] ?? 0);
            }
            $value = round($pret * (1 + ($tva / 100)), 2);
            Package::updateSagaValue((int) $package['id'], $value);
            $matched++;
        }

        $note = $columns['tva'] === null ? ' TVA preluat din pachet.' : '';
        Session::flash('status', 'CSV procesat. Pachete actualizate: ' . $matched . '.' . $note);
        Response::redirect('/admin/pachete-confirmate');
    }

    public function showImport(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        Response::view('admin/invoices/import');
    }

    public function showAviz(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);

        $packages = Package::forInvoice($invoiceId);
        $lines = InvoiceInLine::forInvoice($invoiceId);
        $packageStats = $this->packageStats($lines, $packages);
        $linesByPackage = $this->groupLinesByPackage($lines, $packages);

        $clientCui = $invoice->selected_client_cui ?? '';
        $clientName = '';
        $clientCompany = null;
        $commissionPercent = null;

        if ($clientCui !== '') {
            $clientCompany = Company::findByCui($clientCui);
            if ($clientCompany) {
                $clientName = $clientCompany->denumire;
            } else {
                $partner = Partner::findByCui($clientCui);
                $clientName = $partner ? $partner->denumire : '';
            }

            $commissionPercent = $this->commissionService->resolveCommissionPercent(
                $invoice->commission_percent !== null ? (float) $invoice->commission_percent : null,
                (string) $invoice->supplier_cui,
                (string) $clientCui
            );
        }

        $totalWithout = 0.0;
        $totalWith = 0.0;

        foreach ($packageStats as $stat) {
            $without = (float) ($stat['total'] ?? 0);
            $with = (float) ($stat['total_vat'] ?? 0);

            if ($commissionPercent !== null) {
                $without = $this->commissionService->applyCommission($without, $commissionPercent);
                $with = $this->commissionService->applyCommission($with, $commissionPercent);
            }

            $totalWithout += $without;
            $totalWith += $with;
        }

        $settings = new SettingsService();
        $company = [
            'denumire' => (string) $settings->get('company.denumire', ''),
            'tip_firma' => (string) $settings->get('company.tip_firma', ''),
            'cui' => (string) $settings->get('company.cui', ''),
            'nr_reg_comertului' => (string) $settings->get('company.nr_reg_comertului', ''),
            'platitor_tva' => (bool) $settings->get('company.platitor_tva', false),
            'adresa' => (string) $settings->get('company.adresa', ''),
            'localitate' => (string) $settings->get('company.localitate', ''),
            'judet' => (string) $settings->get('company.judet', ''),
            'tara' => (string) $settings->get('company.tara', 'Romania'),
            'email' => (string) $settings->get('company.email', ''),
            'telefon' => (string) $settings->get('company.telefon', ''),
            'banca' => (string) $settings->get('company.banca', ''),
            'iban' => (string) $settings->get('company.iban', ''),
        ];
        $brandingLogo = (string) $settings->get('branding.logo_path', '');
        if ($brandingLogo !== '') {
            $absolutePath = BASE_PATH . '/' . ltrim($brandingLogo, '/');
            if (file_exists($absolutePath)) {
                $company['logo_url'] = \App\Support\Url::asset($brandingLogo);
            }
        }

        Response::view('admin/invoices/aviz', [
            'invoice' => $invoice,
            'packages' => $packages,
            'packageStats' => $packageStats,
            'linesByPackage' => $linesByPackage,
            'totalWithout' => $totalWithout,
            'totalWith' => $totalWith,
            'company' => $company,
            'clientCui' => $clientCui,
            'clientName' => $clientName,
            'clientCompany' => $clientCompany,
            'commissionPercent' => $commissionPercent,
        ], 'layouts/print');
    }

    public function showOrderNote(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);

        $this->ensureOrderNote($invoice);
        $invoice = InvoiceIn::find($invoiceId);

        $lines = InvoiceInLine::forInvoice($invoiceId);

        $clientCui = $invoice->selected_client_cui ?: $invoice->customer_cui;
        $clientName = '';
        $clientCompany = null;

        if ($clientCui !== '') {
            $clientCompany = Company::findByCui($clientCui);
            if ($clientCompany) {
                $clientName = $clientCompany->denumire;
            } else {
                $partner = Partner::findByCui($clientCui);
                $clientName = $partner ? $partner->denumire : '';
            }
        }

        Response::view('admin/invoices/order_note', [
            'invoice' => $invoice,
            'lines' => $lines,
            'clientCui' => $clientCui,
            'clientName' => $clientName,
            'clientCompany' => $clientCompany,
        ], 'layouts/print');
    }

    public function showManual(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $form = Session::pull('manual_invoice', []);
        $user = Auth::user();
        $partners = Partner::all();
        $commissions = Commission::allWithPartners();

        if ($user && $user->isSupplierUser()) {
            $suppliers = $this->allowedSuppliers($user);
            if (!empty($suppliers)) {
                $partners = array_values(array_filter($partners, static function ($partner) use ($suppliers): bool {
                    return in_array((string) $partner->cui, $suppliers, true);
                }));
                $commissions = array_values(array_filter($commissions, static function ($row) use ($suppliers): bool {
                    return in_array((string) ($row['supplier_cui'] ?? ''), $suppliers, true);
                }));
            } else {
                $partners = [];
                $commissions = [];
            }
        }

        if (($form['supplier_cui'] ?? '') === '' && count($partners) === 1) {
            $supplier = $partners[0];
            $form['supplier_cui'] = (string) ($supplier->cui ?? '');
            $form['supplier_name'] = (string) ($supplier->denumire ?? '');
        }

        Response::view('admin/invoices/manual', [
            'form' => $form,
            'partners' => $partners,
            'commissions' => $commissions,
        ]);
    }

    public function import(): void
    {
        $this->requireInvoiceRole();

        if (!isset($_FILES['xml']) || $_FILES['xml']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Te rog incarca un fisier XML valid.');
            Response::redirect('/admin/facturi/import');
        }

        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru facturi. Importa schema manual.');
            Response::redirect('/admin/facturi');
        }

        $file = $_FILES['xml'];
        $parser = new InvoiceXmlParser();

        try {
            $data = $parser->parse($file['tmp_name']);
        } catch (\Throwable $exception) {
            Session::flash('error', 'Nu pot citi XML-ul: ' . $exception->getMessage());
            Response::redirect('/admin/facturi/import');
        }

        $data['supplier_name'] = CompanyName::normalize((string) ($data['supplier_name'] ?? ''));
        $data['customer_name'] = CompanyName::normalize((string) ($data['customer_name'] ?? ''));
        $data['supplier_cui'] = preg_replace('/\D+/', '', (string) ($data['supplier_cui'] ?? ''));
        $data['customer_cui'] = preg_replace('/\D+/', '', (string) ($data['customer_cui'] ?? ''));

        $this->ensureSupplierAccess($data['supplier_cui'] ?? '');
        if (!$this->supplierExistsInPlatform((string) ($data['supplier_cui'] ?? ''))) {
            Session::flash('error', 'Furnizorul din XML nu exista in lista de furnizori din platforma.');
            Response::redirect('/admin/facturi/import');
        }

        if ($this->invoiceExists($data['supplier_cui'], $data['invoice_series'], $data['invoice_no'], $data['invoice_number'])) {
            Session::flash('error', 'Factura a fost deja importata pentru acest furnizor.');
            Response::redirect('/admin/facturi/import');
        }

        $xmlPath = $this->storeXml($file['tmp_name'], $data['invoice_number']);

        $invoice = InvoiceIn::create([
            'invoice_number' => $data['invoice_number'],
            'invoice_series' => $data['invoice_series'],
            'invoice_no' => $data['invoice_no'],
            'supplier_cui' => $data['supplier_cui'],
            'supplier_name' => $data['supplier_name'],
            'customer_cui' => $data['customer_cui'],
            'customer_name' => $data['customer_name'],
            'issue_date' => $data['issue_date'],
            'due_date' => $data['due_date'],
            'currency' => $data['currency'],
            'total_without_vat' => $data['total_without_vat'],
            'total_vat' => $data['total_vat'],
            'total_with_vat' => $data['total_with_vat'],
            'xml_path' => $xmlPath,
        ]);

        $currentUser = Auth::user();
        if ($currentUser && $currentUser->isSupplierUser()) {
            Database::execute(
                'UPDATE invoices_in SET supplier_request_at = :requested_at, updated_at = :now WHERE id = :id',
                [
                    'requested_at' => date('Y-m-d H:i:s'),
                    'now' => date('Y-m-d H:i:s'),
                    'id' => $invoice->id,
                ]
            );
            $invoice = InvoiceIn::find($invoice->id);
        }

        Partner::createIfMissing($data['supplier_cui'], $data['supplier_name']);
        Partner::createIfMissing($data['customer_cui'], $data['customer_name']);

        foreach ($data['lines'] as $line) {
            InvoiceInLine::create($invoice->id, $line);
        }

        $this->generatePackages($invoice->id);
        $this->invoiceAuditService->recordImportXml($invoice);

        Session::flash('status', 'Factura a fost importata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoice->id);
    }

    public function storeManual(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru facturi. Importa schema manual.');
            Response::redirect('/admin/facturi');
        }

        $payload = $_POST;
        $supplierName = trim($payload['supplier_name'] ?? '');
        $supplierCui = trim($payload['supplier_cui'] ?? '');
        $customerName = trim($payload['customer_name'] ?? '');
        $customerCui = trim($payload['customer_cui'] ?? '');
        $invoiceSeries = trim($payload['invoice_series'] ?? '');
        $invoiceNo = trim($payload['invoice_no'] ?? '');
        $issueDate = trim($payload['issue_date'] ?? '');
        $dueDate = trim($payload['due_date'] ?? '');
        $currency = 'RON';

        $errors = [];

        if ($supplierName === '' && $supplierCui !== '') {
            $supplierCompany = Company::findByCui($supplierCui);
            if ($supplierCompany) {
                $supplierName = $supplierCompany->denumire;
            } else {
                $supplier = Partner::findByCui($supplierCui);
                if ($supplier) {
                    $supplierName = $supplier->denumire;
                }
            }
        }
        if ($customerName === '' && $customerCui !== '') {
            $customerCompany = Company::findByCui($customerCui);
            if ($customerCompany) {
                $customerName = $customerCompany->denumire;
            } else {
                $customer = Partner::findByCui($customerCui);
                if ($customer) {
                    $customerName = $customer->denumire;
                }
            }
        }

        $supplierName = CompanyName::normalize($supplierName);
        $customerName = CompanyName::normalize($customerName);

        if ($invoiceSeries === '' || $invoiceNo === '') {
            $errors[] = 'Completeaza seria si numarul facturii.';
        }
        if ($supplierName === '' || $supplierCui === '') {
            $errors[] = 'Completeaza furnizorul (denumire si CUI).';
        }
        if ($customerName === '' || $customerCui === '') {
            $errors[] = 'Completeaza clientul (denumire si CUI).';
        }
        if ($issueDate === '') {
            $errors[] = 'Completeaza data emiterii.';
        }

        $linesInput = $payload['lines'] ?? [];
        $lines = [];
        $lineIndex = 1;

        foreach ((array) $linesInput as $line) {
            $name = trim($line['product_name'] ?? '');
            $unit = trim($line['unit_code'] ?? '');
            $qtyRaw = $line['quantity'] ?? '';
            $priceRaw = $line['unit_price'] ?? '';
            $taxRaw = $line['tax_percent'] ?? '';

            if ($name === '' && $unit === '' && trim((string) $qtyRaw) === '' && trim((string) $priceRaw) === '' && trim((string) $taxRaw) === '') {
                continue;
            }

            $qty = $this->parseNumber($qtyRaw);
            $price = $this->parseNumber($priceRaw);
            $tax = $this->parseNumber($taxRaw);
            $lineErrors = [];

            if ($name === '' || $unit === '') {
                $lineErrors[] = 'Completeaza denumirea si unitatea pentru produsul #' . $lineIndex . '.';
            }
            if ($qty === null || $qty <= 0) {
                $lineErrors[] = 'Cantitatea produsului #' . $lineIndex . ' trebuie sa fie > 0.';
            }
            if ($price === null || $price < 0) {
                $lineErrors[] = 'Pretul produsului #' . $lineIndex . ' este invalid.';
            }
            if ($tax === null || $tax < 0) {
                $lineErrors[] = 'Cota TVA a produsului #' . $lineIndex . ' este invalida.';
            }

            if (!empty($lineErrors)) {
                $errors = array_merge($errors, $lineErrors);
                $lineIndex++;
                continue;
            }

            $lineTotal = round($qty * $price, 2);
            $lineTotalVat = round($lineTotal * (1 + ($tax / 100)), 2);

            $lines[] = [
                'line_no' => (string) $lineIndex,
                'product_name' => $name,
                'quantity' => $qty,
                'unit_code' => $unit,
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'tax_percent' => $tax,
                'line_total_vat' => $lineTotalVat,
            ];

            $lineIndex++;
        }

        if (empty($lines)) {
            $errors[] = 'Adauga cel putin un produs pe factura.';
        }

        if (!empty($errors)) {
            Session::flash('error', implode(' ', $errors));
            Session::flash('manual_invoice', $payload);
            Response::redirect('/admin/facturi/adauga');
        }

        $this->ensureSupplierAccess($supplierCui);

        $invoiceNumber = trim($invoiceSeries . ' ' . $invoiceNo);

        if ($this->invoiceExists($supplierCui, $invoiceSeries, $invoiceNo, $invoiceNumber)) {
            Session::flash('error', 'Factura a fost deja importata pentru acest furnizor.');
            Session::flash('manual_invoice', $payload);
            Response::redirect('/admin/facturi/adauga');
        }

        $totalWithoutVat = 0.0;
        $totalWithVat = 0.0;

        foreach ($lines as $line) {
            $totalWithoutVat += $line['line_total'];
            $totalWithVat += $line['line_total_vat'];
        }

        $totalVat = round($totalWithVat - $totalWithoutVat, 2);

        $invoice = InvoiceIn::create([
            'invoice_number' => $invoiceNumber,
            'invoice_series' => $invoiceSeries,
            'invoice_no' => $invoiceNo,
            'supplier_cui' => $supplierCui,
            'supplier_name' => $supplierName,
            'customer_cui' => $customerCui,
            'customer_name' => $customerName,
            'issue_date' => $issueDate,
            'due_date' => $dueDate !== '' ? $dueDate : null,
            'currency' => $currency,
            'total_without_vat' => round($totalWithoutVat, 2),
            'total_vat' => $totalVat,
            'total_with_vat' => round($totalWithVat, 2),
            'xml_path' => null,
        ]);

        if ($customerCui !== '') {
            Database::execute(
                'UPDATE invoices_in SET selected_client_cui = :client, updated_at = :now WHERE id = :id',
                [
                    'client' => $customerCui,
                    'now' => date('Y-m-d H:i:s'),
                    'id' => $invoice->id,
                ]
            );
        }

        Partner::createIfMissing($supplierCui, $supplierName);
        Partner::createIfMissing($customerCui, $customerName);

        foreach ($lines as $line) {
            InvoiceInLine::create($invoice->id, $line);
        }

        $this->generatePackages($invoice->id);
        $this->invoiceAuditService->recordManualCreate($invoice);

        Session::flash('status', 'Factura a fost adaugata manual.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoice->id);
    }

    public function calcManualTotals(): void
    {
        $this->requireInvoiceRole();

        $raw = $_POST['lines_json'] ?? '';
        $lines = json_decode((string) $raw, true);

        if (!is_array($lines)) {
            $lines = [];
        }

        $responseLines = [];
        $totalWithout = 0.0;
        $totalWith = 0.0;

        foreach ($lines as $line) {
            $index = (int) ($line['index'] ?? 0);
            $qty = $this->parseNumber($line['quantity'] ?? null);
            $price = $this->parseNumber($line['unit_price'] ?? null);
            $tax = $this->parseNumber($line['tax_percent'] ?? null);

            if ($qty === null || $price === null || $tax === null) {
                $responseLines[] = [
                    'index' => $index,
                    'total' => 0,
                    'total_vat' => 0,
                ];
                continue;
            }

            $lineTotal = round($qty * $price, 2);
            $lineTotalVat = round($lineTotal * (1 + ($tax / 100)), 2);
            $totalWithout += $lineTotal;
            $totalWith += $lineTotalVat;

            $responseLines[] = [
                'index' => $index,
                'total' => $lineTotal,
                'total_vat' => $lineTotalVat,
            ];
        }

        $totalVat = round($totalWith - $totalWithout, 2);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'lines' => $responseLines,
            'totals' => [
                'without_vat' => round($totalWithout, 2),
                'vat' => $totalVat,
                'with_vat' => round($totalWith, 2),
            ],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function packages(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $action = $_POST['action'] ?? '';

        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru facturi.');
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);

        if ($action === 'generate') {
            if ($invoiceLock) {
                Session::flash('error', 'Pachetele sunt confirmate si nu mai pot fi regenerate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $counts = $_POST['package_counts'] ?? [];
            $this->generatePackages($invoiceId, $counts);
            Session::flash('status', 'Pachetele au fost reorganizate.');
        }

        if ($action === 'delete') {
            $user = Auth::user();
            if ($user && $user->isOperator()) {
                Response::abort(403, 'Acces interzis.');
            }
            if ($invoiceLock) {
                Session::flash('error', 'Pachetele sunt confirmate si nu pot fi sterse.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
            if ($packageId) {
                $package = Package::find($packageId);

                if ($package && $package->invoice_in_id === $invoiceId) {
                    Database::execute(
                        'UPDATE invoice_in_lines SET package_id = NULL WHERE package_id = :package',
                        ['package' => $packageId]
                    );
                    Database::execute('DELETE FROM packages WHERE id = :id', ['id' => $packageId]);
                    $this->renumberPackages($invoiceId);
                    Session::flash('status', 'Pachet sters. Produsele au fost trecute la nealocate.');
                }
            }
        }

        if ($action === 'confirm') {
            if ($invoiceLock) {
                Session::flash('status', 'Pachetele sunt deja confirmate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $this->confirmPackages($invoiceId);
            Session::flash('status', 'Pachetele au fost confirmate.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#supplier-request');
        }

        if ($action === 'unconfirm') {
            $user = Auth::user();
            if (!$this->canUnconfirmPackages($user)) {
                Response::abort(403, 'Acces interzis.');
            }

            if (!$invoiceLock) {
                Session::flash('status', 'Pachetele nu sunt confirmate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            if ($this->hasFgoInvoice($invoice)) {
                Session::flash('error', 'Nu poti anula confirmarea dupa emiterea facturii FGO.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $this->unconfirmPackages($invoiceId);
            Session::forget($this->clientUnlockKey($invoiceId));
            Session::flash('status', 'Confirmarea pachetelor a fost anulata.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function delete(): void
    {
        Auth::requireAdminWithoutOperator();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);

        Database::execute('DELETE FROM invoice_in_lines WHERE invoice_in_id = :invoice', ['invoice' => $invoiceId]);
        Database::execute('DELETE FROM packages WHERE invoice_in_id = :invoice', ['invoice' => $invoiceId]);
        Database::execute('DELETE FROM invoices_in WHERE id = :id', ['id' => $invoiceId]);

        if (!empty($invoice->xml_path)) {
            $path = BASE_PATH . '/' . ltrim($invoice->xml_path, '/');
            if (file_exists($path)) {
                @unlink($path);
            }
        }

        Session::flash('status', 'Factura de intrare a fost stearsa.');
        Response::redirect('/admin/facturi');
    }

    public function generateInvoice(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice) {
            Response::redirect('/admin/facturi');
        }

        $currentUser = Auth::user();
        if ($currentUser && $currentUser->hasRole('staff') && !$this->invoiceFilePath($invoice)) {
            Session::flash('error', 'Nu poti genera factura fara fisierul furnizorului.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
        }

        if (!$this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed])) {
            Session::flash('error', 'Confirma pachetele inainte de generare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        if (!empty($invoice->fgo_number)) {
            Session::flash('error', 'Factura FGO a fost deja generata.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        if ($clientCui === '' && !empty($invoice->selected_client_cui)) {
            $clientCui = preg_replace('/\D+/', '', (string) $invoice->selected_client_cui);
        }

        if ($clientCui === '') {
            Session::flash('error', 'Selecteaza clientul pentru generarea facturii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
        }

        $settings = new SettingsService();
        $codUnic = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        $secret = trim((string) $settings->get('fgo.api_key', ''));
        if ($secret === '') {
            $secret = trim((string) $settings->get('fgo.secret_key', ''));
        }
        $seriesOptions = $settings->get('fgo.series_list', []);
        if (!is_array($seriesOptions)) {
            $seriesOptions = [];
        }
        $series = trim((string) ($_POST['fgo_series'] ?? ''));
        if ($series === '') {
            $series = trim((string) $settings->get('fgo.series', ''));
        }
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if (!empty($seriesOptions) && $series !== '' && !in_array($series, $seriesOptions, true)) {
            Session::flash('error', 'Seria selectata nu exista in setarile FGO.');
            Response::redirect('/admin/setari');
        }

        if ($codUnic === '' || $secret === '' || $series === '') {
            Session::flash('error', 'Completeaza CUI companie, Cheia API si seria FGO.');
            Response::redirect('/admin/setari');
        }

        if ($baseUrl === '') {
            $baseUrl = 'https://api.fgo.ro/v1';
        }

        $clientCompany = Company::findByCui($clientCui);
        if (!$clientCompany) {
            Session::flash('error', 'Completeaza datele clientului in pagina Companii.');
            Response::redirect('/admin/companii');
        }

        $clientCountry = $this->normalizeCountry($clientCompany->tara ?? '');
        $missing = [];

        if (trim($clientCompany->denumire ?? '') === '') {
            $missing[] = 'denumire';
        }
        if (trim($clientCompany->adresa ?? '') === '') {
            $missing[] = 'adresa';
        }
        if (trim($clientCompany->localitate ?? '') === '') {
            $missing[] = 'localitate';
        }
        if ($clientCountry === 'RO' && trim($clientCompany->judet ?? '') === '') {
            $missing[] = 'judet';
        }

        if (!empty($missing)) {
            Session::flash('error', 'Completeaza datele clientului: ' . implode(', ', $missing) . '.');
            Response::redirect('/admin/companii/edit?cui=' . urlencode($clientCui));
        }

        $commission = Commission::forSupplierClient($invoice->supplier_cui, $clientCui);
        if (!$commission) {
            Session::flash('error', 'Nu exista comision pentru acest client.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
        }

        Database::execute(
            'UPDATE invoices_in SET commission_percent = :commission, updated_at = :now WHERE id = :id',
            [
                'commission' => $commission->commission,
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoice->id,
            ]
        );

        $lines = InvoiceInLine::forInvoice($invoiceId);
        $packages = Package::forInvoice($invoiceId);
        if (empty($packages)) {
            Session::flash('error', 'Nu exista pachete pentru facturare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $packageStats = $this->packageStats($lines, $packages);
        $content = [];

        foreach ($packages as $package) {
            if (!isset($packageStats[$package->id])) {
                continue;
            }

            $stat = $packageStats[$package->id];
            $total = $this->commissionService->applyCommission($stat['total_vat'], $commission->commission);

            $content[] = [
                'Denumire' => $this->packageLabel($package),
                'UM' => 'BUC',
                'NrProduse' => 1,
                'CotaTVA' => (float) $package->vat_percent,
                'PretTotal' => number_format($total, 2, '.', ''),
            ];
        }

        if (empty($content)) {
            Session::flash('error', 'Nu am putut construi continutul facturii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $issueDate = date('Y-m-d');
        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForEmitere($codUnic, $secret, $clientCompany->denumire),
            'Valuta' => $invoice->currency ?: 'RON',
            'TipFactura' => 'Factura',
            'Serie' => $series,
            'DataEmitere' => $issueDate,
            'VerificareDuplicat' => 'true',
            'IdExtern' => 'INV-IN-' . $invoice->id,
            'Client' => [
                'Denumire' => $clientCompany->denumire,
                'CodUnic' => $clientCompany->cui,
                'NrRegCom' => $clientCompany->nr_reg_comertului,
                'Email' => $clientCompany->email,
                'Telefon' => $clientCompany->telefon,
                'Tara' => $clientCountry,
                'Judet' => $clientCompany->judet,
                'Localitate' => $clientCompany->localitate,
                'Adresa' => $clientCompany->adresa,
                'Tip' => 'PJ',
                'PlatitorTVA' => $clientCompany->platitor_tva ? 'true' : 'false',
            ],
            'Continut' => $content,
            'PlatformaUrl' => FgoClient::platformUrl(),
        ];

        if (!empty($invoice->due_date)) {
            $payload['DataScadenta'] = $invoice->due_date;
        }

        $client = new FgoClient($baseUrl);
        $response = $client->post('factura/emitere', $payload);

        if (empty($response['Success'])) {
            $message = isset($response['Message']) ? (string) $response['Message'] : 'Eroare emitere factura FGO.';
            Session::flash('error', $message);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $factura = $response['Factura'] ?? [];
        $fgoNumber = (string) ($factura['Numar'] ?? '');
        $fgoSeries = (string) ($factura['Serie'] ?? $series);
        $fgoLink = (string) ($factura['Link'] ?? '');

        if ($fgoNumber === '') {
            Session::flash('error', 'Factura FGO nu a returnat numarul emis.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        Database::execute(
            'UPDATE invoices_in
             SET fgo_series = :serie,
                 fgo_number = :numar,
                 fgo_date = :fgo_date,
                 fgo_generated_at = :generated_at,
                 fgo_link = :link,
                 updated_at = :now
             WHERE id = :id',
            [
                'serie' => $fgoSeries,
                'numar' => $fgoNumber,
                'fgo_date' => $issueDate,
                'generated_at' => date('Y-m-d H:i:s'),
                'link' => $fgoLink,
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoice->id,
            ]
        );

        Session::flash('status', 'Factura FGO a fost generata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function printInvoice(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice || empty($invoice->fgo_number) || empty($invoice->fgo_series)) {
            Session::flash('error', 'Factura FGO nu este disponibila pentru printare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $settings = new SettingsService();
        $codUnic = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        $secret = trim((string) $settings->get('fgo.api_key', ''));
        if ($secret === '') {
            $secret = trim((string) $settings->get('fgo.secret_key', ''));
        }
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if ($codUnic === '' || $secret === '') {
            Session::flash('error', 'Completeaza CUI companie si Cheia API in setarile FGO.');
            Response::redirect('/admin/setari');
        }

        if ($baseUrl === '') {
            $baseUrl = 'https://api.fgo.ro/v1';
        }

        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForNumber($codUnic, $secret, $invoice->fgo_number),
            'Serie' => $invoice->fgo_series,
            'Numar' => $invoice->fgo_number,
            'PlatformaUrl' => FgoClient::platformUrl(),
        ];

        $client = new FgoClient($baseUrl);
        $response = $client->post('factura/print', $payload);

        if (empty($response['Success'])) {
            $message = isset($response['Message']) ? (string) $response['Message'] : 'Eroare la printare FGO.';
            Session::flash('error', $message);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $factura = $response['Factura'] ?? [];
        $link = (string) ($factura['Link'] ?? '');

        if ($link !== '') {
            Database::execute(
                'UPDATE invoices_in SET fgo_link = :link, updated_at = :now WHERE id = :id',
                [
                    'link' => $link,
                    'now' => date('Y-m-d H:i:s'),
                    'id' => $invoice->id,
                ]
            );
        } else {
            $link = (string) ($invoice->fgo_link ?? '');
        }

        if ($link !== '') {
            $filename = 'factura_' . $this->safeFileName(trim($invoice->fgo_series . '_' . $invoice->fgo_number)) . '.pdf';
            $content = $this->fetchRemoteFile($link);
            if ($content !== null) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;
            }

            header('Location: ' . $link);
            exit;
        }

        Session::flash('status', 'Factura FGO a fost printata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function stornoInvoice(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice || empty($invoice->fgo_number) || empty($invoice->fgo_series)) {
            Session::flash('error', 'Factura FGO nu este disponibila pentru stornare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        if (!empty($invoice->fgo_storno_number)) {
            Session::flash('error', 'Factura FGO este deja stornata.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $settings = new SettingsService();
        $codUnic = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        $secret = trim((string) $settings->get('fgo.api_key', ''));
        if ($secret === '') {
            $secret = trim((string) $settings->get('fgo.secret_key', ''));
        }
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if ($codUnic === '' || $secret === '') {
            Session::flash('error', 'Completeaza CUI companie si Cheia API in setarile FGO.');
            Response::redirect('/admin/setari');
        }

        if ($baseUrl === '') {
            $baseUrl = 'https://api.fgo.ro/v1';
        }

        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForNumber($codUnic, $secret, $invoice->fgo_number),
            'Serie' => $invoice->fgo_series,
            'Numar' => $invoice->fgo_number,
            'PlatformaUrl' => FgoClient::platformUrl(),
        ];

        $client = new FgoClient($baseUrl);
        $response = $client->post('factura/stornare', $payload);

        if (empty($response['Success'])) {
            $message = isset($response['Message']) ? (string) $response['Message'] : 'Eroare la stornare FGO.';
            Session::flash('error', $message);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $factura = $response['Factura'] ?? [];
        $stornoSeries = (string) ($factura['Serie'] ?? '');
        $stornoNumber = (string) ($factura['Numar'] ?? '');
        $stornoLink = (string) ($factura['Link'] ?? '');

        if ($stornoNumber === '') {
            Session::flash('error', 'Factura FGO storno nu a returnat numarul emis.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        Database::execute(
            'UPDATE invoices_in
             SET fgo_storno_series = :serie,
                 fgo_storno_number = :numar,
                 fgo_storno_link = :link,
                 fgo_storno_at = :storno_at,
                 updated_at = :now
             WHERE id = :id',
            [
                'serie' => $stornoSeries,
                'numar' => $stornoNumber,
                'link' => $stornoLink,
                'storno_at' => date('Y-m-d H:i:s'),
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoice->id,
            ]
        );

        $message = 'Factura FGO a fost stornata.';
        try {
            $stornoPackages = $this->createStornoPackagesForInvoice((int) $invoice->id);
            Audit::record('invoice.storno_packages_created', 'invoice_in', $invoice->id, [
                'rows_count' => (int) ($stornoPackages['packages_created'] ?? 0),
                'lines_created' => (int) ($stornoPackages['lines_created'] ?? 0),
            ]);

            if (($stornoPackages['packages_created'] ?? 0) > 0) {
                $message .= ' Pachete storno create automat: '
                    . (int) $stornoPackages['packages_created']
                    . ' (linii: '
                    . (int) $stornoPackages['lines_created']
                    . ').';
            } else {
                $message .= ' Nu au fost gasite pachete pentru generare storno.';
            }
        } catch (\Throwable $exception) {
            Audit::record('invoice.storno_packages_failed', 'invoice_in', $invoice->id, [
                'error' => $exception->getMessage(),
                'rows_count' => 0,
            ]);
            $message .= ' Pachetele storno nu au putut fi generate automat.';
        }
        Session::flash('status', $message);
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function printStornoInvoice(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice || empty($invoice->fgo_storno_number) || empty($invoice->fgo_storno_series)) {
            Session::flash('error', 'Factura storno nu este disponibila pentru printare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $settings = new SettingsService();
        $codUnic = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        $secret = trim((string) $settings->get('fgo.api_key', ''));
        if ($secret === '') {
            $secret = trim((string) $settings->get('fgo.secret_key', ''));
        }
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if ($codUnic === '' || $secret === '') {
            Session::flash('error', 'Completeaza CUI companie si Cheia API in setarile FGO.');
            Response::redirect('/admin/setari');
        }

        if ($baseUrl === '') {
            $baseUrl = 'https://api.fgo.ro/v1';
        }

        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForNumber($codUnic, $secret, $invoice->fgo_storno_number),
            'Serie' => $invoice->fgo_storno_series,
            'Numar' => $invoice->fgo_storno_number,
            'PlatformaUrl' => FgoClient::platformUrl(),
        ];

        $client = new FgoClient($baseUrl);
        $response = $client->post('factura/print', $payload);

        if (empty($response['Success'])) {
            $message = isset($response['Message']) ? (string) $response['Message'] : 'Eroare la printare FGO.';
            Session::flash('error', $message);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $factura = $response['Factura'] ?? [];
        $link = (string) ($factura['Link'] ?? '');

        if ($link !== '') {
            Database::execute(
                'UPDATE invoices_in SET fgo_storno_link = :link, updated_at = :now WHERE id = :id',
                [
                    'link' => $link,
                    'now' => date('Y-m-d H:i:s'),
                    'id' => $invoice->id,
                ]
            );
        } else {
            $link = (string) ($invoice->fgo_storno_link ?? '');
        }

        if ($link !== '') {
            $filename = 'storno_' . $this->safeFileName(trim($invoice->fgo_storno_series . '_' . $invoice->fgo_storno_number)) . '.pdf';
            $content = $this->fetchRemoteFile($link);
            if ($content !== null) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen($content));
                echo $content;
                exit;
            }

            header('Location: ' . $link);
            exit;
        }

        Session::flash('status', 'Factura storno a fost printata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function rebuildInvoice(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot pregati structura pentru refacerea facturii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $invoice = $this->guardInvoice($invoiceId);
        $user = Auth::user();
        if (!$this->canRefacereInvoice($user)) {
            Response::abort(403, 'Acces interzis.');
        }
        if (!$this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed])) {
            Session::flash('error', 'Confirma pachetele inainte de refacerea facturii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if (empty($invoice->fgo_number) || empty($invoice->fgo_series)) {
            Session::flash('error', 'Refacerea este disponibila dupa emiterea facturii FGO initiale.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if (!empty($invoice->fgo_storno_number)) {
            Session::flash('error', 'Factura este deja stornata integral si nu mai poate fi refacuta.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $lines = InvoiceInLine::forInvoice($invoiceId);
        $packages = Package::forInvoice($invoiceId);
        $packageStats = $this->packageStats($lines, $packages);
        $linesByPackage = $this->groupLinesByPackage($lines, $packages);
        $candidates = $this->buildInvoiceAdjustmentCandidates($invoiceId, $packages, $linesByPackage, $packageStats);
        if (empty($candidates)) {
            Session::flash('error', 'Nu exista pachete active eligibile pentru refacere.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $requested = $_POST['adjust_qty'] ?? [];
        [$changes, $summary, $errors] = $this->buildInvoiceAdjustmentPlan($candidates, is_array($requested) ? $requested : []);
        if (!empty($errors)) {
            Session::flash('error', (string) $errors[0]);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if (empty($changes)) {
            Session::flash('error', 'Nu exista modificari de aplicat. Ajusteaza cel putin o cantitate.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if ((float) ($summary['decrease_total_vat'] ?? 0.0) <= 0.0) {
            Session::flash('error', 'Refacerea trebuie sa scada totalul facturii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        try {
            $result = $this->applyInvoiceAdjustmentPlan($invoice, $changes, $summary, $user);
            Audit::record('invoice.adjustment_applied', 'invoice_in', $invoice->id, [
                'adjustment_id' => (int) ($result['adjustment_id'] ?? 0),
                'packages_created' => (int) ($result['packages_created'] ?? 0),
                'lines_created' => (int) ($result['lines_created'] ?? 0),
                'decrease_total_vat' => (float) ($result['decrease_total_vat'] ?? 0.0),
            ]);
            $message = 'Refacerea a fost aplicata. Pachete storno: '
                . (int) ($result['storno_packages'] ?? 0)
                . ', pachete noi: '
                . (int) ($result['replacement_packages'] ?? 0)
                . '. Scadere neta: '
                . number_format((float) ($result['decrease_total_vat'] ?? 0.0), 2, '.', ' ')
                . ' RON.';
            Session::flash('status', $message);
        } catch (\Throwable $exception) {
            Audit::record('invoice.adjustment_failed', 'invoice_in', $invoice->id, [
                'error' => $exception->getMessage(),
                'rows_count' => 0,
            ]);
            Session::flash('error', 'Refacerea nu a putut fi aplicata. Verifica datele introduse.');
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
    }

    public function generateAdjustmentInvoice(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $adjustmentId = isset($_POST['adjustment_id']) ? (int) $_POST['adjustment_id'] : 0;
        if (!$invoiceId || !$adjustmentId) {
            Response::redirect('/admin/facturi');
        }
        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot pregati structura pentru factura de refacere.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $invoice = $this->guardInvoice($invoiceId);
        $user = Auth::user();
        if (!$this->canRefacereInvoice($user)) {
            Response::abort(403, 'Acces interzis.');
        }

        $adjustment = $this->findInvoiceAdjustmentById($adjustmentId);
        if (!$adjustment || (int) ($adjustment['invoice_in_id'] ?? 0) !== $invoiceId) {
            Session::flash('error', 'Refacerea selectata nu exista.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if (!empty($adjustment['fgo_number'])) {
            Session::flash('status', 'Factura FGO pentru aceasta refacere este deja generata.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }
        if (!empty($invoice->fgo_storno_number)) {
            Session::flash('error', 'Factura este stornata integral. Nu se mai poate genera factura de refacere.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $selectedClientCui = preg_replace('/\D+/', '', (string) ($adjustment['selected_client_cui'] ?? $invoice->selected_client_cui ?? ''));
        if ($selectedClientCui === '') {
            Session::flash('error', 'Selecteaza clientul pentru emiterea facturii de refacere.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
        }

        $adjustmentRows = $this->loadInvoiceAdjustmentPackages($adjustmentId);
        if (empty($adjustmentRows)) {
            Session::flash('error', 'Refacerea nu are pachete asociate pentru facturare.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $settings = new SettingsService();
        $codUnic = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        $secret = trim((string) $settings->get('fgo.api_key', ''));
        if ($secret === '') {
            $secret = trim((string) $settings->get('fgo.secret_key', ''));
        }
        $seriesOptions = $settings->get('fgo.series_list', []);
        if (!is_array($seriesOptions)) {
            $seriesOptions = [];
        }
        $series = trim((string) ($_POST['fgo_series'] ?? ''));
        if ($series === '') {
            $series = trim((string) $settings->get('fgo.series', ''));
        }
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));
        if (!empty($seriesOptions) && $series !== '' && !in_array($series, $seriesOptions, true)) {
            Session::flash('error', 'Seria selectata nu exista in setarile FGO.');
            Response::redirect('/admin/setari');
        }
        if ($codUnic === '' || $secret === '' || $series === '') {
            Session::flash('error', 'Completeaza CUI companie, Cheia API si seria FGO.');
            Response::redirect('/admin/setari');
        }
        if ($baseUrl === '') {
            $baseUrl = 'https://api.fgo.ro/v1';
        }

        $clientCompany = Company::findByCui($selectedClientCui);
        if (!$clientCompany) {
            Session::flash('error', 'Completeaza datele clientului in pagina Companii.');
            Response::redirect('/admin/companii');
        }
        $clientCountry = $this->normalizeCountry($clientCompany->tara ?? '');
        $missing = [];
        if (trim((string) ($clientCompany->denumire ?? '')) === '') {
            $missing[] = 'denumire';
        }
        if (trim((string) ($clientCompany->adresa ?? '')) === '') {
            $missing[] = 'adresa';
        }
        if (trim((string) ($clientCompany->localitate ?? '')) === '') {
            $missing[] = 'localitate';
        }
        if ($clientCountry === 'RO' && trim((string) ($clientCompany->judet ?? '')) === '') {
            $missing[] = 'judet';
        }
        if (!empty($missing)) {
            Session::flash('error', 'Completeaza datele clientului: ' . implode(', ', $missing) . '.');
            Response::redirect('/admin/companii/edit?cui=' . urlencode($selectedClientCui));
        }

        $commissionPercent = isset($adjustment['commission_percent']) && $adjustment['commission_percent'] !== null
            ? (float) $adjustment['commission_percent']
            : null;
        if ($commissionPercent === null) {
            $commission = Commission::forSupplierClient($invoice->supplier_cui, $selectedClientCui);
            if (!$commission) {
                Session::flash('error', 'Nu exista comision pentru acest client.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#client-select');
            }
            $commissionPercent = (float) $commission->commission;
        }

        $packageIds = [];
        foreach ($adjustmentRows as $row) {
            $stornoId = (int) ($row['storno_package_id'] ?? 0);
            $replacementId = (int) ($row['replacement_package_id'] ?? 0);
            if ($stornoId > 0) {
                $packageIds[$stornoId] = true;
            }
            if ($replacementId > 0) {
                $packageIds[$replacementId] = true;
            }
        }
        $packageIds = array_map('intval', array_keys($packageIds));
        if (empty($packageIds)) {
            Session::flash('error', 'Nu exista pachete pentru emiterea refacerii.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $packagesById = $this->fetchPackagesByIds($packageIds);
        $totalsByPackage = $this->packageTotalsForIds($packageIds);
        $content = [];
        foreach (['storno_package_id', 'replacement_package_id'] as $column) {
            foreach ($adjustmentRows as $row) {
                $packageId = (int) ($row[$column] ?? 0);
                if ($packageId <= 0 || !isset($packagesById[$packageId])) {
                    continue;
                }
                $package = $packagesById[$packageId];
                $packageTotalVat = (float) (($totalsByPackage[$packageId]['total_vat'] ?? 0.0));
                if (abs($packageTotalVat) < 0.0001) {
                    continue;
                }
                $packageClientTotal = $this->commissionService->applyCommission($packageTotalVat, $commissionPercent);
                $label = $this->packageLabel($package);
                $content[] = [
                    'Denumire' => $label,
                    'UM' => 'BUC',
                    'NrProduse' => 1,
                    'CotaTVA' => (float) $package->vat_percent,
                    'PretTotal' => number_format($packageClientTotal, 2, '.', ''),
                ];
            }
        }
        if (empty($content)) {
            Session::flash('error', 'Nu am putut construi continutul facturii de refacere.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $issueDate = date('Y-m-d');
        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForEmitere($codUnic, $secret, (string) ($clientCompany->denumire ?? '')),
            'Valuta' => $invoice->currency ?: 'RON',
            'TipFactura' => 'Factura',
            'Serie' => $series,
            'DataEmitere' => $issueDate,
            'VerificareDuplicat' => 'true',
            'IdExtern' => 'INV-ADJ-' . $invoice->id . '-' . $adjustmentId,
            'Client' => [
                'Denumire' => $clientCompany->denumire,
                'CodUnic' => $clientCompany->cui,
                'NrRegCom' => $clientCompany->nr_reg_comertului,
                'Email' => $clientCompany->email,
                'Telefon' => $clientCompany->telefon,
                'Tara' => $clientCountry,
                'Judet' => $clientCompany->judet,
                'Localitate' => $clientCompany->localitate,
                'Adresa' => $clientCompany->adresa,
                'Tip' => 'PJ',
                'PlatitorTVA' => $clientCompany->platitor_tva ? 'true' : 'false',
            ],
            'Continut' => $content,
            'PlatformaUrl' => FgoClient::platformUrl(),
        ];
        if (!empty($invoice->due_date)) {
            $payload['DataScadenta'] = $invoice->due_date;
        }

        $client = new FgoClient($baseUrl);
        $response = $client->post('factura/emitere', $payload);
        if (empty($response['Success'])) {
            $message = isset($response['Message']) ? (string) $response['Message'] : 'Eroare emitere factura FGO pentru refacere.';
            Session::flash('error', $message);
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        $factura = $response['Factura'] ?? [];
        $fgoNumber = (string) ($factura['Numar'] ?? '');
        $fgoSeries = (string) ($factura['Serie'] ?? $series);
        $fgoLink = (string) ($factura['Link'] ?? '');
        if ($fgoNumber === '') {
            Session::flash('error', 'Factura FGO de refacere nu a returnat numarul emis.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
        }

        Database::execute(
            'UPDATE invoice_adjustments
             SET fgo_series = :serie,
                 fgo_number = :numar,
                 fgo_link = :link,
                 fgo_generated_at = :generated_at,
                 status = :status,
                 updated_at = :now
             WHERE id = :id',
            [
                'serie' => $fgoSeries,
                'numar' => $fgoNumber,
                'link' => $fgoLink,
                'generated_at' => date('Y-m-d H:i:s'),
                'status' => 'fgo_generated',
                'now' => date('Y-m-d H:i:s'),
                'id' => $adjustmentId,
            ]
        );

        Audit::record('invoice.adjustment_fgo_generated', 'invoice_in', $invoice->id, [
            'adjustment_id' => $adjustmentId,
            'fgo_series' => $fgoSeries,
            'fgo_number' => $fgoNumber,
            'rows_count' => count($content),
        ]);
        Session::flash('status', 'Factura FGO pentru refacere a fost generata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#invoice-refacere');
    }

    public function moveLine(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $lineId = isset($_POST['line_id']) ? (int) $_POST['line_id'] : 0;
        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;

        if ($invoiceId && $lineId) {
            $invoice = $this->guardInvoice($invoiceId);
            if ($this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed])) {
                Session::flash('error', 'Pachetele sunt confirmate si nu mai pot fi modificate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $line = InvoiceInLine::find($lineId);

            if (!$line || $line->invoice_in_id !== $invoiceId) {
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            if ($packageId) {
                $package = Package::find($packageId);

                if (!$package || $package->invoice_in_id !== $invoiceId) {
                    Session::flash('error', 'Pachet invalid.');
                    Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
                }

                if ($package->vat_percent <= 0) {
                    Package::updateVat($packageId, $line->tax_percent);
                } elseif (abs($package->vat_percent - $line->tax_percent) > 0.01) {
                    Session::flash('error', 'Poti muta doar produse cu aceeasi cota TVA.');
                    Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
                }
            }

            InvoiceInLine::updatePackage($lineId, $packageId ?: null);
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function splitLine(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $lineId = isset($_POST['line_id']) ? (int) $_POST['line_id'] : 0;
        $splitQtyRaw = trim((string) ($_POST['split_qty'] ?? ''));
        $splitQty = $this->parseNumber($splitQtyRaw);

        if (!$invoiceId || !$lineId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);
        if ($this->packageLockService->isInvoiceLocked(['packages_confirmed' => $invoice->packages_confirmed])) {
            Session::flash('error', 'Pachetele sunt confirmate si nu pot fi modificate.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $line = InvoiceInLine::find($lineId);
        if (!$line || $line->invoice_in_id !== $invoiceId) {
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $currentQty = (float) $line->quantity;
        if ($currentQty <= 1.0) {
            Session::flash('error', 'Linia selectata nu poate fi separata.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        if ($splitQty === null || $splitQty <= 0 || !ctype_digit(str_replace(' ', '', $splitQtyRaw))) {
            Session::flash('error', 'Cantitatea de separat este invalida.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        if ($splitQty >= $currentQty) {
            Session::flash('error', 'Cantitatea de separat trebuie sa fie mai mica decat cantitatea initiala.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $splitQty = (int) round($splitQty);
        $remainingQty = (int) round($currentQty - $splitQty);
        if ($remainingQty <= 0) {
            Session::flash('error', 'Cantitatea ramasa este invalida.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $unitPrice = (float) $line->unit_price;
        $taxPercent = (float) $line->tax_percent;

        $remainingTotal = round($remainingQty * $unitPrice, 2);
        $remainingTotalVat = round($remainingTotal * (1 + $taxPercent / 100), 2);

        $splitTotal = round($splitQty * $unitPrice, 2);
        $splitTotalVat = round($splitTotal * (1 + $taxPercent / 100), 2);

        Database::execute(
            'UPDATE invoice_in_lines
             SET quantity = :qty, line_total = :total, line_total_vat = :total_vat
             WHERE id = :id',
            [
                'qty' => $remainingQty,
                'total' => $remainingTotal,
                'total_vat' => $remainingTotalVat,
                'id' => $line->id,
            ]
        );

        $newLineNo = trim((string) $line->line_no);
        if ($newLineNo === '') {
            $newLineNo = 'split';
        } else {
            $newLineNo .= 'S';
        }
        if (strlen($newLineNo) > 32) {
            $newLineNo = substr($newLineNo, 0, 32);
        }

        Database::execute(
            'INSERT INTO invoice_in_lines (
                invoice_in_id,
                line_no,
                product_name,
                quantity,
                unit_code,
                unit_price,
                line_total,
                tax_percent,
                line_total_vat,
                package_id,
                created_at
            ) VALUES (
                :invoice_in_id,
                :line_no,
                :product_name,
                :quantity,
                :unit_code,
                :unit_price,
                :line_total,
                :tax_percent,
                :line_total_vat,
                :package_id,
                :created_at
            )',
            [
                'invoice_in_id' => $invoiceId,
                'line_no' => $newLineNo,
                'product_name' => $line->product_name,
                'quantity' => $splitQty,
                'unit_code' => $line->unit_code,
                'unit_price' => $unitPrice,
                'line_total' => $splitTotal,
                'tax_percent' => $taxPercent,
                'line_total_vat' => $splitTotalVat,
                'package_id' => $line->package_id,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('status', 'Linia a fost separata.');
        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    private function storeXml(string $tmpPath, string $invoiceNumber): ?string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $invoiceNumber ?: 'factura');
        $dir = BASE_PATH . '/storage/invoices_in';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $target = $dir . '/' . $safe . '_' . date('Ymd_His') . '.xml';

        if (!copy($tmpPath, $target)) {
            return null;
        }

        return 'storage/invoices_in/' . basename($target);
    }

    private function storeSupplierFile(string $tmpPath, string $invoiceNumber, string $extension): ?string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $invoiceNumber ?: 'factura');
        $dir = BASE_PATH . '/storage/invoices_in';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $target = $dir . '/' . $safe . '_' . date('Ymd_His') . '.' . $extension;

        if (!move_uploaded_file($tmpPath, $target)) {
            return null;
        }

        return 'storage/invoices_in/' . basename($target);
    }

    private function invoiceFilePath(InvoiceIn $invoice): ?string
    {
        $path = trim((string) ($invoice->xml_path ?? ''));
        if ($path === '') {
            return null;
        }

        $full = BASE_PATH . '/' . ltrim($path, '/');
        return $full !== '' ? $full : null;
    }

    private function xmlDataIncomplete(?array $data): bool
    {
        if (!$data) {
            return true;
        }

        $lines = $data['lines'] ?? [];
        $hasLines = !empty($lines);
        $hasInvoice = !empty($data['invoice_number']);
        $hasSupplier = !empty($data['supplier_name']) || !empty($data['supplier_cui']);
        $hasCustomer = !empty($data['customer_name']) || !empty($data['customer_cui']);

        if (!$hasLines) {
            return true;
        }

        if (!$hasInvoice && !$hasSupplier && !$hasCustomer) {
            return true;
        }

        return false;
    }

    private function buildXmlFallbackData(InvoiceIn $invoice): array
    {
        $lines = InvoiceInLine::forInvoice($invoice->id);
        $mappedLines = [];

        foreach ($lines as $line) {
            $mappedLines[] = [
                'line_no' => $line->line_no,
                'product_name' => $line->product_name,
                'quantity' => $line->quantity,
                'unit_code' => $line->unit_code,
                'unit_price' => $line->unit_price,
                'line_total' => $line->line_total,
                'tax_percent' => $line->tax_percent,
                'line_total_vat' => $line->line_total_vat,
            ];
        }

        return [
            'invoice_number' => (string) $invoice->invoice_number,
            'invoice_series' => (string) $invoice->invoice_series,
            'invoice_no' => (string) $invoice->invoice_no,
            'issue_date' => (string) $invoice->issue_date,
            'due_date' => $invoice->due_date ? (string) $invoice->due_date : null,
            'currency' => (string) ($invoice->currency ?: 'RON'),
            'supplier_cui' => (string) $invoice->supplier_cui,
            'supplier_name' => (string) $invoice->supplier_name,
            'customer_cui' => (string) $invoice->customer_cui,
            'customer_name' => (string) $invoice->customer_name,
            'total_without_vat' => (float) $invoice->total_without_vat,
            'total_vat' => (float) $invoice->total_vat,
            'total_with_vat' => (float) $invoice->total_with_vat,
            'lines' => $mappedLines,
        ];
    }

    private function mergeXmlViewData(?array $parsed, array $fallback): array
    {
        $data = $parsed ?? [];

        foreach ($fallback as $key => $value) {
            if ($key === 'lines') {
                if (empty($data['lines']) && !empty($value)) {
                    $data['lines'] = $value;
                }
                continue;
            }

            if (!array_key_exists($key, $data) || $data[$key] === null || $data[$key] === '') {
                $data[$key] = $value;
                continue;
            }

            if (is_numeric($data[$key]) && (float) $data[$key] == 0.0 && (float) $value != 0.0) {
                $data[$key] = $value;
            }
        }

        return $data;
    }

    private function formatXmlForDisplay(string $content): string
    {
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content) ?? $content;
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        if (class_exists(\DOMDocument::class)) {
            libxml_use_internal_errors(true);
            $dom = new \DOMDocument('1.0', 'UTF-8');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (@$dom->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE)) {
                libxml_clear_errors();
                return $dom->saveXML();
            }

            libxml_clear_errors();
        }

        return preg_replace('/>\s*</', ">\n<", $content) ?? $content;
    }

    private function loadXmlDocument(string $content, ?string &$error = null): ?\DOMDocument
    {
        $content = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $content) ?? $content;
        $content = trim($content);

        if ($content === '') {
            $error = 'Fisierul XML este gol.';
            return null;
        }

        if (!class_exists(\DOMDocument::class)) {
            $error = 'Parserul XML nu este disponibil.';
            return null;
        }

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;

        if (!@$dom->loadXML($content, LIBXML_NONET | LIBXML_NOCDATA | LIBXML_PARSEHUGE)) {
            $error = $this->xmlErrorMessage() ?? 'XML invalid.';
            return null;
        }

        libxml_clear_errors();

        if (!$dom->documentElement) {
            $error = 'XML invalid.';
            return null;
        }

        return $dom;
    }

    private function xmlNodeToTree(\DOMNode $node): array
    {
        $name = $node->localName ?: $node->nodeName;
        $attributes = [];

        if ($node->attributes) {
            foreach ($node->attributes as $attr) {
                if (!$attr instanceof \DOMAttr) {
                    continue;
                }
                $attributes[$attr->name] = $attr->value;
            }
        }

        $children = [];
        $textParts = [];

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE) {
                $children[] = $this->xmlNodeToTree($child);
                continue;
            }

            if (in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $text = trim((string) $child->textContent);
                if ($text !== '') {
                    $textParts[] = $text;
                }
            }
        }

        $value = !empty($textParts) ? implode(' ', $textParts) : null;

        return [
            'name' => $name,
            'label' => $this->humanizeXmlTag($name),
            'attributes' => $attributes,
            'value' => $value,
            'children' => $children,
        ];
    }

    private function buildXmlDisplayData(\DOMDocument $dom): array
    {
        $xpath = new \DOMXPath($dom);

        $invoiceNumber = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="ID"][1]') ?? '';
        $issueDate = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="IssueDate"][1]') ?? '';
        $dueDate = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="DueDate"][1]');
        $currency = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="DocumentCurrencyCode"][1]') ?? 'RON';
        $invoiceType = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="InvoiceTypeCode"][1]');
        $note = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="Note"][1]');
        $orderRef = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="OrderReference"]/*[local-name()="ID"][1]');
        $despatchRef = $this->domValue($xpath, '/*[local-name()="Invoice"]/*[local-name()="DespatchDocumentReference"]/*[local-name()="ID"][1]');

        $supplierParty = $this->domNode($xpath, '//*[local-name()="AccountingSupplierParty"]//*[local-name()="Party"][1]');
        $customerParty = $this->domNode($xpath, '//*[local-name()="AccountingCustomerParty"]//*[local-name()="Party"][1]');

        $supplier = $this->domPartyData($xpath, $supplierParty);
        $customer = $this->domPartyData($xpath, $customerParty);

        $deliveryNode = $this->domNode($xpath, '//*[local-name()="Delivery"][1]');
        $deliveryAddressNode = $deliveryNode ? $this->domNode($xpath, './/*[local-name()="DeliveryLocation"]//*[local-name()="Address"][1]', $deliveryNode) : null;
        $delivery = [
            'date' => $deliveryNode ? $this->domValue($xpath, './/*[local-name()="ActualDeliveryDate"][1]', $deliveryNode) : null,
            'location_id' => $deliveryNode ? $this->domValue($xpath, './/*[local-name()="DeliveryLocation"]/*[local-name()="ID"][1]', $deliveryNode) : null,
            'address' => $deliveryAddressNode ? $this->domAddressData($xpath, $deliveryAddressNode) : [],
        ];

        $paymentNode = $this->domNode($xpath, '//*[local-name()="PaymentMeans"][1]');
        $payment = [
            'code' => $paymentNode ? $this->domValue($xpath, './/*[local-name()="PaymentMeansCode"][1]', $paymentNode) : null,
            'account' => $paymentNode ? $this->domValue($xpath, './/*[local-name()="PayeeFinancialAccount"]/*[local-name()="ID"][1]', $paymentNode) : null,
            'bank' => $paymentNode ? $this->domValue($xpath, './/*[local-name()="PayeeFinancialAccount"]/*[local-name()="Name"][1]', $paymentNode) : null,
        ];

        $totalsNode = $this->domNode($xpath, '//*[local-name()="LegalMonetaryTotal"][1]');
        $totals = [
            'line_extension' => $totalsNode ? $this->domValue($xpath, './/*[local-name()="LineExtensionAmount"][1]', $totalsNode) : null,
            'tax_exclusive' => $totalsNode ? $this->domValue($xpath, './/*[local-name()="TaxExclusiveAmount"][1]', $totalsNode) : null,
            'tax_inclusive' => $totalsNode ? $this->domValue($xpath, './/*[local-name()="TaxInclusiveAmount"][1]', $totalsNode) : null,
            'payable' => $totalsNode ? $this->domValue($xpath, './/*[local-name()="PayableAmount"][1]', $totalsNode) : null,
        ];

        $taxTotalNode = $this->domNode($xpath, '//*[local-name()="TaxTotal"][1]');
        $taxTotal = $taxTotalNode ? $this->domValue($xpath, './/*[local-name()="TaxAmount"][1]', $taxTotalNode) : null;

        $taxSubtotals = [];
        foreach ($this->domNodes($xpath, '//*[local-name()="TaxSubtotal"]') as $node) {
            $taxSubtotals[] = [
                'taxable' => $this->domValue($xpath, './/*[local-name()="TaxableAmount"][1]', $node),
                'tax' => $this->domValue($xpath, './/*[local-name()="TaxAmount"][1]', $node),
                'category' => $this->domValue($xpath, './/*[local-name()="TaxCategory"]/*[local-name()="ID"][1]', $node),
                'percent' => $this->domValue($xpath, './/*[local-name()="TaxCategory"]/*[local-name()="Percent"][1]', $node),
                'scheme' => $this->domValue($xpath, './/*[local-name()="TaxCategory"]/*[local-name()="TaxScheme"]/*[local-name()="ID"][1]', $node),
            ];
        }

        $lines = [];
        foreach ($this->domNodes($xpath, '//*[local-name()="InvoiceLine"]') as $lineNode) {
            $quantityNode = $this->domNode($xpath, './/*[local-name()="InvoicedQuantity"][1]', $lineNode);
            $lineAmountNode = $this->domNode($xpath, './/*[local-name()="LineExtensionAmount"][1]', $lineNode);
            $priceAmountNode = $this->domNode($xpath, './/*[local-name()="PriceAmount"][1]', $lineNode);

            $lineTotal = $lineAmountNode ? $this->domValue($xpath, '.', $lineAmountNode) : null;
            $taxPercent = $this->domValue($xpath, './/*[local-name()="ClassifiedTaxCategory"]/*[local-name()="Percent"][1]', $lineNode);

            $lineTotalVat = null;
            if (is_numeric($lineTotal) && is_numeric($taxPercent)) {
                $lineTotalVat = round((float) $lineTotal * (1 + (float) $taxPercent / 100), 2);
            }

            $allowances = [];
            foreach ($this->domNodes($xpath, './/*[local-name()="AllowanceCharge"]', $lineNode) as $allowNode) {
                $amountNode = $this->domNode($xpath, './/*[local-name()="Amount"][1]', $allowNode);
                $allowances[] = [
                    'charge' => $this->domValue($xpath, './/*[local-name()="ChargeIndicator"][1]', $allowNode),
                    'reason' => $this->domValue($xpath, './/*[local-name()="AllowanceChargeReason"][1]', $allowNode),
                    'amount' => $amountNode ? $this->domValue($xpath, '.', $amountNode) : null,
                    'currency' => $amountNode ? $this->domAttr($amountNode, 'currencyID') : null,
                ];
            }

            $lines[] = [
                'id' => $this->domValue($xpath, './/*[local-name()="ID"][1]', $lineNode),
                'line_ref' => $this->domValue($xpath, './/*[local-name()="OrderLineReference"]/*[local-name()="LineID"][1]', $lineNode),
                'quantity' => $quantityNode ? $this->domValue($xpath, '.', $quantityNode) : null,
                'unit_code' => $quantityNode ? $this->domAttr($quantityNode, 'unitCode') : null,
                'line_total' => $lineTotal,
                'line_currency' => $lineAmountNode ? $this->domAttr($lineAmountNode, 'currencyID') : null,
                'product_name' => $this->domValue($xpath, './/*[local-name()="Item"]/*[local-name()="Name"][1]', $lineNode),
                'tax_category' => $this->domValue($xpath, './/*[local-name()="ClassifiedTaxCategory"]/*[local-name()="ID"][1]', $lineNode),
                'tax_percent' => $taxPercent,
                'unit_price' => $priceAmountNode ? $this->domValue($xpath, '.', $priceAmountNode) : null,
                'unit_currency' => $priceAmountNode ? $this->domAttr($priceAmountNode, 'currencyID') : null,
                'base_qty' => $this->domValue($xpath, './/*[local-name()="BaseQuantity"][1]', $lineNode),
                'base_unit' => ($baseNode = $this->domNode($xpath, './/*[local-name()="BaseQuantity"][1]', $lineNode)) ? $this->domAttr($baseNode, 'unitCode') : null,
                'total_with_vat' => $lineTotalVat,
                'allowances' => $allowances,
            ];
        }

        return [
            'invoice_number' => $invoiceNumber,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'currency' => $currency,
            'invoice_type' => $invoiceType,
            'note' => $note,
            'order_ref' => $orderRef,
            'despatch_ref' => $despatchRef,
            'supplier' => $supplier,
            'customer' => $customer,
            'delivery' => $delivery,
            'payment' => $payment,
            'totals' => $totals,
            'tax_total' => $taxTotal,
            'tax_subtotals' => $taxSubtotals,
            'lines' => $lines,
        ];
    }

    private function humanizeXmlTag(string $name): string
    {
        $map = [
            'Invoice' => 'Factura',
            'UBLVersionID' => 'Versiune UBL',
            'CustomizationID' => 'Personalizare',
            'ID' => 'ID',
            'IssueDate' => 'Data emitere',
            'DueDate' => 'Scadenta',
            'InvoiceTypeCode' => 'Tip factura',
            'Note' => 'Nota',
            'TaxPointDate' => 'Data taxare',
            'DocumentCurrencyCode' => 'Moneda',
            'TaxCurrencyCode' => 'Moneda TVA',
            'OrderReference' => 'Referinta comanda',
            'DespatchDocumentReference' => 'Document expeditie',
            'AccountingSupplierParty' => 'Furnizor',
            'AccountingCustomerParty' => 'Client',
            'PartyIdentification' => 'Identificare',
            'PartyName' => 'Denumire',
            'PostalAddress' => 'Adresa',
            'StreetName' => 'Strada',
            'CityName' => 'Localitate',
            'PostalZone' => 'Cod postal',
            'CountrySubentity' => 'Judet',
            'Country' => 'Tara',
            'IdentificationCode' => 'Cod tara',
            'PartyTaxScheme' => 'Date fiscale',
            'CompanyID' => 'CUI',
            'TaxScheme' => 'Schema TVA',
            'PartyLegalEntity' => 'Entitate juridica',
            'RegistrationName' => 'Denumire registru',
            'CompanyLegalForm' => 'Forma juridica',
            'Delivery' => 'Livrare',
            'ActualDeliveryDate' => 'Data livrare',
            'DeliveryLocation' => 'Loc livrare',
            'Address' => 'Adresa',
            'DeliveryParty' => 'Destinatar',
            'PaymentMeans' => 'Plata',
            'PaymentMeansCode' => 'Cod plata',
            'PayeeFinancialAccount' => 'Cont incasare',
            'TaxTotal' => 'Total TVA',
            'TaxSubtotal' => 'Subtotal TVA',
            'TaxableAmount' => 'Baza TVA',
            'TaxAmount' => 'Valoare TVA',
            'TaxCategory' => 'Categorie TVA',
            'Percent' => 'Procent',
            'LegalMonetaryTotal' => 'Totaluri',
            'LineExtensionAmount' => 'Valoare fara TVA',
            'TaxExclusiveAmount' => 'Total fara TVA',
            'TaxInclusiveAmount' => 'Total cu TVA',
            'PayableAmount' => 'Total de plata',
            'InvoiceLine' => 'Linie factura',
            'InvoicedQuantity' => 'Cantitate',
            'LineID' => 'ID linie',
            'Item' => 'Produs',
            'Name' => 'Denumire',
            'ClassifiedTaxCategory' => 'TVA produs',
            'Price' => 'Pret',
            'PriceAmount' => 'Pret unitar',
            'BaseQuantity' => 'Cantitate baza',
            'AllowanceCharge' => 'Taxa/Discount',
            'ChargeIndicator' => 'Taxa aplicata',
            'AllowanceChargeReason' => 'Motiv taxa',
            'Amount' => 'Suma',
        ];

        if (isset($map[$name])) {
            return $map[$name];
        }

        $label = preg_replace('/[_-]+/', ' ', $name);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', (string) $label);
        $label = trim((string) $label);

        if ($label === '') {
            return $name;
        }

        return ucfirst($label);
    }

    private function domValue(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        $value = trim((string) $nodes->item(0)?->textContent);
        return $value !== '' ? $value : null;
    }

    private function domNode(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): ?\DOMNode
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return null;
        }

        return $nodes->item(0);
    }

    private function domNodes(\DOMXPath $xpath, string $query, ?\DOMNode $context = null): array
    {
        $nodes = $xpath->query($query, $context);
        if (!$nodes || $nodes->length === 0) {
            return [];
        }

        $result = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMNode) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function domAttr(?\DOMNode $node, string $name): ?string
    {
        if (!$node || !$node->attributes) {
            return null;
        }

        $attr = $node->attributes->getNamedItem($name);
        if (!$attr) {
            return null;
        }

        $value = trim((string) $attr->nodeValue);
        return $value !== '' ? $value : null;
    }

    private function domAddressData(\DOMXPath $xpath, \DOMNode $context): array
    {
        return [
            'street' => $this->domValue($xpath, './/*[local-name()="StreetName"][1]', $context),
            'city' => $this->domValue($xpath, './/*[local-name()="CityName"][1]', $context),
            'postal' => $this->domValue($xpath, './/*[local-name()="PostalZone"][1]', $context),
            'region' => $this->domValue($xpath, './/*[local-name()="CountrySubentity"][1]', $context),
            'country' => $this->domValue($xpath, './/*[local-name()="Country"]/*[local-name()="IdentificationCode"][1]', $context),
        ];
    }

    private function domPartyData(\DOMXPath $xpath, ?\DOMNode $party): array
    {
        if (!$party) {
            return [
                'name' => null,
                'cui' => null,
                'reg' => null,
                'address' => [],
            ];
        }

        $addressNode = $this->domNode($xpath, './/*[local-name()="PostalAddress"][1]', $party);

        return [
            'name' => $this->domValue($xpath, './/*[local-name()="PartyName"]/*[local-name()="Name"][1]', $party)
                ?: $this->domValue($xpath, './/*[local-name()="PartyLegalEntity"]/*[local-name()="RegistrationName"][1]', $party),
            'cui' => $this->domValue($xpath, './/*[local-name()="PartyTaxScheme"]/*[local-name()="CompanyID"][1]', $party)
                ?: $this->domValue($xpath, './/*[local-name()="PartyIdentification"]/*[local-name()="ID"][1]', $party),
            'reg' => $this->domValue($xpath, './/*[local-name()="PartyLegalEntity"]/*[local-name()="CompanyID"][1]', $party),
            'address' => $addressNode ? $this->domAddressData($xpath, $addressNode) : [],
        ];
    }

    private function xmlErrorMessage(): ?string
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();

        if (!$errors) {
            return null;
        }

        $first = $errors[0];
        $message = trim($first->message ?? '');

        if ($message === '') {
            return null;
        }

        $line = $first->line ?? 0;
        $column = $first->column ?? 0;

        return 'Detalii: ' . $message . ($line ? ' (linia ' . $line . ', coloana ' . $column . ')' : '');
    }

    private function detectMimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);
            if (is_string($mime) && $mime !== '') {
                return $mime;
            }
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'pdf' => 'application/pdf',
            'xml' => 'application/xml',
            default => 'application/octet-stream',
        };
    }

    private function packageStats(array $lines, array $packages): array
    {
        $stats = [];

        foreach ($packages as $package) {
            $stats[$package->id] = [
                'label' => $this->packageLabel($package),
                'package_no' => $package->package_no,
                'vat_percent' => $package->vat_percent,
                'line_count' => 0,
                'total' => 0.0,
                'total_vat' => 0.0,
            ];
        }

        foreach ($lines as $line) {
            if (!$line->package_id || !isset($stats[$line->package_id])) {
                continue;
            }

            $stats[$line->package_id]['line_count']++;
            $stats[$line->package_id]['total'] += $line->line_total;
            $stats[$line->package_id]['total_vat'] += $line->line_total_vat;
        }

        return $stats;
    }

    private function ensureInvoiceTables(): bool
    {
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS invoices_in (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_number VARCHAR(64) NOT NULL,
                    invoice_series VARCHAR(32) NOT NULL DEFAULT "",
                    invoice_no VARCHAR(32) NOT NULL DEFAULT "",
                    supplier_cui VARCHAR(32) NOT NULL,
                    supplier_name VARCHAR(255) NOT NULL,
                    customer_cui VARCHAR(32) NOT NULL,
                    customer_name VARCHAR(255) NOT NULL,
                    selected_client_cui VARCHAR(32) NULL,
                    issue_date DATE NOT NULL,
                    due_date DATE NULL,
                    currency VARCHAR(8) NOT NULL,
                    total_without_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    xml_path VARCHAR(255) NULL,
                    packages_confirmed TINYINT(1) NOT NULL DEFAULT 0,
                    packages_confirmed_at DATETIME NULL,
                    fgo_series VARCHAR(32) NULL,
                    fgo_number VARCHAR(32) NULL,
                    fgo_date DATE NULL,
                    fgo_generated_at DATETIME NULL,
                    fgo_link VARCHAR(255) NULL,
                    fgo_storno_series VARCHAR(32) NULL,
                    fgo_storno_number VARCHAR(32) NULL,
                    fgo_storno_link VARCHAR(255) NULL,
                    fgo_storno_at DATETIME NULL,
                    order_note_no INT NULL,
                    order_note_date DATE NULL,
                    commission_percent DECIMAL(6,2) NULL,
                    supplier_request_at DATETIME NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            Database::execute(
                'CREATE TABLE IF NOT EXISTS packages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_in_id BIGINT UNSIGNED NOT NULL,
                    package_no INT NOT NULL DEFAULT 0,
                    label VARCHAR(64) NULL,
                    vat_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                    saga_value DECIMAL(12,2) NULL,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci AUTO_INCREMENT=10000'
            );

            Database::execute(
                'CREATE TABLE IF NOT EXISTS invoice_in_lines (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_in_id BIGINT UNSIGNED NOT NULL,
                    package_id BIGINT UNSIGNED NULL,
                    line_no VARCHAR(32) NOT NULL,
                    product_name VARCHAR(255) NOT NULL,
                    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
                    unit_code VARCHAR(16) NOT NULL,
                    unit_price DECIMAL(12,4) NOT NULL DEFAULT 0,
                    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                    tax_percent DECIMAL(6,2) NOT NULL DEFAULT 0,
                    line_total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            Database::execute(
                'CREATE TABLE IF NOT EXISTS invoice_adjustments (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    invoice_in_id BIGINT UNSIGNED NOT NULL,
                    source_fgo_series VARCHAR(32) NULL,
                    source_fgo_number VARCHAR(32) NULL,
                    selected_client_cui VARCHAR(32) NULL,
                    commission_percent DECIMAL(6,2) NULL,
                    source_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    target_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    decrease_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    storno_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    status VARCHAR(32) NOT NULL DEFAULT \'applied\',
                    fgo_series VARCHAR(32) NULL,
                    fgo_number VARCHAR(32) NULL,
                    fgo_link VARCHAR(255) NULL,
                    fgo_generated_at DATETIME NULL,
                    created_by_user_id INT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    INDEX idx_invoice_adjustments_invoice (invoice_in_id, id),
                    INDEX idx_invoice_adjustments_status (status, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );

            Database::execute(
                'CREATE TABLE IF NOT EXISTS invoice_adjustment_packages (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    adjustment_id BIGINT UNSIGNED NOT NULL,
                    source_package_id BIGINT UNSIGNED NOT NULL,
                    storno_package_id BIGINT UNSIGNED NULL,
                    replacement_package_id BIGINT UNSIGNED NULL,
                    source_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    replacement_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    delta_total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    INDEX idx_invoice_adjustment_packages_adjustment (adjustment_id),
                    INDEX idx_invoice_adjustment_packages_source (source_package_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $exception) {
            return false;
        }

        if (Database::tableExists('packages') && !Database::columnExists('packages', 'vat_percent')) {
            Database::execute('ALTER TABLE packages ADD COLUMN vat_percent DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER label');
        }
        if (Database::tableExists('packages') && !Database::columnExists('packages', 'saga_value')) {
            Database::execute('ALTER TABLE packages ADD COLUMN saga_value DECIMAL(12,2) NULL AFTER vat_percent');
        }
        if (Database::tableExists('packages') && !Database::columnExists('packages', 'package_no')) {
            Database::execute('ALTER TABLE packages ADD COLUMN package_no INT NOT NULL DEFAULT 0 AFTER invoice_in_id');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'invoice_series')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN invoice_series VARCHAR(32) NOT NULL DEFAULT "" AFTER invoice_number');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'invoice_no')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN invoice_no VARCHAR(32) NOT NULL DEFAULT "" AFTER invoice_series');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'selected_client_cui')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN selected_client_cui VARCHAR(32) NULL AFTER customer_name');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'packages_confirmed')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN packages_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER xml_path');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'packages_confirmed_at')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN packages_confirmed_at DATETIME NULL AFTER packages_confirmed');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_series')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_series VARCHAR(32) NULL AFTER packages_confirmed_at');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_number')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_number VARCHAR(32) NULL AFTER fgo_series');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_date')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_date DATE NULL AFTER fgo_number');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_generated_at')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_generated_at DATETIME NULL AFTER fgo_date');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_link')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_link VARCHAR(255) NULL AFTER fgo_generated_at');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_storno_series')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_storno_series VARCHAR(32) NULL AFTER fgo_link');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_storno_number')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_storno_number VARCHAR(32) NULL AFTER fgo_storno_series');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_storno_link')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_storno_link VARCHAR(255) NULL AFTER fgo_storno_number');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_storno_at')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_storno_at DATETIME NULL AFTER fgo_storno_link');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'order_note_no')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN order_note_no INT NULL AFTER fgo_storno_at');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'order_note_date')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN order_note_date DATE NULL AFTER order_note_no');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'commission_percent')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN commission_percent DECIMAL(6,2) NULL AFTER order_note_date');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'supplier_request_at')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN supplier_request_at DATETIME NULL AFTER commission_percent');
        }
        if (Database::tableExists('invoice_adjustments') && !Database::columnExists('invoice_adjustments', 'fgo_series')) {
            Database::execute('ALTER TABLE invoice_adjustments ADD COLUMN fgo_series VARCHAR(32) NULL AFTER status');
        }
        if (Database::tableExists('invoice_adjustments') && !Database::columnExists('invoice_adjustments', 'fgo_number')) {
            Database::execute('ALTER TABLE invoice_adjustments ADD COLUMN fgo_number VARCHAR(32) NULL AFTER fgo_series');
        }
        if (Database::tableExists('invoice_adjustments') && !Database::columnExists('invoice_adjustments', 'fgo_link')) {
            Database::execute('ALTER TABLE invoice_adjustments ADD COLUMN fgo_link VARCHAR(255) NULL AFTER fgo_number');
        }
        if (Database::tableExists('invoice_adjustments') && !Database::columnExists('invoice_adjustments', 'fgo_generated_at')) {
            Database::execute('ALTER TABLE invoice_adjustments ADD COLUMN fgo_generated_at DATETIME NULL AFTER fgo_link');
        }
        if (Database::tableExists('invoice_in_lines') && !Database::columnExists('invoice_in_lines', 'cod_saga')) {
            Database::execute('ALTER TABLE invoice_in_lines ADD COLUMN cod_saga VARCHAR(64) NULL AFTER product_name');
        }
        if (Database::tableExists('invoice_in_lines') && !Database::columnExists('invoice_in_lines', 'stock_saga')) {
            Database::execute('ALTER TABLE invoice_in_lines ADD COLUMN stock_saga DECIMAL(12,3) NULL AFTER cod_saga');
        }
        if (!Database::tableExists('saga_products')) {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS saga_products (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name_key VARCHAR(255) NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    cod_saga VARCHAR(64) NOT NULL,
                    stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $this->ensurePackageAutoIncrement();

        return true;
    }

    private function generatePackages(int $invoiceId, array $counts = []): void
    {
        $lines = InvoiceInLine::forInvoice($invoiceId);

        if (empty($lines)) {
            return;
        }

        Database::execute(
            'UPDATE invoice_in_lines SET package_id = NULL WHERE invoice_in_id = :invoice',
            ['invoice' => $invoiceId]
        );
        Database::execute('DELETE FROM packages WHERE invoice_in_id = :invoice', ['invoice' => $invoiceId]);

        $groups = $this->buildVatGroups($lines);
        $nextNumber = $this->lastConfirmedPackageNo() + 1;

        foreach ($groups as $vat => $groupLines) {
            $requested = isset($counts[$vat]) ? (int) $counts[$vat] : 1;
            $maxAllowed = max(1, count($groupLines));
            $packageCount = max(1, min($requested, $maxAllowed));

            $packages = [];
            for ($i = 0; $i < $packageCount; $i++) {
                $packages[] = Package::create($invoiceId, $nextNumber, (float) $vat);
                $nextNumber++;
            }

            $index = 0;
            foreach ($groupLines as $line) {
                $target = $packages[$index % $packageCount];
                InvoiceInLine::updatePackage($line->id, $target->id);
                $index++;
            }
        }
    }

    private function buildVatGroups(array $lines): array
    {
        $groups = [];

        foreach ($lines as $line) {
            $vat = number_format($line->tax_percent, 2, '.', '');
            $groups[$vat][] = $line;
        }

        ksort($groups, SORT_NUMERIC);

        return $groups;
    }

    private function vatRates(array $lines): array
    {
        $rates = [];

        foreach ($lines as $line) {
            $rates[number_format($line->tax_percent, 2, '.', '')] = true;
        }

        $vatRates = array_keys($rates);
        sort($vatRates, SORT_NUMERIC);

        return $vatRates;
    }

    private function packageDefaults(array $packages, array $vatRates): array
    {
        $counts = [];

        foreach ($packages as $package) {
            $vat = number_format($package->vat_percent, 2, '.', '');
            $counts[$vat] = ($counts[$vat] ?? 0) + 1;
        }

        $defaults = [];
        foreach ($vatRates as $vat) {
            $defaults[$vat] = $counts[$vat] ?? 1;
        }

        return $defaults;
    }

    private function groupLinesByPackage(array $lines, array $packages): array
    {
        $grouped = [];

        foreach ($packages as $package) {
            $grouped[$package->id] = [];
        }

        $grouped[0] = [];

        foreach ($lines as $line) {
            $key = $line->package_id ?? 0;
            $grouped[$key][] = $line;
        }

        return $grouped;
    }

    private function invoiceExists(string $supplierCui, string $invoiceSeries, string $invoiceNo, string $invoiceNumber): bool
    {
        if (!Database::tableExists('invoices_in')) {
            return false;
        }

        if ($invoiceSeries !== '' && $invoiceNo !== '') {
            $row = Database::fetchOne(
                'SELECT id FROM invoices_in WHERE supplier_cui = :supplier AND invoice_series = :series AND invoice_no = :no LIMIT 1',
                ['supplier' => $supplierCui, 'series' => $invoiceSeries, 'no' => $invoiceNo]
            );
        } else {
            $row = Database::fetchOne(
                'SELECT id FROM invoices_in WHERE supplier_cui = :supplier AND invoice_number = :number LIMIT 1',
                ['supplier' => $supplierCui, 'number' => $invoiceNumber]
            );
        }

        return $row !== null;
    }

    private function packageTotalsWithCommission(array $packageStats, ?float $commissionPercent): array
    {
        $totals = [
            'packages' => [],
            'invoice_total' => 0.0,
        ];

        if ($commissionPercent === null) {
            return $totals;
        }

        foreach ($packageStats as $packageId => $stat) {
            $value = $this->commissionService->applyCommission($stat['total_vat'], $commissionPercent);
            $totals['packages'][$packageId] = $value;
            $totals['invoice_total'] += $value;
        }

        return $totals;
    }

    private function lastConfirmedPackageNo(): int
    {
        $settings = new SettingsService();
        $value = (int) $settings->get('packages.last_confirmed_no', 10000);

        return $value > 0 ? $value : 10000;
    }

    private function setLastConfirmedPackageNo(int $value): void
    {
        $settings = new SettingsService();
        $settings->set('packages.last_confirmed_no', $value);
    }

    private function confirmPackages(int $invoiceId): void
    {
        $packages = Package::forInvoice($invoiceId);

        if (empty($packages)) {
            return;
        }

        $ordered = $this->orderPackages($packages);
        $nextNumber = $this->lastConfirmedPackageNo() + 1;

        foreach ($ordered as $package) {
            Package::updateNumber($package->id, $nextNumber);
            $nextNumber++;
        }

        $this->setLastConfirmedPackageNo($nextNumber - 1);

        Database::execute(
            'UPDATE invoices_in SET packages_confirmed = 1, packages_confirmed_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $invoiceId]
        );
        $this->invoiceAuditService->recordPackagesConfirmed($invoiceId, count($packages));
    }

    private function renumberPackages(int $invoiceId): void
    {
        $packages = Package::forInvoice($invoiceId);

        if (empty($packages)) {
            return;
        }

        $ordered = $this->orderPackages($packages);
        $nextNumber = $this->lastConfirmedPackageNo() + 1;

        foreach ($ordered as $package) {
            Package::updateNumber($package->id, $nextNumber);
            $nextNumber++;
        }
    }

    private function orderPackages(array $packages): array
    {
        usort($packages, function (Package $a, Package $b): int {
            if (abs($a->vat_percent - $b->vat_percent) > 0.01) {
                return $a->vat_percent <=> $b->vat_percent;
            }

            return $a->id <=> $b->id;
        });

        return $packages;
    }

    private function unconfirmPackages(int $invoiceId): void
    {
        Database::execute(
            'UPDATE invoices_in SET packages_confirmed = 0, packages_confirmed_at = NULL, updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $invoiceId]
        );
    }

    private function createStornoPackagesForInvoice(int $invoiceId): array
    {
        if ($invoiceId <= 0 || !Database::tableExists('packages') || !Database::tableExists('invoice_in_lines')) {
            return ['packages_created' => 0, 'lines_created' => 0];
        }

        $packages = Package::forInvoice($invoiceId);
        if (empty($packages)) {
            return ['packages_created' => 0, 'lines_created' => 0];
        }

        $pdo = Database::pdo();
        $hasSagaStatus = Database::columnExists('packages', 'saga_status');
        $hasCodSaga = Database::columnExists('invoice_in_lines', 'cod_saga');
        $hasStockSaga = Database::columnExists('invoice_in_lines', 'stock_saga');
        $now = date('Y-m-d H:i:s');
        $createdPackages = 0;
        $createdLines = 0;

        $pdo->beginTransaction();
        try {
            foreach ($packages as $package) {
                $sourceLines = Database::fetchAll(
                    'SELECT * FROM invoice_in_lines WHERE package_id = :package ORDER BY id ASC',
                    ['package' => $package->id]
                );
                if (empty($sourceLines)) {
                    continue;
                }

                $sourceTotalVat = 0.0;
                foreach ($sourceLines as $sourceLine) {
                    $sourceTotalVat += (float) ($sourceLine['line_total_vat'] ?? 0);
                }
                if ($sourceTotalVat <= 0.0) {
                    continue;
                }

                $stornoLabel = $package->label;
                $stornoPackageNo = (int) $package->package_no;
                if ($stornoPackageNo <= 0) {
                    $stornoPackageNo = $package->id;
                }

                if ($hasSagaStatus) {
                    Database::execute(
                        'INSERT INTO packages (invoice_in_id, package_no, label, vat_percent, saga_status, created_at)
                         VALUES (:invoice_in_id, :package_no, :label, :vat_percent, :saga_status, :created_at)',
                        [
                            'invoice_in_id' => $invoiceId,
                            'package_no' => $stornoPackageNo,
                            'label' => $stornoLabel,
                            'vat_percent' => $package->vat_percent,
                            'saga_status' => 'pending',
                            'created_at' => $now,
                        ]
                    );
                } else {
                    Database::execute(
                        'INSERT INTO packages (invoice_in_id, package_no, label, vat_percent, created_at)
                         VALUES (:invoice_in_id, :package_no, :label, :vat_percent, :created_at)',
                        [
                            'invoice_in_id' => $invoiceId,
                            'package_no' => $stornoPackageNo,
                            'label' => $stornoLabel,
                            'vat_percent' => $package->vat_percent,
                            'created_at' => $now,
                        ]
                    );
                }
                $newPackageId = (int) Database::lastInsertId();
                $createdPackages++;

                foreach ($sourceLines as $line) {
                    $lineNo = $this->buildStornoLineNo((string) ($line['line_no'] ?? ''));
                    $params = [
                        'invoice_in_id' => $invoiceId,
                        'line_no' => $lineNo,
                        'product_name' => (string) ($line['product_name'] ?? ''),
                        'quantity' => -1 * (float) ($line['quantity'] ?? 0),
                        'unit_code' => (string) ($line['unit_code'] ?? ''),
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'line_total' => -1 * (float) ($line['line_total'] ?? 0),
                        'tax_percent' => (float) ($line['tax_percent'] ?? 0),
                        'line_total_vat' => -1 * (float) ($line['line_total_vat'] ?? 0),
                        'package_id' => $newPackageId,
                        'created_at' => $now,
                    ];

                    if ($hasCodSaga && $hasStockSaga) {
                        Database::execute(
                            'INSERT INTO invoice_in_lines (
                                invoice_in_id, line_no, product_name, cod_saga, stock_saga,
                                quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                            ) VALUES (
                                :invoice_in_id, :line_no, :product_name, :cod_saga, :stock_saga,
                                :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                            )',
                            array_merge($params, [
                                'cod_saga' => $line['cod_saga'] ?? null,
                                'stock_saga' => isset($line['stock_saga']) ? (float) $line['stock_saga'] : null,
                            ])
                        );
                    } elseif ($hasCodSaga) {
                        Database::execute(
                            'INSERT INTO invoice_in_lines (
                                invoice_in_id, line_no, product_name, cod_saga,
                                quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                            ) VALUES (
                                :invoice_in_id, :line_no, :product_name, :cod_saga,
                                :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                            )',
                            array_merge($params, [
                                'cod_saga' => $line['cod_saga'] ?? null,
                            ])
                        );
                    } else {
                        Database::execute(
                            'INSERT INTO invoice_in_lines (
                                invoice_in_id, line_no, product_name,
                                quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                            ) VALUES (
                                :invoice_in_id, :line_no, :product_name,
                                :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                            )',
                            $params
                        );
                    }
                    $createdLines++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'packages_created' => $createdPackages,
            'lines_created' => $createdLines,
        ];
    }

    private function buildStornoLineNo(string $lineNo): string
    {
        $lineNo = trim($lineNo);
        if ($lineNo === '') {
            $lineNo = 'storno';
        } else {
            $lineNo .= 'ST';
        }

        return strlen($lineNo) > 32 ? substr($lineNo, 0, 32) : $lineNo;
    }

    private function canRefacereInvoice(?\App\Domain\Users\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->isSupplierUser()) {
            return true;
        }

        return $user->hasRole(['super_admin', 'admin', 'contabil', 'operator', 'staff']);
    }

    private function buildInvoiceAdjustmentCandidates(int $invoiceId, array $packages, array $linesByPackage, array $packageStats): array
    {
        $usedSourcePackageIds = $this->usedSourcePackageIdsForAdjustments($invoiceId);
        $candidates = [];

        foreach ($packages as $package) {
            $packageId = (int) ($package->id ?? 0);
            if ($packageId <= 0 || isset($usedSourcePackageIds[$packageId])) {
                continue;
            }

            $stat = $packageStats[$packageId] ?? null;
            $packageTotalVat = (float) (($stat['total_vat'] ?? 0.0));
            if ($packageTotalVat <= 0.009) {
                continue;
            }

            $lines = $linesByPackage[$packageId] ?? [];
            $lineRows = [];
            foreach ($lines as $line) {
                $quantity = (float) ($line->quantity ?? 0.0);
                if ($quantity <= 0.0001) {
                    continue;
                }
                $lineRows[] = [
                    'line_id' => (int) ($line->id ?? 0),
                    'line_no' => (string) ($line->line_no ?? ''),
                    'product_name' => (string) ($line->product_name ?? ''),
                    'quantity' => $quantity,
                    'unit_code' => (string) ($line->unit_code ?? ''),
                    'unit_price' => (float) ($line->unit_price ?? 0.0),
                    'tax_percent' => (float) ($line->tax_percent ?? 0.0),
                    'line_total_vat' => (float) ($line->line_total_vat ?? 0.0),
                    'cod_saga' => $line->cod_saga ?? null,
                    'stock_saga' => $line->stock_saga ?? null,
                ];
            }
            if (empty($lineRows)) {
                continue;
            }

            $labelText = $this->packageLabelText($package);
            $candidates[] = [
                'package_id' => $packageId,
                'package_no' => (int) ($package->package_no ?? 0),
                'label_text' => $labelText,
                'label' => $labelText . ' #' . (int) ($package->package_no ?? 0),
                'vat_percent' => (float) ($package->vat_percent ?? 0.0),
                'total_vat' => round($packageTotalVat, 2),
                'line_count' => count($lineRows),
                'lines' => $lineRows,
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            $leftNo = (int) ($left['package_no'] ?? 0);
            $rightNo = (int) ($right['package_no'] ?? 0);
            if ($leftNo !== $rightNo) {
                return $leftNo <=> $rightNo;
            }

            return ((int) ($left['package_id'] ?? 0)) <=> ((int) ($right['package_id'] ?? 0));
        });

        return $candidates;
    }

    private function usedSourcePackageIdsForAdjustments(int $invoiceId): array
    {
        if (
            $invoiceId <= 0
            || !Database::tableExists('invoice_adjustments')
            || !Database::tableExists('invoice_adjustment_packages')
        ) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT DISTINCT ap.source_package_id
             FROM invoice_adjustment_packages ap
             JOIN invoice_adjustments a ON a.id = ap.adjustment_id
             WHERE a.invoice_in_id = :invoice',
            ['invoice' => $invoiceId]
        );

        $ids = [];
        foreach ($rows as $row) {
            $id = (int) ($row['source_package_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function buildInvoiceAdjustmentPlan(array $candidates, array $requestedQuantities): array
    {
        $changes = [];
        $errors = [];
        $stornoTotalVat = 0.0;
        $replacementTotalVat = 0.0;

        foreach ($candidates as $candidate) {
            $packageId = (int) ($candidate['package_id'] ?? 0);
            if ($packageId <= 0) {
                continue;
            }

            $lines = is_array($candidate['lines'] ?? null) ? $candidate['lines'] : [];
            if (empty($lines)) {
                continue;
            }

            $packageChanged = false;
            $packageNewTotalVat = 0.0;
            $plannedLines = [];

            foreach ($lines as $line) {
                $lineId = (int) ($line['line_id'] ?? 0);
                if ($lineId <= 0) {
                    continue;
                }
                $oldQty = round((float) ($line['quantity'] ?? 0.0), 3);
                if ($oldQty <= 0.0001) {
                    continue;
                }

                $rawRequested = $requestedQuantities[$packageId][$lineId] ?? $oldQty;
                $newQty = $this->parseNumber($rawRequested);
                if ($newQty === null) {
                    $errors[] = 'Cantitate invalida pentru produsul "' . (string) ($line['product_name'] ?? '') . '".';
                    continue;
                }

                $newQty = round($newQty, 3);
                if ($newQty < -0.0001) {
                    $errors[] = 'Cantitatea noua nu poate fi negativa pentru produsul "' . (string) ($line['product_name'] ?? '') . '".';
                    continue;
                }
                if ($newQty > $oldQty + 0.0001) {
                    $errors[] = 'Cantitatea noua nu poate depasi cantitatea initiala pentru produsul "' . (string) ($line['product_name'] ?? '') . '".';
                    continue;
                }
                if ($newQty < 0.0) {
                    $newQty = 0.0;
                }

                $unitPrice = (float) ($line['unit_price'] ?? 0.0);
                $taxPercent = (float) ($line['tax_percent'] ?? 0.0);
                $newLineTotal = round($newQty * $unitPrice, 2);
                $newLineTotalVat = round($newLineTotal * (1 + ($taxPercent / 100)), 2);

                if (abs($newQty - $oldQty) > 0.0005) {
                    $packageChanged = true;
                }
                $packageNewTotalVat += $newLineTotalVat;

                $plannedLines[] = array_merge($line, [
                    'old_quantity' => $oldQty,
                    'new_quantity' => $newQty,
                    'new_line_total' => $newLineTotal,
                    'new_line_total_vat' => $newLineTotalVat,
                ]);
            }

            if (!$packageChanged || !empty($errors)) {
                continue;
            }

            $sourceTotalVat = round((float) ($candidate['total_vat'] ?? 0.0), 2);
            if ($sourceTotalVat <= 0.009) {
                continue;
            }

            $packageNewTotalVat = round($packageNewTotalVat, 2);
            $changes[] = [
                'source_package_id' => $packageId,
                'source_package_no' => (int) ($candidate['package_no'] ?? 0),
                'label_text' => (string) ($candidate['label_text'] ?? ''),
                'vat_percent' => (float) ($candidate['vat_percent'] ?? 0.0),
                'source_total_vat' => $sourceTotalVat,
                'replacement_total_vat' => $packageNewTotalVat,
                'delta_total_vat' => round($packageNewTotalVat - $sourceTotalVat, 2),
                'lines' => $plannedLines,
            ];
            $stornoTotalVat += $sourceTotalVat;
            $replacementTotalVat += $packageNewTotalVat;
        }

        $summary = [
            'storno_total_vat' => round($stornoTotalVat, 2),
            'replacement_total_vat' => round($replacementTotalVat, 2),
            'decrease_total_vat' => round($stornoTotalVat - $replacementTotalVat, 2),
        ];

        return [$changes, $summary, $errors];
    }

    private function applyInvoiceAdjustmentPlan(InvoiceIn $invoice, array $changes, array $summary, ?\App\Domain\Users\Models\User $user): array
    {
        if (
            !Database::tableExists('invoice_adjustments')
            || !Database::tableExists('invoice_adjustment_packages')
            || !Database::tableExists('packages')
            || !Database::tableExists('invoice_in_lines')
        ) {
            throw new \RuntimeException('Tabelele de refacere nu sunt disponibile.');
        }

        $pdo = Database::pdo();
        $hasSagaStatus = Database::columnExists('packages', 'saga_status');
        $hasCodSaga = Database::columnExists('invoice_in_lines', 'cod_saga');
        $hasStockSaga = Database::columnExists('invoice_in_lines', 'stock_saga');
        $now = date('Y-m-d H:i:s');

        $createdPackages = 0;
        $createdLines = 0;
        $stornoPackages = 0;
        $replacementPackages = 0;
        $nextPackageNo = $this->lastConfirmedPackageNo() + 1;

        $sourceTotalVat = round((float) ($invoice->total_with_vat ?? 0.0), 2);
        $decreaseTotalVat = round((float) ($summary['decrease_total_vat'] ?? 0.0), 2);
        $targetTotalVat = round($sourceTotalVat - $decreaseTotalVat, 2);
        if ($targetTotalVat < 0) {
            $targetTotalVat = 0.0;
        }

        $adjustmentId = 0;
        $pdo->beginTransaction();
        try {
            Database::execute(
                'INSERT INTO invoice_adjustments (
                    invoice_in_id,
                    source_fgo_series,
                    source_fgo_number,
                    selected_client_cui,
                    commission_percent,
                    source_total_with_vat,
                    target_total_with_vat,
                    decrease_total_with_vat,
                    storno_total_with_vat,
                    status,
                    created_by_user_id,
                    created_at,
                    updated_at
                ) VALUES (
                    :invoice_in_id,
                    :source_fgo_series,
                    :source_fgo_number,
                    :selected_client_cui,
                    :commission_percent,
                    :source_total_with_vat,
                    :target_total_with_vat,
                    :decrease_total_with_vat,
                    :storno_total_with_vat,
                    :status,
                    :created_by_user_id,
                    :created_at,
                    :updated_at
                )',
                [
                    'invoice_in_id' => (int) $invoice->id,
                    'source_fgo_series' => (string) ($invoice->fgo_series ?? ''),
                    'source_fgo_number' => (string) ($invoice->fgo_number ?? ''),
                    'selected_client_cui' => (string) ($invoice->selected_client_cui ?? ''),
                    'commission_percent' => $invoice->commission_percent !== null ? (float) $invoice->commission_percent : null,
                    'source_total_with_vat' => $sourceTotalVat,
                    'target_total_with_vat' => $targetTotalVat,
                    'decrease_total_with_vat' => $decreaseTotalVat,
                    'storno_total_with_vat' => round((float) ($summary['storno_total_vat'] ?? 0.0), 2),
                    'status' => 'applied',
                    'created_by_user_id' => $user ? (int) $user->id : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            $adjustmentId = (int) Database::lastInsertId();

            $changeRows = [];
            foreach ($changes as $change) {
                $labelText = trim((string) ($change['label_text'] ?? ''));
                if ($labelText === '') {
                    $labelText = 'Pachet de produse';
                }
                $vatPercent = (float) ($change['vat_percent'] ?? 0.0);
                $sourcePackageId = (int) ($change['source_package_id'] ?? 0);
                $sourcePackageNo = (int) ($change['source_package_no'] ?? 0);
                $lines = is_array($change['lines'] ?? null) ? $change['lines'] : [];
                if ($sourcePackageId <= 0 || empty($lines)) {
                    continue;
                }
                if ($sourcePackageNo <= 0) {
                    $sourcePackageNo = $sourcePackageId;
                }

                // Storno keeps original package label and number.
                $stornoPackageId = $this->insertAdjustmentPackage(
                    (int) $invoice->id,
                    $sourcePackageNo,
                    $labelText,
                    $vatPercent,
                    $now,
                    $hasSagaStatus
                );
                $createdPackages++;
                $stornoPackages++;

                foreach ($lines as $line) {
                    $oldQty = round((float) ($line['old_quantity'] ?? $line['quantity'] ?? 0.0), 3);
                    if ($oldQty <= 0.0001) {
                        continue;
                    }
                    $this->insertAdjustmentLine(
                        (int) $invoice->id,
                        $stornoPackageId,
                        $line,
                        -1 * $oldQty,
                        $this->buildStornoLineNo((string) ($line['line_no'] ?? '')),
                        $now,
                        $hasCodSaga,
                        $hasStockSaga
                    );
                    $createdLines++;
                }

                $changeRows[] = [
                    'change' => $change,
                    'label_text' => $labelText,
                    'vat_percent' => $vatPercent,
                    'lines' => $lines,
                    'source_package_id' => $sourcePackageId,
                    'storno_package_id' => $stornoPackageId,
                    'replacement_package_id' => null,
                ];
            }

            // Replacement packages are created only after all storno packages.
            foreach ($changeRows as &$changeRow) {
                $change = is_array($changeRow['change'] ?? null) ? $changeRow['change'] : [];
                if ((float) ($change['replacement_total_vat'] ?? 0.0) <= 0.009) {
                    continue;
                }

                $replacementPackageId = $this->insertAdjustmentPackage(
                    (int) $invoice->id,
                    $nextPackageNo,
                    (string) ($changeRow['label_text'] ?? 'Pachet de produse'),
                    (float) ($changeRow['vat_percent'] ?? 0.0),
                    $now,
                    $hasSagaStatus
                );
                $nextPackageNo++;
                $createdPackages++;
                $replacementPackages++;

                $createdReplacementLines = 0;
                $lines = is_array($changeRow['lines'] ?? null) ? $changeRow['lines'] : [];
                foreach ($lines as $line) {
                    $newQty = round((float) ($line['new_quantity'] ?? 0.0), 3);
                    if ($newQty <= 0.0001) {
                        continue;
                    }
                    $this->insertAdjustmentLine(
                        (int) $invoice->id,
                        (int) $replacementPackageId,
                        $line,
                        $newQty,
                        $this->buildRefacereLineNo((string) ($line['line_no'] ?? '')),
                        $now,
                        $hasCodSaga,
                        $hasStockSaga
                    );
                    $createdLines++;
                    $createdReplacementLines++;
                }

                if ($createdReplacementLines <= 0) {
                    Database::execute('DELETE FROM packages WHERE id = :id', ['id' => $replacementPackageId]);
                    $createdPackages--;
                    $replacementPackages--;
                    continue;
                }

                $changeRow['replacement_package_id'] = (int) $replacementPackageId;
            }
            unset($changeRow);

            foreach ($changeRows as $changeRow) {
                $change = is_array($changeRow['change'] ?? null) ? $changeRow['change'] : [];
                Database::execute(
                    'INSERT INTO invoice_adjustment_packages (
                        adjustment_id,
                        source_package_id,
                        storno_package_id,
                        replacement_package_id,
                        source_total_with_vat,
                        replacement_total_with_vat,
                        delta_total_with_vat,
                        created_at
                    ) VALUES (
                        :adjustment_id,
                        :source_package_id,
                        :storno_package_id,
                        :replacement_package_id,
                        :source_total_with_vat,
                        :replacement_total_with_vat,
                        :delta_total_with_vat,
                        :created_at
                    )',
                    [
                        'adjustment_id' => $adjustmentId,
                        'source_package_id' => (int) ($changeRow['source_package_id'] ?? 0),
                        'storno_package_id' => (int) ($changeRow['storno_package_id'] ?? 0),
                        'replacement_package_id' => (int) ($changeRow['replacement_package_id'] ?? 0) ?: null,
                        'source_total_with_vat' => round((float) ($change['source_total_vat'] ?? 0.0), 2),
                        'replacement_total_with_vat' => round((float) ($change['replacement_total_vat'] ?? 0.0), 2),
                        'delta_total_with_vat' => round((float) ($change['delta_total_vat'] ?? 0.0), 2),
                        'created_at' => $now,
                    ]
                );
            }

            if ($createdPackages <= 0) {
                throw new \RuntimeException('Nu s-au putut crea pachete pentru refacere.');
            }

            if ($createdPackages > 0) {
                $this->setLastConfirmedPackageNo($nextPackageNo - 1);
            }
            $totals = $this->recalculateInvoiceTotals((int) $invoice->id, $now);

            $pdo->commit();

            return [
                'adjustment_id' => $adjustmentId,
                'packages_created' => $createdPackages,
                'lines_created' => $createdLines,
                'storno_packages' => $stornoPackages,
                'replacement_packages' => $replacementPackages,
                'decrease_total_vat' => $decreaseTotalVat,
                'invoice_total_with_vat' => (float) ($totals['total_with_vat'] ?? 0.0),
            ];
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    private function insertAdjustmentPackage(
        int $invoiceId,
        int $packageNo,
        string $labelText,
        float $vatPercent,
        string $now,
        bool $hasSagaStatus
    ): int {
        if ($hasSagaStatus) {
            Database::execute(
                'INSERT INTO packages (invoice_in_id, package_no, label, vat_percent, saga_status, created_at)
                 VALUES (:invoice_in_id, :package_no, :label, :vat_percent, :saga_status, :created_at)',
                [
                    'invoice_in_id' => $invoiceId,
                    'package_no' => $packageNo,
                    'label' => $labelText,
                    'vat_percent' => $vatPercent,
                    'saga_status' => 'pending',
                    'created_at' => $now,
                ]
            );
        } else {
            Database::execute(
                'INSERT INTO packages (invoice_in_id, package_no, label, vat_percent, created_at)
                 VALUES (:invoice_in_id, :package_no, :label, :vat_percent, :created_at)',
                [
                    'invoice_in_id' => $invoiceId,
                    'package_no' => $packageNo,
                    'label' => $labelText,
                    'vat_percent' => $vatPercent,
                    'created_at' => $now,
                ]
            );
        }

        return (int) Database::lastInsertId();
    }

    private function insertAdjustmentLine(
        int $invoiceId,
        int $packageId,
        array $lineData,
        float $quantity,
        string $lineNo,
        string $now,
        bool $hasCodSaga,
        bool $hasStockSaga
    ): void {
        $unitPrice = (float) ($lineData['unit_price'] ?? 0.0);
        $taxPercent = (float) ($lineData['tax_percent'] ?? 0.0);
        $lineTotal = round($quantity * $unitPrice, 2);
        $lineTotalVat = round($lineTotal * (1 + ($taxPercent / 100)), 2);

        $params = [
            'invoice_in_id' => $invoiceId,
            'line_no' => $lineNo,
            'product_name' => (string) ($lineData['product_name'] ?? ''),
            'quantity' => $quantity,
            'unit_code' => (string) ($lineData['unit_code'] ?? ''),
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'tax_percent' => $taxPercent,
            'line_total_vat' => $lineTotalVat,
            'package_id' => $packageId,
            'created_at' => $now,
        ];

        if ($hasCodSaga && $hasStockSaga) {
            Database::execute(
                'INSERT INTO invoice_in_lines (
                    invoice_in_id, line_no, product_name, cod_saga, stock_saga,
                    quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                ) VALUES (
                    :invoice_in_id, :line_no, :product_name, :cod_saga, :stock_saga,
                    :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                )',
                array_merge($params, [
                    'cod_saga' => $lineData['cod_saga'] ?? null,
                    'stock_saga' => isset($lineData['stock_saga']) ? (float) $lineData['stock_saga'] : null,
                ])
            );
        } elseif ($hasCodSaga) {
            Database::execute(
                'INSERT INTO invoice_in_lines (
                    invoice_in_id, line_no, product_name, cod_saga,
                    quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                ) VALUES (
                    :invoice_in_id, :line_no, :product_name, :cod_saga,
                    :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                )',
                array_merge($params, [
                    'cod_saga' => $lineData['cod_saga'] ?? null,
                ])
            );
        } else {
            Database::execute(
                'INSERT INTO invoice_in_lines (
                    invoice_in_id, line_no, product_name,
                    quantity, unit_code, unit_price, line_total, tax_percent, line_total_vat, package_id, created_at
                ) VALUES (
                    :invoice_in_id, :line_no, :product_name,
                    :quantity, :unit_code, :unit_price, :line_total, :tax_percent, :line_total_vat, :package_id, :created_at
                )',
                $params
            );
        }
    }

    private function buildRefacereLineNo(string $lineNo): string
    {
        $lineNo = trim($lineNo);
        if ($lineNo === '') {
            $lineNo = 'refacere';
        } else {
            $lineNo .= 'RF';
        }

        return strlen($lineNo) > 32 ? substr($lineNo, 0, 32) : $lineNo;
    }

    private function recalculateInvoiceTotals(int $invoiceId, string $now): array
    {
        $totals = Database::fetchOne(
            'SELECT
                COALESCE(SUM(line_total), 0) AS total_without_vat,
                COALESCE(SUM(line_total_vat), 0) AS total_with_vat
             FROM invoice_in_lines
             WHERE invoice_in_id = :invoice',
            ['invoice' => $invoiceId]
        ) ?? [];

        $totalWithoutVat = round((float) ($totals['total_without_vat'] ?? 0.0), 2);
        $totalWithVat = round((float) ($totals['total_with_vat'] ?? 0.0), 2);
        $totalVat = round($totalWithVat - $totalWithoutVat, 2);

        Database::execute(
            'UPDATE invoices_in
             SET total_without_vat = :without_vat,
                 total_vat = :vat,
                 total_with_vat = :with_vat,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'without_vat' => $totalWithoutVat,
                'vat' => $totalVat,
                'with_vat' => $totalWithVat,
                'updated_at' => $now,
                'id' => $invoiceId,
            ]
        );

        return [
            'total_without_vat' => $totalWithoutVat,
            'total_vat' => $totalVat,
            'total_with_vat' => $totalWithVat,
        ];
    }

    private function loadInvoiceAdjustments(int $invoiceId, int $limit = 10): array
    {
        if ($invoiceId <= 0 || !Database::tableExists('invoice_adjustments')) {
            return [];
        }
        $limit = max(1, min(50, $limit));

        return Database::fetchAll(
            'SELECT a.*,
                    (
                        SELECT COUNT(*)
                        FROM invoice_adjustment_packages ap
                        WHERE ap.adjustment_id = a.id
                    ) AS package_changes
             FROM invoice_adjustments a
             WHERE a.invoice_in_id = :invoice
             ORDER BY a.id DESC
             LIMIT ' . $limit,
            ['invoice' => $invoiceId]
        );
    }

    private function findInvoiceAdjustmentById(int $adjustmentId): ?array
    {
        if ($adjustmentId <= 0 || !Database::tableExists('invoice_adjustments')) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM invoice_adjustments WHERE id = :id LIMIT 1',
            ['id' => $adjustmentId]
        );
    }

    private function loadInvoiceAdjustmentPackages(int $adjustmentId): array
    {
        if (
            $adjustmentId <= 0
            || !Database::tableExists('invoice_adjustment_packages')
        ) {
            return [];
        }

        return Database::fetchAll(
            'SELECT *
             FROM invoice_adjustment_packages
             WHERE adjustment_id = :adjustment
             ORDER BY id ASC',
            ['adjustment' => $adjustmentId]
        );
    }

    private function fetchPackagesByIds(array $packageIds): array
    {
        $normalized = [];
        foreach ($packageIds as $packageId) {
            $id = (int) $packageId;
            if ($id > 0) {
                $normalized[$id] = true;
            }
        }
        $packageIds = array_keys($normalized);
        if (empty($packageIds) || !Database::tableExists('packages')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($packageIds as $index => $packageId) {
            $key = 'p' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $packageId;
        }

        $rows = Database::fetchAll(
            'SELECT *
             FROM packages
             WHERE id IN (' . implode(',', $placeholders) . ')',
            $params
        );
        $result = [];
        foreach ($rows as $row) {
            $package = Package::fromArray($row);
            $result[(int) $package->id] = $package;
        }

        return $result;
    }

    private function safeFileName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $value);

        return $safe !== '' ? $safe : 'document';
    }

    private function fetchRemoteFile(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $data = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($data === false || $status >= 400) {
            return null;
        }

        return $data;
    }

    private function ensureOrderNote(InvoiceIn $invoice): void
    {
        if ($invoice->order_note_no && $invoice->order_note_date) {
            return;
        }

        $settings = new SettingsService();
        $last = (int) $settings->get('order_note.last_no', 999);
        $next = $last > 0 ? $last + 1 : 1000;

        $baseDate = $invoice->issue_date ?: date('Y-m-d');
        $baseTs = strtotime($baseDate);
        if ($baseTs === false) {
            $baseTs = time();
        }
        $daysBack = random_int(0, 15);
        $noteDate = date('Y-m-d', strtotime('-' . $daysBack . ' days', $baseTs));

        Database::execute(
            'UPDATE invoices_in SET order_note_no = :no, order_note_date = :date, updated_at = :now WHERE id = :id',
            [
                'no' => $next,
                'date' => $noteDate,
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoice->id,
            ]
        );

        $settings->set('order_note.last_no', $next);
    }

    private function fetchConfirmedPackagesByIds(array $packageIds, ?array $supplierCuis = null): array
    {
        if (empty($packageIds) || !Database::tableExists('packages')) {
            return [];
        }

        $placeholders = [];
        $params = [];

        foreach ($packageIds as $index => $id) {
            $key = 'id' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $supplierFilter = '';
        if (is_array($supplierCuis) && !empty($supplierCuis)) {
            $supplierPlaceholders = [];
            foreach ($supplierCuis as $index => $supplier) {
                $key = 's' . $index;
                $supplierPlaceholders[] = ':' . $key;
                $params[$key] = $supplier;
            }
            $supplierFilter = ' AND i.supplier_cui IN (' . implode(',', $supplierPlaceholders) . ')';
        }

        $sql = 'SELECT p.*, i.invoice_number, i.issue_date, i.packages_confirmed_at, i.supplier_cui
                FROM packages p
                JOIN invoices_in i ON i.id = p.invoice_in_id
                WHERE i.packages_confirmed = 1
                  AND p.id IN (' . implode(',', $placeholders) . ')' . $supplierFilter . '
                ORDER BY i.packages_confirmed_at DESC, p.package_no ASC, p.id ASC';

        return Database::fetchAll($sql, $params);
    }

    private function packageTotalsForIds(array $packageIds): array
    {
        if (empty($packageIds) || !Database::tableExists('invoice_in_lines')) {
            return [];
        }

        $totals = [];
        foreach ($packageIds as $packageId) {
            $data = $this->packageTotalsService->calculatePackageTotals((int) $packageId);
            $totals[(int) $packageId] = [
                'line_count' => (int) $data['line_count'],
                'total' => (float) $data['sum_net'],
                'total_vat' => (float) $data['sum_gross'],
            ];
        }

        return $totals;
    }

    private function ensurePackageAutoIncrement(): void
    {
        $count = (int) Database::fetchValue('SELECT COUNT(*) FROM packages');

        if ($count === 0) {
            Database::execute('ALTER TABLE packages AUTO_INCREMENT = 10000');
        }
    }

    private function normalizeCountry(string $country): string
    {
        $value = trim($country);
        if ($value === '') {
            return 'RO';
        }

        $normalized = $value;
        $translit = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);
        if ($translit !== false) {
            $normalized = $translit;
        }

        $lower = strtolower($normalized);
        if ($lower === 'romania' || $lower === 'ro') {
            return 'RO';
        }

        return $value;
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

    private function invoiceAllocationTotals(string $table): array
    {
        if (!Database::tableExists($table)) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT invoice_in_id, COALESCE(SUM(amount), 0) AS total FROM ' . $table . ' GROUP BY invoice_in_id'
        );
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['invoice_in_id']] = (float) $row['total'];
        }

        return $map;
    }

    private function commissionMap(): array
    {
        if (!Database::tableExists('commissions')) {
            return [];
        }

        $rows = Database::fetchAll('SELECT supplier_cui, client_cui, commission FROM commissions');
        $map = [];

        foreach ($rows as $row) {
            $supplier = (string) $row['supplier_cui'];
            $client = (string) $row['client_cui'];
            if ($supplier === '' || $client === '') {
                continue;
            }
            $map[$supplier][$client] = (float) $row['commission'];
        }

        return $map;
    }

    private function commissionForInvoice(InvoiceIn $invoice, array $commissionMap): ?float
    {
        if ($invoice->commission_percent !== null) {
            return (float) $invoice->commission_percent;
        }

        $supplier = (string) $invoice->supplier_cui;
        $client = (string) ($invoice->selected_client_cui ?? '');
        if ($supplier === '' || $client === '') {
            return null;
        }

        return $commissionMap[$supplier][$client] ?? null;
    }

    private function buildInvoiceStatus(InvoiceIn $invoice, float $collected, float $paid, array $commissionMap): array
    {
        $commission = $this->commissionForInvoice($invoice, $commissionMap);
        $clientTotal = null;

        if ($commission !== null) {
            $clientTotal = $this->commissionService->applyCommission((float) $invoice->total_with_vat, (float) $commission);
        }

        $clientStatus = $this->clientStatus($clientTotal, $collected);
        $supplierStatus = $this->supplierStatus((float) $invoice->total_with_vat, $paid);

        return [
            'collected' => $collected,
            'paid' => $paid,
            'client_total' => $clientTotal,
            'client_label' => $clientStatus['label'],
            'client_class' => $clientStatus['class'],
            'supplier_label' => $supplierStatus['label'],
            'supplier_class' => $supplierStatus['class'],
        ];
    }

    private function clientStatus(?float $total, float $collected): array
    {
        if ($total === null) {
            return [
                'label' => 'Client nesetat',
                'class' => 'bg-slate-100 text-slate-600',
            ];
        }

        if ($collected <= 0.009) {
            return [
                'label' => 'Neincasat',
                'class' => 'bg-rose-50 text-rose-700',
            ];
        }

        if ($collected + 0.01 < $total) {
            return [
                'label' => 'Incasat partial',
                'class' => 'bg-amber-50 text-amber-700',
            ];
        }

        return [
            'label' => 'Incasat integral',
            'class' => 'bg-emerald-50 text-emerald-700',
        ];
    }

    private function supplierStatus(float $total, float $paid): array
    {
        if ($paid <= 0.009) {
            return [
                'label' => 'Neplatit',
                'class' => 'bg-rose-50 text-rose-700',
            ];
        }

        if ($paid + 0.01 < $total) {
            return [
                'label' => 'Platit partial',
                'class' => 'bg-amber-50 text-amber-700',
            ];
        }

        return [
            'label' => 'Platit integral',
            'class' => 'bg-emerald-50 text-emerald-700',
        ];
    }

    private function collectSelectedClientCuis(array $invoices): array
    {
        $cuis = [];
        foreach ($invoices as $invoice) {
            $cui = (string) ($invoice->selected_client_cui ?? '');
            if ($cui !== '') {
                $cuis[$cui] = true;
            }
        }

        return array_keys($cuis);
    }

    private function clientNameMap(array $clientCuis): array
    {
        if (empty($clientCuis)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($clientCuis) as $index => $cui) {
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

    private function clientFinals(array $invoices, array $clientNameMap): array
    {
        $finals = [];
        foreach ($invoices as $invoice) {
            $cui = (string) ($invoice->selected_client_cui ?? '');
            if ($cui === '') {
                $finals[$invoice->id] = ['cui' => '', 'name' => ''];
                continue;
            }
            $finals[$invoice->id] = [
                'cui' => $cui,
                'name' => (string) ($clientNameMap[$cui] ?? $cui),
            ];
        }

        return $finals;
    }

    private function filterInvoices(array $invoices, string $query, array $clientNameMap, array $invoiceStatuses = []): array
    {
        $needle = $this->normalizeSearch($query);
        if ($needle === '') {
            return $invoices;
        }

        $filtered = [];

        foreach ($invoices as $invoice) {
            $clientCui = (string) ($invoice->selected_client_cui ?? '');
            $clientName = $clientCui !== '' ? (string) ($clientNameMap[$clientCui] ?? '') : '';
            $status = $invoiceStatuses[$invoice->id] ?? null;
            $clientTotal = $status['client_total'] ?? null;
            $fields = [
                $invoice->invoice_number ?? '',
                $invoice->invoice_series ?? '',
                $invoice->invoice_no ?? '',
                $invoice->supplier_name ?? '',
                $invoice->supplier_cui ?? '',
                $invoice->customer_name ?? '',
                $invoice->customer_cui ?? '',
                $clientCui,
                $clientName,
                trim((string) ($invoice->fgo_series ?? '') . ' ' . (string) ($invoice->fgo_number ?? '')),
                (string) ($invoice->fgo_series ?? ''),
                (string) ($invoice->fgo_number ?? ''),
            ];
            $fields = array_merge(
                $fields,
                $this->searchableNumberVariants((float) $invoice->total_with_vat),
                $this->searchableNumberVariants($clientTotal)
            );

            foreach ($fields as $field) {
                if ($this->containsSearch((string) $field, $needle)) {
                    $filtered[] = $invoice;
                    break;
                }
            }
        }

        return $filtered;
    }

    private function invoiceFiltersFromRequest(): array
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_GET['supplier_cui'] ?? ''));
        $clientRaw = (string) ($_GET['client_cui'] ?? '');
        if ($clientRaw === 'none') {
            $clientCui = 'none';
        } else {
            $clientCui = preg_replace('/\D+/', '', $clientRaw);
        }
        $clientStatus = $this->normalizeStatusFilters(
            $_GET['client_status'] ?? [],
            $this->clientStatusOptions()
        );
        $supplierStatus = $this->normalizeStatusFilters(
            $_GET['supplier_status'] ?? [],
            $this->supplierStatusOptions()
        );
        $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
        $dateTo = trim((string) ($_GET['date_to'] ?? ''));
        $perPage = (int) ($_GET['per_page'] ?? 25);
        $allowedPerPage = [25, 50, 250, 500];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 25;
        }
        $page = max(1, (int) ($_GET['page'] ?? 1));

        return [
            'query' => $query,
            'supplier_cui' => $supplierCui,
            'client_cui' => $clientCui,
            'client_status' => $clientStatus,
            'supplier_status' => $supplierStatus,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'per_page' => $perPage,
            'page' => $page,
        ];
    }

    private function applyInvoiceFilters(
        array $invoices,
        array $filters,
        array $clientNameMap,
        array $invoiceStatuses
    ): array {
        $filtered = $invoices;

        if ($filters['query'] !== '') {
            $filtered = $this->filterInvoices($filtered, $filters['query'], $clientNameMap, $invoiceStatuses);
        }

        if ($filters['supplier_cui'] !== '') {
            $supplierCui = $filters['supplier_cui'];
            $filtered = array_filter($filtered, static fn (InvoiceIn $invoice) => (string) $invoice->supplier_cui === $supplierCui);
        }

        if ($filters['client_cui'] !== '') {
            $clientCui = $filters['client_cui'];
            if ($clientCui === 'none') {
                $filtered = array_filter(
                    $filtered,
                    static fn (InvoiceIn $invoice) => trim((string) ($invoice->selected_client_cui ?? '')) === ''
                );
            } else {
                $filtered = array_filter(
                    $filtered,
                    static fn (InvoiceIn $invoice) => (string) ($invoice->selected_client_cui ?? '') === $clientCui
                );
            }
        }

        $fromTs = $this->parseDateFilter($filters['date_from'] ?? '');
        $toTs = $this->parseDateFilter($filters['date_to'] ?? '', true);
        if ($fromTs !== null || $toTs !== null) {
            $filtered = array_filter($filtered, static function (InvoiceIn $invoice) use ($fromTs, $toTs): bool {
                $issueTs = strtotime((string) $invoice->issue_date);
                if ($issueTs === false) {
                    return false;
                }
                if ($fromTs !== null && $issueTs < $fromTs) {
                    return false;
                }
                if ($toTs !== null && $issueTs > $toTs) {
                    return false;
                }
                return true;
            });
        }

        $clientStatuses = $filters['client_status'] ?? [];
        if (!is_array($clientStatuses)) {
            $clientStatuses = $clientStatuses !== '' ? [(string) $clientStatuses] : [];
        }
        if (!empty($clientStatuses)) {
            $filtered = array_filter($filtered, static function (InvoiceIn $invoice) use ($invoiceStatuses, $clientStatuses): bool {
                $status = $invoiceStatuses[$invoice->id] ?? null;
                return $status && in_array($status['client_label'], $clientStatuses, true);
            });
        }

        $supplierStatuses = $filters['supplier_status'] ?? [];
        if (!is_array($supplierStatuses)) {
            $supplierStatuses = $supplierStatuses !== '' ? [(string) $supplierStatuses] : [];
        }
        if (!empty($supplierStatuses)) {
            $filtered = array_filter($filtered, static function (InvoiceIn $invoice) use ($invoiceStatuses, $supplierStatuses): bool {
                $status = $invoiceStatuses[$invoice->id] ?? null;
                return $status && in_array($status['supplier_label'], $supplierStatuses, true);
            });
        }

        return array_values($filtered);
    }

    private function normalizeStatusFilters($raw, array $allowedOptions): array
    {
        if (is_string($raw)) {
            $raw = trim($raw);
            $values = $raw === '' ? [] : [$raw];
        } elseif (is_array($raw)) {
            $values = $raw;
        } else {
            return [];
        }

        $values = array_map(static fn ($value) => trim((string) $value), $values);
        $values = array_values(array_filter($values, static fn ($value) => $value !== ''));
        $values = array_values(array_unique($values));

        if (empty($allowedOptions)) {
            return $values;
        }

        $allowed = array_flip($allowedOptions);
        $values = array_values(array_filter($values, static fn ($value) => isset($allowed[$value])));

        return $values;
    }

    private function parseDateFilter(?string $value, bool $endOfDay = false): ?int
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }
        $candidate = $endOfDay ? ($raw . ' 23:59:59') : $raw;
        $timestamp = strtotime($candidate);
        if ($timestamp === false) {
            return null;
        }
        return $timestamp;
    }

    private function resolveSupplierLabel(string $supplierCui, array $invoices): string
    {
        if ($supplierCui === '') {
            return '';
        }
        foreach ($invoices as $invoice) {
            if ((string) $invoice->supplier_cui === $supplierCui) {
                $name = (string) ($invoice->supplier_name ?: $supplierCui);
                return $name !== '' ? ($name . ' - ' . $supplierCui) : $supplierCui;
            }
        }
        return $supplierCui;
    }

    private function resolveClientLabel(string $clientCui, array $clientNameMap): string
    {
        if ($clientCui === '') {
            return '';
        }
        if ($clientCui === 'none') {
            return 'Fara client';
        }

        $name = (string) ($clientNameMap[$clientCui] ?? $clientCui);
        return $name !== '' ? ($name . ' - ' . $clientCui) : $clientCui;
    }

    private function hasEmptySelectedClient(array $invoices): bool
    {
        foreach ($invoices as $invoice) {
            if (trim((string) ($invoice->selected_client_cui ?? '')) === '') {
                return true;
            }
        }

        return false;
    }

    private function manualSupplierOptions(array $allowedSuppliers): array
    {
        $allowedSuppliers = array_values(array_filter(array_map(static fn ($cui) => preg_replace('/\D+/', '', (string) $cui), $allowedSuppliers)));
        if (!Database::tableExists('partners')) {
            return [];
        }

        $defaultCommissionColumn = Database::columnExists('partners', 'default_commission')
            ? 'default_commission'
            : '0 AS default_commission';
        $sql = 'SELECT cui, denumire, ' . $defaultCommissionColumn . ' FROM partners';
        $params = [];
        $where = [];
        if (!empty($allowedSuppliers)) {
            $placeholders = [];
            foreach ($allowedSuppliers as $index => $cui) {
                $key = 's' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cui;
            }
            $where[] = 'cui IN (' . implode(',', $placeholders) . ')';
        } else {
            $hasIsSupplier = Database::columnExists('partners', 'is_supplier');
            $hasCommissions = Database::tableExists('commissions');
            if ($hasIsSupplier && $hasCommissions) {
                $where[] = '(is_supplier = 1 OR cui IN (SELECT DISTINCT supplier_cui FROM commissions WHERE supplier_cui IS NOT NULL AND supplier_cui <> \'\'))';
            } elseif ($hasIsSupplier) {
                $where[] = 'is_supplier = 1';
            } elseif ($hasCommissions) {
                $where[] = 'cui IN (SELECT DISTINCT supplier_cui FROM commissions WHERE supplier_cui IS NOT NULL AND supplier_cui <> \'\')';
            }
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY denumire ASC, cui ASC';

        $rows = Database::fetchAll($sql, $params);
        $items = [];
        foreach ($rows as $row) {
            $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
            if ($cui === '') {
                continue;
            }
            $name = trim((string) ($row['denumire'] ?? ''));
            if ($name === '') {
                $name = $cui;
            }
            $commission = (float) ($row['default_commission'] ?? 0.0);
            $items[] = [
                'cui' => $cui,
                'name' => $name,
                'label' => $name !== $cui ? ($name . ' - ' . $cui) : $cui,
                'default_commission' => $commission,
            ];
        }

        return $items;
    }

    private function supplierExistsInPlatform(string $supplierCui): bool
    {
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        if ($supplierCui === '' || !Database::tableExists('partners')) {
            return false;
        }

        $sql = 'SELECT cui FROM partners WHERE cui = :cui';
        $hasIsSupplier = Database::columnExists('partners', 'is_supplier');
        $hasCommissions = Database::tableExists('commissions');

        if ($hasIsSupplier && $hasCommissions) {
            $sql .= ' AND (is_supplier = 1 OR cui IN (SELECT DISTINCT supplier_cui FROM commissions WHERE supplier_cui IS NOT NULL AND supplier_cui <> \'\'))';
        } elseif ($hasIsSupplier) {
            $sql .= ' AND is_supplier = 1';
        } elseif ($hasCommissions) {
            $sql .= ' AND cui IN (SELECT DISTINCT supplier_cui FROM commissions WHERE supplier_cui IS NOT NULL AND supplier_cui <> \'\')';
        }

        return Database::fetchOne($sql . ' LIMIT 1', ['cui' => $supplierCui]) !== null;
    }

    private function manualClientOptions(string $supplierCui, array $allowedSuppliers): array
    {
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        $allowedSuppliers = array_values(array_filter(array_map(static fn ($cui) => preg_replace('/\D+/', '', (string) $cui), $allowedSuppliers)));
        if (!empty($allowedSuppliers) && $supplierCui !== '' && !in_array($supplierCui, $allowedSuppliers, true)) {
            return [];
        }

        if (Database::tableExists('commissions')) {
            if ($supplierCui === '') {
                return [];
            }
            $rows = Database::fetchAll(
                'SELECT c.client_cui AS cui,
                        c.commission AS commission,
                        COALESCE(NULLIF(p.denumire, ""), c.client_cui) AS denumire
                 FROM commissions c
                 LEFT JOIN partners p ON p.cui = c.client_cui
                 WHERE c.supplier_cui = :supplier
                 ORDER BY denumire ASC, c.client_cui ASC',
                ['supplier' => $supplierCui]
            );
            $items = [];
            foreach ($rows as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
                if ($cui === '') {
                    continue;
                }
                $name = trim((string) ($row['denumire'] ?? ''));
                if ($name === '') {
                    $name = $cui;
                }
                $items[] = [
                    'cui' => $cui,
                    'name' => $name,
                    'label' => $name !== $cui ? ($name . ' - ' . $cui) : $cui,
                    'commission' => (float) ($row['commission'] ?? 0),
                ];
            }

            return $items;
        }

        if (!Database::tableExists('partners')) {
            return [];
        }

        $sql = 'SELECT cui, denumire FROM partners';
        $params = [];
        $where = [];
        if ($supplierCui !== '') {
            $where[] = 'cui <> :supplier';
            $params['supplier'] = $supplierCui;
        }
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY denumire ASC, cui ASC';

        $rows = Database::fetchAll($sql, $params);
        $items = [];
        foreach ($rows as $row) {
            $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
            if ($cui === '') {
                continue;
            }
            $name = trim((string) ($row['denumire'] ?? ''));
            if ($name === '') {
                $name = $cui;
            }
            $items[] = [
                'cui' => $cui,
                'name' => $name,
                'label' => $name !== $cui ? ($name . ' - ' . $cui) : $cui,
                'commission' => null,
            ];
        }

        return $items;
    }

    private function supplierOptionsFromInvoices(array $invoices): array
    {
        $map = [];
        foreach ($invoices as $invoice) {
            $cui = (string) $invoice->supplier_cui;
            if ($cui === '') {
                continue;
            }
            $map[$cui] = (string) ($invoice->supplier_name ?: $cui);
        }

        $options = [];
        foreach ($map as $cui => $name) {
            $options[] = [
                'cui' => $cui,
                'name' => $name !== '' ? $name : $cui,
            ];
        }

        usort($options, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $options;
    }

    private function clientOptionsFromInvoices(array $invoices, array $clientNameMap): array
    {
        $map = [];
        $hasEmpty = false;
        foreach ($invoices as $invoice) {
            $cui = trim((string) ($invoice->selected_client_cui ?? ''));
            if ($cui === '') {
                $hasEmpty = true;
                continue;
            }
            $map[$cui] = (string) ($clientNameMap[$cui] ?? $cui);
        }

        $options = [];
        foreach ($map as $cui => $name) {
            $options[] = [
                'cui' => $cui,
                'name' => $name !== '' ? $name : $cui,
            ];
        }

        usort($options, static fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return [$options, $hasEmpty];
    }

    private function filterLookupOptions(array $items, string $term): array
    {
        $needle = $this->normalizeSearch($term);
        if ($needle === '') {
            return $items;
        }

        return array_values(array_filter($items, function (array $item) use ($needle): bool {
            $name = (string) ($item['name'] ?? '');
            $cui = (string) ($item['cui'] ?? '');
            return $this->containsSearch($name, $needle) || $this->containsSearch($cui, $needle);
        }));
    }

    private function resolveSupplierName(string $supplierCui, array $invoices): string
    {
        if ($supplierCui === '') {
            return '';
        }
        foreach ($invoices as $invoice) {
            if ((string) $invoice->supplier_cui === $supplierCui) {
                return (string) ($invoice->supplier_name ?: $supplierCui);
            }
        }
        return $supplierCui;
    }

    private function resolveClientName(string $clientCui, array $clientNameMap): string
    {
        if ($clientCui === '') {
            return '';
        }
        if ($clientCui === 'none') {
            return 'Fara client';
        }
        return (string) ($clientNameMap[$clientCui] ?? $clientCui);
    }

    private function formatPeriodLabel(string $dateFrom, string $dateTo): string
    {
        $from = $this->formatDateForTitle($dateFrom);
        $to = $this->formatDateForTitle($dateTo);
        if ($from === '' && $to === '') {
            return '';
        }
        if ($from !== '' && $to !== '') {
            return 'Perioada: ' . $from . '-' . $to;
        }
        if ($from !== '') {
            return 'Perioada: ' . $from . '-';
        }
        return 'Perioada: -' . $to;
    }

    private function formatDateForTitle(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return '';
        }
        return date('d.m.Y', $timestamp);
    }

    private function clientUnlockKey(int $invoiceId): string
    {
        return 'invoice_client_unlocked_' . $invoiceId;
    }

    private function isClientUnlocked(int $invoiceId): bool
    {
        return (bool) Session::get($this->clientUnlockKey($invoiceId), false);
    }

    private function canRenamePackages(?\App\Domain\Users\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }
        if ($user->hasRole(['super_admin', 'admin', 'contabil', 'operator', 'staff'])) {
            return true;
        }

        UserPermission::ensureTable();
        return UserPermission::userHas($user->id, UserPermission::RENAME_PACKAGES);
    }

    private function canUnconfirmPackages(?\App\Domain\Users\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'contabil', 'operator', 'staff']);
    }

    private function hasFgoInvoice(InvoiceIn $invoice): bool
    {
        return trim((string) ($invoice->fgo_number ?? '')) !== ''
            || trim((string) ($invoice->fgo_series ?? '')) !== ''
            || trim((string) ($invoice->fgo_storno_number ?? '')) !== ''
            || trim((string) ($invoice->fgo_storno_series ?? '')) !== '';
    }

    private function packageLabelText(Package $package): string
    {
        $label = trim((string) ($package->label ?? ''));
        if ($label === '') {
            $label = 'Pachet de produse';
        }
        return $label;
    }

    private function packageLabel(Package $package): string
    {
        return $this->packageLabelText($package) . ' #' . $package->package_no;
    }

    private function paginateInvoices(array $invoices, int $page, int $perPage): array
    {
        if ($perPage <= 0) {
            $perPage = 25;
        }

        $total = count($invoices);
        $totalPages = max(1, (int) ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $items = array_slice($invoices, $offset, $perPage);
        $start = $total === 0 ? 0 : $offset + 1;
        $end = $total === 0 ? 0 : min($offset + $perPage, $total);

        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'start' => $start,
            'end' => $end,
        ];
    }

    private function searchableNumberVariants(?float $value): array
    {
        if ($value === null) {
            return [];
        }

        $number = (float) $value;

        return [
            number_format($number, 2, '.', ''),
            number_format($number, 2, ',', ''),
            number_format($number, 2, '.', ' '),
            number_format($number, 2, ',', ' '),
        ];
    }

    private function jsonResponse(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function clientStatusOptions(): array
    {
        return [
            'Client nesetat',
            'Neincasat',
            'Incasat partial',
            'Incasat integral',
        ];
    }

    private function supplierStatusOptions(): array
    {
        return [
            'Neplatit',
            'Platit partial',
            'Platit integral',
        ];
    }

    private function normalizeSearch(string $value): string
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

    private function containsSearch(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }

        $hay = $this->normalizeSearch($haystack);

        return $hay !== '' && str_contains($hay, $needle);
    }

    private function requireInvoiceRole(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }

        if ($user->isPlatformUser() || $user->isSupplierUser() || $user->hasRole('staff')) {
            return;
        }

        Response::abort(403, 'Acces interzis.');
    }

    private function allowedSuppliers(?\App\Domain\Users\Models\User $user): array
    {
        if (!$user || !$user->isSupplierUser()) {
            return [];
        }

        UserSupplierAccess::ensureTable();

        return UserSupplierAccess::suppliersForUser($user->id);
    }

    private function ensureSupplierAccess(string $supplierCui): void
    {
        $user = Auth::user();
        if (!$user || $user->isPlatformUser()) {
            return;
        }

        if (!$user->isSupplierUser()) {
            Response::abort(403, 'Acces interzis.');
        }

        $supplierCui = preg_replace('/\D+/', '', (string) $supplierCui);
        if ($supplierCui === '') {
            Response::abort(403, 'Acces interzis.');
        }

        UserSupplierAccess::ensureTable();
        if (!UserSupplierAccess::userHasSupplier($user->id, $supplierCui)) {
            Response::abort(403, 'Nu ai acces la acest furnizor.');
        }
    }

    private function requireSagaToken(): void
    {
        $token = Env::get('SAGA_EXPORT_TOKEN', '');
        $provided = $_SERVER['HTTP_X_ERP_TOKEN'] ?? ($_GET['token'] ?? '');
        if ($token === '' || $provided === '' || !hash_equals($token, (string) $provided)) {
            $this->json(['success' => false, 'message' => 'Token invalid.'], 403);
        }
    }

    private function readJsonBody(): array
    {
        $raw = (string) file_get_contents('php://input');
        if ($raw === '') {
            return [];
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function detectCsvDelimiter(string $line): string
    {
        $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];
        foreach ($delimiters as $delimiter => $count) {
            $delimiters[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($delimiters);
        $best = array_key_first($delimiters);
        return $best ?? ';';
    }

    private function mapSagaColumns(array $header): array
    {
        $columns = [
            'denumire' => null,
            'pret_vanz' => null,
            'tva' => null,
        ];

        foreach ($header as $index => $value) {
            $key = $this->normalizeSagaHeader((string) $value);

            if ($columns['denumire'] === null && (str_contains($key, 'denumire') || str_contains($key, 'produs'))) {
                $columns['denumire'] = $index;
                continue;
            }

            if ($columns['pret_vanz'] === null) {
                if (str_contains($key, 'pret_vanz') || (str_contains($key, 'pret') && str_contains($key, 'vanz')) || $key === 'pret') {
                    $columns['pret_vanz'] = $index;
                    continue;
                }
            }

            if ($columns['tva'] === null && (str_contains($key, 'tva') || str_contains($key, 'vat'))) {
                $columns['tva'] = $index;
            }
        }

        return $columns;
    }

    private function normalizeSagaHeader(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\x{FEFF}/u', '', $value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = str_replace([' ', '-'], '_', $value);
        return $value;
    }

    private function normalizeSagaName(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }
        return strtoupper($value);
    }

    private function parseSagaNumber(string $value): ?float
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^0-9\.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || $raw === '.') {
            return null;
        }
        return (float) $raw;
    }

    private function readSagaRows(string $path): array
    {
        $signature = @file_get_contents($path, false, null, 0, 4);
        if ($signature !== false && str_starts_with($signature, 'PK')) {
            $rows = $this->readSagaRowsFromXlsx($path);
            if (!empty($rows)) {
                return $rows;
            }
        }

        return $this->readSagaRowsFromCsv($path);
    }

    private function readSagaRowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = $this->detectCsvDelimiter($headerLine);
        $rows = [str_getcsv($headerLine, $delimiter)];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readSagaRowsFromXlsx(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $shared = simplexml_load_string($sharedXml);
            if ($shared) {
                $shared->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
                foreach ($shared->xpath('//a:si') as $node) {
                    $texts = $node->xpath('.//a:t');
                    $text = '';
                    foreach ($texts as $textNode) {
                        $text .= (string) $textNode;
                    }
                    $sharedStrings[] = $text;
                }
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                if (str_starts_with($name, 'xl/worksheets/sheet') && str_ends_with($name, '.xml')) {
                    $sheetXml = $zip->getFromIndex($i);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);
        if (!$sheet) {
            return [];
        }
        $sheet->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];
        foreach ($sheet->xpath('//a:sheetData/a:row') as $rowNode) {
            $rowData = [];
            $cells = $rowNode->xpath('a:c');
            foreach ($cells as $cell) {
                $ref = (string) ($cell['r'] ?? '');
                if ($ref === '') {
                    continue;
                }
                $colIndex = $this->columnIndexFromCell($ref);
                $type = (string) ($cell['t'] ?? '');
                $value = '';
                if ($type === 's') {
                    $v = (string) ($cell->v ?? '');
                    $value = $sharedStrings[(int) $v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $texts = $cell->xpath('a:is/a:t');
                    foreach ($texts as $textNode) {
                        $value .= (string) $textNode;
                    }
                } else {
                    $value = (string) ($cell->v ?? '');
                }
                $rowData[$colIndex] = $value;
            }
            if (empty($rowData)) {
                continue;
            }
            $maxIndex = max(array_keys($rowData));
            $filled = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $filled[] = $rowData[$i] ?? '';
            }
            $rows[] = $filled;
        }

        return $rows;
    }

    private function columnIndexFromCell(string $cellRef): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($cellRef));
        $index = 0;
        for ($i = 0, $len = strlen($letters); $i < $len; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $index - 1);
    }
    private function guardInvoice(int $invoiceId): InvoiceIn
    {
        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice) {
            Response::abort(404, 'Factura nu a fost gasita.');
        }

        $this->ensureSupplierAccess($invoice->supplier_cui);

        return $invoice;
    }
}
