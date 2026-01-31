<?php

namespace App\Domain\Invoices\Http\Controllers;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Invoices\Services\FgoClient;
use App\Domain\Invoices\Services\InvoiceXmlParser;
use App\Domain\Invoices\Services\SagaAhkGenerator;
use App\Domain\Companies\Models\Company;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Settings\Services\SettingsService;
use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class InvoiceController
{
    public function index(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
            return;
        }

        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
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

            if ($selectedClientCui === '' && $storedClientCui !== '') {
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

            $commissionBase = $invoice->commission_percent ?? $commissionPercent;
            if ($commissionBase !== null) {
                $clientTotal = $this->applyCommission($invoice->total_with_vat, (float) $commissionBase);
            }

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
                'isPlatform' => $isPlatform,
                'fgoSeriesOptions' => $fgoSeriesOptions,
                'fgoSeriesSelected' => $fgoSeriesSelected,
                'collectedTotal' => $collectedTotal,
                'paidTotal' => $paidTotal,
                'clientTotal' => $clientTotal,
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
            'filters' => $filters,
            'pagination' => $pagination,
            'supplierFilterLabel' => $supplierFilterLabel,
            'clientFilterLabel' => $clientFilterLabel,
            'hasEmptyClients' => $hasEmptyClients,
            'clientStatusOptions' => $this->clientStatusOptions(),
            'supplierStatusOptions' => $this->supplierStatusOptions(),
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
        ], null);
    }

    public function lookupSuppliers(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;

        $where = ['supplier_cui <> ""'];
        $params = [];

        if ($query !== '') {
            $where[] = '(supplier_name LIKE :q OR supplier_cui LIKE :q)';
            $params['q'] = '%' . $query . '%';
        }

        if (!$isPlatform) {
            $suppliers = $this->allowedSuppliers($user);
            if (empty($suppliers)) {
                $this->jsonResponse(['items' => []]);
            }
            $placeholders = [];
            foreach (array_values($suppliers) as $index => $cui) {
                $key = 's' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cui;
            }
            $where[] = 'supplier_cui IN (' . implode(',', $placeholders) . ')';
        }

        $rows = Database::fetchAll(
            'SELECT DISTINCT supplier_cui, supplier_name
             FROM invoices_in
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY supplier_name ASC
             LIMIT ' . $limit,
            $params
        );

        $items = array_map(static function (array $row): array {
            $cui = (string) ($row['supplier_cui'] ?? '');
            $name = (string) ($row['supplier_name'] ?? $cui);
            return [
                'cui' => $cui,
                'name' => $name !== '' ? $name : $cui,
            ];
        }, $rows);

        $this->jsonResponse(['items' => $items]);
    }

    public function lookupClients(): void
    {
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $query = trim((string) ($_GET['q'] ?? ''));
        $limit = min(20, max(1, (int) ($_GET['limit'] ?? 15)));
        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;

        $where = ['i.selected_client_cui IS NOT NULL', 'i.selected_client_cui <> ""'];
        $params = [];
        $joinCompanies = Database::tableExists('companies');
        $joinPartners = Database::tableExists('partners');
        $nameParts = [];
        if ($joinCompanies) {
            $nameParts[] = 'NULLIF(MAX(c.denumire), "")';
        }
        if ($joinPartners) {
            $nameParts[] = 'NULLIF(MAX(p.denumire), "")';
        }
        $nameParts[] = 'i.selected_client_cui';
        $nameExpression = 'COALESCE(' . implode(', ', $nameParts) . ')';

        if ($query !== '') {
            $searchParts = ['i.selected_client_cui LIKE :q'];
            if ($joinCompanies) {
                $searchParts[] = 'c.denumire LIKE :q';
            }
            if ($joinPartners) {
                $searchParts[] = 'p.denumire LIKE :q';
            }
            $where[] = '(' . implode(' OR ', $searchParts) . ')';
            $params['q'] = '%' . $query . '%';
        }

        if (!$isPlatform) {
            $suppliers = $this->allowedSuppliers($user);
            if (empty($suppliers)) {
                $this->jsonResponse(['items' => []]);
            }
            $placeholders = [];
            foreach (array_values($suppliers) as $index => $cui) {
                $key = 's' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cui;
            }
            $where[] = 'i.supplier_cui IN (' . implode(',', $placeholders) . ')';
        }

        $joins = '';
        if ($joinCompanies) {
            $joins .= ' LEFT JOIN companies c ON c.cui = i.selected_client_cui';
        }
        if ($joinPartners) {
            $joins .= ' LEFT JOIN partners p ON p.cui = i.selected_client_cui';
        }

        $rows = Database::fetchAll(
            'SELECT i.selected_client_cui AS cui,
                    ' . $nameExpression . ' AS name
             FROM invoices_in i' . $joins . '
             WHERE ' . implode(' AND ', $where) . '
             GROUP BY i.selected_client_cui
             ORDER BY name ASC
             LIMIT ' . $limit,
            $params
        );

        $items = array_map(static function (array $row): array {
            $cui = (string) ($row['cui'] ?? '');
            $name = (string) ($row['name'] ?? $cui);
            return [
                'cui' => $cui,
                'name' => $name !== '' ? $name : $cui,
            ];
        }, $rows);

        $this->jsonResponse(['items' => $items]);
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
        $this->requireInvoiceRole();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $user = Auth::user();
        $isPlatform = $user ? $user->isPlatformUser() : false;
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

        Response::view('admin/invoices/confirmed', [
            'packages' => $rows,
            'totals' => $totals,
        ]);
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

            if ($invoice->commission_percent !== null) {
                $commissionPercent = (float) $invoice->commission_percent;
            } else {
                $commission = Commission::forSupplierClient($invoice->supplier_cui, $clientCui);
                if ($commission) {
                    $commissionPercent = (float) $commission->commission;
                }
            }
        }

        $totalWithout = 0.0;
        $totalWith = 0.0;

        foreach ($packageStats as $stat) {
            $without = (float) ($stat['total'] ?? 0);
            $with = (float) ($stat['total_vat'] ?? 0);

            if ($commissionPercent !== null) {
                $without = $this->applyCommission($without, $commissionPercent);
                $with = $this->applyCommission($with, $commissionPercent);
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

        $this->ensureSupplierAccess($data['supplier_cui'] ?? '');

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

        Partner::createIfMissing($data['supplier_cui'], $data['supplier_name']);
        Partner::createIfMissing($data['customer_cui'], $data['customer_name']);

        foreach ($data['lines'] as $line) {
            InvoiceInLine::create($invoice->id, $line);
        }

        $this->generatePackages($invoice->id);

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
            $supplier = Partner::findByCui($supplierCui);
            if ($supplier) {
                $supplierName = $supplier->denumire;
            }
        }
        if ($customerName === '' && $customerCui !== '') {
            $customer = Partner::findByCui($customerCui);
            if ($customer) {
                $customerName = $customer->denumire;
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

        $this->guardInvoice($invoiceId);

        if ($action === 'generate') {
            if ($this->isInvoiceConfirmed($invoiceId)) {
                Session::flash('error', 'Pachetele sunt confirmate si nu mai pot fi regenerate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $counts = $_POST['package_counts'] ?? [];
            $this->generatePackages($invoiceId, $counts);
            Session::flash('status', 'Pachetele au fost reorganizate.');
        }

        if ($action === 'delete') {
            if ($this->isInvoiceConfirmed($invoiceId)) {
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
            if ($this->isInvoiceConfirmed($invoiceId)) {
                Session::flash('status', 'Pachetele sunt deja confirmate.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
            }

            $this->confirmPackages($invoiceId);
            $this->storeSagaFiles($invoiceId);
            Session::flash('status', 'Pachetele au fost confirmate.');
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
    }

    public function delete(): void
    {
        Auth::requireAdmin();

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

        if (!$this->isInvoiceConfirmed($invoiceId)) {
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
            $total = $this->applyCommission($stat['total_vat'], $commission->commission);

            $content[] = [
                'Denumire' => 'Pachet de produse #' . $package->package_no,
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
            'UPDATE invoices_in SET fgo_series = :serie, fgo_number = :numar, fgo_date = :fgo_date, fgo_link = :link, updated_at = :now WHERE id = :id',
            [
                'serie' => $fgoSeries,
                'numar' => $fgoNumber,
                'fgo_date' => $issueDate,
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
            'UPDATE invoices_in SET fgo_storno_series = :serie, fgo_storno_number = :numar, fgo_storno_link = :link, updated_at = :now WHERE id = :id',
            [
                'serie' => $stornoSeries,
                'numar' => $stornoNumber,
                'link' => $stornoLink,
                'now' => date('Y-m-d H:i:s'),
                'id' => $invoice->id,
            ]
        );

        Session::flash('status', 'Factura FGO a fost stornata.');
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

    public function downloadPackageSaga(): void
    {
        $this->requireInvoiceRole();

        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;
        if (!$packageId) {
            Response::redirect('/admin/facturi');
        }

        $package = Package::find($packageId);
        if (!$package) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($package->invoice_in_id);
        if (!$invoice || empty($invoice->packages_confirmed)) {
            Session::flash('error', 'Pachetele nu sunt confirmate.');
            Response::redirect('/admin/facturi?invoice_id=' . $package->invoice_in_id . '#drag-drop');
        }

        $lines = InvoiceInLine::forPackage($packageId);
        $packageStats = $this->packageStats($lines, [$package]);
        $linesByPackage = $this->groupLinesByPackage($lines, [$package]);
        $date = $this->formatSagaDate($invoice->packages_confirmed_at ?? $invoice->issue_date);

        $packagesData = $this->buildSagaPackagesData([$package], $linesByPackage, $packageStats, $date);
        $content = (new SagaAhkGenerator())->buildScript($packagesData, $date);
        $filename = 'pachet_' . $this->safeFileName((string) $package->package_no) . '.ahk';

        $this->downloadAhk($filename, $content);
    }

    public function downloadInvoiceSaga(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        $invoice = $this->guardInvoice($invoiceId);
        if (!$invoice || empty($invoice->packages_confirmed)) {
            Session::flash('error', 'Pachetele nu sunt confirmate.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $packages = Package::forInvoice($invoiceId);
        if (empty($packages)) {
            Session::flash('error', 'Nu exista pachete pentru export.');
            Response::redirect('/admin/facturi?invoice_id=' . $invoiceId . '#drag-drop');
        }

        $lines = InvoiceInLine::forInvoice($invoiceId);
        $packageStats = $this->packageStats($lines, $packages);
        $linesByPackage = $this->groupLinesByPackage($lines, $packages);
        $date = $this->formatSagaDate($invoice->packages_confirmed_at ?? $invoice->issue_date);

        $packagesData = $this->buildSagaPackagesData($packages, $linesByPackage, $packageStats, $date);
        $content = (new SagaAhkGenerator())->buildScript($packagesData, $date);
        $filename = 'pachete_' . $this->safeFileName($invoice->invoice_number) . '.ahk';

        $this->downloadAhk($filename, $content);
    }

    public function downloadSelectedSaga(): void
    {
        $this->requireInvoiceRole();

        $packageIds = $_POST['package_ids'] ?? [];
        $packageIds = array_values(array_filter(array_map('intval', (array) $packageIds)));

        if (empty($packageIds)) {
            Session::flash('error', 'Selecteaza cel putin un pachet.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $user = Auth::user();
        $suppliers = $user && $user->isSupplierUser() ? $this->allowedSuppliers($user) : null;
        $packages = $this->fetchConfirmedPackagesByIds($packageIds, $suppliers);
        if (empty($packages)) {
            Session::flash('error', 'Nu exista pachete confirmate pentru export.');
            Response::redirect('/admin/pachete-confirmate');
        }
        if ($user && $user->isSupplierUser() && count($packages) !== count($packageIds)) {
            Session::flash('error', 'Nu ai acces la toate pachetele selectate.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $packagesData = [];
        foreach ($packages as $packageRow) {
            $package = Package::fromArray($packageRow);
            $lines = InvoiceInLine::forPackage($package->id);
            $stats = $this->packageStats($lines, [$package]);
            $linesByPackage = $this->groupLinesByPackage($lines, [$package]);
            $date = $this->formatSagaDate($packageRow['packages_confirmed_at'] ?? $packageRow['issue_date'] ?? null);

            $data = $this->buildSagaPackagesData([$package], $linesByPackage, $stats, $date);
            if (!empty($data[0])) {
                $packagesData[] = $data[0];
            }
        }

        if (empty($packagesData)) {
            Session::flash('error', 'Nu exista produse pentru pachetele selectate.');
            Response::redirect('/admin/pachete-confirmate');
        }

        $content = (new SagaAhkGenerator())->buildScript($packagesData, $this->formatSagaDate(null));
        $filename = 'pachete_selectate_' . date('Ymd_His') . '.ahk';

        $this->downloadAhk($filename, $content);
    }

    public function moveLine(): void
    {
        $this->requireInvoiceRole();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $lineId = isset($_POST['line_id']) ? (int) $_POST['line_id'] : 0;
        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;

        if ($invoiceId && $lineId) {
            $this->guardInvoice($invoiceId);
            if ($this->isInvoiceConfirmed($invoiceId)) {
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

    private function packageStats(array $lines, array $packages): array
    {
        $stats = [];

        foreach ($packages as $package) {
            $stats[$package->id] = [
                'label' => 'Pachet de produse #' . $package->package_no,
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
                    fgo_link VARCHAR(255) NULL,
                    fgo_storno_series VARCHAR(32) NULL,
                    fgo_storno_number VARCHAR(32) NULL,
                    fgo_storno_link VARCHAR(255) NULL,
                    order_note_no INT NULL,
                    order_note_date DATE NULL,
                    commission_percent DECIMAL(6,2) NULL,
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
        } catch (\Throwable $exception) {
            return false;
        }

        if (Database::tableExists('packages') && !Database::columnExists('packages', 'vat_percent')) {
            Database::execute('ALTER TABLE packages ADD COLUMN vat_percent DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER label');
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
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_link')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_link VARCHAR(255) NULL AFTER fgo_date');
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
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'order_note_no')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN order_note_no INT NULL AFTER fgo_storno_link');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'order_note_date')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN order_note_date DATE NULL AFTER order_note_no');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'commission_percent')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN commission_percent DECIMAL(6,2) NULL AFTER order_note_date');
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
            $value = $this->applyCommission($stat['total_vat'], $commissionPercent);
            $totals['packages'][$packageId] = $value;
            $totals['invoice_total'] += $value;
        }

        return $totals;
    }

    private function applyCommission(float $amount, float $percent): float
    {
        $factor = 1 + (abs($percent) / 100);

        if ($percent >= 0) {
            return round($amount * $factor, 2);
        }

        return round($amount / $factor, 2);
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

    private function isInvoiceConfirmed(int $invoiceId): bool
    {
        $invoice = InvoiceIn::find($invoiceId);

        return $invoice ? (bool) $invoice->packages_confirmed : false;
    }

    private function buildSagaPackagesData(array $packages, array $linesByPackage, array $packageStats, string $date): array
    {
        $data = [];

        foreach ($packages as $package) {
            $lines = $linesByPackage[$package->id] ?? [];
            $items = [];

            foreach ($lines as $line) {
                $items[] = [
                    'name' => $line->product_name,
                    'quantity' => $line->quantity,
                    'total' => $line->line_total_vat,
                ];
            }

            $stat = $packageStats[$package->id] ?? null;
            $total = $stat ? (float) $stat['total_vat'] : 0.0;

            $data[] = [
                'package_no' => $package->package_no,
                'label' => 'Pachet de produse #' . $package->package_no,
                'total' => $total,
                'lines' => $items,
                'date' => $date,
            ];
        }

        return $data;
    }

    private function formatSagaDate(?string $date): string
    {
        if ($date) {
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                return date('d.m.Y', $timestamp);
            }
        }

        return date('d.m.Y');
    }

    private function safeFileName(string $value): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $value);

        return $safe !== '' ? $safe : 'document';
    }

    private function downloadAhk(string $filename, string $content): void
    {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
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

    private function storeSagaFiles(int $invoiceId): void
    {
        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $packages = Package::forInvoice($invoiceId);
        if (empty($packages)) {
            return;
        }

        $lines = InvoiceInLine::forInvoice($invoiceId);
        $packageStats = $this->packageStats($lines, $packages);
        $linesByPackage = $this->groupLinesByPackage($lines, $packages);
        $date = $this->formatSagaDate($invoice->packages_confirmed_at ?? $invoice->issue_date);
        $generator = new SagaAhkGenerator();

        $packagesData = $this->buildSagaPackagesData($packages, $linesByPackage, $packageStats, $date);

        $dir = BASE_PATH . '/storage/saga/invoice_' . $invoiceId;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        foreach ($packagesData as $packageData) {
            $fileName = 'pachet_' . $this->safeFileName((string) $packageData['package_no']) . '.ahk';
            $content = $generator->buildScript([$packageData], $date);
            file_put_contents($dir . '/' . $fileName, $content);
        }

        $allFile = 'pachete_' . $this->safeFileName($invoice->invoice_number) . '.ahk';
        $allContent = $generator->buildScript($packagesData, $date);
        file_put_contents($dir . '/' . $allFile, $allContent);
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

        $placeholders = [];
        $params = [];

        foreach ($packageIds as $index => $id) {
            $key = 'p' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }

        $sql = 'SELECT package_id, COUNT(*) AS line_count,
                       COALESCE(SUM(line_total), 0) AS total,
                       COALESCE(SUM(line_total_vat), 0) AS total_vat
                FROM invoice_in_lines
                WHERE package_id IN (' . implode(',', $placeholders) . ')
                GROUP BY package_id';

        $rows = Database::fetchAll($sql, $params);
        $totals = [];

        foreach ($rows as $row) {
            $totals[(int) $row['package_id']] = [
                'line_count' => (int) $row['line_count'],
                'total' => (float) $row['total'],
                'total_vat' => (float) $row['total_vat'],
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
            $clientTotal = $this->applyCommission((float) $invoice->total_with_vat, (float) $commission);
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
        $clientStatus = trim((string) ($_GET['client_status'] ?? ''));
        $supplierStatus = trim((string) ($_GET['supplier_status'] ?? ''));
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

        if ($filters['client_status'] !== '') {
            $clientStatus = $filters['client_status'];
            $filtered = array_filter($filtered, static function (InvoiceIn $invoice) use ($invoiceStatuses, $clientStatus): bool {
                $status = $invoiceStatuses[$invoice->id] ?? null;
                return $status && $status['client_label'] === $clientStatus;
            });
        }

        if ($filters['supplier_status'] !== '') {
            $supplierStatus = $filters['supplier_status'];
            $filtered = array_filter($filtered, static function (InvoiceIn $invoice) use ($invoiceStatuses, $supplierStatus): bool {
                $status = $invoiceStatuses[$invoice->id] ?? null;
                return $status && $status['supplier_label'] === $supplierStatus;
            });
        }

        return array_values($filtered);
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

        if ($user->isPlatformUser() || $user->isSupplierUser()) {
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
