<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\DocumentNumberService;
use App\Support\Auth;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class DocumentRegistryController
{
    private DocumentNumberService $numberService;

    public function __construct(?DocumentNumberService $numberService = null)
    {
        $this->numberService = $numberService ?? new DocumentNumberService();
    }

    public function index(): void
    {
        Auth::requireInternalStaff();
        $activeTab = $this->sanitizeRegistryScope((string) ($_GET['tab'] ?? DocumentNumberService::REGISTRY_SCOPE_CLIENT));
        $filters = [
            'tab' => $activeTab,
            'doc_type' => $this->sanitizeDocType((string) ($_GET['doc_type'] ?? '')),
        ];
        $docTypeOptions = $this->loadDocTypeOptions($activeTab);
        $documents = $this->loadDocuments($filters['doc_type'], $activeTab);

        Response::view('admin/contracts/document_registry', [
            'filters' => $filters,
            'activeTab' => $activeTab,
            'docTypeOptions' => $docTypeOptions,
            'documents' => $documents,
        ]);
    }

    public function save(): void
    {
        Auth::requireInternalStaff();

        $registryScope = $this->sanitizeRegistryScope((string) ($_POST['registry_scope'] ?? DocumentNumberService::REGISTRY_SCOPE_CLIENT));
        $series = $this->sanitizeSeries((string) ($_POST['series'] ?? ''));
        $startNo = max(1, (int) ($_POST['start_no'] ?? 1));
        $nextNo = max(1, (int) ($_POST['next_no'] ?? $startNo));
        if ($nextNo < $startNo) {
            $nextNo = $startNo;
        }
        $registryDocType = $this->numberService->registryKey($registryScope);

        $this->numberService->ensureRegistryRow('contract', [
            'series' => $series,
            'start_no' => $startNo,
            'registry_scope' => $registryScope,
        ]);
        Database::execute(
            'UPDATE document_registry
             SET series = :series,
                 start_no = :start_no,
                 next_no = :next_no,
                 updated_at = :updated_at
             WHERE doc_type = :doc_type',
            [
                'series' => $series !== '' ? $series : null,
                'start_no' => $startNo,
                'next_no' => $nextNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $registryDocType,
            ]
        );
        Audit::record('document_registry.updated', 'document_registry', null, [
            'registry_doc_type' => $registryDocType,
            'registry_scope' => $registryScope,
            'series' => $series !== '' ? $series : null,
            'start_no' => $startNo,
            'next_no' => $nextNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Registrul a fost actualizat.');
        Response::redirect($this->resolveRedirectPath($registryScope));
    }

    public function setStart(): void
    {
        Auth::requireInternalStaff();

        $registryScope = $this->sanitizeRegistryScope((string) ($_POST['registry_scope'] ?? DocumentNumberService::REGISTRY_SCOPE_CLIENT));
        $startNo = max(1, (int) ($_POST['start_no'] ?? 1));
        $series = $this->sanitizeSeries((string) ($_POST['series'] ?? ''));
        $registryDocType = $this->numberService->registryKey($registryScope);

        $this->numberService->ensureRegistryRow('contract', [
            'series' => $series,
            'start_no' => $startNo,
            'registry_scope' => $registryScope,
        ]);
        Database::execute(
            'UPDATE document_registry
             SET series = :series,
                 start_no = :start_no,
                 next_no = :next_no,
                 updated_at = :updated_at
             WHERE doc_type = :doc_type',
            [
                'series' => $series !== '' ? $series : null,
                'start_no' => $startNo,
                'next_no' => $startNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $registryDocType,
            ]
        );
        Audit::record('document_registry.start_set', 'document_registry', null, [
            'registry_doc_type' => $registryDocType,
            'registry_scope' => $registryScope,
            'series' => $series !== '' ? $series : null,
            'start_no' => $startNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Start-ul registrului a fost setat.');
        Response::redirect($this->resolveRedirectPath($registryScope));
    }

    public function resetStart(): void
    {
        Auth::requireInternalStaff();

        $registryScope = $this->sanitizeRegistryScope((string) ($_POST['registry_scope'] ?? DocumentNumberService::REGISTRY_SCOPE_CLIENT));
        $registryDocType = $this->numberService->registryKey($registryScope);

        $row = $this->numberService->ensureRegistryRow('contract', [
            'registry_scope' => $registryScope,
        ]);
        $startNo = max(1, (int) ($row['start_no'] ?? 1));
        Database::execute(
            'UPDATE document_registry
             SET next_no = :next_no,
                 updated_at = :updated_at
             WHERE doc_type = :doc_type',
            [
                'next_no' => $startNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $registryDocType,
            ]
        );
        Audit::record('document_registry.reset_start', 'document_registry', null, [
            'registry_doc_type' => $registryDocType,
            'registry_scope' => $registryScope,
            'start_no' => $startNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Registrul a fost resetat la numarul de start.');
        Response::redirect($this->resolveRedirectPath($registryScope));
    }

    private function loadDocTypeOptions(string $registryScope): array
    {
        if (!Database::tableExists('contracts')) {
            return [];
        }

        $scopeWhere = $this->registryScopeWhereSql($registryScope);
        $rows = Database::fetchAll(
            'SELECT DISTINCT doc_type
             FROM contracts
             WHERE doc_type IS NOT NULL
               AND doc_type <> ""
               AND ' . $scopeWhere . '
             ORDER BY doc_type ASC'
        );
        if (empty($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            $docType = $this->sanitizeDocType((string) ($row['doc_type'] ?? ''));
            if ($docType !== '') {
                $options[$docType] = true;
            }
        }

        return array_keys($options);
    }

    private function sanitizeDocType(string $docType): string
    {
        $docType = trim($docType);
        $docType = preg_replace('/[^a-zA-Z0-9_.-]/', '', $docType ?? '');

        return strtolower((string) $docType);
    }

    private function sanitizeSeries(string $series): string
    {
        $series = trim($series);
        if ($series === '') {
            return '';
        }
        $series = preg_replace('/[^a-zA-Z0-9._-]/', '', $series);
        if ($series === null) {
            return '';
        }

        return strtoupper(substr($series, 0, 16));
    }

    private function sanitizeRegistryScope(string $scope): string
    {
        $scope = strtolower(trim($scope));

        return $scope === DocumentNumberService::REGISTRY_SCOPE_SUPPLIER
            ? DocumentNumberService::REGISTRY_SCOPE_SUPPLIER
            : DocumentNumberService::REGISTRY_SCOPE_CLIENT;
    }

    private function resolveRedirectPath(string $registryScope): string
    {
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '') {
            $parsed = parse_url($returnTo);
            $path = trim((string) ($parsed['path'] ?? ''));
            if (str_starts_with($path, '/admin/')) {
                return $returnTo;
            }
        }

        return '/admin/registru-documente?tab=' . urlencode($registryScope);
    }

    private function registryScopeWhereSql(string $registryScope): string
    {
        if ($registryScope === DocumentNumberService::REGISTRY_SCOPE_SUPPLIER) {
            return '(supplier_cui IS NOT NULL AND supplier_cui <> "" AND (client_cui IS NULL OR client_cui = ""))';
        }

        return '((client_cui IS NOT NULL AND client_cui <> "") OR ((client_cui IS NULL OR client_cui = "") AND (supplier_cui IS NULL OR supplier_cui = "")))';
    }

    private function loadDocuments(string $docType, string $registryScope): array
    {
        if (!Database::tableExists('contracts')) {
            return [];
        }

        $where = [];
        $params = [];
        $where[] = $this->registryScopeWhereSql($registryScope);
        if ($docType !== '') {
            $where[] = 'doc_type = :doc_type';
            $params['doc_type'] = $docType;
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';
        $selectColumns = [
            'id',
            'title',
            'doc_type',
            'contract_date',
            'doc_no',
            'doc_series',
            'doc_full_no',
            'status',
            'partner_cui',
            'supplier_cui',
            'client_cui',
            'created_at',
        ];
        $selectColumns[] = Database::columnExists('contracts', 'signed_upload_path')
            ? 'signed_upload_path'
            : 'NULL AS signed_upload_path';
        $selectColumns[] = Database::columnExists('contracts', 'signed_file_path')
            ? 'signed_file_path'
            : 'NULL AS signed_file_path';

        $documents = Database::fetchAll(
            'SELECT ' . implode(', ', $selectColumns) . '
             FROM contracts'
            . $whereSql .
            ' ORDER BY created_at DESC, id DESC LIMIT 500',
            $params
        );
        if (empty($documents)) {
            return [];
        }

        $cuiSet = [];
        foreach ($documents as $index => $document) {
            $companyCui = $this->resolveCompanyCui($document, $registryScope);
            $documents[$index]['registry_company_cui'] = $companyCui !== '' ? $companyCui : null;
            if ($companyCui !== '') {
                $cuiSet[$companyCui] = true;
            }
        }

        $companyNames = $this->fetchCompanyNames(array_keys($cuiSet));
        $prefillNames = $this->fetchEnrollmentPrefillNamesByCuis(array_keys($cuiSet));
        foreach ($prefillNames as $cui => $name) {
            if (!isset($companyNames[$cui])) {
                $companyNames[$cui] = $name;
            }
        }
        foreach ($documents as $index => $document) {
            $companyCui = (string) ($document['registry_company_cui'] ?? '');
            if ($companyCui !== '' && isset($companyNames[$companyCui])) {
                $documents[$index]['registry_company_name'] = $companyNames[$companyCui];
            } elseif ($companyCui !== '') {
                // Better than a dash: show the company CUI when name lookup is missing.
                $documents[$index]['registry_company_name'] = $companyCui;
            } else {
                $documents[$index]['registry_company_name'] = '—';
            }
        }

        return $documents;
    }

    private function resolveCompanyCui(array $document, string $registryScope): string
    {
        $candidates = $registryScope === DocumentNumberService::REGISTRY_SCOPE_SUPPLIER
            ? [
                (string) ($document['supplier_cui'] ?? ''),
                (string) ($document['partner_cui'] ?? ''),
                (string) ($document['client_cui'] ?? ''),
            ]
            : [
                (string) ($document['client_cui'] ?? ''),
                (string) ($document['partner_cui'] ?? ''),
                (string) ($document['supplier_cui'] ?? ''),
            ];
        foreach ($candidates as $candidate) {
            $cui = preg_replace('/\D+/', '', $candidate);
            if ($cui !== '') {
                return $cui;
            }
        }

        return '';
    }

    private function fetchCompanyNames(array $cuis): array
    {
        if (empty($cuis)) {
            return [];
        }

        $names = [];
        $placeholders = [];
        $params = [];
        foreach (array_values($cuis) as $index => $cui) {
            $key = 'c' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }
        $inSql = implode(',', $placeholders);

        if (Database::tableExists('companies')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM companies WHERE cui IN (' . $inSql . ')',
                $params
            );
            foreach ($rows as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
                $name = trim((string) ($row['denumire'] ?? ''));
                if ($cui !== '' && $name !== '') {
                    $names[$cui] = $name;
                }
            }
        }

        if (Database::tableExists('partners')) {
            $rows = Database::fetchAll(
                'SELECT cui, denumire FROM partners WHERE cui IN (' . $inSql . ')',
                $params
            );
            foreach ($rows as $row) {
                $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
                $name = trim((string) ($row['denumire'] ?? ''));
                if ($cui !== '' && $name !== '' && !isset($names[$cui])) {
                    $names[$cui] = $name;
                }
            }
        }

        return $names;
    }

    private function fetchEnrollmentPrefillNamesByCuis(array $cuis): array
    {
        if (
            empty($cuis)
            || !Database::tableExists('enrollment_links')
            || !Database::columnExists('enrollment_links', 'prefill_json')
        ) {
            return [];
        }

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

        $candidateColumns = [];
        foreach (['partner_cui', 'supplier_cui', 'relation_supplier_cui', 'relation_client_cui'] as $column) {
            if (Database::columnExists('enrollment_links', $column)) {
                $candidateColumns[] = $column;
            }
        }
        if (empty($candidateColumns)) {
            return [];
        }

        $params = [];
        $whereByColumn = [];
        $paramIndex = 0;
        foreach ($candidateColumns as $column) {
            $placeholders = [];
            foreach ($cuis as $cui) {
                $paramName = 'c' . $paramIndex;
                $paramIndex++;
                $placeholders[] = ':' . $paramName;
                $params[$paramName] = $cui;
            }
            $whereByColumn[] = $column . ' IN (' . implode(',', $placeholders) . ')';
        }
        if (empty($whereByColumn)) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT prefill_json
             FROM enrollment_links
             WHERE prefill_json IS NOT NULL
               AND prefill_json <> ""
               AND (' . implode(' OR ', $whereByColumn) . ')
             ORDER BY id DESC
             LIMIT 5000',
            $params
        );
        if (empty($rows)) {
            return [];
        }

        $cuiMap = array_fill_keys($cuis, true);
        $result = [];
        foreach ($rows as $row) {
            $prefillRaw = trim((string) ($row['prefill_json'] ?? ''));
            if ($prefillRaw === '') {
                continue;
            }
            $decoded = json_decode($prefillRaw, true);
            if (!is_array($decoded)) {
                continue;
            }
            $cui = preg_replace('/\D+/', '', (string) ($decoded['cui'] ?? ''));
            $name = trim((string) ($decoded['denumire'] ?? ''));
            if ($cui === '' || $name === '' || !isset($cuiMap[$cui])) {
                continue;
            }
            if (!isset($result[$cui])) {
                $result[$cui] = $name;
            }
        }

        return $result;
    }
}
