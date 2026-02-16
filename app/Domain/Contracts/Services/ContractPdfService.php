<?php

namespace App\Domain\Contracts\Services;

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
        $html = '';

        if ($templateId > 0 && Database::tableExists('contract_templates')) {
            $template = Database::fetchOne(
                'SELECT html_content FROM contract_templates WHERE id = :id LIMIT 1',
                ['id' => $templateId]
            );
            $html = (string) ($template['html_content'] ?? '');
        }

        if ($html === '') {
            $html = '<html><body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
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

        return $this->renderer->render($html, $vars);
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
}
