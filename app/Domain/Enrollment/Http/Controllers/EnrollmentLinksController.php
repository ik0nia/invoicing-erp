<?php

namespace App\Domain\Enrollment\Http\Controllers;

use App\Domain\Companies\Services\CompanyLookupService;
use App\Domain\Partners\Models\Partner;
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
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $isPendingPage = str_starts_with($currentPath, '/admin/inrolari');

        $filters = [
            'status' => trim((string) ($_GET['status'] ?? '')),
            'type' => trim((string) ($_GET['type'] ?? '')),
            'onboarding_status' => trim((string) ($_GET['onboarding_status'] ?? '')),
            'supplier_cui' => trim((string) ($_GET['supplier_cui'] ?? '')),
            'partner_cui' => trim((string) ($_GET['partner_cui'] ?? '')),
            'relation_supplier_cui' => trim((string) ($_GET['relation_supplier_cui'] ?? '')),
            'relation_client_cui' => trim((string) ($_GET['relation_client_cui'] ?? '')),
            'page' => (int) ($_GET['page'] ?? 1),
            'per_page' => (int) ($_GET['per_page'] ?? 50),
        ];
        if ($isPendingPage && $filters['onboarding_status'] === '') {
            $filters['onboarding_status'] = 'submitted';
        }
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
        $hasOnboardingStatus = Database::columnExists('enrollment_links', 'onboarding_status');
        if ($filters['onboarding_status'] !== '' && $hasOnboardingStatus) {
            $where[] = 'onboarding_status = :onboarding_status';
            $params['onboarding_status'] = $filters['onboarding_status'];
        }
        $hasPartnerCui = Database::columnExists('enrollment_links', 'partner_cui');
        $hasRelationSupplier = Database::columnExists('enrollment_links', 'relation_supplier_cui');
        $hasRelationClient = Database::columnExists('enrollment_links', 'relation_client_cui');
        if ($filters['supplier_cui'] !== '') {
            $where[] = 'supplier_cui = :supplier_cui';
            $params['supplier_cui'] = preg_replace('/\D+/', '', $filters['supplier_cui']);
        }
        if ($filters['partner_cui'] !== '' && $hasPartnerCui) {
            $where[] = 'partner_cui = :partner_cui';
            $params['partner_cui'] = preg_replace('/\D+/', '', $filters['partner_cui']);
        }
        if ($filters['relation_supplier_cui'] !== '' && $hasRelationSupplier) {
            $where[] = 'relation_supplier_cui = :relation_supplier_cui';
            $params['relation_supplier_cui'] = preg_replace('/\D+/', '', $filters['relation_supplier_cui']);
        }
        if ($filters['relation_client_cui'] !== '' && $hasRelationClient) {
            $where[] = 'relation_client_cui = :relation_client_cui';
            $params['relation_client_cui'] = preg_replace('/\D+/', '', $filters['relation_client_cui']);
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
            'newLink' => Session::pull('public_link'),
            'userSuppliers' => $suppliers,
            'canApproveOnboarding' => Auth::isInternalStaff(),
            'isPendingPage' => $isPendingPage,
        ]);
    }

    public function pending(): void
    {
        Auth::requireInternalStaff();
        $_GET['onboarding_status'] = trim((string) ($_GET['onboarding_status'] ?? 'submitted'));
        $this->index();
    }

    public function create(): void
    {
        $user = $this->requireEnrollmentRole();

        $type = trim((string) ($_POST['type'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
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
        $permissionsJson = json_encode([
            'can_view' => true,
            'can_upload_signed' => true,
            'can_upload_custom' => false,
        ], JSON_UNESCAPED_UNICODE);
        $relationSupplier = $type === 'client' && $supplierCui !== '' ? $supplierCui : null;

        Database::execute(
            'INSERT INTO enrollment_links (
                token_hash,
                type,
                created_by_user_id,
                supplier_cui,
                partner_cui,
                relation_supplier_cui,
                relation_client_cui,
                commission_percent,
                prefill_json,
                permissions_json,
                max_uses,
                uses,
                current_step,
                status,
                onboarding_status,
                submitted_at,
                approved_at,
                approved_by_user_id,
                checkbox_confirmed,
                expires_at,
                last_used_at,
                created_at,
                updated_at
            ) VALUES (
                :token_hash,
                :type,
                :user_id,
                :supplier_cui,
                :partner_cui,
                :relation_supplier_cui,
                :relation_client_cui,
                :commission_percent,
                :prefill_json,
                :permissions_json,
                :max_uses,
                0,
                :current_step,
                :status,
                :onboarding_status,
                :submitted_at,
                :approved_at,
                :approved_by_user_id,
                :checkbox_confirmed,
                :expires_at,
                :last_used_at,
                :created_at,
                :updated_at
            )',
            [
                'token_hash' => $tokenHash,
                'type' => $type,
                'user_id' => $user ? $user->id : null,
                'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
                'partner_cui' => null,
                'relation_supplier_cui' => $relationSupplier,
                'relation_client_cui' => null,
                'commission_percent' => $commission,
                'prefill_json' => $prefillJson,
                'permissions_json' => $permissionsJson,
                'max_uses' => 0,
                'current_step' => 1,
                'status' => 'active',
                'onboarding_status' => 'draft',
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'checkbox_confirmed' => 0,
                'expires_at' => $expiresAt !== '' ? ($expiresAt . ' 23:59:59') : null,
                'last_used_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        $linkId = (int) Database::lastInsertId();
        Audit::record('public_link.create', 'public_link', $linkId ?: null, [
            'supplier_cui' => $supplierCui !== '' ? $supplierCui : null,
            'rows_count' => 1,
        ]);

        $link = [
            'token' => $token,
            'url' => $this->absoluteUrl(Url::to('p/' . $token)),
        ];
        Session::flash('status', 'Link public creat.');
        Session::flash('public_link', $link);
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
            'UPDATE enrollment_links SET status = :status, updated_at = :updated_at WHERE id = :id',
            ['status' => 'disabled', 'updated_at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
        Audit::record('public_link.disable', 'public_link', $id, []);

        Session::flash('status', 'Link dezactivat.');
        Response::redirect('/admin/enrollment-links');
    }

    public function regenerate(): void
    {
        $this->requireEnrollmentRole();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/admin/enrollment-links');
        }

        $token = TokenService::generateToken(32);
        $tokenHash = TokenService::hashToken($token);

        Database::execute(
            'UPDATE enrollment_links
             SET token_hash = :token_hash, status = :status, updated_at = :updated_at
             WHERE id = :id',
            [
                'token_hash' => $tokenHash,
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Audit::record('public_link.regenerate', 'public_link', $id, ['rows_count' => 1]);

        $link = [
            'token' => $token,
            'url' => $this->absoluteUrl(Url::to('p/' . $token)),
        ];
        Session::flash('status', 'Link public regenerat.');
        Session::flash('public_link', $link);
        Response::redirect('/admin/enrollment-links');
    }

    public function approveOnboarding(): void
    {
        Auth::requireInternalStaff();
        $user = Auth::user();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/inrolari');
        }

        $row = Database::fetchOne('SELECT * FROM enrollment_links WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row) {
            Response::abort(404, 'Inrolare inexistenta.');
        }

        if (!Database::columnExists('enrollment_links', 'onboarding_status')) {
            Response::abort(500, 'Coloanele onboarding lipsesc.');
        }

        $status = (string) ($row['onboarding_status'] ?? 'draft');
        if ($status !== 'submitted') {
            Session::flash('error', 'Inrolarea nu este in status trimis.');
            Response::redirect('/admin/inrolari');
        }

        $now = date('Y-m-d H:i:s');
        Database::execute(
            'UPDATE enrollment_links
             SET onboarding_status = :onboarding_status,
                 approved_at = :approved_at,
                 approved_by_user_id = :approved_by_user_id,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'onboarding_status' => 'approved',
                'approved_at' => $now,
                'approved_by_user_id' => $user ? (int) $user->id : null,
                'updated_at' => $now,
                'id' => $id,
            ]
        );

        $partnerCui = preg_replace('/\D+/', '', (string) ($row['partner_cui'] ?? ''));
        if ($partnerCui !== '') {
            Partner::updateFlags(
                $partnerCui,
                (string) ($row['type'] ?? '') === 'supplier',
                (string) ($row['type'] ?? '') === 'client'
            );
        }

        Audit::record('onboarding.approved', 'enrollment_link', $id, [
            'rows_count' => 1,
            'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
        ]);

        Session::flash('status', 'Inrolarea a fost aprobata si activata.');
        Response::redirect('/admin/inrolari');
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
