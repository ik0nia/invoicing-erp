<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\DocumentNumberService;
use App\Domain\Contracts\Services\ContractPdfService;
use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Logger;
use App\Support\Response;
use App\Support\Session;

class ContractsController
{
    private const MAX_UPLOAD_BYTES = 20971520;
    private const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    public function index(): void
    {
        $user = $this->requireContractsRole();

        $templates = Database::fetchAll('SELECT * FROM contract_templates ORDER BY name ASC');
        $contracts = [];

        if ($user->isSupplierUser()) {
            UserSupplierAccess::ensureTable();
            $suppliers = UserSupplierAccess::suppliersForUser($user->id);
            if (!empty($suppliers)) {
                $placeholders = [];
                $params = [];
                foreach ($suppliers as $index => $cui) {
                    $key = 's' . $index;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $cui;
                }
                $contracts = Database::fetchAll(
                    'SELECT * FROM contracts
                     WHERE supplier_cui IN (' . implode(',', $placeholders) . ')
                        OR partner_cui IN (' . implode(',', $placeholders) . ')
                     ORDER BY created_at DESC, id DESC',
                    $params
                );
            }
        } else {
            $contracts = Database::fetchAll('SELECT * FROM contracts ORDER BY created_at DESC, id DESC');
        }
        $companyNamesByCui = $this->resolveCompanyNamesByCuis($contracts);

        Response::view('admin/contracts/index', [
            'templates' => $templates,
            'contracts' => $contracts,
            'companyNamesByCui' => $companyNamesByCui,
            'pdfAvailable' => (new ContractPdfService())->isPdfGenerationAvailable(),
        ]);
    }

