<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Support\Audit;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class ContractTemplatesController
{
    private const STAMP_UPLOAD_SUBDIR = 'contract_templates/stamps';
    private const MAX_STAMP_UPLOAD_BYTES = 5242880;
    private const ALLOWED_STAMP_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];
    private const ALLOWED_STAMP_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    public function index(): void
    {
        $this->requireTemplateRole();

        $templates = Database::fetchAll('SELECT * FROM contract_templates ORDER BY created_at DESC, id DESC');
        $variables = (new ContractTemplateVariables())->listPlaceholders();

        Response::view('admin/contracts/templates', [
            'templates' => $templates,
            'variables' => $variables,
        ]);
    }

    public function edit(): void
    {
        $this->requireTemplateRole();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $variables = (new ContractTemplateVariables())->listPlaceholders();

        Response::view('admin/contracts/template_edit', [
            'template' => $template,
            'variables' => $variables,
        ]);
    }

    public function save(): void
    {
        $user = $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $name = trim((string) ($_POST['name'] ?? ''));
        $docType = trim((string) ($_POST['doc_type'] ?? ''));
        $docKind = trim((string) ($_POST['doc_kind'] ?? ''));
        $appliesTo = trim((string) ($_POST['applies_to'] ?? 'both'));
        $auto = !empty($_POST['auto_on_enrollment']) ? 1 : 0;
        $requiredOnboarding = !empty($_POST['required_onboarding']) ? 1 : 0;
        $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 100;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $html = (string) ($_POST['html_content'] ?? '');

        if ($docType === '') {
            $docType = $docKind;
        }

        if ($name === '' || $docKind === '' || $docType === '') {
            Session::flash('error', 'Completeaza numele si tipul template-ului.');
            Response::redirect('/admin/contract-templates');
        }

        if ($id > 0) {
            Database::execute(
                'UPDATE contract_templates
                 SET name = :name,
                     template_type = :type,
                     doc_type = :doc_type,
                     doc_kind = :doc_kind,
                     applies_to = :applies_to,
                     auto_on_enrollment = :auto_on,
                     required_onboarding = :required_onboarding,
                     priority = :priority,
                     is_active = :is_active,
                     html_content = :html,
                     updated_at = :updated_at
                 WHERE id = :id',
                [
                    'name' => $name,
                    'type' => $docType,
                    'doc_type' => $docType,
                    'doc_kind' => $docKind,
                    'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                    'auto_on' => $auto,
                    'required_onboarding' => $requiredOnboarding,
                    'priority' => $priority,
                    'is_active' => $isActive,
                    'html' => $html,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'id' => $id,
                ]
            );
            Session::flash('status', 'Template actualizat.');
            Response::redirect('/admin/contract-templates');
        }

        Database::execute(
            'INSERT INTO contract_templates (
                name,
                template_type,
                doc_type,
                doc_kind,
                applies_to,
                auto_on_enrollment,
                required_onboarding,
                priority,
                is_active,
                html_content,
                created_by_user_id,
                created_at
            ) VALUES (
                :name,
                :type,
                :doc_type,
                :doc_kind,
                :applies_to,
                :auto_on,
                :required_onboarding,
                :priority,
                :is_active,
                :html,
                :user_id,
                :created_at
            )',
            [
                'name' => $name,
                'type' => $docType,
                'doc_type' => $docType,
                'doc_kind' => $docKind,
                'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                'auto_on' => $auto,
                'required_onboarding' => $requiredOnboarding,
                'priority' => $priority,
                'is_active' => $isActive,
                'html' => $html,
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
        Session::flash('status', 'Template creat.');
        Response::redirect('/admin/contract-templates');
    }

    public function update(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $docType = trim((string) ($_POST['doc_type'] ?? ''));
        $docKind = trim((string) ($_POST['doc_kind'] ?? ''));
        $appliesTo = trim((string) ($_POST['applies_to'] ?? 'both'));
        $auto = !empty($_POST['auto_on_enrollment']) ? 1 : 0;
        $requiredOnboarding = !empty($_POST['required_onboarding']) ? 1 : 0;
        $priority = isset($_POST['priority']) ? (int) $_POST['priority'] : 100;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $html = (string) ($_POST['html_content'] ?? '');

        if ($docType === '') {
            $docType = $docKind;
        }

        if ($name === '' || $docKind === '' || $docType === '') {
            Session::flash('error', 'Completeaza numele si tipul documentului.');
            Response::redirect('/admin/contract-templates/edit?id=' . $id);
        }

        Database::execute(
            'UPDATE contract_templates
             SET name = :name,
                 template_type = :type,
                 doc_type = :doc_type,
                 doc_kind = :doc_kind,
                 applies_to = :applies_to,
                 auto_on_enrollment = :auto_on,
                 required_onboarding = :required_onboarding,
                 priority = :priority,
                 is_active = :is_active,
                 html_content = :html,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'name' => $name,
                'type' => $docType,
                'doc_type' => $docType,
                'doc_kind' => $docKind,
                'applies_to' => in_array($appliesTo, ['client', 'supplier', 'both'], true) ? $appliesTo : 'both',
                'auto_on' => $auto,
                'required_onboarding' => $requiredOnboarding,
                'priority' => $priority,
                'is_active' => $isActive,
                'html' => $html,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Session::flash('status', 'Model actualizat.');
        Response::redirect('/admin/contract-templates/edit?id=' . $id);
    }

    public function duplicate(): void
    {
        $user = $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        Database::execute(
            'INSERT INTO contract_templates (
                name,
                template_type,
                doc_type,
                doc_kind,
                applies_to,
                auto_on_enrollment,
                required_onboarding,
                priority,
                is_active,
                html_content,
                created_by_user_id,
                created_at
            ) VALUES (
                :name,
                :type,
                :doc_type,
                :doc_kind,
                :applies_to,
                :auto_on,
                :required_onboarding,
                :priority,
                :is_active,
                :html,
                :user_id,
                :created_at
            )',
            [
                'name' => (string) ($template['name'] ?? '') . ' (copie)',
                'type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? ''),
                'doc_type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? ''),
                'doc_kind' => (string) ($template['doc_kind'] ?? 'contract'),
                'applies_to' => (string) ($template['applies_to'] ?? 'both'),
                'auto_on' => !empty($template['auto_on_enrollment']) ? 1 : 0,
                'required_onboarding' => !empty($template['required_onboarding']) ? 1 : 0,
                'priority' => (int) ($template['priority'] ?? 100),
                'is_active' => !empty($template['is_active']) ? 1 : 0,
                'html' => (string) ($template['html_content'] ?? ''),
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('status', 'Model duplicat.');
        Response::redirect('/admin/contract-templates');
    }

    public function preview(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        $template = null;
        if ($id) {
            $template = Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        }
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));

        $variablesService = new ContractTemplateVariables();
        $renderer = new TemplateRenderer();
        $vars = $variablesService->buildVariables(
            $partnerCui !== '' ? $partnerCui : null,
            $supplierCui !== '' ? $supplierCui : null,
            $clientCui !== '' ? $clientCui : null,
            [
                'template_id' => (int) ($template['id'] ?? 0),
                'render_context' => 'admin',
                'title' => (string) ($template['name'] ?? ''),
                'created_at' => date('Y-m-d'),
                'contract_date' => date('Y-m-d'),
                'doc_type' => (string) ($template['doc_type'] ?? $template['template_type'] ?? 'contract'),
                'doc_no' => 123,
                'doc_series' => 'CTR',
                'doc_full_no' => 'CTR-000123',
            ]
        );
        $rendered = $renderer->render((string) ($template['html_content'] ?? ''), $vars);

        Response::view('admin/contracts/template_preview', [
            'template' => $template,
            'rendered' => $rendered,
            'sample' => [
                'partner_cui' => $partnerCui,
                'supplier_cui' => $supplierCui,
                'client_cui' => $clientCui,
            ],
        ]);
    }

    public function uploadStamp(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT id FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $stored = $this->storeStampUpload($_FILES['stamp_image'] ?? null);
        if ($stored === null) {
            Session::flash('error', 'Fisier invalid. Sunt acceptate PNG/JPG/JPEG/WEBP (maxim 5MB).');
            Response::redirect('/admin/contract-templates/edit?id=' . $id);
        }

        $metaJson = json_encode([
            'original_name' => $stored['original_name'],
            'size' => $stored['size'],
            'mime' => $stored['mime'],
            'uploaded_at' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        Database::execute(
            'UPDATE contract_templates
             SET stamp_image_path = :path,
                 stamp_image_meta = :meta,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'path' => $stored['path'],
                'meta' => $metaJson !== false ? $metaJson : null,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Audit::record('contract_template.stamp_uploaded', 'contract_template', $id, ['rows_count' => 1]);
        Session::flash('status', 'Stampila a fost incarcata.');
        Response::redirect('/admin/contract-templates/edit?id=' . $id);
    }

    public function removeStamp(): void
    {
        $this->requireTemplateRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/contract-templates');
        }

        $template = Database::fetchOne('SELECT id FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        Database::execute(
            'UPDATE contract_templates
             SET stamp_image_path = NULL,
                 stamp_image_meta = NULL,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Audit::record('contract_template.stamp_removed', 'contract_template', $id, ['rows_count' => 1]);
        Session::flash('status', 'Stampila a fost stearsa din model.');
        Response::redirect('/admin/contract-templates/edit?id=' . $id);
    }

    public function stamp(): void
    {
        $this->requireTemplateRole();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::abort(404, 'Stampila indisponibila.');
        }

        $template = Database::fetchOne(
            'SELECT stamp_image_path FROM contract_templates WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
        if (!$template) {
            Response::abort(404, 'Model inexistent.');
        }

        $path = trim((string) ($template['stamp_image_path'] ?? ''));
        $absolute = $this->resolveStampAbsolutePath($path);
        if ($absolute === '' || !is_file($absolute) || !is_readable($absolute)) {
            Response::abort(404, 'Stampila indisponibila.');
        }

        $mime = $this->detectImageMime($absolute);
        if ($mime === null || !in_array($mime, self::ALLOWED_STAMP_MIMES, true)) {
            Response::abort(404, 'Stampila indisponibila.');
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($absolute));
        header('Cache-Control: private, max-age=300');
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
        exit;
    }

    private function requireTemplateRole(): ?\App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || !($user->hasRole('super_admin') || $user->hasRole('admin'))) {
            Response::abort(403, 'Acces interzis.');
        }

        return $user;
    }

    private function storeStampUpload(?array $file): ?array
    {
        if (!$file || !isset($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return null;
        }
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > self::MAX_STAMP_UPLOAD_BYTES) {
            return null;
        }

        $originalName = (string) ($file['name'] ?? '');
        $extRaw = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/i', '', $extRaw);
        if ($ext === '' || !in_array($ext, self::ALLOWED_STAMP_EXTENSIONS, true)) {
            return null;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_readable($tmp)) {
            return null;
        }

        $mime = $this->detectImageMime($tmp);
        if ($mime === null || !in_array($mime, self::ALLOWED_STAMP_MIMES, true)) {
            return null;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $basePath . '/storage/uploads/' . self::STAMP_UPLOAD_SUBDIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            $targetName = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Throwable $exception) {
            return null;
        }
        $target = $dir . '/' . $targetName;
        if (!move_uploaded_file($tmp, $target)) {
            return null;
        }

        return [
            'path' => 'storage/uploads/' . self::STAMP_UPLOAD_SUBDIR . '/' . $targetName,
            'mime' => $mime,
            'size' => $size,
            'original_name' => $originalName,
        ];
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

        $target = realpath($basePath . '/' . $relativePath);
        if ($target === false || !str_starts_with($target, $root)) {
            return '';
        }

        return $target;
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
}
