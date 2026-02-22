<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Contracts\Services\DocumentNumberService;
use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Partners\Models\Commission;
use App\Domain\Settings\Services\SettingsService;
use App\Support\CompanyName;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class SettingsController
{
    private const APP_VERSION = 'v1.1.0';
    private const ANNEX_SIGNATURE_BASE_NAME = 'annex-signature';
    private const ANNEX_SIGNATURE_MAX_UPLOAD_BYTES = 3145728;
    private const ANNEX_SIGNATURE_ALLOWED_MIMES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];
    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
    }

    public function edit(): void
    {
        Auth::requireSuperAdmin();

        $logoPath = (string) $this->settings->get('branding.logo_path', '');
        $logoDarkPath = (string) $this->settings->get('branding.logo_dark_path', '');
        $logoUrl = $this->resolveBrandingLogoUrl($logoPath);
        $logoDarkUrl = $this->resolveBrandingLogoUrl($logoDarkPath);

        $fgoApiKey = (string) $this->settings->get('fgo.api_key', '');
        $fgoSeries = (string) $this->settings->get('fgo.series', '');
        $fgoSeriesList = $this->settings->get('fgo.series_list', []);
        if (!is_array($fgoSeriesList)) {
            $fgoSeriesList = [];
        }
        $fgoSeriesListText = implode(', ', $fgoSeriesList);
        $fgoBaseUrl = (string) $this->settings->get('fgo.base_url', '');
        $openApiKey = (string) $this->settings->get('openapi.api_key', '');
        $annexSupplierSignaturePath = (string) $this->settings->get('annex.supplier_signature_path', '');
        $annexSupplierSignatureUrl = $this->resolveBrandingLogoUrl($annexSupplierSignaturePath);
        $company = [
            'denumire' => (string) $this->settings->get('company.denumire', ''),
            'cui' => (string) $this->settings->get('company.cui', ''),
            'nr_reg_comertului' => (string) $this->settings->get('company.nr_reg_comertului', ''),
            'platitor_tva' => (bool) $this->settings->get('company.platitor_tva', false),
            'adresa' => (string) $this->settings->get('company.adresa', ''),
            'localitate' => (string) $this->settings->get('company.localitate', ''),
            'judet' => (string) $this->settings->get('company.judet', ''),
            'tara' => (string) $this->settings->get('company.tara', 'Romania'),
            'email' => (string) $this->settings->get('company.email', ''),
            'telefon' => (string) $this->settings->get('company.telefon', ''),
            'banca' => (string) $this->settings->get('company.banca', ''),
            'iban' => (string) $this->settings->get('company.iban', ''),
        ];
        $documentRegistry = [
            DocumentNumberService::REGISTRY_SCOPE_CLIENT => $this->loadDocumentRegistrySettings(DocumentNumberService::REGISTRY_SCOPE_CLIENT),
            DocumentNumberService::REGISTRY_SCOPE_SUPPLIER => $this->loadDocumentRegistrySettings(DocumentNumberService::REGISTRY_SCOPE_SUPPLIER),
        ];

        Response::view('admin/settings/index', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
            'logoDarkPath' => $logoDarkPath,
            'logoDarkUrl' => $logoDarkUrl,
            'fgoApiKey' => $fgoApiKey,
            'fgoSeries' => $fgoSeries,
            'fgoSeriesList' => $fgoSeriesList,
            'fgoSeriesListText' => $fgoSeriesListText,
            'fgoBaseUrl' => $fgoBaseUrl,
            'openApiKey' => $openApiKey,
            'annexSupplierSignaturePath' => $annexSupplierSignaturePath,
            'annexSupplierSignatureUrl' => $annexSupplierSignatureUrl,
            'company' => $company,
            'documentRegistry' => $documentRegistry,
        ]);
    }

    public function update(): void
    {
        Auth::requireSuperAdmin();

        $logoUpdated = false;
        $logoDarkUpdated = false;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $storedPath = $this->storeBrandingLogoUpload($_FILES['logo'], 'logo');
            if ($storedPath === null) {
                Response::redirect('/admin/setari');
            }
            $this->settings->set('branding.logo_path', $storedPath);
            $logoUpdated = true;
        }
        if (isset($_FILES['logo_dark']) && $_FILES['logo_dark']['error'] !== UPLOAD_ERR_NO_FILE) {
            $storedPath = $this->storeBrandingLogoUpload($_FILES['logo_dark'], 'logo-dark');
            if ($storedPath === null) {
                Response::redirect('/admin/setari');
            }
            $this->settings->set('branding.logo_dark_path', $storedPath);
            $logoDarkUpdated = true;
        }

        $apiKey = trim($_POST['fgo_api_key'] ?? '');
        $series = trim($_POST['fgo_series'] ?? '');
        $seriesListRaw = trim($_POST['fgo_series_list'] ?? '');
        $baseUrl = trim($_POST['fgo_base_url'] ?? '');
        $openApiKey = trim($_POST['openapi_api_key'] ?? '');
        $removeAnnexSignature = !empty($_POST['annex_remove_signature']);
        $currentAnnexSignaturePath = (string) $this->settings->get('annex.supplier_signature_path', '');
        $companyData = [
            'denumire' => \App\Support\CompanyName::normalize((string) ($_POST['company_denumire'] ?? '')),
            'cui' => trim($_POST['company_cui'] ?? ''),
            'nr_reg_comertului' => trim($_POST['company_nr_reg_comertului'] ?? ''),
            'platitor_tva' => ($_POST['company_platitor_tva'] ?? '') === '1',
            'adresa' => trim($_POST['company_adresa'] ?? ''),
            'localitate' => trim($_POST['company_localitate'] ?? ''),
            'judet' => trim($_POST['company_judet'] ?? ''),
            'tara' => trim($_POST['company_tara'] ?? ''),
            'email' => trim($_POST['company_email'] ?? ''),
            'telefon' => trim($_POST['company_telefon'] ?? ''),
            'banca' => trim($_POST['company_banca'] ?? ''),
            'iban' => trim($_POST['company_iban'] ?? ''),
        ];

        $savedSomething = $logoUpdated || $logoDarkUpdated;
        $hasAnnexSignatureUpload = isset($_FILES['annex_signature'])
            && ($_FILES['annex_signature']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasAnnexSignatureUpload) {
            $storedAnnexSignature = $this->storeAnnexSignatureUpload($_FILES['annex_signature']);
            if ($storedAnnexSignature === null) {
                Response::redirect('/admin/setari');
            }
            if ($currentAnnexSignaturePath !== '' && $currentAnnexSignaturePath !== $storedAnnexSignature) {
                $this->deleteStorageErpFile($currentAnnexSignaturePath);
            }
            $this->settings->set('annex.supplier_signature_path', $storedAnnexSignature);
            $currentAnnexSignaturePath = $storedAnnexSignature;
            $savedSomething = true;
        } elseif ($removeAnnexSignature && $currentAnnexSignaturePath !== '') {
            $this->deleteStorageErpFile($currentAnnexSignaturePath);
            $this->settings->set('annex.supplier_signature_path', '');
            $currentAnnexSignaturePath = '';
            $savedSomething = true;
        }
        if ($apiKey !== '') {
            $this->settings->set('fgo.api_key', $apiKey);
            $savedSomething = true;
        }

        $seriesList = [];
        if ($seriesListRaw !== '') {
            $parts = preg_split('/[,\n;]+/', $seriesListRaw);
            foreach ($parts as $part) {
                $value = trim((string) $part);
                if ($value === '') {
                    continue;
                }
                $seriesList[$value] = true;
            }
        }
        $seriesList = array_keys($seriesList);

        if ($series !== '' && !in_array($series, $seriesList, true)) {
            $seriesList[] = $series;
        }

        if ($seriesListRaw !== '') {
            $this->settings->set('fgo.series_list', $seriesList);
            $savedSomething = true;
        }

        if ($series !== '') {
            $this->settings->set('fgo.series', $series);
            $savedSomething = true;
        }

        if ($baseUrl !== '') {
            $this->settings->set('fgo.base_url', $baseUrl);
            $savedSomething = true;
        }

        if ($openApiKey !== '') {
            $this->settings->set('openapi.api_key', $openApiKey);
            $savedSomething = true;
        }

        foreach ($companyData as $key => $value) {
            $this->settings->set('company.' . $key, $value);
        }
        $savedSomething = true;

        if ($savedSomething) {
            Session::flash('status', 'Setarile au fost salvate.');
        } else {
            Session::flash('status', 'Nimic de actualizat.');
        }

        Response::redirect('/admin/setari');
    }

    private function resolveBrandingLogoUrl(string $logoPath): ?string
    {
        $logoPath = trim($logoPath);
        if ($logoPath === '') {
            return null;
        }

        $absolutePath = BASE_PATH . '/' . ltrim($logoPath, '/');
        if (!file_exists($absolutePath)) {
            return null;
        }

        return \App\Support\Url::asset($logoPath);
    }

    private function loadDocumentRegistrySettings(string $registryScope): array
    {
        $settings = [
            'scope' => $registryScope,
            'series' => '',
            'start_no' => 1,
            'next_no' => 1,
            'updated_at' => '',
            'available' => false,
        ];
        if (!Database::tableExists('document_registry')) {
            return $settings;
        }

        try {
            $numberService = new DocumentNumberService();
            $row = $numberService->ensureRegistryRow('contract', [
                'registry_scope' => $registryScope,
            ]);
            $startNo = max(1, (int) ($row['start_no'] ?? 1));
            $nextNo = max($startNo, (int) ($row['next_no'] ?? $startNo));
            $settings['series'] = trim((string) ($row['series'] ?? ''));
            $settings['start_no'] = $startNo;
            $settings['next_no'] = $nextNo;
            $settings['updated_at'] = (string) ($row['updated_at'] ?? '');
            $settings['available'] = true;
        } catch (\Throwable $exception) {
            $settings['available'] = false;
        }

        return $settings;
    }

    private function storeBrandingLogoUpload(array $file, string $baseName): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Te rog incarca un fisier valid.');
            return null;
        }
        $maxSize = 2 * 1024 * 1024;
        if ((int) ($file['size'] ?? 0) > $maxSize) {
            Session::flash('error', 'Logo-ul trebuie sa fie sub 2 MB.');
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Session::flash('error', 'Te rog incarca un fisier valid.');
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : (string) ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }

        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/svg+xml' => 'svg',
        ];
        if (!array_key_exists($mime, $allowed)) {
            Session::flash('error', 'Format logo invalid. Acceptam png, jpg sau svg.');
            return null;
        }

        $extension = $allowed[$mime];
        $storageDir = BASE_PATH . '/storage/erp';
        $publicDir = BASE_PATH . '/public/storage/erp';

        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }

        $filename = $baseName . '.' . $extension;
        $targetPath = $storageDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            Session::flash('error', 'Nu am putut salva fisierul incarcat.');
            return null;
        }

        foreach (['png', 'jpg', 'svg'] as $ext) {
            if ($ext === $extension) {
                continue;
            }
            @unlink($storageDir . '/' . $baseName . '.' . $ext);
            @unlink($publicDir . '/' . $baseName . '.' . $ext);
        }

        @copy($targetPath, $publicDir . '/' . $filename);

        return 'storage/erp/' . $filename;
    }

    private function storeAnnexSignatureUpload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Te rog incarca o semnatura valida.');
            return null;
        }
        if ((int) ($file['size'] ?? 0) > self::ANNEX_SIGNATURE_MAX_UPLOAD_BYTES) {
            Session::flash('error', 'Semnatura trebuie sa fie sub 3 MB.');
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            Session::flash('error', 'Te rog incarca o semnatura valida.');
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeRaw = $finfo ? (string) finfo_file($finfo, $tmpName) : (string) ($file['type'] ?? '');
        if ($finfo) {
            finfo_close($finfo);
        }
        $mime = $this->normalizeImageMime($mimeRaw);
        if (!array_key_exists($mime, self::ANNEX_SIGNATURE_ALLOWED_MIMES)) {
            Session::flash('error', 'Format semnatura invalid. Acceptam png, jpg sau webp.');
            return null;
        }

        $extension = self::ANNEX_SIGNATURE_ALLOWED_MIMES[$mime];
        $storageDir = BASE_PATH . '/storage/erp';
        $publicDir = BASE_PATH . '/public/storage/erp';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }

        $filename = self::ANNEX_SIGNATURE_BASE_NAME . '.' . $extension;
        $targetPath = $storageDir . '/' . $filename;
        if (!move_uploaded_file($tmpName, $targetPath)) {
            Session::flash('error', 'Nu am putut salva semnatura incarcata.');
            return null;
        }

        foreach (['png', 'jpg', 'webp'] as $ext) {
            if ($ext === $extension) {
                continue;
            }
            @unlink($storageDir . '/' . self::ANNEX_SIGNATURE_BASE_NAME . '.' . $ext);
            @unlink($publicDir . '/' . self::ANNEX_SIGNATURE_BASE_NAME . '.' . $ext);
        }

        @copy($targetPath, $publicDir . '/' . $filename);

        return 'storage/erp/' . $filename;
    }

    private function normalizeImageMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        if ($mime === 'image/jpg') {
            return 'image/jpeg';
        }
        if ($mime === 'image/x-png') {
            return 'image/png';
        }

        return $mime;
    }

    private function deleteStorageErpFile(string $relativePath): void
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'storage/erp/')) {
            return;
        }

        $absolutePath = BASE_PATH . '/' . $relativePath;
        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $publicPath = BASE_PATH . '/public/' . $relativePath;
        if (is_file($publicPath)) {
            @unlink($publicPath);
        }
    }

    public function generateDemo(): void
    {
        Auth::requireSuperAdmin();

        if (!$this->ensureDemoTables()) {
            Session::flash('error', 'Importa schema SQL inainte de generare.');
            Response::redirect('/admin/setari');
        }

        $existingInvoices = (int) Database::fetchValue('SELECT COUNT(*) FROM invoices_in');
        if ($existingInvoices > 0) {
            Session::flash('error', 'Exista deja facturi. Foloseste butonul de golire pentru demo.');
            Response::redirect('/admin/setari');
        }

        $settings = new SettingsService();
        $settings->set('packages.last_confirmed_no', 10000);

        $platformName = (string) $settings->get('company.denumire', 'ERP PLATFORM');
        $platformCui = preg_replace('/\D+/', '', (string) $settings->get('company.cui', ''));
        if ($platformCui === '') {
            $platformCui = '99999999';
        }

        $pairs = array_values(array_filter(Commission::allWithPartners(), static function (array $row): bool {
            return !empty($row['supplier_cui']) && !empty($row['client_cui']);
        }));

        if (empty($pairs)) {
            Session::flash('error', 'Nu exista asocieri furnizor-client. Creeaza comisioane inainte de demo.');
            Response::redirect('/admin/setari');
        }

        $packageNo = 10001;
        $fgoCounter = 100000;
        $invoicesCreated = 0;

        for ($i = 0; $i < 30; $i++) {
            $pair = $pairs[$i % count($pairs)];
            $supplierCui = (string) $pair['supplier_cui'];
            $clientCui = (string) $pair['client_cui'];
            $supplierName = CompanyName::normalize((string) ($pair['supplier_name'] ?? $supplierCui));
            $clientName = CompanyName::normalize((string) ($pair['client_name'] ?? $clientCui));
            $commission = (float) ($pair['commission'] ?? 0);

            $issueTs = strtotime('-' . rand(1, 60) . ' days');
            $issueDate = date('Y-m-d', $issueTs);
            $dueDate = date('Y-m-d', strtotime('+15 days', $issueTs));
            $invoiceSeries = $this->buildSeries($supplierName, $i);
            $invoiceNo = (string) ($i + 1000);
            $invoiceNumber = trim($invoiceSeries . ' ' . $invoiceNo);

            $lines = $this->buildDemoLines();
            $totals = $this->sumDemoLines($lines);

            $invoice = InvoiceIn::create([
                'invoice_number' => $invoiceNumber,
                'invoice_series' => $invoiceSeries,
                'invoice_no' => $invoiceNo,
                'supplier_cui' => $supplierCui,
                'supplier_name' => $supplierName,
                'customer_cui' => $platformCui,
                'customer_name' => $platformName,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency' => 'RON',
                'total_without_vat' => $totals['without'],
                'total_vat' => $totals['vat'],
                'total_with_vat' => $totals['with'],
                'xml_path' => null,
            ]);

            Database::execute(
                'UPDATE invoices_in SET selected_client_cui = :client, commission_percent = :commission WHERE id = :id',
                [
                    'client' => $clientCui,
                    'commission' => $commission,
                    'id' => $invoice->id,
                ]
            );

            $lineIndex = 1;
            foreach ($lines as $line) {
                $line['line_no'] = (string) $lineIndex;
                InvoiceInLine::create($invoice->id, $line);
                $lineIndex++;
            }

            $confirmed = $i % 3 !== 0;
            $packageNo = $this->createDemoPackages($invoice->id, $packageNo, $confirmed);

            if ($confirmed) {
                Database::execute(
                    'UPDATE invoices_in SET packages_confirmed = 1, packages_confirmed_at = :at WHERE id = :id',
                    [
                        'at' => date('Y-m-d H:i:s', strtotime('+1 day', $issueTs)),
                        'id' => $invoice->id,
                    ]
                );
            }

            $hasFgo = $confirmed && $i % 2 === 0;
            $hasStorno = $hasFgo && $i % 10 === 0;
            if ($hasFgo) {
                $fgoSeries = 'DEWEB';
                $fgoNumber = (string) $fgoCounter++;
                $fgoDate = date('Y-m-d', strtotime('+2 days', $issueTs));
                Database::execute(
                    'UPDATE invoices_in SET fgo_series = :serie, fgo_number = :numar, fgo_date = :data, fgo_link = :link WHERE id = :id',
                    [
                        'serie' => $fgoSeries,
                        'numar' => $fgoNumber,
                        'data' => $fgoDate,
                        'link' => 'https://example.com/fgo/' . $fgoSeries . $fgoNumber . '.pdf',
                        'id' => $invoice->id,
                    ]
                );
                if ($hasStorno) {
                    Database::execute(
                        'UPDATE invoices_in SET fgo_storno_series = :serie, fgo_storno_number = :numar, fgo_storno_link = :link WHERE id = :id',
                        [
                            'serie' => $fgoSeries,
                            'numar' => $fgoNumber,
                            'link' => 'https://example.com/fgo/storno/' . $fgoSeries . $fgoNumber . '.pdf',
                            'id' => $invoice->id,
                        ]
                    );
                }
            }

            if (!$hasStorno) {
                $this->createDemoPayments($invoice->id, $clientCui, $clientName, $commission);
            }

            $invoicesCreated++;
        }

        $settings->set('packages.last_confirmed_no', $packageNo - 1);
        $settings->set('order_note.last_no', 999);

        Session::flash('status', 'Date demo generate: ' . $invoicesCreated . ' facturi.');
        Response::redirect('/admin/setari');
    }

    public function resetDemo(): void
    {
        Auth::requireSuperAdmin();

        if (!$this->ensureDemoTables()) {
            Session::flash('error', 'Importa schema SQL inainte de reset.');
            Response::redirect('/admin/setari');
        }

        Database::execute('DELETE FROM payment_in_allocations');
        Database::execute('DELETE FROM payment_out_allocations');
        Database::execute('DELETE FROM payments_in');
        Database::execute('DELETE FROM payments_out');
        if (Database::tableExists('payment_orders')) {
            Database::execute('DELETE FROM payment_orders');
        }
        Database::execute('DELETE FROM invoice_in_lines');
        Database::execute('DELETE FROM packages');
        Database::execute('DELETE FROM invoices_in');

        if (Database::tableExists('packages')) {
            Database::execute('ALTER TABLE packages AUTO_INCREMENT = 10000');
        }

        $settings = new SettingsService();
        $settings->set('packages.last_confirmed_no', 10000);
        $settings->set('order_note.last_no', 999);

        $this->purgeDirectory(BASE_PATH . '/storage/invoices_in');
        $this->purgeDirectory(BASE_PATH . '/storage/saga');

        Session::flash('status', 'Datele demo au fost sterse.');
        Response::redirect('/admin/setari');
    }

    private function buildSeries(string $supplierName, int $index): string
    {
        $parts = preg_split('/\s+/', trim($supplierName));
        $letters = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $letters .= substr($part, 0, 1);
            if (strlen($letters) >= 3) {
                break;
            }
        }
        $letters = strtoupper($letters);
        if ($letters === '') {
            $letters = 'F' . (($index % 9) + 1);
        }

        return $letters;
    }
    private function buildDemoLines(): array
    {
        $products = [
            'TONER LASER', 'HARTIE A4', 'CARTUS CERNEALA', 'SERVICII PRINT', 'MENTENANTA',
            'FOTOCOPII', 'BANNER PUBLICITAR', 'MAPE BIROU', 'ETICHETE AUTOADEZIVE', 'DOSARE',
        ];
        $lines = [];
        $lineCount = rand(2, 5);
        for ($i = 0; $i < $lineCount; $i++) {
            $qty = rand(1, 5);
            $price = rand(50, 350) / 10;
            $tax = rand(0, 10) > 2 ? 21 : 9;
            $lineTotal = round($qty * $price, 2);
            $lineTotalVat = round($lineTotal * (1 + $tax / 100), 2);
            $lines[] = [
                'product_name' => $products[array_rand($products)],
                'quantity' => $qty,
                'unit_code' => 'BUC',
                'unit_price' => $price,
                'line_total' => $lineTotal,
                'tax_percent' => $tax,
                'line_total_vat' => $lineTotalVat,
            ];
        }

        return $lines;
    }

    private function sumDemoLines(array $lines): array
    {
        $without = 0.0;
        $with = 0.0;
        foreach ($lines as $line) {
            $without += (float) $line['line_total'];
            $with += (float) $line['line_total_vat'];
        }

        return [
            'without' => round($without, 2),
            'with' => round($with, 2),
            'vat' => round($with - $without, 2),
        ];
    }

    private function createDemoPackages(int $invoiceId, int $packageNo, bool $confirmed): int
    {
        $lines = InvoiceInLine::forInvoice($invoiceId);
        if (empty($lines)) {
            return $packageNo;
        }

        $groups = [];
        foreach ($lines as $line) {
            $vat = number_format($line->tax_percent, 2, '.', '');
            $groups[$vat][] = $line;
        }

        foreach ($groups as $vat => $groupLines) {
            $count = count($groupLines) > 3 ? 2 : 1;
            $packages = [];
            for ($i = 0; $i < $count; $i++) {
                $packages[] = Package::create($invoiceId, $packageNo, (float) $vat);
                $packageNo++;
            }
            $index = 0;
            foreach ($groupLines as $line) {
                $target = $packages[$index % $count];
                InvoiceInLine::updatePackage($line->id, $target->id);
                $index++;
            }
        }

        return $packageNo;
    }

    private function createDemoPayments(int $invoiceId, string $clientCui, string $clientName, float $commission): void
    {
        $invoice = InvoiceIn::find($invoiceId);
        if (!$invoice) {
            return;
        }

        $clientTotal = $this->applyCommission($invoice->total_with_vat, $commission);
        $status = rand(1, 3);
        $collected = 0.0;
        if ($status === 2) {
            $collected = round($clientTotal * 0.6, 2);
        } elseif ($status === 3) {
            $collected = $clientTotal;
        }

        if ($collected > 0) {
            Database::execute(
                'INSERT INTO payments_in (client_cui, client_name, amount, paid_at, notes, created_at)
                 VALUES (:client_cui, :client_name, :amount, :paid_at, :notes, :created_at)',
                [
                    'client_cui' => $clientCui,
                    'client_name' => $clientName,
                    'amount' => $collected,
                    'paid_at' => date('Y-m-d'),
                    'notes' => 'DEMO',
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $paymentId = (int) Database::lastInsertId();
            Database::execute(
                'INSERT INTO payment_in_allocations (payment_in_id, invoice_in_id, amount, created_at)
                 VALUES (:payment, :invoice, :amount, :created_at)',
                [
                    'payment' => $paymentId,
                    'invoice' => $invoiceId,
                    'amount' => $collected,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        $paidStatus = rand(1, 3);
        $paid = 0.0;
        if ($paidStatus === 2) {
            $paid = round($invoice->total_with_vat * 0.4, 2);
        } elseif ($paidStatus === 3) {
            $paid = (float) $invoice->total_with_vat;
        }

        if ($paid > 0) {
            Database::execute(
                'INSERT INTO payments_out (supplier_cui, supplier_name, amount, paid_at, notes, created_at)
                 VALUES (:supplier_cui, :supplier_name, :amount, :paid_at, :notes, :created_at)',
                [
                    'supplier_cui' => $invoice->supplier_cui,
                    'supplier_name' => $invoice->supplier_name,
                    'amount' => $paid,
                    'paid_at' => date('Y-m-d'),
                    'notes' => 'DEMO',
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $paymentId = (int) Database::lastInsertId();
            Database::execute(
                'INSERT INTO payment_out_allocations (payment_out_id, invoice_in_id, amount, created_at)
                 VALUES (:payment, :invoice, :amount, :created_at)',
                [
                    'payment' => $paymentId,
                    'invoice' => $invoiceId,
                    'amount' => $paid,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    private function applyCommission(float $amount, float $percent): float
    {
        $factor = 1 + (abs($percent) / 100);
        if ($percent >= 0) {
            return round($amount * $factor, 2);
        }

        return round($amount / $factor, 2);
    }

    private function ensureDemoTables(): bool
    {
        $required = [
            'companies',
            'partners',
            'commissions',
            'invoices_in',
            'invoice_in_lines',
            'packages',
            'payments_in',
            'payment_in_allocations',
            'payments_out',
            'payment_out_allocations',
            'settings',
        ];
        foreach ($required as $table) {
            if (!Database::tableExists($table)) {
                return false;
            }
        }

        return true;
    }

    private function purgeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = glob($path . '/*');
        if (!$items) {
            return;
        }

        foreach ($items as $item) {
            if (is_dir($item)) {
                $this->purgeDirectory($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
    }

    public function changelog(): void
    {
        Auth::requireAdmin();

        Response::view('admin/changelog', [
            'version' => self::APP_VERSION,
            'releases' => $this->readChangelog(),
        ]);
    }

    private function readChangelog(): array
    {
        $path = BASE_PATH . '/CHANGELOG.md';
        if (!file_exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $releases = [];
        $currentIndex = null;

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^##\s+v?([0-9A-Za-z\.\-]+)/', $line, $match)) {
                $version = 'v' . ltrim($match[1], 'v');
                $releases[] = [
                    'version' => $version,
                    'items' => [],
                ];
                $currentIndex = count($releases) - 1;
                continue;
            }

            if ($currentIndex === null) {
                continue;
            }

            if (str_starts_with($line, '- ')) {
                $releases[$currentIndex]['items'][] = substr($line, 2);
            }
        }

        return $releases;
    }
}
