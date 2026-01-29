<?php

namespace App\Domain\Companies\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Partners\Models\Partner;
use App\Support\Auth;
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
                'SELECT p.cui, p.denumire, c.id AS company_id, c.tip_firma, c.tip_companie, c.activ
                 FROM partners p
                 LEFT JOIN companies c ON c.cui = p.cui
                 ORDER BY p.denumire ASC'
            );
        } elseif (Database::tableExists('companies')) {
            $companies = Database::fetchAll(
                'SELECT c.cui, c.denumire, c.id AS company_id, c.tip_firma, c.tip_companie, c.activ
                 FROM companies c
                 ORDER BY c.denumire ASC'
            );
        }

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

        Response::view('admin/companies/edit', [
            'form' => $form,
            'isNew' => $company === null,
        ]);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        $this->ensureCompaniesTable();

        $data = [
            'denumire' => trim($_POST['denumire'] ?? ''),
            'tip_firma' => trim($_POST['tip_firma'] ?? ''),
            'cui' => preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? '')),
            'nr_reg_comertului' => trim($_POST['nr_reg_comertului'] ?? ''),
            'platitor_tva' => !empty($_POST['platitor_tva']) ? 1 : 0,
            'adresa' => trim($_POST['adresa'] ?? ''),
            'localitate' => trim($_POST['localitate'] ?? ''),
            'judet' => trim($_POST['judet'] ?? ''),
            'tara' => trim($_POST['tara'] ?? 'România'),
            'email' => trim($_POST['email'] ?? ''),
            'telefon' => trim($_POST['telefon'] ?? ''),
            'tip_companie' => trim($_POST['tip_companie'] ?? ''),
            'activ' => !empty($_POST['activ']) ? 1 : 0,
        ];

        $errors = $this->validate($data);
        if (!empty($errors)) {
            Session::flash('error', $errors[0]);
            Response::view('admin/companies/edit', [
                'form' => $data,
                'isNew' => true,
            ]);
        }

        Company::save($data);
        Partner::upsert($data['cui'], $data['denumire']);

        Session::flash('status', 'Compania a fost salvata.');
        Response::redirect('/admin/companii/edit?cui=' . urlencode($data['cui']));
    }

    private function validate(array $data): array
    {
        $required = [
            'denumire' => 'Denumire',
            'tip_firma' => 'Tip firma',
            'cui' => 'CUI',
            'nr_reg_comertului' => 'Nr. Reg. Comertului',
            'adresa' => 'Adresa',
            'localitate' => 'Localitate',
            'judet' => 'Judet',
            'tara' => 'Tara',
            'email' => 'Email',
            'telefon' => 'Telefon',
            'tip_companie' => 'Tip companie',
        ];

        foreach ($required as $field => $label) {
            if (empty($data[$field])) {
                return ['Campul "' . $label . '" este obligatoriu.'];
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['Email invalid.'];
        }

        $tipFirmaAllowed = ['SRL', 'SA', 'PFA', 'II', 'IF'];
        if (!in_array($data['tip_firma'], $tipFirmaAllowed, true)) {
            return ['Tip firma invalid.'];
        }

        $tipCompanieAllowed = ['client', 'furnizor', 'intermediar'];
        if (!in_array($data['tip_companie'], $tipCompanieAllowed, true)) {
            return ['Tip companie invalid.'];
        }

        return [];
    }

    private function buildFormData(?Company $company, ?Partner $partner): array
    {
        return [
            'denumire' => $company?->denumire ?? $partner?->denumire ?? '',
            'tip_firma' => $company?->tip_firma ?? '',
            'cui' => $company?->cui ?? $partner?->cui ?? '',
            'nr_reg_comertului' => $company?->nr_reg_comertului ?? '',
            'platitor_tva' => $company?->platitor_tva ?? false,
            'adresa' => $company?->adresa ?? '',
            'localitate' => $company?->localitate ?? '',
            'judet' => $company?->judet ?? '',
            'tara' => $company?->tara ?? 'România',
            'email' => $company?->email ?? '',
            'telefon' => $company?->telefon ?? '',
            'tip_companie' => $company?->tip_companie ?? '',
            'activ' => $company?->activ ?? true,
        ];
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
                    tip_companie ENUM("client", "furnizor", "intermediar") NOT NULL,
                    activ TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
