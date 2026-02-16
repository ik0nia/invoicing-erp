<?php

namespace App\Domain\Contracts\Services;

use App\Support\Database;
use App\Support\Url;

class ContractTemplateVariables
{
    private const STAMP_UPLOAD_SUBDIR = 'contract_templates/stamps';
    private const STAMP_INLINE_MAX_BYTES = 204800;
    private const STAMP_ALLOWED_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function listPlaceholders(): array
    {
        return [
            ['key' => 'partner.name', 'label' => 'Denumirea companiei'],
            ['key' => 'partner.cui', 'label' => 'CUI companie'],
            ['key' => 'partner.email', 'label' => 'Email companie'],
            ['key' => 'partner.phone', 'label' => 'Telefon companie'],
            ['key' => 'partner.address', 'label' => 'Adresa companiei'],
            ['key' => 'partner.city', 'label' => 'Localitate'],
            ['key' => 'partner.county', 'label' => 'Judet'],
            ['key' => 'partner.country', 'label' => 'Tara'],
            ['key' => 'partner.reg_no', 'label' => 'Nr. Reg. Comertului'],
            ['key' => 'partner.bank', 'label' => 'Banca'],
            ['key' => 'partner.iban', 'label' => 'IBAN'],
            ['key' => 'partner.representative_name', 'label' => 'Reprezentant legal (nume) companie'],
            ['key' => 'partner.representative_function', 'label' => 'Functie reprezentant companie'],
            ['key' => 'partner.bank_account', 'label' => 'Cont bancar companie (IBAN/cont)'],
            ['key' => 'partner.bank_name', 'label' => 'Banca companie'],
            ['key' => 'company.legal_representative', 'label' => 'Reprezentant legal companie (contract)'],
            ['key' => 'company.legal_representative_role', 'label' => 'Functie reprezentant companie (contract)'],
            ['key' => 'company.bank', 'label' => 'Banca companie (contract)'],
            ['key' => 'company.iban', 'label' => 'IBAN companie (contract)'],
            ['key' => 'stamp.image', 'label' => 'Imagine stampila model (HTML img)'],
            ['key' => 'stamp.url', 'label' => 'URL stampila model (protejat/admin)'],
            ['key' => 'supplier.name', 'label' => 'Denumire furnizor'],
            ['key' => 'supplier.cui', 'label' => 'CUI furnizor'],
            ['key' => 'supplier.email', 'label' => 'Email furnizor'],
            ['key' => 'supplier.phone', 'label' => 'Telefon furnizor'],
            ['key' => 'supplier.representative_name', 'label' => 'Reprezentant legal furnizor'],
            ['key' => 'supplier.representative_function', 'label' => 'Functie reprezentant furnizor'],
            ['key' => 'supplier.bank_account', 'label' => 'Cont bancar furnizor (IBAN/cont)'],
            ['key' => 'supplier.bank_name', 'label' => 'Banca furnizor'],
            ['key' => 'client.name', 'label' => 'Denumire client'],
            ['key' => 'client.cui', 'label' => 'CUI client'],
            ['key' => 'client.email', 'label' => 'Email client'],
            ['key' => 'client.phone', 'label' => 'Telefon client'],
            ['key' => 'client.representative_name', 'label' => 'Reprezentant legal client'],
            ['key' => 'client.representative_function', 'label' => 'Functie reprezentant client'],
            ['key' => 'client.bank_account', 'label' => 'Cont bancar client (IBAN/cont)'],
            ['key' => 'client.bank_name', 'label' => 'Banca client'],
            ['key' => 'relation.supplier_cui', 'label' => 'Relatie - CUI furnizor'],
            ['key' => 'relation.client_cui', 'label' => 'Relatie - CUI client'],
            ['key' => 'relation.invoice_inbox_email', 'label' => 'Email inbox facturi (relatie)'],
            ['key' => 'contract.title', 'label' => 'Titlu contract'],
            ['key' => 'contract.created_at', 'label' => 'Data creare contract'],
            ['key' => 'contract.date', 'label' => 'Data contract'],
            ['key' => 'contract.doc_type', 'label' => 'Tip document (doc_type)'],
            ['key' => 'contract.no', 'label' => 'Numar document'],
            ['key' => 'contract.series', 'label' => 'Serie document'],
            ['key' => 'contract.full_no', 'label' => 'Numar complet document'],
            ['key' => 'doc.type', 'label' => 'Tip document (shortcut)'],
            ['key' => 'doc.no', 'label' => 'Numar document (shortcut)'],
            ['key' => 'doc.series', 'label' => 'Serie document (shortcut)'],
            ['key' => 'doc.full_no', 'label' => 'Numar complet document (shortcut)'],
            ['key' => 'date.today', 'label' => 'Data curenta'],
        ];
    }

