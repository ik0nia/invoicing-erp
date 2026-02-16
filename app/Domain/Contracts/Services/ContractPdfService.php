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
    private ?string $dompdfAutoloadPath = null;
    private bool $dompdfChecked = false;

    public function __construct(
        ?TemplateRenderer $renderer = null,
        ?ContractTemplateVariables $variablesService = null
    ) {
        $this->renderer = $renderer ?? new TemplateRenderer();
        $this->variablesService = $variablesService ?? new ContractTemplateVariables();
    }

    public function isPdfGenerationAvailable(): bool
    {
        return $this->resolveWkhtmltopdfPath() !== '' || $this->isDompdfAvailable();
    }

    public function renderHtmlForContract(array $contract, string $renderContext = 'admin'): string
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
                'template_id' => $templateId,
                'render_context' => $renderContext,
                'contract_id' => (int) ($contract['id'] ?? 0),
                'doc_no' => (int) ($contract['doc_no'] ?? 0),
                'doc_series' => (string) ($contract['doc_series'] ?? ''),
                'doc_full_no' => (string) ($contract['doc_full_no'] ?? ''),
            ]
        );

        $renderedBody = $this->renderer->render($templateHtml, $vars);

        return $this->wrapPrintableDocument($renderedBody);
    }

    public function generatePdfForContract(int $contractId, string $renderContext = 'admin'): string
    {
        if ($contractId <= 0 || !Database::tableExists('contracts')) {
            return '';
        }

        $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $contractId]);
        if (!$contract) {
            return '';
        }

        $html = $this->renderHtmlForContract($contract, $renderContext);
        if ($html === '') {
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $outputDir = $basePath . '/storage/uploads/contracts';
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $pdfName = 'contract-' . $contractId . '-' . bin2hex(random_bytes(8)) . '.pdf';
        $pdfAbsolute = $outputDir . '/' . $pdfName;
        $generator = '';
        $binary = $this->resolveWkhtmltopdfPath();
        $hasWkhtmltopdf = $binary !== '';
        if ($hasWkhtmltopdf && $this->generateWithWkhtmltopdf($binary, $html, $pdfAbsolute, $contractId)) {
            $generator = 'wkhtmltopdf';
        }
        $dompdfStatus = $this->dompdfAvailability($contractId, false);
        $hasDompdf = (bool) ($dompdfStatus['available'] ?? false);
        $dompdfTried = false;
        if ($generator === '' && $hasDompdf) {
            $dompdfTried = true;
            if ($this->generateWithDompdf($html, $pdfAbsolute, $contractId)) {
                $generator = 'dompdf';
            }
        }

        if ($generator === '' || !is_file($pdfAbsolute) || filesize($pdfAbsolute) === 0) {
            @unlink($pdfAbsolute);
            $this->storeHtmlFallback($contractId, $html);
            if ($dompdfTried && $generator === '' && ($dompdfStatus['reason'] ?? '') === 'ready') {
                $dompdfStatus['reason'] = 'generation_failed';
            }
            Logger::logWarning('contract_pdf_tool_missing', [
                'contract_id' => $contractId,
                'has_wkhtmltopdf' => $hasWkhtmltopdf,
                'has_dompdf' => $hasDompdf,
                'dompdf_reason' => (string) ($dompdfStatus['reason'] ?? ''),
                'dompdf_missing_extensions' => (array) ($dompdfStatus['missing_extensions'] ?? []),
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
            'generator' => $generator,
        ]);

        return $relative;
    }

    private function generateWithWkhtmltopdf(string $binary, string $html, string $pdfAbsolute, int $contractId): bool
    {
        $tmpHtml = tempnam(sys_get_temp_dir(), 'ctr_pdf_');
        if ($tmpHtml === false) {
            return false;
        }

        if (file_put_contents($tmpHtml, $html) === false) {
            @unlink($tmpHtml);
            return false;
        }

        $command = escapeshellarg($binary)
            . ' --quiet'
            . ' --footer-center ' . escapeshellarg('[page]/[toPage] pagini')
            . ' --footer-font-size ' . escapeshellarg('9')
            . ' --footer-spacing ' . escapeshellarg('4')
            . ' --margin-bottom ' . escapeshellarg('22mm')
            . ' '
            . escapeshellarg($tmpHtml)
            . ' '
            . escapeshellarg($pdfAbsolute)
            . ' 2>&1';

        $output = shell_exec($command);
        @unlink($tmpHtml);

        if (!is_file($pdfAbsolute) || filesize($pdfAbsolute) === 0) {
            @unlink($pdfAbsolute);
            Logger::logWarning('contract_pdf_generation_failed', [
                'contract_id' => $contractId,
                'generator' => 'wkhtmltopdf',
                'output' => is_string($output) ? $output : '',
            ]);
            return false;
        }

        return true;
    }

    private function generateWithDompdf(string $html, string $pdfAbsolute, int $contractId): bool
    {
        $dompdfStatus = $this->dompdfAvailability($contractId, true);
        if (empty($dompdfStatus['available'])) {
            return false;
        }
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $tempDir = $basePath . '/storage/cache/dompdf/tmp';
        $fontDir = $basePath . '/storage/cache/dompdf/fonts';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('chroot', $basePath);
            $options->set('tempDir', $tempDir);
            $options->set('fontDir', $fontDir);
            $options->set('fontCache', $fontDir);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            $canvas = $dompdf->getCanvas();
            if ($canvas !== null) {
                $footerText = '{PAGE_NUM}/{PAGE_COUNT} pagini';
                $fontSize = 9.0;
                $fontMetrics = $dompdf->getFontMetrics();
                $font = $fontMetrics->getFont('DejaVu Sans', 'normal');
                $textWidth = $this->measureTextWidth($fontMetrics, $footerText, $font, $fontSize);
                $x = max(12.0, ((float) $canvas->get_width() - $textWidth) / 2.0);
                $y = (float) $canvas->get_height() - 24.0;
                $canvas->page_text($x, $y, $footerText, $font, $fontSize, [0, 0, 0]);
            }

            $binaryPdf = $dompdf->output();
            if ($binaryPdf === '') {
                return false;
            }

            return file_put_contents($pdfAbsolute, $binaryPdf) !== false;
        } catch (\Throwable $exception) {
            Logger::logWarning('contract_pdf_generation_failed', [
                'contract_id' => $contractId,
                'generator' => 'dompdf',
                'output' => $exception->getMessage(),
            ]);

            return false;
        }
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

    private function isDompdfAvailable(): bool
    {
        $status = $this->dompdfAvailability(0, false);

        return !empty($status['available']);
    }

    private function dompdfAvailability(int $contractId = 0, bool $logFailures = false): array
    {
        if (!$this->ensureDompdfLoaded($logFailures)) {
            return [
                'available' => false,
                'reason' => 'autoload_or_class_missing',
                'missing_extensions' => [],
            ];
        }

        $missingExtensions = $this->missingDompdfExtensions();
        if (!empty($missingExtensions)) {
            if ($logFailures) {
                Logger::logWarning('dompdf_extensions_missing', [
                    'contract_id' => $contractId > 0 ? $contractId : null,
                    'missing' => $missingExtensions,
                ]);
            }

            return [
                'available' => false,
                'reason' => 'extensions_missing',
                'missing_extensions' => $missingExtensions,
            ];
        }

        if (!$this->ensureDompdfStorageReady($contractId, $logFailures)) {
            return [
                'available' => false,
                'reason' => 'storage_not_writable',
                'missing_extensions' => [],
            ];
        }

        return [
            'available' => true,
            'reason' => 'ready',
            'missing_extensions' => [],
        ];
    }

    private function ensureDompdfLoaded(bool $logFailures = true): bool
    {
        if ($this->dompdfClassExists()) {
            return true;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $candidates = $this->dompdfAutoloadCandidates($basePath);
        $autoloadPath = $this->resolveDompdfAutoloadPath();
        $attemptedPaths = [];
        $failedIncludes = [];

        if ($autoloadPath !== '') {
            $attemptedPaths[] = $autoloadPath;
            $loaded = @include_once $autoloadPath;
            if ($loaded === false && !$this->dompdfClassExists()) {
                $failedIncludes[] = $autoloadPath;
            }
            if ($this->dompdfClassExists()) {
                $this->dompdfAutoloadPath = $autoloadPath;
                return true;
            }
        }

        if (!$this->dompdfClassExists()) {
            foreach ($candidates as $candidate) {
                if ($candidate === $autoloadPath) {
                    continue;
                }
                if (!is_file($candidate) || !is_readable($candidate)) {
                    continue;
                }

                $attemptedPaths[] = $candidate;
                $loaded = @include_once $candidate;
                if ($loaded === false && !$this->dompdfClassExists()) {
                    $failedIncludes[] = $candidate;
                    continue;
                }

                if ($this->dompdfClassExists()) {
                    $this->dompdfAutoloadPath = $candidate;
                    break;
                }
            }
        }

        if ($this->dompdfClassExists()) {
            return true;
        }

        if (!$logFailures) {
            return false;
        }

        if (empty($attemptedPaths)) {
            Logger::logWarning('dompdf_autoload_missing', [
                'checked' => $candidates,
            ]);
            return false;
        }

        if (!empty($failedIncludes)) {
            Logger::logWarning('dompdf_autoload_failed', [
                'autoload_path' => $failedIncludes[0],
                'checked' => $failedIncludes,
            ]);
        }

        Logger::logWarning('dompdf_class_missing', [
            'autoload_path' => $autoloadPath !== '' ? $autoloadPath : ($attemptedPaths[0] ?? ''),
            'checked' => $attemptedPaths,
        ]);

        return false;
    }

    private function dompdfClassExists(): bool
    {
        if (class_exists(\Dompdf\Dompdf::class, false)) {
            return true;
        }

        return class_exists(\Dompdf\Dompdf::class);
    }

    private function resolveDompdfAutoloadPath(): string
    {
        if ($this->dompdfChecked) {
            return $this->dompdfAutoloadPath ?? '';
        }
        $this->dompdfChecked = true;

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $candidates = $this->dompdfAutoloadCandidates($basePath);
        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate)) {
                $this->dompdfAutoloadPath = $candidate;
                return $candidate;
            }
        }

        $this->dompdfAutoloadPath = '';
        return '';
    }

    private function dompdfAutoloadCandidates(string $basePath): array
    {
        return [
            $basePath . '/app/Support/Dompdf/vendor/autoload.php',
            $basePath . '/app/Support/dompdf/vendor/autoload.php',
            $basePath . '/app/Support/Dompdf/autoload.inc.php',
            $basePath . '/app/Support/dompdf/autoload.inc.php',
        ];
    }

    private function missingDompdfExtensions(): array
    {
        $missing = [];
        if (!class_exists(\DOMDocument::class)) {
            $missing[] = 'dom';
        }
        if (!function_exists('mb_detect_encoding')) {
            $missing[] = 'mbstring';
        }

        return $missing;
    }

    private function ensureDompdfStorageReady(int $contractId = 0, bool $logFailures = true): bool
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $tempDir = $basePath . '/storage/cache/dompdf/tmp';
        $fontDir = $basePath . '/storage/cache/dompdf/fonts';
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }
        if (!is_dir($fontDir)) {
            @mkdir($fontDir, 0775, true);
        }
        $ready = is_dir($tempDir) && is_writable($tempDir) && is_dir($fontDir) && is_writable($fontDir);
        if ($ready || !$logFailures) {
            return $ready;
        }

        Logger::logWarning('dompdf_storage_not_writable', [
            'contract_id' => $contractId > 0 ? $contractId : null,
            'temp_dir' => $tempDir,
            'font_dir' => $fontDir,
            'temp_exists' => is_dir($tempDir),
            'font_exists' => is_dir($fontDir),
            'temp_writable' => is_writable($tempDir),
            'font_writable' => is_writable($fontDir),
        ]);

        return false;
    }

    private function measureTextWidth(object $fontMetrics, string $text, $font, float $fontSize): float
    {
        if (method_exists($fontMetrics, 'getTextWidth')) {
            $width = $fontMetrics->getTextWidth($text, $font, $fontSize);
            if (is_numeric($width)) {
                return (float) $width;
            }
        }
        if (method_exists($fontMetrics, 'get_text_width')) {
            $width = $fontMetrics->get_text_width($text, $font, $fontSize);
            if (is_numeric($width)) {
                return (float) $width;
            }
        }

        return max(20.0, strlen($text) * $fontSize * 0.55);
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
        .print-document {
            width: 100%;
        }
        .print-header {
            border-bottom: 1px solid #cbd5e1;
            padding: 0 0 10px 0;
            margin-bottom: 12px;
            page-break-inside: avoid;
            break-inside: avoid;
        }
        .print-header-row {
            display: table;
            width: 100%;
            table-layout: fixed;
            gap: 18px;
        }
        .print-logo,
        .print-logo-fallback,
        .print-company {
            display: table-cell;
            vertical-align: top;
        }
        .print-logo,
        .print-logo-fallback {
            width: 45%;
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
        .print-content > *:first-child {
            margin-top: 0;
        }
    </style>
    ' . $styleBlocks . '
</head>
<body>
    <div class="print-document">
        ' . $headerHtml . '
        <div class="print-content">' . $bodyHtml . '</div>
    </div>
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
