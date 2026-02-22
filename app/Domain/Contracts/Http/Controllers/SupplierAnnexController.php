<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\ContractPdfService;
use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Domain\Settings\Services\SettingsService;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class SupplierAnnexController
{
    public function index(): void
    {
        $this->requireAccessRole();

        $templates = $this->fetchSupplierAnnexTemplates();
        $preset = $this->loadPresetSettings();
        $form = $this->defaultFormValues($templates);

        $this->renderPage($templates, $preset, $form);
    }

    public function preview(): void
    {
        $this->requireAccessRole();

        $templates = $this->fetchSupplierAnnexTemplates();
        $preset = $this->loadPresetSettings();
        $payload = $this->formFromRequest($templates);

        if (!empty($payload['error'])) {
            $this->renderPage($templates, $preset, $payload['form'], (string) $payload['error']);
        }

        $template = $payload['template'];
        $form = $payload['form'];
        $previewHtml = $this->buildAnnexDocumentHtml($template, $form, $preset);

        Audit::record('supplier_annex.preview', 'contract_template', (int) ($template['id'] ?? 0), [
            'rows_count' => 1,
            'supplier_cui' => $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null,
            'client_cui' => $form['client_cui'] !== '' ? $form['client_cui'] : null,
        ]);

        $this->renderPage($templates, $preset, $form, '', $previewHtml);
    }

    public function download(): void
    {
        $this->requireAccessRole();

        $templates = $this->fetchSupplierAnnexTemplates();
        $preset = $this->loadPresetSettings();
        $payload = $this->formFromRequest($templates);

        if (!empty($payload['error'])) {
            $this->renderPage($templates, $preset, $payload['form'], (string) $payload['error']);
        }

        $template = $payload['template'];
        $form = $payload['form'];
        $documentHtml = $this->buildAnnexDocumentHtml($template, $form, $preset);

        $pdfBinary = (new ContractPdfService())->generatePdfBinaryFromHtml($documentHtml, 'anexa-furnizor');
        $baseFilename = $this->sanitizeFilenamePart($form['annex_title']);
        if ($baseFilename === '') {
            $baseFilename = 'anexa-furnizor';
        }

        if ($pdfBinary !== '') {
            Audit::record('supplier_annex.download', 'contract_template', (int) ($template['id'] ?? 0), [
                'rows_count' => 1,
                'mode' => 'pdf',
            ]);

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $baseFilename . '.pdf"');
            header('Content-Length: ' . strlen($pdfBinary));
            header('X-Content-Type-Options: nosniff');
            echo $pdfBinary;
            exit;
        }

        Audit::record('supplier_annex.download', 'contract_template', (int) ($template['id'] ?? 0), [
            'rows_count' => 1,
            'mode' => 'html',
        ]);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseFilename . '.html"');
        header('X-Content-Type-Options: nosniff');
        echo $documentHtml;
        exit;
    }

    private function renderPage(
        array $templates,
        array $preset,
        array $form,
        string $errorMessage = '',
        ?string $previewHtml = null
    ): void {
        Response::view('admin/contracts/supplier_annex', [
            'templates' => $templates,
            'preset' => $preset,
            'form' => $form,
            'errorMessage' => $errorMessage,
            'previewHtml' => $previewHtml,
        ]);
    }

    private function requireAccessRole(): ?\App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (
            !$user
            || !(
                $user->hasRole('super_admin')
                || $user->hasRole('admin')
                || $user->hasRole('operator')
                || $user->hasRole('contabil')
            )
        ) {
            Response::abort(403, 'Acces interzis.');
        }

        return $user;
    }

    private function fetchSupplierAnnexTemplates(): array
    {
        if (!Database::tableExists('contract_templates')) {
            return [];
        }

        $where = ['doc_kind = :doc_kind'];
        $params = ['doc_kind' => 'anexa'];

        if (Database::columnExists('contract_templates', 'is_active')) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = 1;
        }
        if (Database::columnExists('contract_templates', 'applies_to')) {
            $where[] = '(applies_to = :applies_supplier OR applies_to = :applies_both)';
            $params['applies_supplier'] = 'supplier';
            $params['applies_both'] = 'both';
        }
        if (Database::columnExists('contract_templates', 'auto_on_enrollment')) {
            $where[] = 'auto_on_enrollment = :auto_on_enrollment';
            $params['auto_on_enrollment'] = 0;
        }
        if (Database::columnExists('contract_templates', 'required_onboarding')) {
            $where[] = 'required_onboarding = :required_onboarding';
            $params['required_onboarding'] = 0;
        }

        $orderParts = [];
        if (Database::columnExists('contract_templates', 'priority')) {
            $orderParts[] = 'priority ASC';
        }
        if (Database::columnExists('contract_templates', 'created_at')) {
            $orderParts[] = 'created_at DESC';
        }
        $orderParts[] = 'id DESC';

        return Database::fetchAll(
            'SELECT * FROM contract_templates WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . implode(', ', $orderParts),
            $params
        );
    }

    private function defaultFormValues(array $templates): array
    {
        $firstTemplateId = 0;
        if (!empty($templates)) {
            $firstTemplateId = (int) ($templates[0]['id'] ?? 0);
        }

        return [
            'template_id' => $firstTemplateId,
            'supplier_cui' => '',
            'client_cui' => '',
            'annex_title' => 'Anexa furnizor',
            'annex_content_html' => '<p>Completeaza continutul anexei.</p>',
        ];
    }

    private function formFromRequest(array $templates): array
    {
        $form = $this->defaultFormValues($templates);
        $templatesById = [];
        foreach ($templates as $template) {
            $templateId = (int) ($template['id'] ?? 0);
            if ($templateId > 0) {
                $templatesById[$templateId] = $template;
            }
        }

        $templateId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        if ($templateId <= 0 || !isset($templatesById[$templateId])) {
            return [
                'error' => 'Selecteaza un template de tip anexa pentru furnizor.',
                'form' => $form,
            ];
        }

        $supplierCui = $this->normalizeCui((string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = $this->normalizeCui((string) ($_POST['client_cui'] ?? ''));
        $annexTitle = $this->sanitizeTitle((string) ($_POST['annex_title'] ?? ''));
        $annexContentHtml = $this->sanitizeLimitedRichText((string) ($_POST['annex_content_html'] ?? ''));

        $form['template_id'] = $templateId;
        $form['supplier_cui'] = $supplierCui;
        $form['client_cui'] = $clientCui;
        $form['annex_title'] = $annexTitle;
        $form['annex_content_html'] = $annexContentHtml !== '' ? $annexContentHtml : '<p></p>';

        if ($annexTitle === '') {
            return [
                'error' => 'Completeaza denumirea anexei.',
                'form' => $form,
            ];
        }

        $plainContent = trim((string) strip_tags(str_replace('<br>', "\n", $annexContentHtml)));
        if ($plainContent === '') {
            return [
                'error' => 'Completeaza continutul anexei.',
                'form' => $form,
            ];
        }

        return [
            'error' => '',
            'form' => $form,
            'template' => $templatesById[$templateId],
        ];
    }

    private function loadPresetSettings(): array
    {
        $settings = new SettingsService();
        $signaturePath = trim((string) $settings->get('annex.supplier_signature_path', ''));
        $signatureAbsolute = $this->resolveAnnexSignatureAbsolutePath($signaturePath);
        $signatureDataUri = $signatureAbsolute !== '' ? $this->imageDataUriFromPath($signatureAbsolute) : '';

        return [
            'signature_path' => $signaturePath,
            'signature_data_uri' => $signatureDataUri,
            'signature_configured' => $signatureDataUri !== '',
        ];
    }

    private function buildAnnexDocumentHtml(array $template, array $form, array $preset): string
    {
        $templateHtml = (string) ($template['html_content'] ?? '');
        if (trim($templateHtml) === '') {
            $templateHtml = '<h2>{{annex.title}}</h2><div>{{annex.content}}</div>{{annex.signature}}';
        }

        $supplierCui = $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null;
        $clientCui = $form['client_cui'] !== '' ? $form['client_cui'] : null;

        $variablesService = new ContractTemplateVariables();
        $vars = $variablesService->buildVariables(
            $supplierCui,
            $supplierCui,
            $clientCui,
            [
                'template_id' => (int) ($template['id'] ?? 0),
                'render_context' => 'admin',
                'title' => $form['annex_title'],
                'created_at' => date('Y-m-d'),
                'contract_date' => date('Y-m-d'),
                'doc_type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? 'anexa'),
                'doc_no' => 0,
                'doc_series' => '',
                'doc_full_no' => '',
            ]
        );

        $signatureHtml = '';
        if (!empty($preset['signature_data_uri'])) {
            $signatureHtml = '<div class="annex-signature-box"><img src="'
                . htmlspecialchars((string) $preset['signature_data_uri'], ENT_QUOTES, 'UTF-8')
                . '" alt="Semnatura"></div>';
        }

        $vars['annex.title'] = htmlspecialchars($form['annex_title'], ENT_QUOTES, 'UTF-8');
        $vars['annex.content'] = (string) $form['annex_content_html'];
        $vars['annex.signature'] = $signatureHtml;

        $renderer = new TemplateRenderer();
        $renderedBody = $renderer->render($templateHtml, $vars);

        if (!$this->templateHasPlaceholder($templateHtml, 'annex.title') && $form['annex_title'] !== '') {
            $renderedBody = '<h2>' . htmlspecialchars($form['annex_title'], ENT_QUOTES, 'UTF-8') . '</h2>' . $renderedBody;
        }
        if (!$this->templateHasPlaceholder($templateHtml, 'annex.content')) {
            $renderedBody .= '<div class="annex-content-block">' . $form['annex_content_html'] . '</div>';
        }
        if (!$this->templateHasPlaceholder($templateHtml, 'annex.signature') && $signatureHtml !== '') {
            $renderedBody .= $signatureHtml;
        }

        return '<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        @page { margin: 16mm 14mm; }
        body {
            margin: 0;
            color: #0f172a;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }
        .annex-shell { width: 100%; }
        .annex-body h1, .annex-body h2, .annex-body h3 {
            margin: 0 0 8px;
            line-height: 1.25;
            color: #0f172a;
        }
        .annex-body p { margin: 0 0 8px; }
        .annex-body ul, .annex-body ol { margin: 0 0 8px 18px; padding: 0; }
        .annex-body li { margin: 0 0 4px; }
        .annex-signature-box {
            margin-top: 18px;
            display: inline-block;
            border-top: 1px solid #94a3b8;
            padding-top: 8px;
        }
        .annex-signature-box img {
            max-height: 80px;
            width: auto;
            display: block;
        }
    </style>
</head>
<body>
    <div class="annex-shell"><div class="annex-body">' . $renderedBody . '</div></div>
</body>
</html>';
    }

    private function templateHasPlaceholder(string $templateHtml, string $placeholder): bool
    {
        return preg_match('/\{\{\s*' . preg_quote($placeholder, '/') . '\s*\}\}/i', $templateHtml) === 1;
    }

    private function sanitizeTitle(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);
        if (function_exists('mb_substr')) {
            $value = mb_substr((string) $value, 0, 180);
        } else {
            $value = substr((string) $value, 0, 180);
        }

        return trim((string) $value);
    }

    private function sanitizeFilenamePart(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9._-]/', '-', (string) $value);
        $value = trim((string) $value, '-.');
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 120) {
            $value = substr($value, 0, 120);
            $value = rtrim($value, '-.');
        }

        return $value;
    }

    private function normalizeCui(string $value): string
    {
        return preg_replace('/\D+/', '', $value);
    }

    private function sanitizeLimitedRichText(string $value): string
    {
        $value = str_replace("\0", '', $value);
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            $value = mb_substr($value, 0, 30000);
        } else {
            $value = substr($value, 0, 30000);
        }

        $value = strip_tags($value, '<p><h2><h3><ul><ol><li><strong><em><b><i><br>');
        $value = preg_replace('/<\s*b\s*>/i', '<strong>', (string) $value);
        $value = preg_replace('/<\s*\/\s*b\s*>/i', '</strong>', (string) $value);
        $value = preg_replace('/<\s*i\s*>/i', '<em>', (string) $value);
        $value = preg_replace('/<\s*\/\s*i\s*>/i', '</em>', (string) $value);
        $value = preg_replace('/<\s*(\/?)\s*(p|h2|h3|ul|ol|li|strong|em|br)\b[^>]*>/i', '<$1$2>', (string) $value);
        $value = preg_replace('/<br\s*\/?>/i', '<br>', (string) $value);

        return trim((string) $value);
    }

    private function resolveAnnexSignatureAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'storage/erp/')) {
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $root = realpath($basePath . '/storage/erp');
        if ($root === false) {
            return '';
        }
        $absolute = realpath($basePath . '/' . $relativePath);
        if ($absolute === false || !str_starts_with($absolute, $root) || !is_file($absolute) || !is_readable($absolute)) {
            return '';
        }

        return $absolute;
    }

    private function imageDataUriFromPath(string $absolutePath): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }
        $binary = @file_get_contents($absolutePath);
        if (!is_string($binary) || $binary === '') {
            return '';
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => '',
        };
        if ($mime === '') {
            return '';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }
}
