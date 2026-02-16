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
        $this->ensureRowsForKnownDocTypes();

        $rows = [];
        if (Database::tableExists('document_registry')) {
            $rows = Database::fetchAll('SELECT * FROM document_registry ORDER BY doc_type ASC');
        }
        $filters = [
            'doc_type' => $this->sanitizeDocType((string) ($_GET['doc_type'] ?? '')),
        ];
        $docTypeOptions = array_values(array_map(
            static fn (array $row): string => (string) ($row['doc_type'] ?? ''),
            $rows
        ));
        $documents = $this->loadDocuments($filters['doc_type']);

        Response::view('admin/contracts/document_registry', [
            'rows' => $rows,
            'filters' => $filters,
            'docTypeOptions' => $docTypeOptions,
            'documents' => $documents,
        ]);
    }

    public function save(): void
    {
        Auth::requireInternalStaff();

        $docType = $this->sanitizeDocType((string) ($_POST['doc_type'] ?? ''));
        $series = $this->sanitizeSeries((string) ($_POST['series'] ?? ''));
        $startNo = max(1, (int) ($_POST['start_no'] ?? 1));
        $nextNo = max(1, (int) ($_POST['next_no'] ?? $startNo));
        if ($nextNo < $startNo) {
            $nextNo = $startNo;
        }

        if ($docType === '') {
            Session::flash('error', 'Tipul documentului este obligatoriu.');
            Response::redirect('/admin/registru-documente');
        }

        $this->numberService->ensureRegistryRow($docType, [
            'series' => $series,
            'start_no' => $startNo,
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
                'doc_type' => $docType,
            ]
        );
        Audit::record('document_registry.updated', 'document_registry', null, [
            'doc_type' => $docType,
            'series' => $series !== '' ? $series : null,
            'start_no' => $startNo,
            'next_no' => $nextNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Registrul a fost actualizat.');
        Response::redirect('/admin/registru-documente');
    }

    public function setStart(): void
    {
        Auth::requireInternalStaff();

        $docType = $this->sanitizeDocType((string) ($_POST['doc_type'] ?? ''));
        $startNo = max(1, (int) ($_POST['start_no'] ?? 1));
        $series = $this->sanitizeSeries((string) ($_POST['series'] ?? ''));

        if ($docType === '') {
            Session::flash('error', 'Tipul documentului este obligatoriu.');
            Response::redirect('/admin/registru-documente');
        }

        $this->numberService->ensureRegistryRow($docType, [
            'series' => $series,
            'start_no' => $startNo,
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
                'doc_type' => $docType,
            ]
        );
        Audit::record('document_registry.start_set', 'document_registry', null, [
            'doc_type' => $docType,
            'series' => $series !== '' ? $series : null,
            'start_no' => $startNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Start-ul registrului a fost setat.');
        Response::redirect('/admin/registru-documente');
    }

    public function resetStart(): void
    {
        Auth::requireInternalStaff();

        $docType = $this->sanitizeDocType((string) ($_POST['doc_type'] ?? ''));
        if ($docType === '') {
            Session::flash('error', 'Tipul documentului este obligatoriu.');
            Response::redirect('/admin/registru-documente');
        }

        $row = $this->numberService->ensureRegistryRow($docType);
        $startNo = max(1, (int) ($row['start_no'] ?? 1));
        Database::execute(
            'UPDATE document_registry
             SET next_no = :next_no,
                 updated_at = :updated_at
             WHERE doc_type = :doc_type',
            [
                'next_no' => $startNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $docType,
            ]
        );
        Audit::record('document_registry.reset_start', 'document_registry', null, [
            'doc_type' => $docType,
            'start_no' => $startNo,
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Registrul a fost resetat la numarul de start.');
        Response::redirect('/admin/registru-documente');
    }

    private function ensureRowsForKnownDocTypes(): void
    {
        $docTypes = [];
        if (Database::tableExists('contract_templates')) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT doc_type FROM contract_templates
                 WHERE doc_type IS NOT NULL AND doc_type <> ""
                 ORDER BY doc_type ASC'
            );
            foreach ($rows as $row) {
                $docType = $this->sanitizeDocType((string) ($row['doc_type'] ?? ''));
                if ($docType !== '') {
                    $docTypes[$docType] = true;
                }
            }
        }
        if (Database::tableExists('contracts')) {
            $rows = Database::fetchAll(
                'SELECT DISTINCT doc_type FROM contracts
                 WHERE doc_type IS NOT NULL AND doc_type <> ""
                 ORDER BY doc_type ASC'
            );
            foreach ($rows as $row) {
                $docType = $this->sanitizeDocType((string) ($row['doc_type'] ?? ''));
                if ($docType !== '') {
                    $docTypes[$docType] = true;
                }
            }
        }

        foreach (array_keys($docTypes) as $docType) {
            $this->numberService->ensureRegistryRow($docType);
        }
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

    private function loadDocuments(string $docType): array
    {
        if (!Database::tableExists('contracts')) {
            return [];
        }

        $where = [];
        $params = [];
        if ($docType !== '') {
            $where[] = 'doc_type = :doc_type';
            $params['doc_type'] = $docType;
        }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $documents = Database::fetchAll(
            'SELECT id, title, doc_type, contract_date, doc_no, doc_series, doc_full_no, status, partner_cui, supplier_cui, client_cui, created_at
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
            $companyCui = $this->resolveCompanyCui($document);
            $documents[$index]['registry_company_cui'] = $companyCui !== '' ? $companyCui : null;
            if ($companyCui !== '') {
                $cuiSet[$companyCui] = true;
            }
        }

        $companyNames = $this->fetchCompanyNames(array_keys($cuiSet));
        foreach ($documents as $index => $document) {
            $companyCui = (string) ($document['registry_company_cui'] ?? '');
            $documents[$index]['registry_company_name'] = $companyCui !== '' && isset($companyNames[$companyCui])
                ? $companyNames[$companyCui]
                : '—';
        }

        return $documents;
    }

    private function resolveCompanyCui(array $document): string
    {
        $candidates = [
            (string) ($document['partner_cui'] ?? ''),
            (string) ($document['client_cui'] ?? ''),
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
}
