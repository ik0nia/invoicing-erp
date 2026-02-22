<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\ContractPdfService;
use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\DocumentNumberService;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Domain\Settings\Services\SettingsService;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

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
        $previewHtml = $this->buildAnnexDocumentHtml($template, $form, $preset, []);

        Audit::record('supplier_annex.preview', 'contract_template', (int) ($template['id'] ?? 0), [
            'rows_count' => 1,
            'supplier_cui' => $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null,
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
        $documentHtml = $this->buildAnnexDocumentHtml($template, $form, $preset, []);

        $pdfBinary = (new ContractPdfService())->generatePdfBinaryFromHtml($documentHtml, 'anexa-furnizor');
        $baseFilename = $this->sanitizeFilenamePart($form['annex_title']);
        if ($baseFilename === '') {
            $baseFilename = 'anexa-furnizor';
        }

        if ($pdfBinary !== '') {
            Audit::record('supplier_annex.download', 'contract_template', (int) ($template['id'] ?? 0), [
                'rows_count' => 1,
                'mode' => 'pdf',
                'supplier_cui' => $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null,
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
            'supplier_cui' => $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null,
        ]);
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $baseFilename . '.html"');
        header('X-Content-Type-Options: nosniff');
        echo $documentHtml;
        exit;
    }

    public function generateDocument(): void
    {
        $user = $this->requireAccessRole();

        $templates = $this->fetchSupplierAnnexTemplates();
        $preset = $this->loadPresetSettings();
        $payload = $this->formFromRequest($templates);
        if (!empty($payload['error'])) {
            $this->renderPage($templates, $preset, $payload['form'], (string) $payload['error']);
        }

        $template = $payload['template'];
        $form = $payload['form'];
        try {
            $allocatedNumber = $this->allocateSupplierDocNumber($template);
        } catch (\Throwable $exception) {
            $this->renderPage($templates, $preset, $form, 'Nu am putut aloca numarul din registrul de furnizori.');
        }
        $allocatedNumber['date'] = date('Y-m-d');

        $documentHtml = $this->buildAnnexDocumentHtml($template, $form, $preset, $allocatedNumber);
        $docType = $this->resolveTemplateDocType($template);
        $pdfBinary = (new ContractPdfService())->generatePdfBinaryFromHtml($documentHtml, 'anexa-furnizor');
        if ($pdfBinary === '') {
            $this->rollbackAllocatedSupplierDocNumber($allocatedNumber);
            $this->renderPage($templates, $preset, $form, 'Documentul nu a putut fi generat PDF momentan.');
        }

        $metadataJson = json_encode([
            'doc_kind' => 'anexa',
            'source' => 'supplier_annex_generator',
            'annex_title' => (string) $form['annex_title'],
            'annex_content_html' => (string) $form['annex_content_html'],
        ], JSON_UNESCAPED_UNICODE);

        $supplierCui = preg_replace('/\D+/', '', (string) ($form['supplier_cui'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $inserted = Database::execute(
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
                'template_id' => ((int) ($template['id'] ?? 0)) > 0 ? (int) ($template['id'] ?? 0) : null,
                'partner_cui' => $supplierCui !== '' ? $supplierCui : null,
                'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                'client_cui' => null,
                'title' => (string) ($form['annex_title'] ?? 'Anexa'),
                'doc_type' => $docType,
                'contract_date' => (string) ($allocatedNumber['date'] ?? date('Y-m-d')),
                'doc_no' => isset($allocatedNumber['no']) ? (int) ($allocatedNumber['no'] ?? 0) : null,
                'doc_series' => isset($allocatedNumber['series']) && (string) ($allocatedNumber['series'] ?? '') !== ''
                    ? (string) ($allocatedNumber['series'] ?? '')
                    : null,
                'doc_full_no' => isset($allocatedNumber['full_no']) && (string) ($allocatedNumber['full_no'] ?? '') !== ''
                    ? (string) ($allocatedNumber['full_no'] ?? '')
                    : null,
                'doc_assigned_at' => isset($allocatedNumber['no']) ? $now : null,
                'required_onboarding' => 0,
                'status' => 'generated',
                'metadata_json' => $metadataJson !== false ? $metadataJson : null,
                'user_id' => $user ? $user->id : null,
                'created_at' => $now,
            ]
        );
        if (!$inserted) {
            $this->rollbackAllocatedSupplierDocNumber($allocatedNumber);
            $this->renderPage($templates, $preset, $form, 'Documentul a fost numerotat dar nu a putut fi salvat.');
        }
        $contractId = (int) Database::lastInsertId();
        if ($contractId <= 0) {
            $this->rollbackAllocatedSupplierDocNumber($allocatedNumber);
            $this->renderPage($templates, $preset, $form, 'Documentul a fost numerotat dar nu a putut fi salvat.');
        }
        Audit::record('contract.number_assigned', 'contract', $contractId, [
            'doc_type' => $docType,
            'registry_scope' => DocumentNumberService::REGISTRY_SCOPE_SUPPLIER,
            'doc_full_no' => (string) ($allocatedNumber['full_no'] ?? ''),
            'rows_count' => 1,
        ]);

        $storedPath = $this->storeGeneratedPdfBinary($pdfBinary, $docType, $contractId);
        if ($storedPath === '') {
            Database::execute('DELETE FROM contracts WHERE id = :id', ['id' => $contractId]);
            $this->rollbackAllocatedSupplierDocNumber($allocatedNumber);
            $this->renderPage($templates, $preset, $form, 'Documentul nu a putut fi salvat complet. Inregistrarea a fost anulata.');
        }

        $setParts = [];
        $params = [
            'id' => $contractId,
            'updated_at' => $now,
        ];
        if (Database::columnExists('contracts', 'generated_pdf_path')) {
            $setParts[] = 'generated_pdf_path = :generated_pdf_path';
            $params['generated_pdf_path'] = $storedPath;
        }
        if (Database::columnExists('contracts', 'generated_file_path')) {
            $setParts[] = 'generated_file_path = :generated_file_path';
            $params['generated_file_path'] = $storedPath;
        }
        if (Database::columnExists('contracts', 'updated_at')) {
            $setParts[] = 'updated_at = :updated_at';
        }
        if (!empty($setParts)) {
            Database::execute(
                'UPDATE contracts SET ' . implode(', ', $setParts) . ' WHERE id = :id',
                $params
            );
        }

        Audit::record('supplier_annex.document_generated', 'contract', $contractId, [
            'rows_count' => 1,
            'doc_full_no' => (string) ($allocatedNumber['full_no'] ?? ''),
            'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
        ]);

        $docFullNo = trim((string) ($allocatedNumber['full_no'] ?? ''));
        $message = $docFullNo !== ''
            ? ('Documentul a fost generat si inregistrat cu numarul ' . $docFullNo . '.')
            : 'Documentul a fost generat si inregistrat in registrul de furnizori.';
        Session::flash('status', $message);
        Response::redirect('/admin/contracts');
    }

    public function deleteLastGeneratedDocument(): void
    {
        $this->requireAccessRole();
        $redirectPath = $this->resolveDeleteRedirectPath((string) ($_POST['return_to'] ?? ''));

        if (!Database::tableExists('contracts')) {
            Session::flash('error', 'Documentele nu sunt disponibile momentan.');
            Response::redirect($redirectPath);
        }
        if (!Database::tableExists('document_registry')) {
            Session::flash('error', 'Registrul de furnizori nu este disponibil momentan.');
            Response::redirect($redirectPath);
        }

        $requestedContractId = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        $latest = $this->fetchLastSupplementaryGeneratedDocument();
        if ($latest === null) {
            Session::flash('error', 'Nu exista documente suplimentare generate pentru stergere.');
            Response::redirect($redirectPath);
        }
        $latestId = (int) ($latest['id'] ?? 0);
        if ($requestedContractId > 0 && $latestId > 0 && $requestedContractId !== $latestId) {
            Session::flash('error', 'Intre timp s-a schimbat ultimul document. Reincarca pagina si incearca din nou.');
            Response::redirect($redirectPath);
        }

        $latestDocNo = (int) ($latest['doc_no'] ?? 0);
        if ($latestDocNo <= 0) {
            Session::flash('error', 'Ultimul document nu are numar de registru valid.');
            Response::redirect($redirectPath);
        }

        $numberService = new DocumentNumberService();
        $registryDocType = $numberService->registryKey(DocumentNumberService::REGISTRY_SCOPE_SUPPLIER);
        $deletedDoc = null;
        $rollbackNo = 0;

        $pdo = Database::pdo();
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $docStmt = $pdo->prepare(
                'SELECT id, doc_no, doc_full_no, generated_pdf_path, generated_file_path
                 FROM contracts
                 WHERE id = :id
                   AND status = :status
                   AND metadata_json LIKE :source
                 LIMIT 1
                 FOR UPDATE'
            );
            $docStmt->execute([
                'id' => $latestId,
                'status' => 'generated',
                'source' => '%supplier_annex_generator%',
            ]);
            $lockedDoc = $docStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$lockedDoc) {
                throw new \RuntimeException('Documentul nu mai este disponibil pentru stergere.');
            }

            $lockedDocNo = (int) ($lockedDoc['doc_no'] ?? 0);
            if ($lockedDocNo <= 0) {
                throw new \RuntimeException('Documentul nu are numar de registru valid.');
            }

            $registryStmt = $pdo->prepare(
                'SELECT doc_type, next_no, start_no
                 FROM document_registry
                 WHERE doc_type = :doc_type
                 LIMIT 1
                 FOR UPDATE'
            );
            $registryStmt->execute(['doc_type' => $registryDocType]);
            $registryRow = $registryStmt->fetch(\PDO::FETCH_ASSOC);
            if (!$registryRow) {
                throw new \RuntimeException('Registrul de furnizori nu este disponibil.');
            }

            $currentNextNo = (int) ($registryRow['next_no'] ?? 0);
            $expectedNextNo = $lockedDocNo + 1;
            if ($currentNextNo !== $expectedNextNo) {
                throw new \RuntimeException('Poti sterge doar ultimul document generat din registrul de furnizori.');
            }

            $startNo = max(1, (int) ($registryRow['start_no'] ?? 1));
            $rollbackNo = max($startNo, $lockedDocNo);

            $deleteStmt = $pdo->prepare('DELETE FROM contracts WHERE id = :id LIMIT 1');
            $deleteStmt->execute(['id' => (int) ($lockedDoc['id'] ?? 0)]);

            $updateStmt = $pdo->prepare(
                'UPDATE document_registry
                 SET next_no = :next_no,
                     updated_at = :updated_at
                 WHERE doc_type = :doc_type'
            );
            $updateStmt->execute([
                'next_no' => $rollbackNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $registryDocType,
            ]);

            $pdo->commit();
            $deletedDoc = $lockedDoc;
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Session::flash('error', $exception->getMessage());
            Response::redirect($redirectPath);
        }

        if (is_array($deletedDoc)) {
            $generatedPdfPath = trim((string) ($deletedDoc['generated_pdf_path'] ?? ''));
            $generatedFilePath = trim((string) ($deletedDoc['generated_file_path'] ?? ''));
            if ($generatedPdfPath !== '') {
                $this->deleteUploadFile($generatedPdfPath);
            }
            if ($generatedFilePath !== '' && $generatedFilePath !== $generatedPdfPath) {
                $this->deleteUploadFile($generatedFilePath);
            }
        }

        Audit::record('supplier_annex.document_deleted', 'contract', (int) ($deletedDoc['id'] ?? 0), [
            'rows_count' => 1,
            'doc_full_no' => (string) ($deletedDoc['doc_full_no'] ?? ''),
            'rollback_next_no' => $rollbackNo,
        ]);
        Session::flash('status', 'Ultimul document suplimentar a fost sters. Contorul registrului de furnizori a fost readus la pozitia corecta.');
        Response::redirect($redirectPath);
    }

    private function renderPage(
        array $templates,
        array $preset,
        array $form,
        string $errorMessage = '',
        ?string $previewHtml = null
    ): void {
        $lastSupplementaryDocumentState = $this->buildLastSupplementaryDocumentState();
        Response::view('admin/contracts/supplier_annex', [
            'templates' => $templates,
            'preset' => $preset,
            'form' => $form,
            'errorMessage' => $errorMessage,
            'previewHtml' => $previewHtml,
            'lastSupplementaryDocumentState' => $lastSupplementaryDocumentState,
        ]);
    }

    private function buildLastSupplementaryDocumentState(): array
    {
        $document = $this->fetchLastSupplementaryGeneratedDocument();
        if ($document === null) {
            return [
                'document' => null,
                'can_delete' => false,
                'message' => 'Nu exista inca documente suplimentare generate.',
            ];
        }

        $docNo = (int) ($document['doc_no'] ?? 0);
        if ($docNo <= 0) {
            return [
                'document' => $document,
                'can_delete' => false,
                'message' => 'Documentul nu are numar de registru valid.',
            ];
        }

        $numberService = new DocumentNumberService();
        $registryDocType = $numberService->registryKey(DocumentNumberService::REGISTRY_SCOPE_SUPPLIER);
        if (!Database::tableExists('document_registry')) {
            return [
                'document' => $document,
                'can_delete' => false,
                'message' => 'Registrul documentelor nu este disponibil.',
            ];
        }

        $registryRow = Database::fetchOne(
            'SELECT next_no, start_no
             FROM document_registry
             WHERE doc_type = :doc_type
             LIMIT 1',
            ['doc_type' => $registryDocType]
        );
        if (!$registryRow) {
            return [
                'document' => $document,
                'can_delete' => false,
                'message' => 'Registrul de furnizori nu este initializat.',
            ];
        }

        $currentNextNo = (int) ($registryRow['next_no'] ?? 0);
        $expectedNextNo = $docNo + 1;
        if ($currentNextNo !== $expectedNextNo) {
            return [
                'document' => $document,
                'can_delete' => false,
                'message' => 'Ultimul document din registru este altul. Se poate sterge doar documentul suplimentar cel mai recent.',
            ];
        }

        return [
            'document' => $document,
            'can_delete' => true,
            'message' => 'Stergerea va reveni contorul registrului la numarul acestui document.',
        ];
    }

    private function fetchLastSupplementaryGeneratedDocument(): ?array
    {
        if (!Database::tableExists('contracts')) {
            return null;
        }

        $row = Database::fetchOne(
            'SELECT id, title, doc_no, doc_series, doc_full_no, contract_date, supplier_cui, created_at, status
             FROM contracts
             WHERE status = :status
               AND doc_no IS NOT NULL
               AND doc_no > 0
               AND metadata_json LIKE :source
             ORDER BY doc_no DESC, id DESC
             LIMIT 1',
            [
                'status' => 'generated',
                'source' => '%supplier_annex_generator%',
            ]
        );

        return $row ?: null;
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
        $annexTitle = $this->sanitizeTitle((string) ($_POST['annex_title'] ?? ''));
        $annexContentHtml = $this->sanitizeLimitedRichText((string) ($_POST['annex_content_html'] ?? ''));

        $form['template_id'] = $templateId;
        $form['supplier_cui'] = $supplierCui;
        $form['annex_title'] = $annexTitle;
        $form['annex_content_html'] = $annexContentHtml !== '' ? $annexContentHtml : '<p></p>';

        if ($supplierCui === '') {
            return [
                'error' => 'Selecteaza furnizorul.',
                'form' => $form,
            ];
        }

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

    private function buildAnnexDocumentHtml(array $template, array $form, array $preset, array $docContext = []): string
    {
        $templateHtml = (string) ($template['html_content'] ?? '');
        if (trim($templateHtml) === '') {
            $templateHtml = '<h2>{{annex.title}}</h2><div>{{annex.content}}</div>{{annex.signature}}';
        }

        $supplierCui = $form['supplier_cui'] !== '' ? $form['supplier_cui'] : null;
        $docType = $this->resolveTemplateDocType($template);
        $docNo = isset($docContext['no']) ? (int) $docContext['no'] : 0;
        $docSeries = trim((string) ($docContext['series'] ?? ''));
        $docFullNo = trim((string) ($docContext['full_no'] ?? ''));
        $docDate = trim((string) ($docContext['date'] ?? date('Y-m-d')));

        $variablesService = new ContractTemplateVariables();
        $vars = $variablesService->buildVariables(
            $supplierCui,
            $supplierCui,
            null,
            [
                'template_id' => (int) ($template['id'] ?? 0),
                'render_context' => 'admin',
                'title' => $form['annex_title'],
                'created_at' => $docDate,
                'contract_date' => $docDate,
                'doc_type' => $docType,
                'doc_no' => $docNo,
                'doc_series' => $docSeries,
                'doc_full_no' => $docFullNo,
                'template_applies_to' => (string) ($template['applies_to'] ?? 'supplier'),
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

    private function resolveTemplateDocType(array $template): string
    {
        $docType = trim((string) ($template['doc_type'] ?? $template['template_type'] ?? 'anexa'));
        $docType = strtolower((string) preg_replace('/[^a-zA-Z0-9_.-]/', '', $docType));
        if ($docType === '') {
            $docType = 'anexa';
        }

        return $docType;
    }

    private function allocateSupplierDocNumber(array $template): array
    {
        $service = new DocumentNumberService();
        $docType = $this->resolveTemplateDocType($template);

        return $service->allocateNumber($docType, [
            'registry_scope' => DocumentNumberService::REGISTRY_SCOPE_SUPPLIER,
        ]);
    }

    private function storeGeneratedPdfBinary(string $pdfBinary, string $docType, int $contractId): string
    {
        if ($pdfBinary === '' || $contractId <= 0) {
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $outputDir = $basePath . '/storage/uploads/contracts';
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }
        if (!is_dir($outputDir) || !is_writable($outputDir)) {
            return '';
        }

        $prefix = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '-', $docType), '-'));
        if ($prefix === '') {
            $prefix = 'document';
        }

        try {
            $name = $prefix . '-' . $contractId . '-' . bin2hex(random_bytes(8)) . '.pdf';
        } catch (\Throwable $exception) {
            $name = $prefix . '-' . $contractId . '-' . uniqid('', true) . '.pdf';
            $name = preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) $name);
        }

        $absolutePath = $outputDir . '/' . $name;
        if (@file_put_contents($absolutePath, $pdfBinary) === false || !is_file($absolutePath) || filesize($absolutePath) <= 0) {
            @unlink($absolutePath);
            return '';
        }

        return 'storage/uploads/contracts/' . $name;
    }

    private function rollbackAllocatedSupplierDocNumber(array $allocatedNumber): void
    {
        $allocatedNo = isset($allocatedNumber['no']) ? (int) ($allocatedNumber['no'] ?? 0) : 0;
        if ($allocatedNo <= 0 || !Database::tableExists('document_registry')) {
            return;
        }

        $registryDocType = (new DocumentNumberService())->registryKey(DocumentNumberService::REGISTRY_SCOPE_SUPPLIER);
        $pdo = Database::pdo();
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }

            $stmt = $pdo->prepare(
                'SELECT next_no, start_no
                 FROM document_registry
                 WHERE doc_type = :doc_type
                 LIMIT 1
                 FOR UPDATE'
            );
            $stmt->execute(['doc_type' => $registryDocType]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                $pdo->rollBack();
                return;
            }

            $currentNextNo = (int) ($row['next_no'] ?? 0);
            if ($currentNextNo !== ($allocatedNo + 1)) {
                $pdo->rollBack();
                return;
            }

            $startNo = max(1, (int) ($row['start_no'] ?? 1));
            $rollbackNo = max($startNo, $allocatedNo);
            $update = $pdo->prepare(
                'UPDATE document_registry
                 SET next_no = :next_no,
                     updated_at = :updated_at
                 WHERE doc_type = :doc_type'
            );
            $update->execute([
                'next_no' => $rollbackNo,
                'updated_at' => date('Y-m-d H:i:s'),
                'doc_type' => $registryDocType,
            ]);

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }

    private function resolveDeleteRedirectPath(string $requestedPath): string
    {
        $requestedPath = trim($requestedPath);
        if ($requestedPath === '') {
            return '/admin/anexe-furnizor';
        }
        $path = parse_url($requestedPath, PHP_URL_PATH);
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return '/admin/anexe-furnizor';
        }

        return in_array($path, ['/admin/anexe-furnizor', '/admin/contracts'], true)
            ? $path
            : '/admin/anexe-furnizor';
    }

    private function deleteUploadFile(string $relativePath): void
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'storage/uploads/')) {
            return;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $root = realpath($basePath . '/storage/uploads');
        if ($root === false) {
            return;
        }
        $absolutePath = realpath($basePath . '/' . $relativePath);
        if ($absolutePath === false || !str_starts_with($absolutePath, $root) || !is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
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