    public function buildVariables(?string $partnerCui, ?string $supplierCui, ?string $clientCui, array $contractContext = []): array
    {
        $vars = [];

        $partnerCompany = $partnerCui ? $this->fetchCompany($partnerCui) : [];
        $supplierCompany = $supplierCui ? $this->fetchCompany($supplierCui) : [];
        $clientCompany = $clientCui ? $this->fetchCompany($clientCui) : [];
        $partner = $partnerCui ? $this->fetchPartner($partnerCui) : [];
        $supplier = $supplierCui ? $this->fetchPartner($supplierCui) : [];
        $client = $clientCui ? $this->fetchPartner($clientCui) : [];

        $vars['partner.name'] = $this->firstNonEmpty($partnerCompany['denumire'] ?? '', $partner['denumire'] ?? '');
        $vars['partner.cui'] = $this->firstNonEmpty($partnerCompany['cui'] ?? '', $partner['cui'] ?? '');
        $vars['partner.email'] = $this->firstNonEmpty($partnerCompany['email'] ?? '', $partner['email'] ?? '');
        $vars['partner.phone'] = $this->firstNonEmpty($partnerCompany['telefon'] ?? '', $partner['telefon'] ?? '');
        $vars['partner.address'] = $this->firstNonEmpty($partnerCompany['adresa'] ?? '', $partner['adresa'] ?? '');
        $vars['partner.city'] = $this->firstNonEmpty($partnerCompany['localitate'] ?? '', $partner['localitate'] ?? '');
        $vars['partner.county'] = $this->firstNonEmpty($partnerCompany['judet'] ?? '', $partner['judet'] ?? '');
        $vars['partner.country'] = $this->firstNonEmpty($partnerCompany['tara'] ?? '', $partner['tara'] ?? '');
        $vars['partner.reg_no'] = $this->firstNonEmpty($partnerCompany['nr_reg_comertului'] ?? '', $partner['nr_reg_comertului'] ?? '');
        $vars['partner.bank'] = $this->firstNonEmpty(
            $partnerCompany['bank_name'] ?? '',
            $partner['bank_name'] ?? '',
            $partnerCompany['banca'] ?? ''
        );
        $vars['partner.iban'] = $this->firstNonEmpty(
            $partnerCompany['iban'] ?? '',
            $partnerCompany['bank_account'] ?? '',
            $partner['bank_account'] ?? ''
        );
        $vars['partner.representative_name'] = $this->firstNonEmpty(
            $partnerCompany['legal_representative_name'] ?? '',
            $partnerCompany['representative_name'] ?? '',
            $partner['representative_name'] ?? ''
        );
        $vars['partner.representative_function'] = $this->firstNonEmpty(
            $partnerCompany['legal_representative_role'] ?? '',
            $partnerCompany['representative_function'] ?? '',
            $partner['representative_function'] ?? ''
        );
        $vars['partner.bank_account'] = $this->resolveBankAccount($partnerCompany, $partner);
        $vars['partner.bank_name'] = $this->resolveBankName($partnerCompany, $partner);
        $vars['company.legal_representative'] = $this->firstNonEmpty(
            $partnerCompany['legal_representative_name'] ?? '',
            $partnerCompany['representative_name'] ?? '',
            $partner['representative_name'] ?? ''
        );
        $vars['company.legal_representative_role'] = $this->firstNonEmpty(
            $partnerCompany['legal_representative_role'] ?? '',
            $partnerCompany['representative_function'] ?? '',
            $partner['representative_function'] ?? ''
        );
        $vars['company.bank'] = $this->resolveBankName($partnerCompany, $partner);
        $vars['company.iban'] = $this->firstNonEmpty(
            $partnerCompany['iban'] ?? '',
            $partnerCompany['bank_account'] ?? '',
            $partner['bank_account'] ?? ''
        );

        $vars['supplier.name'] = $this->firstNonEmpty($supplierCompany['denumire'] ?? '', $supplier['denumire'] ?? '');
        $vars['supplier.cui'] = $this->firstNonEmpty($supplierCompany['cui'] ?? '', $supplier['cui'] ?? '');
        $vars['supplier.email'] = $this->firstNonEmpty($supplierCompany['email'] ?? '', $supplier['email'] ?? '');
        $vars['supplier.phone'] = $this->firstNonEmpty($supplierCompany['telefon'] ?? '', $supplier['telefon'] ?? '');
        $vars['supplier.representative_name'] = $this->firstNonEmpty(
            $supplierCompany['legal_representative_name'] ?? '',
            $supplierCompany['representative_name'] ?? '',
            $supplier['representative_name'] ?? ''
        );
        $vars['supplier.representative_function'] = $this->firstNonEmpty(
            $supplierCompany['legal_representative_role'] ?? '',
            $supplierCompany['representative_function'] ?? '',
            $supplier['representative_function'] ?? ''
        );
        $vars['supplier.bank_account'] = $this->resolveBankAccount($supplierCompany, $supplier);
        $vars['supplier.bank_name'] = $this->resolveBankName($supplierCompany, $supplier);

        $vars['client.name'] = $this->firstNonEmpty($clientCompany['denumire'] ?? '', $client['denumire'] ?? '');
        $vars['client.cui'] = $this->firstNonEmpty($clientCompany['cui'] ?? '', $client['cui'] ?? '');
        $vars['client.email'] = $this->firstNonEmpty($clientCompany['email'] ?? '', $client['email'] ?? '');
        $vars['client.phone'] = $this->firstNonEmpty($clientCompany['telefon'] ?? '', $client['telefon'] ?? '');
        $vars['client.representative_name'] = $this->firstNonEmpty(
            $clientCompany['legal_representative_name'] ?? '',
            $clientCompany['representative_name'] ?? '',
            $client['representative_name'] ?? ''
        );
        $vars['client.representative_function'] = $this->firstNonEmpty(
            $clientCompany['legal_representative_role'] ?? '',
            $clientCompany['representative_function'] ?? '',
            $client['representative_function'] ?? ''
        );
        $vars['client.bank_account'] = $this->resolveBankAccount($clientCompany, $client);
        $vars['client.bank_name'] = $this->resolveBankName($clientCompany, $client);

        $vars['relation.supplier_cui'] = $supplierCui ?? '';
        $vars['relation.client_cui'] = $clientCui ?? '';
        $vars['relation.invoice_inbox_email'] = $this->fetchRelationEmail($supplierCui, $clientCui);
        $stampVars = $this->resolveStampVariables(
            isset($contractContext['template_id']) ? (int) $contractContext['template_id'] : 0,
            trim((string) ($contractContext['render_context'] ?? 'admin'))
        );
        $vars['stamp.image'] = $stampVars['image'];
        $vars['stamp.url'] = $stampVars['url'];

        $docType = trim((string) ($contractContext['doc_type'] ?? ''));
        if ($docType === '') {
            $docType = 'contract';
        }
        $contractDate = trim((string) ($contractContext['contract_date'] ?? $contractContext['created_at'] ?? ''));
        if ($contractDate === '') {
            $contractDate = date('Y-m-d');
        }
        $contractDateDisplay = $this->formatDateForDisplay($contractDate);
        $docNo = isset($contractContext['doc_no']) && (int) $contractContext['doc_no'] > 0
            ? (string) (int) $contractContext['doc_no']
            : '';
        $docSeries = trim((string) ($contractContext['doc_series'] ?? ''));
        $docFullNo = trim((string) ($contractContext['doc_full_no'] ?? ''));
        if ($docFullNo === '' && $docNo !== '') {
            $paddedNo = str_pad($docNo, 6, '0', STR_PAD_LEFT);
            $docFullNo = $docSeries !== '' ? ($docSeries . '-' . $paddedNo) : $paddedNo;
        }

        $vars['contract.title'] = (string) ($contractContext['title'] ?? '');
        $vars['contract.created_at'] = (string) ($contractContext['created_at'] ?? date('Y-m-d'));
        $vars['contract.date'] = $contractDateDisplay;
        $vars['contract.doc_type'] = $docType;
        $vars['contract.no'] = $docNo;
        $vars['contract.series'] = $docSeries;
        $vars['contract.full_no'] = $docFullNo;
        $vars['doc.type'] = $docType;
        $vars['doc.no'] = $docNo;
        $vars['doc.series'] = $docSeries;
        $vars['doc.full_no'] = $docFullNo;
        $vars['date.today'] = date('Y-m-d');

        return $vars;
    }

