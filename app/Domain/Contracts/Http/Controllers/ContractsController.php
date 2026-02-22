<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Companies\Models\Company;
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
        $legacyPartnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $contractDate = trim((string) ($_POST['contract_date'] ?? ''));

        if ($title === '') {
            Session::flash('error', 'Completeaza titlul contractului.');
            Response::redirect('/admin/contracts');
        }
        if ($supplierCui === '' && $legacyPartnerCui !== '') {
            // Backward compatibility for older payloads that only sent partner_cui.
            $supplierCui = $legacyPartnerCui;
        }
        if ($supplierCui === '') {
            Session::flash('error', 'Completeaza CUI furnizor.');
            Response::redirect('/admin/contracts');
        }
        if ($clientCui !== '' && $clientCui === $supplierCui) {
            Session::flash('error', 'Clientul trebuie sa fie diferit de furnizor.');
            Response::redirect('/admin/contracts');
        }
        $partnerCui = $clientCui !== '' ? $clientCui : $supplierCui;
        $scopePartnerCui = $clientCui !== '' ? '' : $partnerCui;

        $template = $templateId > 0 ? Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $templateId]) : null;
        $docKind = strtolower(trim((string) ($template['doc_kind'] ?? '')));
        $docType = $this->resolveTemplateDocType($template, $docKind);
        if ($contractDate === '') {
            $contractDate = date('Y-m-d');
        }
        if ($docType === 'contract') {
            $existingPrimary = $this->findExistingPrimaryContract($scopePartnerCui, $supplierCui, $clientCui);
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
        $pdfValidationError = $this->validatePdfGenerationPrerequisites($partnerCui, $supplierCui, $clientCui);
        if ($pdfValidationError !== null) {
            Session::flash('error', $pdfValidationError);
            Response::redirect('/admin/contracts');
        }
        $numberService = new DocumentNumberService();
        $number = null;
        $numberWarning = null;
        $registryScope = $this->resolveRegistryScope($partnerCui, $supplierCui, $clientCui, $template);
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

    public function uploadSignedCompanies(): void
    {
        $this->requireGenerateRole();

        $contracts = $this->contractsForSignedUpload();
        if (empty($contracts)) {
            $this->json([
                'success' => true,
                'items' => [],
            ]);
        }

        $companyCounts = [];
        foreach ($contracts as $contract) {
            $seenForContract = [];
            foreach (['supplier_cui', 'client_cui', 'partner_cui'] as $column) {
                $cui = preg_replace('/\D+/', '', (string) ($contract[$column] ?? ''));
                if ($cui === '' || isset($seenForContract[$cui])) {
                    continue;
                }
                $seenForContract[$cui] = true;
                $companyCounts[$cui] = (int) ($companyCounts[$cui] ?? 0) + 1;
            }
        }

        if (empty($companyCounts)) {
            $this->json([
                'success' => true,
                'items' => [],
            ]);
        }

        $cuis = array_keys($companyCounts);
        $namesByCui = $this->fetchCompanyNamesByCuis($cuis);
        $items = [];
        foreach ($cuis as $cui) {
            $items[] = [
                'cui' => $cui,
                'name' => trim((string) ($namesByCui[$cui] ?? $cui)),
                'contracts_count' => (int) ($companyCounts[$cui] ?? 0),
            ];
        }
        usort($items, static function (array $left, array $right): int {
            $leftName = strtolower((string) ($left['name'] ?? ''));
            $rightName = strtolower((string) ($right['name'] ?? ''));
            if ($leftName === $rightName) {
                return strcmp((string) ($left['cui'] ?? ''), (string) ($right['cui'] ?? ''));
            }

            return strcmp($leftName, $rightName);
        });

        $this->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function uploadSignedContracts(): void
    {
        $this->requireGenerateRole();

        $companyCui = preg_replace('/\D+/', '', (string) ($_GET['company_cui'] ?? ''));
        if ($companyCui === '') {
            $this->json([
                'success' => false,
                'message' => 'Selectati firma pentru filtrarea documentelor.',
                'items' => [],
            ]);
        }

        $contracts = $this->contractsForSignedUpload($companyCui);
        $namesByCui = $this->resolveCompanyNamesByCuis($contracts);
        $items = [];
        foreach ($contracts as $contract) {
            $id = (int) ($contract['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $items[] = [
                'id' => $id,
                'label' => $this->buildUploadSignedContractLabel($contract, $namesByCui),
            ];
        }

        $this->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function companySearch(): void
    {
        $this->requireGenerateRole();

        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(30, max(1, (int) ($_GET['limit'] ?? 15)));
        $role = $this->normalizeCompanySearchRole((string) ($_GET['role'] ?? 'all'));
        $items = $this->searchCompanies($term, $limit, $role);

        $this->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function approve(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contracts');
        }

        $contract = Database::fetchOne(
            'SELECT id, status FROM contracts WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$contract) {
            Session::flash('error', 'Contract inexistent.');
            Response::redirect('/admin/contracts');
        }

        $currentStatus = strtolower(trim((string) ($contract['status'] ?? '')));
        if ($currentStatus !== 'signed_uploaded') {
            Session::flash('error', 'Contractul poate fi aprobat doar din statusul "Semnat (incarcat)".');
            Response::redirect('/admin/contracts');
        }

        Database::execute(
            'UPDATE contracts SET status = :status, updated_at = :now WHERE id = :id AND status = :current_status',
            [
                'status' => 'approved',
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
                'current_status' => 'signed_uploaded',
            ]
        );
        Audit::record('contract.approved', 'contract', $id, []);

        Session::flash('status', 'Contract aprobat.');
        Response::redirect('/admin/contracts');
    }

    public function resetGeneratedPdf(): void
    {
        $this->requireGenerateRole();

        $id = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/contracts');
        }

        $contract = Database::fetchOne(
            'SELECT id, generated_pdf_path, generated_file_path
             FROM contracts
             WHERE id = :id
             LIMIT 1',
            ['id' => $id]
        );
        if (!$contract) {
            Session::flash('error', 'Contract inexistent.');
            Response::redirect('/admin/contracts');
        }

        $generatedPdfPath = trim((string) ($contract['generated_pdf_path'] ?? ''));
        $generatedFilePath = trim((string) ($contract['generated_file_path'] ?? ''));
        if ($generatedPdfPath !== '') {
            $this->deleteUploadFile($generatedPdfPath);
        }
        if ($generatedFilePath !== '' && $generatedFilePath !== $generatedPdfPath) {
            $this->deleteUploadFile($generatedFilePath);
        }

        $setParts = [];
        $params = ['id' => $id];
        if (Database::columnExists('contracts', 'generated_pdf_path')) {
            $setParts[] = 'generated_pdf_path = NULL';
        }
        if (Database::columnExists('contracts', 'generated_file_path')) {
            $setParts[] = 'generated_file_path = NULL';
        }
        if (Database::columnExists('contracts', 'updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
            $params['updated_at'] = date('Y-m-d H:i:s');
        }
        if (!empty($setParts)) {
            Database::execute(
                'UPDATE contracts
                 SET ' . implode(', ', $setParts) . '
                 WHERE id = :id',
                $params
            );
        }

        Audit::record('contract.generated_pdf_reset', 'contract', $id, ['rows_count' => 1]);
        Session::flash('status', 'PDF-ul nesemnat a fost resetat. Se va regenera la urmatoarea descarcare.');
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
                $pdfValidationError = $this->validatePdfGenerationPrerequisitesFromContractRow($row);
                if ($pdfValidationError !== null) {
                    Response::abort(422, $pdfValidationError);
                }
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
                $pdfValidationError = $this->validatePdfGenerationPrerequisitesFromContractRow($row);
                if ($pdfValidationError !== null) {
                    Response::abort(422, $pdfValidationError);
                }
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

    private function deleteUploadFile(string $relativePath): void
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $clean = ltrim($relativePath, '/');
        if ($clean === '' || !str_starts_with($clean, 'storage/uploads/')) {
            return;
        }
        $path = realpath($base . '/' . $clean);
        $root = realpath($base . '/storage/uploads');
        if (!$path || !$root || !str_starts_with($path, $root) || !is_file($path)) {
            return;
        }
        @unlink($path);
    }

    private function contractsForSignedUpload(?string $companyCui = null): array
    {
        if (!Database::tableExists('contracts')) {
            return [];
        }

        $sql = 'SELECT id,
                       title,
                       doc_no,
                       doc_series,
                       doc_full_no,
                       contract_date,
                       status,
                       supplier_cui,
                       client_cui,
                       partner_cui,
                       created_at
                FROM contracts';
        $params = [];
        $companyCui = $companyCui !== null ? preg_replace('/\D+/', '', (string) $companyCui) : null;
        if ($companyCui !== null && $companyCui !== '') {
            $sql .= ' WHERE supplier_cui = :company_cui
                          OR client_cui = :company_cui
                          OR partner_cui = :company_cui';
            $params['company_cui'] = $companyCui;
        }
        $sql .= ' ORDER BY created_at DESC, id DESC';

        return Database::fetchAll($sql, $params);
    }

    private function buildUploadSignedContractLabel(array $contract, array $companyNamesByCui): string
    {
        $title = trim((string) ($contract['title'] ?? ''));
        if ($title === '') {
            $title = 'Document #' . (int) ($contract['id'] ?? 0);
        }

        $parts = [$title];
        $docNo = $this->formatContractNumber($contract);
        if ($docNo !== '') {
            $parts[] = '[' . $docNo . ']';
        }

        $dateRaw = trim((string) ($contract['contract_date'] ?? ''));
        if ($dateRaw !== '') {
            $timestamp = strtotime($dateRaw);
            $dateDisplay = $timestamp !== false ? date('d.m.Y', $timestamp) : $dateRaw;
            $parts[] = $dateDisplay;
        }

        $relation = $this->uploadSignedContractRelationLabel($contract, $companyNamesByCui);
        if ($relation !== '') {
            $parts[] = $relation;
        }

        return implode(' - ', $parts);
    }

    private function uploadSignedContractRelationLabel(array $contract, array $companyNamesByCui): string
    {
        $supplierCui = preg_replace('/\D+/', '', (string) ($contract['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($contract['client_cui'] ?? ''));
        $partnerCui = preg_replace('/\D+/', '', (string) ($contract['partner_cui'] ?? ''));

        $supplierName = $supplierCui !== '' ? trim((string) ($companyNamesByCui[$supplierCui] ?? $supplierCui)) : '';
        $clientName = $clientCui !== '' ? trim((string) ($companyNamesByCui[$clientCui] ?? $clientCui)) : '';
        $partnerName = $partnerCui !== '' ? trim((string) ($companyNamesByCui[$partnerCui] ?? $partnerCui)) : '';

        if ($supplierName !== '' && $clientName !== '') {
            return $supplierName . ' -> ' . $clientName;
        }
        if ($partnerName !== '') {
            return $partnerName;
        }
        if ($supplierName !== '') {
            return 'Furnizor: ' . $supplierName;
        }
        if ($clientName !== '') {
            return 'Client: ' . $clientName;
        }

        return '';
    }

    private function searchCompanies(string $term, int $limit, string $role = 'all'): array
    {
        $term = trim($term);
        $termDigits = preg_replace('/\D+/', '', $term);
        $role = $this->normalizeCompanySearchRole($role);
        $itemsByCui = [];

        $appendItem = static function (array &$bucket, array $item): void {
            $cui = preg_replace('/\D+/', '', (string) ($item['cui'] ?? ''));
            if ($cui === '') {
                return;
            }
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                $name = $cui;
            }
            if (!isset($bucket[$cui])) {
                $bucket[$cui] = [
                    'cui' => $cui,
                    'name' => $name,
                    'label' => $name !== $cui ? ($name . ' - ' . $cui) : $cui,
                ];
                return;
            }
            if ($bucket[$cui]['name'] === $cui && $name !== '') {
                $bucket[$cui]['name'] = $name;
                $bucket[$cui]['label'] = $name !== $cui ? ($name . ' - ' . $cui) : $cui;
            }
        };

        if (Database::tableExists('partners')) {
            $where = [];
            $params = [];
            if ($term !== '') {
                $parts = ['denumire LIKE :name_term'];
                $params['name_term'] = '%' . $term . '%';
                if ($termDigits !== '') {
                    $parts[] = 'cui LIKE :cui_term';
                    $params['cui_term'] = '%' . $termDigits . '%';
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $rows = Database::fetchAll(
                'SELECT cui, denumire
                 FROM partners
                 ' . $whereSql . '
                 ORDER BY denumire ASC, cui ASC
                 LIMIT ' . (int) max($limit * 3, $limit),
                $params
            );
            foreach ($rows as $row) {
                $appendItem($itemsByCui, [
                    'cui' => (string) ($row['cui'] ?? ''),
                    'name' => (string) ($row['denumire'] ?? ''),
                ]);
            }
        }

        if (Database::tableExists('companies')) {
            $where = [];
            $params = [];
            if ($term !== '') {
                $parts = ['denumire LIKE :name_term'];
                $params['name_term'] = '%' . $term . '%';
                if ($termDigits !== '') {
                    $parts[] = 'cui LIKE :cui_term';
                    $params['cui_term'] = '%' . $termDigits . '%';
                }
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
            $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $rows = Database::fetchAll(
                'SELECT cui, denumire
                 FROM companies
                 ' . $whereSql . '
                 ORDER BY denumire ASC, cui ASC
                 LIMIT ' . (int) max($limit * 3, $limit),
                $params
            );
            foreach ($rows as $row) {
                $appendItem($itemsByCui, [
                    'cui' => (string) ($row['cui'] ?? ''),
                    'name' => (string) ($row['denumire'] ?? ''),
                ]);
            }
        }

        if ($termDigits !== '' && !isset($itemsByCui[$termDigits])) {
            $appendItem($itemsByCui, [
                'cui' => $termDigits,
                'name' => $termDigits,
            ]);
        }

        $items = array_values($itemsByCui);
        $termLower = strtolower($term);
        usort($items, static function (array $left, array $right) use ($termDigits, $termLower): int {
            $score = static function (array $item) use ($termDigits, $termLower): int {
                $cui = (string) ($item['cui'] ?? '');
                $name = strtolower((string) ($item['name'] ?? ''));
                $rank = 100;

                if ($termDigits !== '') {
                    if (str_starts_with($cui, $termDigits)) {
                        $rank = min($rank, 0);
                    } elseif (strpos($cui, $termDigits) !== false) {
                        $rank = min($rank, 10);
                    }
                }

                if ($termLower !== '') {
                    if ($name !== '' && str_starts_with($name, $termLower)) {
                        $rank = min($rank, 1);
                    } elseif ($name !== '' && strpos($name, $termLower) !== false) {
                        $rank = min($rank, 20);
                    }
                }

                return $rank;
            };

            $scoreLeft = $score($left);
            $scoreRight = $score($right);
            if ($scoreLeft !== $scoreRight) {
                return $scoreLeft <=> $scoreRight;
            }

            $nameCompare = strcmp(
                strtolower((string) ($left['name'] ?? '')),
                strtolower((string) ($right['name'] ?? ''))
            );
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return strcmp((string) ($left['cui'] ?? ''), (string) ($right['cui'] ?? ''));
        });

        if ($role !== 'all') {
            $items = array_values(array_filter($items, function (array $item) use ($role): bool {
                $cui = preg_replace('/\D+/', '', (string) ($item['cui'] ?? ''));
                if ($cui === '') {
                    return false;
                }

                return $this->companyMatchesSearchRole($cui, $role);
            }));
        }

        if (count($items) > $limit) {
            $items = array_slice($items, 0, $limit);
        }

        return $items;
    }

    private function normalizeCompanySearchRole(string $role): string
    {
        $role = strtolower(trim($role));
        if (!in_array($role, ['all', 'supplier', 'client'], true)) {
            return 'all';
        }

        return $role;
    }

    private function companyMatchesSearchRole(string $cui, string $role): bool
    {
        $role = $this->normalizeCompanySearchRole($role);
        if ($role === 'all') {
            return true;
        }

        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '') {
            return false;
        }

        static $scopeCache = [];
        if (!array_key_exists($cui, $scopeCache)) {
            $scopeCache[$cui] = $this->partnerRegistryScopeHint($cui);
        }
        $scope = $scopeCache[$cui];
        if ($scope === null) {
            return true;
        }

        if ($role === 'supplier') {
            return $scope === DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }

        return $scope === DocumentNumberService::REGISTRY_SCOPE_CLIENT;
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

    private function resolveRegistryScope(string $partnerCui, string $supplierCui, string $clientCui, ?array $template): string
    {
        $partnerCui = preg_replace('/\D+/', '', $partnerCui);
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        $clientCui = preg_replace('/\D+/', '', $clientCui);
        $appliesTo = strtolower(trim((string) ($template['applies_to'] ?? '')));

        if ($appliesTo === 'supplier') {
            return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }
        if ($appliesTo === 'client') {
            return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
        }

        if ($clientCui !== '' && $supplierCui === '') {
            return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
        }
        if ($supplierCui !== '' && $clientCui === '') {
            return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }

        if ($clientCui !== '' && $supplierCui !== '') {
            return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
        }

        $partnerScopeHint = $this->partnerRegistryScopeHint($partnerCui);
        if ($partnerScopeHint !== null) {
            return $partnerScopeHint;
        }

        if ($supplierCui !== '') {
            return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
        }
        if ($clientCui !== '') {
            return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
        }

        return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
    }

    private function partnerRegistryScopeHint(string $partnerCui): ?string
    {
        $partnerCui = preg_replace('/\D+/', '', $partnerCui);
        if ($partnerCui === '') {
            return null;
        }

        if (
            Database::tableExists('partners')
            && Database::columnExists('partners', 'is_supplier')
            && Database::columnExists('partners', 'is_client')
        ) {
            $partnerRow = Database::fetchOne(
                'SELECT is_supplier, is_client FROM partners WHERE cui = :cui LIMIT 1',
                ['cui' => $partnerCui]
            );
            if ($partnerRow) {
                $isSupplier = !empty($partnerRow['is_supplier']);
                $isClient = !empty($partnerRow['is_client']);
                if ($isSupplier && !$isClient) {
                    return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
                }
                if ($isClient && !$isSupplier) {
                    return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
                }
                if ($isSupplier && $isClient) {
                    // Prefer supplier scope for mixed-role partners when no explicit relation is set.
                    return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
                }
            }
        }

        if (Database::tableExists('companies') && Database::columnExists('companies', 'tip_companie')) {
            $companyRow = Database::fetchOne(
                'SELECT tip_companie FROM companies WHERE cui = :cui LIMIT 1',
                ['cui' => $partnerCui]
            );
            $companyType = strtolower(trim((string) ($companyRow['tip_companie'] ?? '')));
            if (in_array($companyType, ['furnizor', 'supplier'], true)) {
                return DocumentNumberService::REGISTRY_SCOPE_SUPPLIER;
            }
            if ($companyType === 'client') {
                return DocumentNumberService::REGISTRY_SCOPE_CLIENT;
            }
        }

        return null;
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

    private function json(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function validatePdfGenerationPrerequisitesFromContractRow(array $row): ?string
    {
        return $this->validatePdfGenerationPrerequisites(
            (string) ($row['partner_cui'] ?? ''),
            (string) ($row['supplier_cui'] ?? ''),
            (string) ($row['client_cui'] ?? '')
        );
    }

    private function validatePdfGenerationPrerequisites(string $partnerCui, string $supplierCui, string $clientCui): ?string
    {
        $companyCuis = $this->contractCompanyCuis($partnerCui, $supplierCui, $clientCui);
        if (empty($companyCuis)) {
            return 'Nu poti genera PDF fara sa selectezi cel putin o firma (CUI).';
        }

        $missing = [];
        foreach ($companyCuis as $cui) {
            if (!$this->hasMandatoryCompanyProfile($cui)) {
                $missing[] = $cui;
            }
        }

        if (!empty($missing)) {
            return 'Nu poti genera PDF pana nu completezi reprezentantul legal, functia, banca si IBAN-ul pentru firmele: '
                . implode(', ', $missing)
                . '.';
        }

        return null;
    }

    private function contractCompanyCuis(string $partnerCui, string $supplierCui, string $clientCui): array
    {
        $result = [];
        foreach ([$partnerCui, $supplierCui, $clientCui] as $cui) {
            $normalized = preg_replace('/\D+/', '', (string) $cui);
            if ($normalized === '') {
                continue;
            }
            $result[$normalized] = true;
        }

        return array_keys($result);
    }

    private function hasMandatoryCompanyProfile(string $cui): bool
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '' || !Database::tableExists('companies')) {
            return false;
        }

        $company = Company::findByCui($cui);
        if (!$company) {
            return false;
        }

        $legalRepresentativeName = $this->sanitizeCompanyValue((string) ($company->legal_representative_name !== '' ? $company->legal_representative_name : ($company->representative_name ?? '')));
        $legalRepresentativeRole = $this->sanitizeCompanyValue((string) ($company->legal_representative_role !== '' ? $company->legal_representative_role : ($company->representative_function ?? '')));
        $bankName = $this->sanitizeCompanyValue((string) ($company->bank_name ?? $company->banca ?? ''));
        $iban = $this->sanitizeIban((string) ($company->iban ?? $company->bank_account ?? ''));

        return $legalRepresentativeName !== ''
            && $legalRepresentativeRole !== ''
            && $bankName !== ''
            && $this->isValidIban($iban);
    }

    private function sanitizeCompanyValue(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function sanitizeIban(string $value): string
    {
        $value = strtoupper(trim((string) $value));
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^A-Z0-9]/', '', (string) $value);

        return (string) $value;
    }

    private function isValidIban(string $iban): bool
    {
        $iban = $this->sanitizeIban($iban);
        $length = strlen($iban);

        return $length >= 15 && $length <= 34;
    }
}