    public function generate(): void
    {
        $user = $this->requireGenerateRole();

        $templateId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $contractDate = trim((string) ($_POST['contract_date'] ?? ''));

        if ($title === '') {
            Session::flash('error', 'Completeaza titlul contractului.');
            Response::redirect('/admin/contracts');
        }

        $template = $templateId > 0 ? Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $templateId]) : null;
        $docKind = strtolower(trim((string) ($template['doc_kind'] ?? '')));
        $docType = $this->resolveTemplateDocType($template, $docKind);
        if ($contractDate === '') {
            $contractDate = date('Y-m-d');
        }
        if ($docType === 'contract') {
            $existingPrimary = $this->findExistingPrimaryContract($partnerCui, $supplierCui, $clientCui);
            if ($existingPrimary) {
                $existingNo = $this->formatContractNumber($existingPrimary);
                $existingDateRaw = trim((string) ($existingPrimary['contract_date'] ?? ''));
                $existingDateDisplay = '—';
                if ($existingDateRaw !== '') {
                    $timestamp = strtotime($existingDateRaw);
                    $existingDateDisplay = $timestamp !== false ? date('d.m.Y', $timestamp) : $existingDateRaw;
                }
                Session::flash(
                    'error',
                    'Exista deja contractul principal pentru aceasta companie'
                    . ($existingNo !== '' ? (' [' . $existingNo . ']') : '')
                    . ' (data: ' . $existingDateDisplay . ').'
                );
                Response::redirect('/admin/contracts');
            }
        }
        $numberService = new DocumentNumberService();
        $number = null;
        $numberWarning = null;
        $registryScope = $this->resolveRegistryScope($supplierCui, $clientCui, $template);
        try {
            $number = $numberService->allocateNumber($docType, [
                'registry_scope' => $registryScope,
            ]);
        } catch (\Throwable $exception) {
            $numberWarning = 'Contractul a fost creat fara numar de registru pentru doc_type "' . $docType . '".';
            Logger::logWarning('document_number_allocate_failed', [
                'doc_type' => $docType,
                'registry_scope' => $registryScope,
                'error' => $exception->getMessage(),
            ]);
        }
        $metadataJson = json_encode([
            'doc_kind' => $docKind !== '' ? $docKind : ($docType === 'contract' ? 'contract' : 'document'),
        ], JSON_UNESCAPED_UNICODE);

        Database::execute(
            'INSERT INTO contracts (
                template_id,
                partner_cui,
                supplier_cui,
                client_cui,
                title,
                doc_type,
                contract_date,
                doc_no,
                doc_series,
                doc_full_no,
                doc_assigned_at,
                required_onboarding,
                status,
                metadata_json,
                created_by_user_id,
                created_at
            ) VALUES (
                :template_id,
                :partner_cui,
                :supplier_cui,
                :client_cui,
                :title,
                :doc_type,
                :contract_date,
                :doc_no,
                :doc_series,
                :doc_full_no,
                :doc_assigned_at,
                :required_onboarding,
                :status,
                :metadata_json,
                :user_id,
                :created_at
            )',
            [
                'template_id' => $templateId ?: null,
                'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
                'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                'client_cui' => $clientCui !== '' ? $clientCui : null,
                'title' => $title,
                'doc_type' => $docType,
                'contract_date' => $contractDate,
                'doc_no' => isset($number['no']) ? (int) $number['no'] : null,
                'doc_series' => isset($number['series']) && $number['series'] !== '' ? (string) $number['series'] : null,
                'doc_full_no' => isset($number['full_no']) && $number['full_no'] !== '' ? (string) $number['full_no'] : null,
                'doc_assigned_at' => isset($number['no']) ? date('Y-m-d H:i:s') : null,
                'required_onboarding' => 0,
                'status' => 'generated',
                'metadata_json' => $metadataJson !== false ? $metadataJson : null,
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $contractId = (int) Database::lastInsertId();
        if ($contractId > 0 && isset($number['no'])) {
            Audit::record('contract.number_assigned', 'contract', $contractId, [
                'doc_type' => $docType,
                'registry_scope' => $registryScope,
                'doc_full_no' => (string) ($number['full_no'] ?? ''),
                'rows_count' => 1,
            ]);
        }
        $pdfPath = (new ContractPdfService())->generatePdfForContract($contractId);
        Audit::record('contract.generated', 'contract', $contractId ?: null, []);

        if ($pdfPath === '' && $numberWarning !== null) {
            Session::flash('status', $numberWarning . ' PDF indisponibil momentan (verifica wkhtmltopdf).');
        } elseif ($pdfPath === '') {
            Session::flash('status', 'Contract generat. PDF indisponibil momentan (verifica wkhtmltopdf).');
        } elseif ($numberWarning !== null) {
            Session::flash('status', 'Contract generat. ' . $numberWarning);
        } else {
            Session::flash('status', 'Contract generat.');
        }
        Response::redirect('/admin/contracts');
    }

    public function uploadSigned(): void
    {
        $this->requireGenerateRole();

        $id = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contracts');
        }

        $path = $this->storeUpload($_FILES['file'] ?? null, 'contracts/signed');
        if ($path === null) {
            Session::flash('error', 'Fisier invalid.');
            Response::redirect('/admin/contracts');
        }

        Database::execute(
            'UPDATE contracts
             SET signed_file_path = :path,
                 signed_upload_path = :path,
                 status = :status,
                 updated_at = :now
             WHERE id = :id',
            [
                'path' => $path,
                'status' => 'signed_uploaded',
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
        Audit::record('contract.signed_uploaded', 'contract', $id, []);

        Session::flash('status', 'Contract semnat incarcat.');
        Response::redirect('/admin/contracts');
    }

    public function approve(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contracts');
        }

        Database::execute(
            'UPDATE contracts SET status = :status, updated_at = :now WHERE id = :id',
            [
                'status' => 'approved',
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
        Audit::record('contract.approved', 'contract', $id, []);

        Session::flash('status', 'Contract aprobat.');
        Response::redirect('/admin/contracts');
    }

    public function download(): void
    {
        $user = $this->requireContractsRole();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $kind = strtolower(trim((string) ($_GET['kind'] ?? 'auto')));
        if (!in_array($kind, ['auto', 'generated', 'signed'], true)) {
            $kind = 'auto';
        }
        if (!$id) {
            Response::redirect('/admin/contracts');
        }

        $row = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row) {
            Response::abort(404, 'Contract inexistent.');
        }

        if ($user->isSupplierUser()) {
            UserSupplierAccess::ensureTable();
            $suppliers = UserSupplierAccess::suppliersForUser($user->id);
            if (!in_array((string) ($row['supplier_cui'] ?? ''), $suppliers, true)
                && !in_array((string) ($row['partner_cui'] ?? ''), $suppliers, true)) {
                Response::abort(403, 'Acces interzis.');
            }
        }

        $path = '';
        if ($kind === 'signed') {
            $path = (string) ($row['signed_upload_path'] ?? '');
            if ($path === '') {
                $path = (string) ($row['signed_file_path'] ?? '');
            }
            if ($path === '') {
                Response::abort(404, 'Contractul semnat nu este disponibil.');
            }
        } elseif ($kind === 'generated') {
            $path = (string) ($row['generated_pdf_path'] ?? '');
            if ($path === '') {
                $path = (new ContractPdfService())->generatePdfForContract((int) ($row['id'] ?? 0));
                if ($path === '') {
                    $refreshed = Database::fetchOne(
                        'SELECT generated_file_path FROM contracts WHERE id = :id LIMIT 1',
                        ['id' => (int) ($row['id'] ?? 0)]
                    );
                    $path = trim((string) ($refreshed['generated_file_path'] ?? ($row['generated_file_path'] ?? '')));
                }
            }
        } else {
            $path = (string) ($row['signed_upload_path'] ?? '');
            if ($path === '') {
                $path = (string) ($row['signed_file_path'] ?? '');
            }
            if ($path === '') {
                $path = (string) ($row['generated_pdf_path'] ?? '');
            }
            if ($path === '') {
                $path = (new ContractPdfService())->generatePdfForContract((int) ($row['id'] ?? 0));
                if ($path === '') {
                    $refreshed = Database::fetchOne(
                        'SELECT generated_file_path FROM contracts WHERE id = :id LIMIT 1',
                        ['id' => (int) ($row['id'] ?? 0)]
                    );
                    $path = trim((string) ($refreshed['generated_file_path'] ?? ($row['generated_file_path'] ?? '')));
                }
            }
        }
        if ($path === '') {
            Response::abort(503, 'Documentul generat este indisponibil momentan. Verifica configurarea wkhtmltopdf/dompdf.');
        }
        $this->streamFile($path);
    }

    private function requireContractsRole(): \App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }
        if ($user->isPlatformUser() || $user->hasRole('operator') || $user->isSupplierUser()) {
            return $user;
        }

        Response::abort(403, 'Acces interzis.');
    }

    private function requireGenerateRole(): \App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || !($user->isPlatformUser() || $user->hasRole('operator'))) {
            Response::abort(403, 'Acces interzis.');
        }
        return $user;
    }

    private function storeUpload(?array $file, string $subdir): ?string
    {
        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!is_uploaded_file($file['tmp_name'] ?? '')) {
            return null;
        }
        if (isset($file['size']) && (int) $file['size'] > self::MAX_UPLOAD_BYTES) {
            return null;
        }
        $tmp = $file['tmp_name'];
        if (!is_readable($tmp)) {
            return null;
        }
        $extRaw = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/i', '', $extRaw);
        if ($ext === '' || !in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return null;
        }
        $ext = '.' . $ext;
        $name = bin2hex(random_bytes(16)) . $ext;
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $base . '/storage/uploads/' . trim($subdir, '/');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $target = $dir . '/' . $name;
        if (!move_uploaded_file($tmp, $target)) {
            return null;
        }

        return 'storage/uploads/' . trim($subdir, '/') . '/' . $name;
    }

    private function streamFile(string $relativePath): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $clean = ltrim($relativePath, '/');
        if (!str_starts_with($clean, 'storage/uploads/')) {
            Response::abort(404);
        }
        $sub = substr($clean, strlen('storage/uploads/'));
        $path = realpath($base . '/storage/uploads/' . ltrim($sub, '/'));
        $root = realpath($base . '/storage/uploads');
        if (!$path || !$root || !str_starts_with($path, $root) || !is_readable($path)) {
            Response::abort(404);
        }
        $filename = basename($path);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function resolveTemplateDocType(?array $template, string $docKind): string
    {
        if (!is_array($template) || empty($template)) {
            return 'contract';
        }
        if ($docKind === 'contract') {
            return 'contract';
        }
        $rawDocType = trim((string) ($template['doc_type'] ?? $template['template_type'] ?? $template['doc_kind'] ?? ''));
        if ($rawDocType === '') {
            return 'document';
        }
        $sanitized = preg_replace('/[^a-zA-Z0-9_.-]/', '', $rawDocType);
        $sanitized = strtolower(trim((string) $sanitized));

        return $sanitized !== '' ? $sanitized : 'document';
    }

    private function findExistingPrimaryContract(string $partnerCui, string $supplierCui, string $clientCui): ?array
    {
        $scope = $this->resolvePrimaryCompanyScope($partnerCui, $supplierCui, $clientCui);
        if ($scope['mode'] === 'none') {
            return null;
        }

        $joinTemplate = Database::tableExists('contract_templates');
        $sql = 'SELECT c.id, c.title, c.doc_full_no, c.doc_no, c.doc_series, c.contract_date
                FROM contracts c';
        if ($joinTemplate) {
            $sql .= ' LEFT JOIN contract_templates t ON t.id = c.template_id';
        }
        $sql .= ' WHERE (' . $this->primaryContractConditionSql($joinTemplate) . ')';

        $params = [
            'contract_doc_type' => 'contract',
        ];
        if ($joinTemplate) {
            $params['contract_doc_kind'] = 'contract';
        }

        if ($scope['mode'] === 'partner') {
            $sql .= ' AND (c.partner_cui = :company OR c.client_cui = :company OR c.supplier_cui = :company)';
            $params['company'] = $scope['company_cui'];
        } elseif ($scope['mode'] === 'relation') {
            $sql .= ' AND c.supplier_cui = :supplier AND c.client_cui = :client';
            $params['supplier'] = $scope['supplier_cui'];
            $params['client'] = $scope['client_cui'];
        }

        $sql .= ' ORDER BY c.created_at DESC, c.id DESC LIMIT 1';

        return Database::fetchOne($sql, $params);
    }

    private function resolvePrimaryCompanyScope(string $partnerCui, string $supplierCui, string $clientCui): array
    {
        $partnerCui = preg_replace('/\D+/', '', $partnerCui);
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        $clientCui = preg_replace('/\D+/', '', $clientCui);

        if ($partnerCui !== '') {
            return [
                'mode' => 'partner',
                'company_cui' => $partnerCui,
            ];
        }
        if ($clientCui !== '' && $supplierCui !== '') {
            return [
                'mode' => 'relation',
                'supplier_cui' => $supplierCui,
                'client_cui' => $clientCui,
            ];
        }
        if ($clientCui !== '') {
            return [
                'mode' => 'partner',
                'company_cui' => $clientCui,
            ];
        }
        if ($supplierCui !== '') {
            return [
                'mode' => 'partner',
                'company_cui' => $supplierCui,
            ];
        }

        return ['mode' => 'none'];
    }

    private function primaryContractConditionSql(bool $joinTemplate): string
    {
        if ($joinTemplate) {
            return 'c.doc_type = :contract_doc_type OR t.doc_kind = :contract_doc_kind';
        }

        return 'c.doc_type = :contract_doc_type';
    }

    private function formatContractNumber(array $contract): string
    {
        $full = trim((string) ($contract['doc_full_no'] ?? ''));
        if ($full !== '') {
            return $full;
        }

        $docNo = (int) ($contract['doc_no'] ?? 0);
        if ($docNo <= 0) {
            return '';
        }
        $series = trim((string) ($contract['doc_series'] ?? ''));
        $padded = str_pad((string) $docNo, 6, '0', STR_PAD_LEFT);

        return $series !== '' ? ($series . '-' . $padded) : $padded;
    }

    private function resolveRegistryScope(string $supplierCui, string $clientCui, ?array $template): string
    {
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        $clientCui = preg_replace('/\D+/', '', $clientCui);

        if ($clientCui !== '') {
            return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
        }
        if ($supplierCui !== '') {
            return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }
        $appliesTo = strtolower(trim((string) ($template['applies_to'] ?? '')));
        if ($appliesTo === 'supplier') {
            return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }

        return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
    }

    private function resolveCompanyNamesByCuis(array $contracts): array
    {
        $cuiSet = [];
        foreach ($contracts as $contract) {
            foreach (['supplier_cui', 'client_cui', 'partner_cui'] as $column) {
                $cui = preg_replace('/\D+/', '', (string) ($contract[$column] ?? ''));
                if ($cui !== '') {
                    $cuiSet[$cui] = true;
                }
            }
        }

        if (empty($cuiSet)) {
            return [];
        }

        return $this->fetchCompanyNamesByCuis(array_keys($cuiSet));
    }

    private function fetchCompanyNamesByCuis(array $cuis): array
    {
        $normalized = [];
        foreach ($cuis as $cui) {
            $value = preg_replace('/\D+/', '', (string) $cui);
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }
        $cuis = array_keys($normalized);
        if (empty($cuis)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach (array_values($cuis) as $index => $cui) {
            $key = 'c' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }
        $inSql = implode(',', $placeholders);

        $result = [];
        if (Database::tableExists('partners')) {
            $partnerRows = Database::fetchAll(
                'SELECT cui, denumire
                 FROM partners
                 WHERE cui IN (' . $inSql . ')',
                $params
            );
            foreach ($partnerRows as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
                $name = trim((string) ($row['denumire'] ?? ''));
                if ($cui !== '' && $name !== '') {
                    $result[$cui] = $name;
                }
            }
        }

        if (Database::tableExists('companies')) {
            $companyRows = Database::fetchAll(
                'SELECT cui, denumire
                 FROM companies
                 WHERE cui IN (' . $inSql . ')',
                $params
            );
            foreach ($companyRows as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
                $name = trim((string) ($row['denumire'] ?? ''));
                if ($cui !== '' && $name !== '' && !isset($result[$cui])) {
                    $result[$cui] = $name;
                }
            }
        }

        return $result;
    }
}
