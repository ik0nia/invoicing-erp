<?php

namespace App\Domain\Invoices\Http\Controllers;

use App\Domain\Invoices\Models\InvoiceIn;
use App\Domain\Invoices\Models\InvoiceInLine;
use App\Domain\Invoices\Models\Package;
use App\Domain\Invoices\Services\InvoiceXmlParser;
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
            $clients = Commission::forSupplier($invoice->supplier_cui);

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

        $xmlPath = $this->storeXml($file['tmp_name'], $data['invoice_number']);

        $invoice = InvoiceIn::create([
            'invoice_number' => $data['invoice_number'],
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
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
            }

            $counts = $_POST['package_counts'] ?? [];
            $this->generatePackages($invoiceId, $counts);
            Session::flash('status', 'Pachetele au fost reorganizate.');
        }

        if ($action === 'delete') {
            if ($this->isInvoiceConfirmed($invoiceId)) {
                Session::flash('error', 'Pachetele sunt confirmate si nu pot fi sterse.');
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
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
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
            }

            $this->confirmPackages($invoiceId);
            Session::flash('status', 'Pachetele au fost confirmate.');
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
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
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
            }

            $line = InvoiceInLine::find($lineId);

            if (!$line || $line->invoice_in_id !== $invoiceId) {
                Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
            }

            if ($packageId) {
                $package = Package::find($packageId);

                if (!$package || $package->invoice_in_id !== $invoiceId) {
                    Session::flash('error', 'Pachet invalid.');
                    Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
                }

                if ($package->vat_percent <= 0) {
                    Package::updateVat($packageId, $line->tax_percent);
                } elseif (abs($package->vat_percent - $line->tax_percent) > 0.01) {
                    Session::flash('error', 'Poti muta doar produse cu aceeasi cota TVA.');
                    Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
                }
            }

            InvoiceInLine::updatePackage($lineId, $packageId ?: null);
        }

        Response::redirect('/admin/facturi?invoice_id=' . $invoiceId);
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
                    supplier_cui VARCHAR(32) NOT NULL,
                    supplier_name VARCHAR(255) NOT NULL,
                    customer_cui VARCHAR(32) NOT NULL,
                    customer_name VARCHAR(255) NOT NULL,
                    issue_date DATE NOT NULL,
                    due_date DATE NULL,
                    currency VARCHAR(8) NOT NULL,
                    total_without_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    total_with_vat DECIMAL(12,2) NOT NULL DEFAULT 0,
                    xml_path VARCHAR(255) NULL,
                    packages_confirmed TINYINT(1) NOT NULL DEFAULT 0,
                    packages_confirmed_at DATETIME NULL,
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
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'packages_confirmed')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN packages_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER xml_path');
        }
        if (Database::tableExists('invoices_in') && !Database::columnExists('invoices_in', 'packages_confirmed_at')) {
            Database::execute('ALTER TABLE invoices_in ADD COLUMN packages_confirmed_at DATETIME NULL AFTER packages_confirmed');
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
}
