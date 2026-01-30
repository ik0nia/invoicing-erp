<?php

namespace App\Domain\Invoices\Http\Controllers;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Invoices\Services\FgoClient;
use App\Domain\Invoices\Services\InvoiceXmlParser;
use App\Domain\Companies\Models\Company;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Settings\Services\SettingsService;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class InvoiceController
{
    public function index(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : null;
        $selectedClientCui = preg_replace('/\D+/', '', (string) ($_GET['client_cui'] ?? ''));

        if ($invoiceId) {
            $invoice = InvoiceIn::find($invoiceId);

            if (!$invoice) {
                Response::abort(404, 'Factura nu a fost gasita.');
            }

            $lines = InvoiceInLine::forInvoice($invoiceId);
            $packages = Package::forInvoice($invoiceId);
            $packageStats = $this->packageStats($lines, $packages);
            $vatRates = $this->vatRates($lines);
            $packageDefaults = $this->packageDefaults($packages, $vatRates);
            $linesByPackage = $this->groupLinesByPackage($lines, $packages);
            $clients = Commission::forSupplierWithPartners($invoice->supplier_cui);
            $commissionPercent = null;
            $selectedClientName = '';
            $user = Auth::user();
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
                'fgoSeriesOptions' => $fgoSeriesOptions,
                'fgoSeriesSelected' => $fgoSeriesSelected,
            ]);
        }

        $invoices = InvoiceIn::all();

        Response::view('admin/invoices/index', [
            'invoices' => $invoices,
        ]);
    }

    public function showImport(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureInvoiceTables()) {
            Response::view('errors/invoices_schema', [], 'layouts/app');
        }

        Response::view('admin/invoices/import');
    }

    public function import(): void
    {
        Auth::requireAdmin();

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

    public function packages(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $action = $_POST['action'] ?? '';

        if (!$invoiceId) {
            Response::redirect('/admin/facturi');
        }

        if (!$this->ensureInvoiceTables()) {
            Session::flash('error', 'Nu pot crea tabelele pentru facturi.');
            Response::redirect('/admin/facturi');
        }

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

        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice) {
            Response::redirect('/admin/facturi');
        }

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
        $codUnic = trim((string) $settings->get('fgo.api_key', ''));
        $secret = trim((string) $settings->get('fgo.secret_key', ''));
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
            Session::flash('error', 'Completeaza Cod unic, Cheie privata si Serie in setarile FGO.');
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
                'Denumire' => $package->label ?: 'Pachet #' . $package->package_no,
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

        $payload = [
            'CodUnic' => $codUnic,
            'Hash' => FgoClient::hashForEmitere($codUnic, $secret, $clientCompany->denumire),
            'Valuta' => $invoice->currency ?: 'RON',
            'TipFactura' => 'Factura',
            'Serie' => $series,
            'DataEmitere' => date('Y-m-d'),
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
            'UPDATE invoices_in SET fgo_series = :serie, fgo_number = :numar, fgo_link = :link, updated_at = :now WHERE id = :id',
            [
                'serie' => $fgoSeries,
                'numar' => $fgoNumber,
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
        $codUnic = trim((string) $settings->get('fgo.api_key', ''));
        $secret = trim((string) $settings->get('fgo.secret_key', ''));
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if ($codUnic === '' || $secret === '') {
            Session::flash('error', 'Completeaza Cod unic si Cheie privata in setarile FGO.');
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
        $codUnic = trim((string) $settings->get('fgo.api_key', ''));
        $secret = trim((string) $settings->get('fgo.secret_key', ''));
        $baseUrl = trim((string) $settings->get('fgo.base_url', ''));

        if ($codUnic === '' || $secret === '') {
            Session::flash('error', 'Completeaza Cod unic si Cheie privata in setarile FGO.');
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

    public function moveLine(): void
    {
        Auth::requireAdmin();

        $invoiceId = isset($_POST['invoice_id']) ? (int) $_POST['invoice_id'] : 0;
        $lineId = isset($_POST['line_id']) ? (int) $_POST['line_id'] : 0;
        $packageId = isset($_POST['package_id']) ? (int) $_POST['package_id'] : 0;

        if ($invoiceId && $lineId) {
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
                'label' => $package->label ?: 'Pachet de produse #' . $package->package_no,
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
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'fgo_link')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN fgo_link VARCHAR(255) NULL AFTER fgo_number');
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
}
