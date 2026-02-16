<?php

namespace App\Domain\Contracts\Services;

use App\Domain\Settings\Services\SettingsService;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Logger;

class ContractPdfService
{
    private TemplateRenderer $renderer;
    private ContractTemplateVariables $variablesService;
    private ?string $wkhtmltopdfPath = null;
    private bool $wkhtmltopdfChecked = false;

    public function __construct(
        ?TemplateRenderer $renderer = null,
        ?ContractTemplateVariables $variablesService = null
    ) {
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->variablesService = $variablesService ?? new ContractTemplateVariables();
    }

    public function isPdfGenerationAvailable(): bool
    {
        return $this->resolveWkhtmltopdfPath() !== '';
    }

    public function renderHtmlForContract(array $contract): string
    {
        $title = (string) ($contract['title'] ?? 'Contract');
        $templateId = isset($contract['template_id']) ? (int) $contract['template_id'] : 0;
        $templateHtml = '';

        if ($templateId > 0 && Database::tableExists('contract_templates')) {
            $template = Database::fetchOne(
                'SELECT html_content FROM contract_templates WHERE id = :id LIMIT 1',
                ['id' => $templateId]
            );
            $templateHtml = (string) ($template['html_content'] ?? '');
        }

        if ($templateHtml === '') {
            $templateHtml = '<html><body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
        }

        $contractDate = $this->resolveContractDate($contract);
        $docType = trim((string) ($contract['doc_type'] ?? ''));
        if ($docType === '') {
            $docType = 'contract';
        }

        $vars = $this->variablesService->buildVariables(
            $contract['partner_cui'] ?? null,
            $contract['supplier_cui'] ?? null,
            $contract['client_cui'] ?? null,
            [
                'title' => $title,
                'created_at' => $contractDate,
                'contract_date' => $contractDate,
                'doc_type' => $docType,
                'doc_no' => (int) ($contract['doc_no'] ?? 0),
                'doc_series' => (string) ($contract['doc_series'] ?? ''),
                'doc_full_no' => (string) ($contract['doc_full_no'] ?? ''),
            ]
        );

        $renderedBody = $this->renderer->render($templateHtml, $vars);

        return $this->wrapPrintableDocument($renderedBody);
    }