    private function formatDateForDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return $value;
        }

        return date('d.m.Y', $timestamp);
    }

    private function fetchPartner(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '') {
            return [];
        }

        if (!Database::tableExists('partners')) {
            return [];
        }

        $select = [
            'cui',
            'denumire',
            $this->optionalColumn('partners', 'representative_name'),
            $this->optionalColumn('partners', 'representative_function'),
            $this->optionalColumn('partners', 'bank_account'),
            $this->optionalColumn('partners', 'bank_name'),
            $this->optionalColumn('partners', 'email'),
            $this->optionalColumn('partners', 'telefon'),
            $this->optionalColumn('partners', 'adresa'),
            $this->optionalColumn('partners', 'localitate'),
            $this->optionalColumn('partners', 'judet'),
            $this->optionalColumn('partners', 'tara'),
            $this->optionalColumn('partners', 'nr_reg_comertului'),
        ];

        return Database::fetchOne(
            'SELECT ' . implode(', ', $select) . '
             FROM partners WHERE cui = :cui LIMIT 1',
            ['cui' => $cui]
        ) ?? [];
    }

    private function fetchCompany(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '' || !Database::tableExists('companies')) {
            return [];
        }

        $select = [
            'cui',
            'denumire',
            $this->optionalColumn('companies', 'nr_reg_comertului'),
            $this->optionalColumn('companies', 'adresa'),
            $this->optionalColumn('companies', 'localitate'),
            $this->optionalColumn('companies', 'judet'),
            $this->optionalColumn('companies', 'tara'),
            $this->optionalColumn('companies', 'email'),
            $this->optionalColumn('companies', 'telefon'),
            $this->optionalColumn('companies', 'legal_representative_name'),
            $this->optionalColumn('companies', 'legal_representative_role'),
            $this->optionalColumn('companies', 'representative_name'),
            $this->optionalColumn('companies', 'representative_function'),
            $this->optionalColumn('companies', 'banca'),
            $this->optionalColumn('companies', 'iban'),
            $this->optionalColumn('companies', 'bank_account'),
            $this->optionalColumn('companies', 'bank_name'),
        ];

        return Database::fetchOne(
            'SELECT ' . implode(', ', $select) . '
             FROM companies WHERE cui = :cui LIMIT 1',
            ['cui' => $cui]
        ) ?? [];
    }

    private function resolveStampVariables(int $templateId, string $renderContext): array
    {
        if ($templateId <= 0) {
            return ['image' => '', 'url' => ''];
        }

        $stamp = $this->fetchTemplateStamp($templateId);
        $path = trim((string) ($stamp['stamp_image_path'] ?? ''));
        if ($path === '') {
            return ['image' => '', 'url' => ''];
        }

        $renderContext = strtolower($renderContext);
        if ($renderContext === 'public') {
            $dataUri = $this->resolveStampDataUri($path, self::STAMP_INLINE_MAX_BYTES);
            if ($dataUri === '') {
                return ['image' => '', 'url' => ''];
            }

            $escaped = htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8');
            return [
                'image' => '<img src="' . $escaped . '" style="max-width:200px;max-height:120px;" alt="Stampila" />',
                'url' => $dataUri,
            ];
        }

        $url = $this->buildStampProtectedUrl($templateId, (string) ($stamp['stamp_image_meta'] ?? ''), $path);
        if ($url === '') {
            return ['image' => '', 'url' => ''];
        }

        $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return [
            'image' => '<img src="' . $escaped . '" style="max-width:200px;max-height:120px;" alt="Stampila" />',
            'url' => $url,
        ];
    }

    private function fetchTemplateStamp(int $templateId): array
    {
        if ($templateId <= 0 || !Database::tableExists('contract_templates')) {
            return [];
        }

        $select = [
            'id',
            $this->optionalColumn('contract_templates', 'stamp_image_path'),
            $this->optionalColumn('contract_templates', 'stamp_image_meta'),
        ];

        return Database::fetchOne(
            'SELECT ' . implode(', ', $select) . '
             FROM contract_templates WHERE id = :id LIMIT 1',
            ['id' => $templateId]
        ) ?? [];
    }

    private function buildStampProtectedUrl(int $templateId, string $metaJson, string $path): string
    {
        if ($templateId <= 0) {
            return '';
        }

        $cacheBuster = '';
        if (trim($metaJson) !== '') {
            $decoded = json_decode($metaJson, true);
            if (is_array($decoded)) {
                $cacheBuster = trim((string) ($decoded['uploaded_at'] ?? ''));
            }
        }
        if ($cacheBuster === '') {
            $cacheBuster = substr(sha1($path), 0, 12);
        }

        return Url::to('admin/contract-templates/stamp?id=' . $templateId . '&t=' . urlencode($cacheBuster));
    }

    private function resolveStampDataUri(string $relativePath, int $maxBytes): string
    {
        $absolute = $this->resolveStampAbsolutePath($relativePath);
        if ($absolute === '' || !is_file($absolute) || !is_readable($absolute)) {
            return '';
        }

        $size = filesize($absolute);
        if (!is_int($size) && !is_float($size)) {
            return '';
        }
        if ((int) $size <= 0 || (int) $size > $maxBytes) {
            return '';
        }

        $mime = $this->detectImageMime($absolute);
        if ($mime === null || !in_array($mime, self::STAMP_ALLOWED_MIMES, true)) {
            return '';
        }

        $raw = @file_get_contents($absolute);
        if ($raw === false || $raw === '') {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    private function resolveStampAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'storage/uploads/' . self::STAMP_UPLOAD_SUBDIR . '/')) {
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $root = realpath($basePath . '/storage/uploads/' . self::STAMP_UPLOAD_SUBDIR);
        if ($root === false) {
            return '';
        }

        $absolute = realpath($basePath . '/' . $relativePath);
        if ($absolute === false || !str_starts_with($absolute, $root)) {
            return '';
        }

        return $absolute;
    }

    private function detectImageMime(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $mime = null;
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && $detected !== '') {
                    $mime = strtolower($detected);
                }
                finfo_close($finfo);
            }
        }
        if ($mime === null && function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                $mime = strtolower($detected);
            }
        }

        if ($mime === null) {
            return null;
        }

        $mime = strtolower(trim($mime));
        if ($mime === 'image/jpg') {
            return 'image/jpeg';
        }
        if ($mime === 'image/x-png') {
            return 'image/png';
        }

        return $mime;
    }

    private function resolveBankAccount(array $company, array $partner): string
    {
        $value = $this->firstNonEmpty($company['bank_account'] ?? '', $partner['bank_account'] ?? '');
        if ($value !== '') {
            return $value;
        }

        return $this->firstNonEmpty($company['iban'] ?? '');
    }

    private function resolveBankName(array $company, array $partner): string
    {
        return $this->firstNonEmpty(
            $company['bank_name'] ?? '',
            $partner['bank_name'] ?? '',
            $company['banca'] ?? ''
        );
    }

    private function firstNonEmpty(string ...$values): string
    {
        foreach ($values as $value) {
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return '';
    }

    private function optionalColumn(string $table, string $column): string
    {
        if (Database::columnExists($table, $column)) {
            return $column;
        }

        return 'NULL AS ' . $column;
    }

    private function fetchRelationEmail(?string $supplierCui, ?string $clientCui): string
    {
        $supplierCui = $supplierCui ? preg_replace('/\D+/', '', $supplierCui) : '';
        $clientCui = $clientCui ? preg_replace('/\D+/', '', $clientCui) : '';
        if ($supplierCui === '' || $clientCui === '' || !Database::tableExists('partner_relations')) {
            return '';
        }

        $row = Database::fetchOne(
            'SELECT invoice_inbox_email FROM partner_relations WHERE supplier_cui = :supplier AND client_cui = :client LIMIT 1',
            ['supplier' => $supplierCui, 'client' => $clientCui]
        );

        return $row ? (string) ($row['invoice_inbox_email'] ?? '') : '';
    }
}
