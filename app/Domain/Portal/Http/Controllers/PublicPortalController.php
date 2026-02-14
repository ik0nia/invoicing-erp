<?php

namespace App\Domain\Portal\Http\Controllers;

use App\Support\Audit;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Response;
use App\Support\TokenService;

class PublicPortalController
{
    public function index(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $hash = TokenService::hashToken($token);
        if (!$this->throttle($hash)) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $link = $this->findActiveLink($hash);
        if (!$link) {
            Response::view('public/portal', [
                'error' => 'Link invalid sau expirat.',
            ], 'layouts/guest');
        }

        $permissions = $this->decodePermissions($link['permissions_json'] ?? null);
        if (empty($permissions['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }

        $scope = $this->resolveScope($link);
        $contracts = $this->fetchContracts($scope);
        $relationDocs = $this->fetchRelationDocuments($scope);

        Audit::record('portal.link_use', 'portal_link', (int) $link['id'], [
            'rows_count' => 1,
        ]);

        Response::view('public/portal', [
            'link' => $link,
            'permissions' => $permissions,
            'contracts' => $contracts,
            'relationDocs' => $relationDocs,
            'scope' => $scope,
            'token' => $token,
        ], 'layouts/guest');
    }

    public function download(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $hash = TokenService::hashToken($token);
        if (!$this->throttle($hash)) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $link = $this->findActiveLink($hash);
        if (!$link) {
            Response::abort(404);
        }

        $permissions = $this->decodePermissions($link['permissions_json'] ?? null);
        if (empty($permissions['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }

        $type = trim((string) ($_GET['type'] ?? ''));
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0 || !in_array($type, ['contract', 'relation'], true)) {
            Response::abort(404);
        }

        $scope = $this->resolveScope($link);

        if ($type === 'contract') {
            $row = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $id]);
            if (!$row || !$this->contractAllowed($row, $scope)) {
                Response::abort(403, 'Acces interzis.');
            }
            $path = (string) ($row['signed_file_path'] ?? $row['generated_file_path'] ?? '');
            if ($path === '') {
                Response::abort(404);
            }
            Audit::record('portal.download', 'contract', $id, []);
            $this->streamFile($path);
        }

        $row = Database::fetchOne('SELECT * FROM relation_documents WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row || !$this->relationAllowed($row, $scope)) {
            Response::abort(403, 'Acces interzis.');
        }
        Audit::record('portal.download', 'relation_document', $id, []);
        $this->streamFile((string) ($row['file_path'] ?? ''));
    }

    public function upload(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $hash = TokenService::hashToken($token);
        if (!$this->throttle($hash)) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $link = $this->findActiveLink($hash);
        if (!$link) {
            Response::abort(404);
        }

        $permissions = $this->decodePermissions($link['permissions_json'] ?? null);
        $scope = $this->resolveScope($link);

        $uploadType = trim((string) ($_POST['upload_type'] ?? ''));
        if ($uploadType === 'signed') {
            if (empty($permissions['can_upload_signed'])) {
                Response::abort(403, 'Acces interzis.');
            }
            $contractId = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
            if (!$contractId) {
                Response::abort(404);
            }
            $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $contractId]);
            if (!$contract || !$this->contractAllowed($contract, $scope)) {
                Response::abort(403, 'Acces interzis.');
            }
            $path = $this->storeUpload($_FILES['file'] ?? null, 'contracts/signed');
            if ($path === null) {
                Response::abort(400, 'Fisier invalid.');
            }
            Database::execute(
                'UPDATE contracts SET signed_file_path = :path, status = :status, updated_at = :now WHERE id = :id',
                [
                    'path' => $path,
                    'status' => 'signed_uploaded',
                    'now' => date('Y-m-d H:i:s'),
                    'id' => $contractId,
                ]
            );
            Audit::record('portal.upload', 'contract', $contractId, []);
            Audit::record('contract.signed_uploaded', 'contract', $contractId, []);
            Response::redirect('/portal/' . $token);
        }

        if ($uploadType === 'custom') {
            if (empty($permissions['can_upload_custom'])) {
                Response::abort(403, 'Acces interzis.');
            }
            $supplier = preg_replace('/\D+/', '', (string) ($_POST['relation_supplier_cui'] ?? ''));
            $client = preg_replace('/\D+/', '', (string) ($_POST['relation_client_cui'] ?? ''));
            $title = trim((string) ($_POST['title'] ?? 'Document'));

            if ($scope['type'] === 'relation') {
                $supplier = $scope['supplier_cui'];
                $client = $scope['client_cui'];
            }

            if ($supplier === '' || $client === '') {
                Response::abort(400, 'Relatie invalida.');
            }
            if (!$this->relationAllowed(['supplier_cui' => $supplier, 'client_cui' => $client], $scope)) {
                Response::abort(403, 'Acces interzis.');
            }

            $path = $this->storeUpload($_FILES['file'] ?? null, 'relations/custom');
            if ($path === null) {
                Response::abort(400, 'Fisier invalid.');
            }
            $meta = [
                'original_name' => $_FILES['file']['name'] ?? '',
            ];
            Database::execute(
                'INSERT INTO relation_documents (supplier_cui, client_cui, title, file_path, metadata_json, created_at)
                 VALUES (:supplier, :client, :title, :path, :meta, :created_at)',
                [
                    'supplier' => $supplier,
                    'client' => $client,
                    'title' => $title !== '' ? $title : 'Document',
                    'path' => $path,
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
            $docId = (int) Database::lastInsertId();
            Audit::record('portal.upload', 'relation_document', $docId ?: null, []);
            Audit::record('relation.document_uploaded', 'relation_document', $docId ?: null, []);
            Response::redirect('/portal/' . $token);
        }

        Response::abort(400, 'Upload invalid.');
    }

    private function findActiveLink(string $hash): ?array
    {
        $row = Database::fetchOne(
            'SELECT * FROM portal_links WHERE token_hash = :hash LIMIT 1',
            ['hash' => $hash]
        );
        if (!$row) {
            return null;
        }
        if (($row['status'] ?? '') !== 'active') {
            return null;
        }
        $expires = $row['expires_at'] ?? null;
        if ($expires && strtotime((string) $expires) < time()) {
            return null;
        }

        return $row;
    }

    private function decodePermissions(mixed $raw): array
    {
        if (!$raw) {
            return [
                'can_view' => false,
                'can_upload_signed' => false,
                'can_upload_custom' => false,
            ];
        }
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            return [
                'can_view' => false,
                'can_upload_signed' => false,
                'can_upload_custom' => false,
            ];
        }

        return [
            'can_view' => !empty($decoded['can_view']),
            'can_upload_signed' => !empty($decoded['can_upload_signed']),
            'can_upload_custom' => !empty($decoded['can_upload_custom']),
        ];
    }

    private function resolveScope(array $link): array
    {
        if (!empty($link['relation_supplier_cui']) && !empty($link['relation_client_cui'])) {
            return [
                'type' => 'relation',
                'supplier_cui' => (string) $link['relation_supplier_cui'],
                'client_cui' => (string) $link['relation_client_cui'],
            ];
        }

        if (($link['owner_type'] ?? '') === 'supplier') {
            return [
                'type' => 'supplier',
                'supplier_cui' => (string) $link['owner_cui'],
            ];
        }

        return [
            'type' => 'client',
            'client_cui' => (string) $link['owner_cui'],
        ];
    }

    private function fetchContracts(array $scope): array
    {
        if ($scope['type'] === 'relation') {
            return Database::fetchAll(
                'SELECT * FROM contracts WHERE supplier_cui = :supplier AND client_cui = :client ORDER BY created_at DESC, id DESC',
                ['supplier' => $scope['supplier_cui'], 'client' => $scope['client_cui']]
            );
        }

        if ($scope['type'] === 'supplier') {
            return Database::fetchAll(
                'SELECT * FROM contracts WHERE partner_cui = :partner OR supplier_cui = :supplier ORDER BY created_at DESC, id DESC',
                ['partner' => $scope['supplier_cui'], 'supplier' => $scope['supplier_cui']]
            );
        }

        return Database::fetchAll(
            'SELECT * FROM contracts WHERE partner_cui = :partner OR client_cui = :client ORDER BY created_at DESC, id DESC',
            ['partner' => $scope['client_cui'], 'client' => $scope['client_cui']]
        );
    }

    private function fetchRelationDocuments(array $scope): array
    {
        if ($scope['type'] === 'relation') {
            return Database::fetchAll(
                'SELECT * FROM relation_documents WHERE supplier_cui = :supplier AND client_cui = :client ORDER BY created_at DESC, id DESC',
                ['supplier' => $scope['supplier_cui'], 'client' => $scope['client_cui']]
            );
        }

        if ($scope['type'] === 'supplier') {
            return Database::fetchAll(
                'SELECT * FROM relation_documents WHERE supplier_cui = :supplier ORDER BY created_at DESC, id DESC',
                ['supplier' => $scope['supplier_cui']]
            );
        }

        return Database::fetchAll(
            'SELECT * FROM relation_documents WHERE client_cui = :client ORDER BY created_at DESC, id DESC',
            ['client' => $scope['client_cui']]
        );
    }

    private function contractAllowed(array $row, array $scope): bool
    {
        if ($scope['type'] === 'relation') {
            return (string) ($row['supplier_cui'] ?? '') === $scope['supplier_cui']
                && (string) ($row['client_cui'] ?? '') === $scope['client_cui'];
        }
        if ($scope['type'] === 'supplier') {
            return (string) ($row['supplier_cui'] ?? '') === $scope['supplier_cui']
                || (string) ($row['partner_cui'] ?? '') === $scope['supplier_cui'];
        }

        return (string) ($row['client_cui'] ?? '') === $scope['client_cui']
            || (string) ($row['partner_cui'] ?? '') === $scope['client_cui'];
    }

    private function relationAllowed(array $row, array $scope): bool
    {
        if ($scope['type'] === 'relation') {
            return (string) ($row['supplier_cui'] ?? '') === $scope['supplier_cui']
                && (string) ($row['client_cui'] ?? '') === $scope['client_cui'];
        }
        if ($scope['type'] === 'supplier') {
            return (string) ($row['supplier_cui'] ?? '') === $scope['supplier_cui'];
        }

        return (string) ($row['client_cui'] ?? '') === $scope['client_cui'];
    }

    private function storeUpload(?array $file, string $subdir): ?string
    {
        if (!$file || !isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $tmp = $file['tmp_name'];
        if (!is_readable($tmp)) {
            return null;
        }
        $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $ext = $ext !== '' ? ('.' . preg_replace('/[^a-z0-9]/i', '', $ext)) : '';
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
        $path = $base . '/' . ltrim($relativePath, '/');
        if (!file_exists($path) || !is_readable($path)) {
            Response::abort(404);
        }
        $filename = basename($path);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    private function throttle(string $hash): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'portal|' . $hash . '|' . $ip;
        return RateLimiter::hit($key, 60, 600);
    }
}
