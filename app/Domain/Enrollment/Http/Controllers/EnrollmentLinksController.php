<?php

namespace App\Domain\Enrollment\Http\Controllers;

use App\Domain\Companies\Services\CompanyLookupService;
use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Env;
use App\Support\Response;
use App\Support\Session;
use App\Support\TokenService;
use App\Support\Url;

class EnrollmentLinksController
{
    public function index(): void
    {
        $user = $this->requireEnrollmentRole();

        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'supplier_cui' => trim((string) ($_GET['supplier_cui'] ?? '')),
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
        if ($filters['type'] !== '') {
            $where[] = 'type = :type';
            $params['type'] = $filters['type'];
        }
        if ($filters['supplier_cui'] !== '') {
            $where[] = 'supplier_cui = :supplier_cui';
            $params['supplier_cui'] = preg_replace('/\D+/', '', $filters['supplier_cui']);
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $total = (int) (Database::fetchValue('SELECT COUNT(*) FROM enrollment_links ' . $whereSql, $params) ?? 0);
        $totalPages = (int) max(1, ceil($total / $filters['per_page']));
        if ($filters['page'] > $totalPages) {
            $filters['page'] = $totalPages;
        }
        $offset = ($filters['page'] - 1) * $filters['per_page'];
        $offset = max(0, (int) $offset);

        $sql = 'SELECT * FROM enrollment_links ' . $whereSql . ' ORDER BY created_at DESC, id DESC';
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

        Response::view('admin/enrollment_links/index', [
            'rows' => $rows,
            'filters' => $filters,
            'pagination' => $pagination,
            'newLink' => Session::pull('enrollment_link'),
            'userSuppliers' => $suppliers,
        ]);
    }

    public function create(): void
    {
        $user = $this->requireEnrollmentRole();

        $type = trim((string) ($_POST['type'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $maxUses = (int) ($_POST['max_uses'] ?? 1);
        $commissionRaw = trim((string) ($_POST['commission_percent'] ?? ''));
        $commission = $commissionRaw !== '' ? (float) str_replace(',', '.', $commissionRaw) : null;
        $expiresAt = trim((string) ($_POST['expires_at'] ?? ''));

        if (!in_array($type, ['supplier', 'client'], true)) {
            Session::flash('error', 'Tip invalid.');
            Response::redirect('/admin/enrollment-links');
        }

        if ($user->isSupplierUser()) {
            if ($type !== 'client') {
                Response::abort(403, 'Acces interzis.');
            }
            if ($supplierCui === '') {
                Session::flash('error', 'Selecteaza furnizorul.');
                Response::redirect('/admin/enrollment-links');
            }
            UserSupplierAccess::ensureTable();
            if (!UserSupplierAccess::userHasSupplier($user->id, $supplierCui)) {
                Response::abort(403, 'Nu ai acces la acest furnizor.');
            }
        }

        if ($type === 'client' && $supplierCui === '') {
            Session::flash('error', 'Completeaza furnizorul pentru client.');
            Response::redirect('/admin/enrollment-links');
        }

        if ($maxUses < 1) {
            $maxUses = 1;
        }

        $prefill = [
            'cui' => preg_replace('/\D+/', '', (string) ($_POST['prefill_cui'] ?? '')),
            'denumire' => trim((string) ($_POST['prefill_denumire'] ?? '')),
            'nr_reg_comertului' => trim((string) ($_POST['prefill_nr_reg_comertului'] ?? '')),
            'adresa' => trim((string) ($_POST['prefill_adresa'] ?? '')),
            'localitate' => trim((string) ($_POST['prefill_localitate'] ?? '')),
            'judet' => trim((string) ($_POST['prefill_judet'] ?? '')),
            'telefon' => trim((string) ($_POST['prefill_telefon'] ?? '')),
            'email' => trim((string) ($_POST['prefill_email'] ?? '')),
        ];
        $prefill = array_filter($prefill, static fn ($value) => $value !== '' && $value !== null);
        $prefillJson = !empty($prefill) ? json_encode($prefill, JSON_UNESCAPED_UNICODE) : null;

        $token = TokenService::generateToken(32);
        $tokenHash = TokenService::hashToken($token);

        Database::execute(
            'INSERT INTO enrollment_links (token_hash, type, created_by_user_id, supplier_cui, commission_percent, prefill_json, max_uses, uses, status, expires_at, created_at)
             VALUES (:token_hash, :type, :user_id, :supplier_cui, :commission_percent, :prefill_json, :max_uses, 0, :status, :expires_at, :created_at)',
            [
                'token_hash' => $tokenHash,
                'type' => $type,
                'user_id' => $user ? $user->id : null,
                'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                'commission_percent' => $commission,
                'prefill_json' => $prefillJson,
                'max_uses' => $maxUses,
                'status' => 'active',
                'expires_at' => $expiresAt !== '' ? ($expiresAt . ' 23:59:59') : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
        $linkId = (int) Database::lastInsertId();
        Audit::record('enrollment.link_create', 'enrollment_link', $linkId ?: null, [
            'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
            'rows_count' => 1,
        ]);

        $link = [
            'token' => $token,
            'url' => $this->absoluteUrl(Url::to('enroll/' . $token)),
        ];
        Session::flash('status', 'Link de inrolare creat.');
        Session::flash('enrollment_link', $link);
        Response::redirect('/admin/enrollment-links');
    }

    public function disable(): void
    {
        $this->requireEnrollmentRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/enrollment-links');
        }

        Database::execute(
            'UPDATE enrollment_links SET status = :status WHERE id = :id',
            ['status' => 'disabled', 'id' => $id]
        );
        Audit::record('enrollment.link_disable', 'enrollment_link', $id, []);

        Session::flash('status', 'Link dezactivat.');
        Response::redirect('/admin/enrollment-links');
    }

    public function lookup(): void
    {
        $this->requireEnrollmentRole();

        $cui = preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? ''));
        $service = new CompanyLookupService();
        $response = $service->lookupByCui($cui);
        if ($response['error'] !== null) {
            $this->json(['success' => false, 'message' => $response['error']]);
        }

        $this->json(['success' => true, 'data' => $response['data']]);
    }

    private function requireEnrollmentRole(): \App\Domain\Users\Models\User
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

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($base === '') {
            return $path;
        }
        $basePath = Url::base();
        $relative = $path;
        if ($basePath !== '' && str_ends_with($base, $basePath)) {
            $relative = substr($path, strlen($basePath));
        }

        return $base . $relative;
    }
}
