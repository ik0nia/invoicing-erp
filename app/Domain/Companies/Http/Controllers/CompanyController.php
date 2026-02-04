<?php

namespace App\Domain\Companies\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Partners\Models\Partner;
use App\Domain\Settings\Services\SettingsService;
use App\Support\Auth;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class CompanyController
{
    public function index(): void
    {
        Auth::requireAdmin();

        if (!$this->ensureCompaniesTable()) {
            Session::flash('error', 'Tabela companii nu exista. Importa schema SQL.');
            Response::view('admin/companies/index', [
                'companies' => [],
                'hasPartners' => false,
            ]);
        }

        $hasPartners = Database::tableExists('partners');
        $companies = [];

        if ($hasPartners) {
            $companies = Database::fetchAll(
                'SELECT p.cui, p.denumire, c.id AS company_id, c.tip_firma, c.tip_companie, c.activ, c.banca, c.iban,
                        c.nr_reg_comertului, c.adresa, c.localitate, c.judet, c.tara, c.email, c.telefon
                 FROM partners p
                 LEFT JOIN companies c ON c.cui = p.cui
                 ORDER BY p.denumire ASC'
            );
        } elseif (Database::tableExists('companies')) {
            $companies = Database::fetchAll(
                'SELECT c.cui, c.denumire, c.id AS company_id, c.tip_firma, c.tip_companie, c.activ, c.banca, c.iban,
                        c.nr_reg_comertului, c.adresa, c.localitate, c.judet, c.tara, c.email, c.telefon
                 FROM companies c
                 ORDER BY c.denumire ASC'
            );
        }

        foreach ($companies as &$company) {
            $company['denumire'] = CompanyName::normalize((string) ($company['denumire'] ?? ''));
            $company['details_complete'] = $this->isCompanyComplete($company);
        }
        unset($company);

        Response::view('admin/companies/index', [
            'companies' => $companies,
            'hasPartners' => $hasPartners,
        ]);
    }

    public function edit(): void
    {
        Auth::requireAdmin();
        $this->ensureCompaniesTable();

        $cui = trim($_GET['cui'] ?? '');
        $company = $cui !== '' ? Company::findByCui($cui) : null;
        $partner = $cui !== '' ? Partner::findByCui($cui) : null;

        $form = $this->buildFormData($company, $partner);
        $settings = new SettingsService();
        $openApiKey = (string) $settings->get('openapi.api_key', '');

        Response::view('admin/companies/edit', [
            'form' => $form,
            'isNew' => $company === null,
            'openApiEnabled' => trim($openApiKey) !== '',
        ]);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        $this->ensureCompaniesTable();

        $data = [
            'denumire' => CompanyName::normalize((string) ($_POST['denumire'] ?? '')),
            'cui' => preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? '')),
            'nr_reg_comertului' => trim($_POST['nr_reg_comertului'] ?? ''),
            'platitor_tva' => !empty($_POST['platitor_tva']) ? 1 : 0,
            'adresa' => trim($_POST['adresa'] ?? ''),
            'localitate' => trim($_POST['localitate'] ?? ''),
            'judet' => trim($_POST['judet'] ?? ''),
            'tara' => trim($_POST['tara'] ?? 'România'),
            'email' => trim($_POST['email'] ?? ''),
            'telefon' => trim($_POST['telefon'] ?? ''),
            'banca' => trim($_POST['banca'] ?? ''),
            'iban' => trim($_POST['iban'] ?? ''),
            'activ' => !empty($_POST['activ']) ? 1 : 0,
        ];
        $defaultCommissionInput = str_replace(',', '.', (string) ($_POST['default_commission'] ?? ''));
        $existing = $data['cui'] !== '' ? Company::findByCui($data['cui']) : null;
        $data['tip_firma'] = $existing?->tip_firma ?? 'SRL';
        $data['tip_companie'] = $existing?->tip_companie ?? 'client';

        $errors = $this->validate($data, $defaultCommissionInput);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            Response::view('admin/companies/edit', [
                'form' => array_merge($data, ['default_commission' => $defaultCommissionInput]),
                'isNew' => $existing === null,
            ]);
        }

        Company::save($data);
        Partner::upsert($data['cui'], $data['denumire']);
        $defaultCommission = $defaultCommissionInput === '' ? 0.0 : (float) $defaultCommissionInput;
        Partner::updateDefaultCommission($data['cui'], $defaultCommission);

        Session::flash('status', 'Compania a fost salvata.');
        Response::redirect('/admin/companii/edit?cui=' . urlencode($data['cui']));
    }

    public function lookupOpenApi(): void
    {
        Auth::requireAdmin();

        $cui = preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? ''));
        if ($cui === '') {
            $this->json(['success' => false, 'message' => 'Completeaza CUI-ul.']);
        }

        $settings = new SettingsService();
        $apiKey = trim((string) $settings->get('openapi.api_key', ''));
        if ($apiKey === '') {
            $this->json(['success' => false, 'message' => 'Completeaza cheia OpenAPI in setari.']);
        }

        $response = $this->fetchOpenApiCompany($cui, $apiKey);
        if ($response['error'] !== null) {
            $this->json(['success' => false, 'message' => $response['error']]);
        }

        $data = $response['data'];
        $denumire = trim((string) ($data['denumire'] ?? ''));
        $denumire = CompanyName::normalize($denumire);
        $adresa = trim((string) ($data['adresa'] ?? ''));
        $localitate = trim((string) ($data['localitate'] ?? ''));
        if ($localitate === '' && $adresa !== '') {
            $parts = array_map('trim', explode(',', $adresa));
            $localitate = end($parts) ?: '';
        }

        $tva = $data['tva'] ?? null;
        $platitorTva = !empty($tva) && strtolower((string) $tva) !== 'null';
        $radiata = $data['radiata'] ?? null;

        $payload = [
            'cui' => (string) ($data['cif'] ?? $cui),
            'denumire' => $denumire,
            'nr_reg_comertului' => (string) ($data['numar_reg_com'] ?? ''),
            'adresa' => $adresa,
            'localitate' => $localitate,
            'judet' => (string) ($data['judet'] ?? ''),
            'telefon' => (string) ($data['telefon'] ?? ''),
            'platitor_tva' => $platitorTva,
            'activ' => $radiata === null ? null : !$radiata,
        ];

        $this->json(['success' => true, 'data' => $payload]);
    }

    private function validate(array $data, string $defaultCommissionInput): array
    {
        if ($data['denumire'] === '') {
            return ['Campul "Denumire" este obligatoriu.'];
        }
        if ($data['cui'] === '') {
            return ['Campul "CUI" este obligatoriu.'];
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['Email invalid.'];
        }
        if ($defaultCommissionInput !== '' && !is_numeric($defaultCommissionInput)) {
            return ['Comision default invalid.'];
        }

        return [];
    }

    private function buildFormData(?Company $company, ?Partner $partner): array
    {
        $defaultCommission = (float) ($partner?->default_commission ?? 0);
        $defaultCommissionValue = $defaultCommission > 0 ? number_format($defaultCommission, 2, '.', '') : '';

        return [
            'denumire' => CompanyName::normalize((string) ($company?->denumire ?? $partner?->denumire ?? '')),
            'cui' => $company?->cui ?? $partner?->cui ?? '',
            'nr_reg_comertului' => $company?->nr_reg_comertului ?? '',
            'platitor_tva' => $company?->platitor_tva ?? false,
            'adresa' => $company?->adresa ?? '',
            'localitate' => $company?->localitate ?? '',
            'judet' => $company?->judet ?? '',
            'tara' => $company?->tara ?? 'România',
            'email' => $company?->email ?? '',
            'telefon' => $company?->telefon ?? '',
            'banca' => $company?->banca ?? '',
            'iban' => $company?->iban ?? '',
            'activ' => $company?->activ ?? true,
            'default_commission' => $defaultCommissionValue,
        ];
    }

    private function isCompanyComplete(array $company): bool
    {
        if (empty($company['company_id'])) {
            return false;
        }

        $required = [
            'denumire',
            'cui',
            'nr_reg_comertului',
            'adresa',
            'localitate',
            'judet',
            'tara',
            'email',
            'telefon',
        ];

        foreach ($required as $field) {
            if (trim((string) ($company[$field] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }

    private function ensureCompaniesTable(): bool
    {
        try {
            Database::execute(
                'CREATE TABLE IF NOT EXISTS companies (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    denumire VARCHAR(255) NOT NULL,
                    tip_firma ENUM("SRL", "SA", "PFA", "II", "IF") NOT NULL,
                    cui VARCHAR(64) NOT NULL UNIQUE,
                    nr_reg_comertului VARCHAR(64) NOT NULL,
                    platitor_tva TINYINT(1) NOT NULL DEFAULT 0,
                    adresa VARCHAR(255) NOT NULL,
                    localitate VARCHAR(255) NOT NULL,
                    judet VARCHAR(255) NOT NULL,
                    tara VARCHAR(255) NOT NULL DEFAULT "România",
                    email VARCHAR(255) NOT NULL,
                    telefon VARCHAR(64) NOT NULL,
                    banca VARCHAR(255) NULL,
                    iban VARCHAR(64) NULL,
                    tip_companie ENUM("client", "furnizor", "intermediar") NOT NULL,
                    activ TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $exception) {
            return false;
        }

        if (Database::tableExists('companies') && !Database::columnExists('companies', 'banca')) {
            Database::execute('ALTER TABLE companies ADD COLUMN banca VARCHAR(255) NULL AFTER telefon');
        }
        if (Database::tableExists('companies') && !Database::columnExists('companies', 'iban')) {
            Database::execute('ALTER TABLE companies ADD COLUMN iban VARCHAR(64) NULL AFTER banca');
        }

        return true;
    }

    private function fetchOpenApiCompany(string $cui, string $apiKey): array
    {
        $url = 'https://api.openapi.ro/api/companies/' . urlencode($cui);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => 'Nu pot initia conexiunea OpenAPI.', 'data' => null];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-key: ' . $apiKey]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 500) {
            return ['error' => 'Eroare OpenAPI: ' . ($error ?: 'server indisponibil'), 'data' => null];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['error' => 'Raspuns OpenAPI invalid.', 'data' => null];
        }

        if ($status >= 400) {
            $message = $decoded['error']['Attributes']['description'] ?? $decoded['error']['Attributes']['title'] ?? 'Eroare OpenAPI.';
            return ['error' => (string) $message, 'data' => null];
        }

        return ['error' => null, 'data' => $decoded];
    }

    private function json(array $payload): void
    {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
