<?php

namespace App\Domain\Contracts\Http\Controllers;

use App\Domain\Users\Models\UserSupplierAccess;
use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Support\Auth;
use App\Support\Audit;
use App\Support\Database;
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

        Response::view('admin/contracts/index', [
            'templates' => $templates,
            'contracts' => $contracts,
        ]);
    }

    public function generate(): void
    {
        $user = $this->requireGenerateRole();

        $templateId = isset($_POST['template_id']) ? (int) $_POST['template_id'] : 0;
        $title = trim((string) ($_POST['title'] ?? ''));
        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));

        if ($title === '') {
            Session::flash('error', 'Completeaza titlul contractului.');
            Response::redirect('/admin/contracts');
        }

        $template = $templateId > 0 ? Database::fetchOne('SELECT * FROM contract_templates WHERE id = :id LIMIT 1', ['id' => $templateId]) : null;
        $html = $template['html_content'] ?? '';
        if ($html === '') {
            $html = '<html><body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
        }
        $variablesService = new ContractTemplateVariables();
        $renderer = new TemplateRenderer();
        $vars = $variablesService->buildVariables(
            $partnerCui !== '' ? $partnerCui : null,
            $supplierCui !== '' ? $supplierCui : null,
            $clientCui !== '' ? $clientCui : null,
            ['title' => $title, 'created_at' => date('Y-m-d')]
        );
        $html = $renderer->render($html, $vars);

        $path = $this->storeGeneratedFile($html);
        if ($path === null) {
            Session::flash('error', 'Nu pot genera fisierul.');
            Response::redirect('/admin/contracts');
        }

        Database::execute(
            'INSERT INTO contracts (template_id, partner_cui, supplier_cui, client_cui, title, status, generated_file_path, created_by_user_id, created_at)
             VALUES (:template_id, :partner_cui, :supplier_cui, :client_cui, :title, :status, :path, :user_id, :created_at)',
            [
                'template_id' => $templateId ?: null,
                'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
                'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                'client_cui' => $clientCui !== '' ? $clientCui : null,
                'title' => $title,
                'status' => 'generated',
                'path' => $path,
                'user_id' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $contractId = (int) Database::lastInsertId();
        Audit::record('contract.generated', 'contract', $contractId ?: null, []);

        Session::flash('status', 'Contract generat.');
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
            'UPDATE contracts SET signed_file_path = :path, status = :status, updated_at = :now WHERE id = :id',
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

    public function approve(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if (!$id) {
            Response::redirect('/admin/contracts');
        }

        Database::execute(
            'UPDATE contracts SET status = :status, updated_at = :now WHERE id = :id',
            [
                'status' => 'approved',
                'now' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
        Audit::record('contract.approved', 'contract', $id, []);

        Session::flash('status', 'Contract aprobat.');
        Response::redirect('/admin/contracts');
    }

    public function download(): void
    {
        $user = $this->requireContractsRole();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
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

        $path = (string) ($row['signed_file_path'] ?? $row['generated_file_path'] ?? '');
        if ($path === '') {
            Response::abort(404, 'Fisier lipsa.');
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

    private function storeGeneratedFile(string $html): ?string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $base . '/storage/uploads/contracts/generated';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $name = bin2hex(random_bytes(16)) . '.html';
        $path = $dir . '/' . $name;
        if (file_put_contents($path, $html) === false) {
            return null;
        }

        return 'storage/uploads/contracts/generated/' . $name;
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
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}
