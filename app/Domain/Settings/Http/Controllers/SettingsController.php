<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Settings\Services\SettingsService;
use App\Support\CompanyName;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class SettingsController
{
    private SettingsService $settings;

    public function __construct()
    {
        $this->settings = new SettingsService();
    }

    public function edit(): void
    {
        Auth::requireAdmin();

        $logoPath = $this->settings->get('branding.logo_path');
        $logoUrl = null;

        if ($logoPath) {
            $absolutePath = BASE_PATH . '/' . ltrim($logoPath, '/');

            if (file_exists($absolutePath)) {
                $logoUrl = \App\Support\Url::asset($logoPath);
            }
        }

        $fgoApiKey = (string) $this->settings->get('fgo.api_key', '');
        $fgoSeries = (string) $this->settings->get('fgo.series', '');
        $fgoSeriesList = $this->settings->get('fgo.series_list', []);
        if (!is_array($fgoSeriesList)) {
            $fgoSeriesList = [];
        }
        $fgoSeriesListText = implode(', ', $fgoSeriesList);
        $fgoBaseUrl = (string) $this->settings->get('fgo.base_url', '');
        $openApiKey = (string) $this->settings->get('openapi.api_key', '');
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

        Response::view('admin/settings/index', [
            'logoPath' => $logoPath,
            'logoUrl' => $logoUrl,
            'fgoApiKey' => $fgoApiKey,
            'fgoSeries' => $fgoSeries,
            'fgoSeriesList' => $fgoSeriesList,
            'fgoSeriesListText' => $fgoSeriesListText,
            'fgoBaseUrl' => $fgoBaseUrl,
            'openApiKey' => $openApiKey,
            'company' => $company,
        ]);
    }

    public function update(): void
    {
        Auth::requireAdmin();

        $logoUpdated = false;
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                Session::flash('error', 'Te rog incarca un fisier valid.');
                Response::redirect('/admin/setari');
            }

            $file = $_FILES['logo'];
            $maxSize = 2 * 1024 * 1024;

            if ($file['size'] > $maxSize) {
                Session::flash('error', 'Logo-ul trebuie sa fie sub 2 MB.');
                Response::redirect('/admin/setari');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : ($file['type'] ?? '');

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
                Response::redirect('/admin/setari');
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

            $filename = 'logo.' . $extension;
            $targetPath = $storageDir . '/' . $filename;

            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                Session::flash('error', 'Nu am putut salva fisierul incarcat.');
                Response::redirect('/admin/setari');
            }

            foreach (['png', 'jpg', 'svg'] as $ext) {
                if ($ext === $extension) {
                    continue;
                }

                @unlink($storageDir . '/logo.' . $ext);
                @unlink($publicDir . '/logo.' . $ext);
            }

            @copy($targetPath, $publicDir . '/' . $filename);

            $this->settings->set('branding.logo_path', 'storage/erp/' . $filename);
            $logoUpdated = true;
        }

        $apiKey = trim($_POST['fgo_api_key'] ?? '');
        $series = trim($_POST['fgo_series'] ?? '');
        $seriesListRaw = trim($_POST['fgo_series_list'] ?? '');
        $baseUrl = trim($_POST['fgo_base_url'] ?? '');
        $openApiKey = trim($_POST['openapi_api_key'] ?? '');
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

        $savedSomething = $logoUpdated;

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

    public function generateDemo(): void
    {
        Auth::requireAdmin();

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

        $suppliers = $this->demoSuppliers();
        $clients = $this->demoClients();
        $demoCuis = [];

        foreach ($suppliers as $supplier) {
            $demoCuis[] = $supplier['cui'];
            $this->upsertCompany($supplier, 'furnizor');
        }
        foreach ($clients as $client) {
            $demoCuis[] = $client['cui'];
            $this->upsertCompany($client, 'client');
        }
        $settings->set('demo.cuis', array_values(array_unique($demoCuis)));

        $supplierClients = [];
        foreach ($suppliers as $supplier) {
            $clientPool = $clients;
            shuffle($clientPool);
            $count = 2 + (int) (count($clientPool) > 3);
            $supplierClients[$supplier['cui']] = array_slice($clientPool, 0, $count);
            foreach ($supplierClients[$supplier['cui']] as $client) {
                Commission::createOrUpdate($supplier['cui'], $client['cui'], $client['commission']);
            }
        }

        $packageNo = 10001;
        $fgoCounter = 100000;
        $invoicesCreated = 0;

        for ($i = 0; $i < 30; $i++) {
            $supplier = $suppliers[$i % count($suppliers)];
            $clientPool = $supplierClients[$supplier['cui']] ?? $clients;
            $client = $clientPool[array_rand($clientPool)];
            $issueTs = strtotime('-' . rand(1, 60) . ' days');
            $issueDate = date('Y-m-d', $issueTs);
            $dueDate = date('Y-m-d', strtotime('+15 days', $issueTs));
            $invoiceSeries = $supplier['series'];
            $invoiceNo = (string) ($i + 1000);
            $invoiceNumber = trim($invoiceSeries . ' ' . $invoiceNo);

            $lines = $this->buildDemoLines();
            $totals = $this->sumDemoLines($lines);

            $invoice = InvoiceIn::create([
                'invoice_number' => $invoiceNumber,
                'invoice_series' => $invoiceSeries,
                'invoice_no' => $invoiceNo,
                'supplier_cui' => $supplier['cui'],
                'supplier_name' => $supplier['name'],
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
                    'client' => $client['cui'],
                    'commission' => $client['commission'],
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
                $this->createDemoPayments($invoice->id, $client['cui'], $client['name'], $client['commission']);
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
        Auth::requireAdmin();

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
        $demoCuis = (array) $settings->get('demo.cuis', []);
        $settings->set('demo.cuis', []);
        $settings->set('packages.last_confirmed_no', 10000);
        $settings->set('order_note.last_no', 999);

        if (!empty($demoCuis)) {
            $placeholders = [];
            $params = [];
            foreach (array_values($demoCuis) as $index => $cui) {
                $key = 'c' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cui;
            }
            Database::execute(
                'DELETE FROM commissions WHERE supplier_cui IN (' . implode(',', $placeholders) . ')
                 OR client_cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            Database::execute(
                'DELETE FROM partners WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
            Database::execute(
                'DELETE FROM companies WHERE cui IN (' . implode(',', $placeholders) . ')',
                $params
            );
        }

        $this->purgeDirectory(BASE_PATH . '/storage/invoices_in');
        $this->purgeDirectory(BASE_PATH . '/storage/saga');

        Session::flash('status', 'Datele demo au fost sterse.');
        Response::redirect('/admin/setari');
    }

    private function demoSuppliers(): array
    {
        return [
            ['cui' => '33288881', 'name' => 'EURO PRINT SHOP SRL', 'iban' => 'RO49AAAA1B31007593840001', 'banca' => 'Banca Transilvania', 'city' => 'Bucuresti', 'judet' => 'Bucuresti', 'series' => 'EPS'],
            ['cui' => '47473680', 'name' => 'DEMO LOGISTIC SRL', 'iban' => 'RO49AAAA1B31007593840002', 'banca' => 'BCR', 'city' => 'Cluj-Napoca', 'judet' => 'Cluj', 'series' => 'DL'],
            ['cui' => '41928357', 'name' => 'PRO SOLUTION SRL', 'iban' => 'RO49AAAA1B31007593840003', 'banca' => 'ING', 'city' => 'Timisoara', 'judet' => 'Timis', 'series' => 'PS'],
            ['cui' => '18273645', 'name' => 'ALFA SUPPLY SRL', 'iban' => 'RO49AAAA1B31007593840004', 'banca' => 'BRD', 'city' => 'Iasi', 'judet' => 'Iasi', 'series' => 'AS'],
            ['cui' => '29584736', 'name' => 'BETA PRINT SRL', 'iban' => 'RO49AAAA1B31007593840005', 'banca' => 'CEC', 'city' => 'Constanta', 'judet' => 'Constanta', 'series' => 'BP'],
            ['cui' => '30658912', 'name' => 'OMEGA OFFICE SRL', 'iban' => 'RO49AAAA1B31007593840006', 'banca' => 'UniCredit', 'city' => 'Brasov', 'judet' => 'Brasov', 'series' => 'OO'],
        ];
    }

    private function demoClients(): array
    {
        return [
            ['cui' => '47310601', 'name' => 'IKONIA AGENCY SRL', 'iban' => 'RO49AAAA1B31007593840101', 'banca' => 'Banca Transilvania', 'city' => 'Bucuresti', 'judet' => 'Bucuresti', 'commission' => 10.0],
            ['cui' => '36958137', 'name' => 'MIVINIA SRL', 'iban' => 'RO49AAAA1B31007593840102', 'banca' => 'BCR', 'city' => 'Cluj-Napoca', 'judet' => 'Cluj', 'commission' => 8.0],
            ['cui' => '51239847', 'name' => 'ALPHA MEDIA SRL', 'iban' => 'RO49AAAA1B31007593840103', 'banca' => 'ING', 'city' => 'Timisoara', 'judet' => 'Timis', 'commission' => 12.5],
            ['cui' => '41873922', 'name' => 'BETA AGENCY SRL', 'iban' => 'RO49AAAA1B31007593840104', 'banca' => 'BRD', 'city' => 'Iasi', 'judet' => 'Iasi', 'commission' => 7.5],
            ['cui' => '51290347', 'name' => 'OMEGA COM SRL', 'iban' => 'RO49AAAA1B31007593840105', 'banca' => 'CEC', 'city' => 'Constanta', 'judet' => 'Constanta', 'commission' => 9.0],
            ['cui' => '42399877', 'name' => 'DELTA CLIENT SRL', 'iban' => 'RO49AAAA1B31007593840106', 'banca' => 'UniCredit', 'city' => 'Brasov', 'judet' => 'Brasov', 'commission' => 11.0],
            ['cui' => '39827461', 'name' => 'ZETA PARTNER SRL', 'iban' => 'RO49AAAA1B31007593840107', 'banca' => 'Raiffeisen', 'city' => 'Oradea', 'judet' => 'Bihor', 'commission' => 6.5],
            ['cui' => '50761239', 'name' => 'GAMMA GROUP SRL', 'iban' => 'RO49AAAA1B31007593840108', 'banca' => 'Alpha Bank', 'city' => 'Ploiesti', 'judet' => 'Prahova', 'commission' => 10.5],
        ];
    }

    private function upsertCompany(array $data, string $tipCompanie): void
    {
        Company::save([
            'denumire' => $data['name'],
            'tip_firma' => 'SRL',
            'cui' => $data['cui'],
            'nr_reg_comertului' => 'J' . rand(1, 40) . '/' . rand(100, 999) . '/2019',
            'platitor_tva' => 1,
            'adresa' => 'Str. Demo nr. ' . rand(1, 50),
            'localitate' => $data['city'],
            'judet' => $data['judet'],
            'tara' => 'Romania',
            'email' => 'contact@' . strtolower(str_replace(' ', '', $data['name'])) . '.ro',
            'telefon' => '07' . rand(10000000, 99999999),
            'banca' => $data['banca'],
            'iban' => $data['iban'],
            'tip_companie' => $tipCompanie,
            'activ' => 1,
        ]);
        Partner::upsert($data['cui'], CompanyName::normalize($data['name']));
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
}