    public function generatePdfForContract(int $contractId): string
    {
        if ($contractId <= 0 || !Database::tableExists('contracts')) {
            return '';
        }

        $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $contractId]);
        if (!$contract) {
            return '';
        }

        $html = $this->renderHtmlForContract($contract);
        if ($html === '') {
            return '';
        }

        $binary = $this->resolveWkhtmltopdfPath();
        if ($binary === '') {
            $this->storeHtmlFallback($contractId, $html);
            Logger::logWarning('contract_pdf_tool_missing', ['contract_id' => $contractId]);
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $outputDir = $basePath . '/storage/uploads/contracts';
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $pdfName = 'contract-' . $contractId . '-' . bin2hex(random_bytes(8)) . '.pdf';
        $pdfAbsolute = $outputDir . '/' . $pdfName;
        $tmpHtml = tempnam(sys_get_temp_dir(), 'ctr_pdf_');
        if ($tmpHtml === false) {
            return '';
        }

        if (file_put_contents($tmpHtml, $html) === false) {
            @unlink($tmpHtml);
            return '';
        }

        $command = escapeshellarg($binary)
            . ' --quiet '
            . escapeshellarg($tmpHtml)
            . ' '
            . escapeshellarg($pdfAbsolute)
            . ' 2>&1';

        $output = shell_exec($command);
        @unlink($tmpHtml);

        if (!is_file($pdfAbsolute) || filesize($pdfAbsolute) === 0) {
            @unlink($pdfAbsolute);
            $this->storeHtmlFallback($contractId, $html);
            Logger::logWarning('contract_pdf_generation_failed', [
                'contract_id' => $contractId,
                'output' => is_string($output) ? $output : '',
            ]);
            return '';
        }

        $relative = 'storage/uploads/contracts/' . $pdfName;
        Database::execute(
            'UPDATE contracts
             SET generated_pdf_path = :generated_pdf_path,
                 status = CASE WHEN status = :draft_status THEN :generated_status ELSE status END,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'generated_pdf_path' => $relative,
                'draft_status' => 'draft',
                'generated_status' => 'generated',
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $contractId,
            ]
        );
        Audit::record('contract.pdf_generated', 'contract', $contractId, [
            'rows_count' => 1,
            'generator' => 'wkhtmltopdf',
        ]);

        return $relative;
    }

    private function resolveWkhtmltopdfPath(): string
    {
        if ($this->wkhtmltopdfChecked) {
            return $this->wkhtmltopdfPath ?? '';
        }
        $this->wkhtmltopdfChecked = true;

        if (!function_exists('shell_exec')) {
            $this->wkhtmltopdfPath = '';
            return '';
        }

        $knownPaths = [
            '/usr/bin/wkhtmltopdf',
            '/usr/local/bin/wkhtmltopdf',
        ];
        foreach ($knownPaths as $path) {
            if (is_file($path) && is_executable($path)) {
                $this->wkhtmltopdfPath = $path;
                return $path;
            }
        }

        $detected = trim((string) shell_exec('command -v wkhtmltopdf 2>/dev/null'));
        if ($detected !== '' && is_file($detected) && is_executable($detected)) {
            $this->wkhtmltopdfPath = $detected;
            return $detected;
        }

        $this->wkhtmltopdfPath = '';
        return '';
    }

    private function resolveContractDate(array $contract): string
    {
        $rawDate = trim((string) ($contract['contract_date'] ?? ''));
        if ($rawDate !== '') {
            return $rawDate;
        }
        $createdAt = trim((string) ($contract['created_at'] ?? ''));
        if ($createdAt !== '') {
            return date('Y-m-d', strtotime($createdAt));
        }

        return date('Y-m-d');
    }

    private function storeHtmlFallback(int $contractId, string $html): void
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $basePath . '/storage/uploads/contracts/generated';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $filename = 'contract-' . $contractId . '-' . bin2hex(random_bytes(8)) . '.html';
        $absolute = $dir . '/' . $filename;
        if (file_put_contents($absolute, $html) === false) {
            return;
        }

        Database::execute(
            'UPDATE contracts
             SET generated_file_path = :generated_file_path,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'generated_file_path' => 'storage/uploads/contracts/generated/' . $filename,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $contractId,
            ]
        );
    }

    private function wrapPrintableDocument(string $renderedHtml): string
    {
        $styleBlocks = '';
        if (preg_match_all('/<style\b[^>]*>.*?<\/style>/is', $renderedHtml, $matches)) {
            $styleBlocks = implode("\n", $matches[0]);
        }

        $bodyHtml = $renderedHtml;
        if (preg_match('/<body\b[^>]*>(.*?)<\/body>/is', $renderedHtml, $bodyMatch)) {
            $bodyHtml = (string) ($bodyMatch[1] ?? '');
        }
        $bodyHtml = preg_replace('/<!doctype[^>]*>/i', '', $bodyHtml);
        $bodyHtml = preg_replace('/<\/?(html|head|body)[^>]*>/i', '', $bodyHtml);
        $bodyHtml = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $bodyHtml);
        $bodyHtml = trim((string) $bodyHtml);

        $headerHtml = $this->buildPrintableHeaderHtml();

        return '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page {
            margin: 28mm 14mm 18mm 14mm;
        }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        table.print-layout {
            width: 100%;
            border-collapse: collapse;
        }
        table.print-layout thead {
            display: table-header-group;
        }
        table.print-layout tbody {
            display: table-row-group;
        }
        .print-header {
            border-bottom: 1px solid #cbd5e1;
            padding: 0 0 10px 0;
            margin-bottom: 12px;
        }
        .print-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .print-logo img {
            display: block;
            max-height: 60px;
            width: auto;
            max-width: 260px;
        }
        .print-logo-fallback {
            font-size: 20px;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: 0.3px;
        }
        .print-company {
            text-align: right;
            font-size: 11px;
            line-height: 1.4;
            color: #334155;
            max-width: 420px;
        }
        .print-company .name {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .print-content {
            width: 100%;
        }
    </style>
    ' . $styleBlocks . '
</head>
<body>
    <table class="print-layout">
        <thead>
            <tr>
                <td>
                    ' . $headerHtml . '
                </td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="print-content">' . $bodyHtml . '</div>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>';
    }

    private function buildPrintableHeaderHtml(): string
    {
        $settings = new SettingsService();
        $company = [
            'denumire' => trim((string) $settings->get('company.denumire', '')),
            'cui' => trim((string) $settings->get('company.cui', '')),
            'nr_reg_comertului' => trim((string) $settings->get('company.nr_reg_comertului', '')),
            'adresa' => trim((string) $settings->get('company.adresa', '')),
            'localitate' => trim((string) $settings->get('company.localitate', '')),
            'judet' => trim((string) $settings->get('company.judet', '')),
            'email' => trim((string) $settings->get('company.email', '')),
            'telefon' => trim((string) $settings->get('company.telefon', '')),
            'banca' => trim((string) $settings->get('company.banca', '')),
            'iban' => trim((string) $settings->get('company.iban', '')),
        ];
        $logoDataUri = $this->resolveLogoDataUri((string) $settings->get('branding.logo_path', ''));

        $name = $company['denumire'] !== '' ? $company['denumire'] : 'ERP Platforma';
        $location = trim($company['localitate'] . ($company['judet'] !== '' ? ', ' . $company['judet'] : ''));

        $lines = [];
        if ($company['cui'] !== '' || $company['nr_reg_comertului'] !== '') {
            $line = [];
            if ($company['cui'] !== '') {
                $line[] = 'CUI: ' . $company['cui'];
            }
            if ($company['nr_reg_comertului'] !== '') {
                $line[] = 'Nr. Reg. Comertului: ' . $company['nr_reg_comertului'];
            }
            $lines[] = implode(' | ', $line);
        }
        if ($company['adresa'] !== '' || $location !== '') {
            $address = trim($company['adresa'] . ($location !== '' ? ', ' . $location : ''));
            if ($address !== '') {
                $lines[] = $address;
            }
        }
        if ($company['telefon'] !== '' || $company['email'] !== '') {
            $contact = [];
            if ($company['telefon'] !== '') {
                $contact[] = 'Tel: ' . $company['telefon'];
            }
            if ($company['email'] !== '') {
                $contact[] = 'Email: ' . $company['email'];
            }
            $lines[] = implode(' | ', $contact);
        }
        if ($company['banca'] !== '' || $company['iban'] !== '') {
            $bank = [];
            if ($company['banca'] !== '') {
                $bank[] = 'Banca: ' . $company['banca'];
            }
            if ($company['iban'] !== '') {
                $bank[] = 'IBAN: ' . $company['iban'];
            }
            $lines[] = implode(' | ', $bank);
        }

        $companyHtml = '<div class="print-company"><div class="name">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</div>';
        foreach ($lines as $line) {
            $companyHtml .= '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $companyHtml .= '</div>';

        $logoHtml = $logoDataUri !== ''
            ? '<div class="print-logo"><img src="' . htmlspecialchars($logoDataUri, ENT_QUOTES, 'UTF-8') . '" alt="Logo"></div>'
            : '<div class="print-logo-fallback">ERP Platforma</div>';

        return '<div class="print-header"><div class="print-header-row">' . $logoHtml . $companyHtml . '</div></div>';
    }

    private function resolveLogoDataUri(string $logoPath): string
    {
        $logoPath = trim($logoPath);
        if ($logoPath === '') {
            return '';
        }
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $absolute = $basePath . '/' . ltrim($logoPath, '/');
        if (!is_file($absolute) || !is_readable($absolute)) {
            return '';
        }
        $data = @file_get_contents($absolute);
        if ($data === false || $data === '') {
            return '';
        }
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            default => 'application/octet-stream',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}
