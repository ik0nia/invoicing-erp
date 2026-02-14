<?php

namespace App\Domain\Portal\Http\Controllers;

use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;
use App\Support\TokenService;
use App\Support\Url;

class PortalLinksController
{
    public function index(): void
    {
        $user = $this->requirePortalRole();

        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'owner_type' => trim((string) ($_GET['owner_type'] ?? '')),
            'owner_cui' => trim((string) ($_GET['owner_cui'] ?? '')),
            'page' => (int) ($_GET['page'] ?? 1),
            'per_page' => (int) ($_GET['per_page'] ?? 50),
        ];
        $allowedPerPage = [25, 50, 100];
        if (!in_array($filters['per_page'], $allowedPerPage, true)) {
            $filters['per_page'] = 50;
        }
        if ($filters['page'] < 1) {
            $filters['page'] = 1;
        }

        $where = [];
        $params = [];
        if ($filters['status'] !== '') {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }
        if ($filters['owner_type'] !== '') {
            $where[] = 'owner_type = :owner_type';
            $params['owner_type'] = $filters['owner_type'];
        }
        if ($filters['owner_cui'] !== '') {
            $where[] = 'owner_cui = :owner_cui';
            $params['owner_cui'] = preg_replace('/\D+/', '', $filters['owner_cui']);
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $total = (int) (Database::fetchValue('SELECT COUNT(*) FROM portal_links ' . $whereSql, $params) ?? 0);
        $totalPages = (int) max(1, ceil($total / $filters['per_page']));
        if ($filters['page'] > $totalPages) {
            $filters['page'] = $totalPages;
        }
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $offset = max(0, (int) $offset);

        $sql = 'SELECT * FROM portal_links ' . $whereSql . ' ORDER BY created_at DESC, id DESC';
        $sql .= ' LIMIT ' . (int) $filters['per_page'] . ' OFFSET ' . (int) $offset;
        $rows = Database::fetchAll($sql, $params);

        $pagination = [
            'page' => $filters['page'],
            'per_page' => $filters['per_page'],
            'total' => $total,
            'total_pages' => $totalPages,
            'start' => $total > 0 ? ($offset + 1) : 0,
            'end' => $total > 0 ? min($total, $offset + count($rows)) : 0,
        ];

        $suppliers = [];
        if ($user->isSupplierUser()) {
            UserSupplierAccess::ensureTable();
            $suppliers = UserSupplierAccess::suppliersForUser($user->id);
        }

        Response::view('admin/portal_links/index', [
            'rows' => $rows,
            'filters' => $filters,
            'pagination' => $pagination,
            'newLink' => Session::pull('portal_link'),
            'userSuppliers' => $suppliers,
        ]);
    }

    public function create(): void
    {
        $user = $this->requirePortalRole();

        $ownerType = trim((string) ($_POST['owner_type'] ?? ''));
        $ownerCui = preg_replace('/\D+/', '', (string) ($_POST['owner_cui'] ?? ''));
        $relationSupplier = preg_replace('/\D+/', '', (string) ($_POST['relation_supplier_cui'] ?? ''));
        $relationClient = preg_replace('/\D+/', '', (string) ($_POST['relation_client_cui'] ?? ''));
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));

        if (!in_array($ownerType, ['supplier', 'client'], true)) {
            Session::flash('error', 'Tip invalid.');
            Response::redirect('/admin/portal-links');
        }
        if ($ownerCui === '') {
            Session::flash('error', 'Completeaza owner CUI.');
            Response::redirect('/admin/portal-links');
        }

        if (($relationSupplier !== '' && $relationClient === '') || ($relationSupplier === '' && $relationClient !== '')) {
            Session::flash('error', 'Completeaza ambii parteneri pentru relatie.');
            Response::redirect('/admin/portal-links');
        }
        if ($relationSupplier !== '' && $relationClient !== '') {
            if ($ownerType === 'supplier' && $ownerCui !== $relationSupplier) {
                Session::flash('error', 'Owner CUI trebuie sa corespunda furnizorului relatiei.');
                Response::redirect('/admin/portal-links');
            }
            if ($ownerType === 'client' && $ownerCui !== $relationClient) {
                Session::flash('error', 'Owner CUI trebuie sa corespunda clientului relatiei.');
                Response::redirect('/admin/portal-links');
            }
        }

        if ($user->isSupplierUser()) {
            UserSupplierAccess::ensureTable();
            if ($ownerType === 'supplier') {
                if (!UserSupplierAccess::userHasSupplier($user->id, $ownerCui)) {
                    Response::abort(403, 'Nu ai acces la acest furnizor.');
                }
            } else {
                if ($relationSupplier === '') {
                    Session::flash('error', 'Relatia este obligatorie pentru cont furnizor.');
                    Response::redirect('/admin/portal-links');
                }
                if (!UserSupplierAccess::userHasSupplier($user->id, $relationSupplier)) {
                    Response::abort(403, 'Nu ai acces la acest furnizor.');
                }
            }
        }

        $permissions = [
            'can_view' => !empty($_POST['can_view']),
            'can_upload_signed' => !empty($_POST['can_upload_signed']),
            'can_upload_custom' => !empty($_POST['can_upload_custom']),
        ];

        $token = TokenService::generateToken(32);
        $hash = TokenService::hashToken($token);

        Database::execute(
            'INSERT INTO portal_links (token_hash, owner_type, owner_cui, relation_supplier_cui, relation_client_cui, permissions_json, status, expires_at, created_by_user_id, created_at)
             VALUES (:token_hash, :owner_type, :owner_cui, :relation_supplier, :relation_client, :permissions_json, :status, :expires_at, :created_by, :created_at)',
            [
                'token_hash' => $hash,
                'owner_type' => $ownerType,
                'owner_cui' => $ownerCui,
                'relation_supplier' => $relationSupplier !== '' ? $relationSupplier : null,
                'relation_client' => $relationClient !== '' ? $relationClient : null,
                'permissions_json' => json_encode($permissions, JSON_UNESCAPED_UNICODE),
                'status' => 'active',
                'expires_at' => $expiresAt !== '' ? ($expiresAt . ' 23:59:59') : null,
                'created_by' => $user ? $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $linkId = (int) Database::lastInsertId();
        Audit::record('portal.link_create', 'portal_link', $linkId ?: null, [
            'rows_count' => 1,
        ]);

        Session::flash('status', 'Link portal creat.');
        Session::flash('portal_link', [
            'token' => $token,
            'url' => Url::to('portal/' . $token),
        ]);
        Response::redirect('/admin/portal-links');
    }

    public function disable(): void
    {
        $this->requirePortalRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/portal-links');
        }

        Database::execute(
            'UPDATE portal_links SET status = :status WHERE id = :id',
            ['status' => 'disabled', 'id' => $id]
        );
        Audit::record('portal.link_disable', 'portal_link', $id, []);

        Session::flash('status', 'Link dezactivat.');
        Response::redirect('/admin/portal-links');
    }

    private function requirePortalRole(): \App\Domain\Users\Models\User
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }
        if ($user->hasRole(['super_admin', 'admin', 'contabil', 'operator']) || $user->isSupplierUser()) {
            return $user;
        }

        Response::abort(403, 'Acces interzis.');
    }
}
