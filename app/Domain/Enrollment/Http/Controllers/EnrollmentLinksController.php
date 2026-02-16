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
    private const RESOURCE_UPLOAD_SUBDIR = 'enrollment/resources';
    private const MAX_RESOURCE_UPLOAD_BYTES = 26214400;
    private const ALLOWED_RESOURCE_EXTENSIONS = [
        'pdf',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'ppt',
        'pptx',
        'txt',
        'jpg',
        'jpeg',
        'png',
        'zip',
    ];

    public function index(): void
    {
        $user = $this->requireEnrollmentRole();
        $currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
        $isPendingPage = !empty($_GET['_pending_page']);
        if (!$isPendingPage) {
            $normalizedPath = '/' . trim($currentPath, '/');
            $basePath = '/' . trim((string) Url::base(), '/');
            if ($basePath === '/') {
                $basePath = '';
            }

            $pendingPathCandidates = [
                '/admin/inrolari',
                $basePath . '/admin/inrolari',
            ];
            foreach ($pendingPathCandidates as $candidate) {
                $candidate = trim($candidate);
                if ($candidate === '' || $candidate === '/') {
                    continue;
                }
                if ($normalizedPath === $candidate || str_starts_with($normalizedPath, $candidate . '/')) {
                    $isPendingPage = true;
                    break;
                }
            }
        }

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

        $onboardingResources = $this->fetchEnrollmentResources(null, true);

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
            'canManageOnboardingResources' => Auth::isInternalStaff(),
            'onboardingResources' => $onboardingResources,
            'isPendingPage' => $isPendingPage,
        ]);
    }

    public function pending(): void
    {
        Auth::requireInternalStaff();
        $_GET['_pending_page'] = '1';
        $_GET['onboarding_status'] = trim((string) ($_GET['onboarding_status'] ?? 'submitted'));
        $this->index();
    }

    public function create(): void
    {
        $user = $this->requireEnrollmentRole();

        $type = trim((string) ($_POST['type'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $commissionRaw = trim((string) ($_POST['commission_percent'] ?? ''));
        $commission = null;
        if ($commissionRaw !== '') {
            $commissionValue = str_replace(',', '.', $commissionRaw);
            if (!is_numeric($commissionValue)) {
                Session::flash('error', 'Comision invalid.');
                Response::redirect('/admin/enrollment-links');
            }
            $commission = (float) $commissionValue;
        }
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
        if ($commission === null && $supplierCui !== '') {
            $defaultCommission = $this->defaultCommissionForSupplier($supplierCui);
            if ($defaultCommission !== null) {
                $commission = $defaultCommission;
            }
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
        $prefill = $this->enrichPrefillFromOpenApi($prefill);
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

    public function supplierSearch(): void
    {
        $user = $this->requireEnrollmentRole();
        $term = trim((string) ($_GET['term'] ?? ''));
        $limit = min(25, max(1, (int) ($_GET['limit'] ?? 15)));
        $items = $this->searchSuppliers($user, $term, $limit);

        $this->json([
            'success' => true,
            'items' => $items,
        ]);
    }

    public function supplierInfo(): void
    {
        $user = $this->requireEnrollmentRole();
        $cui = preg_replace('/\D+/', '', (string) ($_GET['cui'] ?? ''));
        if ($cui === '') {
            $this->json(['success' => false, 'message' => 'CUI furnizor invalid.'], 400);
        }

        $item = $this->findSupplierByCui($user, $cui);
        if ($item === null) {
            $this->json(['success' => false, 'message' => 'Furnizorul nu a fost gasit.'], 404);
        }

        $this->json([
            'success' => true,
            'item' => $item,
        ]);
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
        Response::redirect($this->resolveAdminReturnPath('/admin/inrolari'));
    }

    public function resetOnboarding(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect($this->resolveAdminReturnPath('/admin/enrollment-links'));
        }

        $row = Database::fetchOne('SELECT * FROM enrollment_links WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row) {
            Response::abort(404, 'Inrolare inexistenta.');
        }
        if (!Database::columnExists('enrollment_links', 'onboarding_status')) {
            Response::abort(500, 'Coloanele onboarding lipsesc.');
        }

        $partnerCui = preg_replace('/\D+/', '', (string) ($row['partner_cui'] ?? ''));
        $type = (string) ($row['type'] ?? '');
        $supplierCui = preg_replace('/\D+/', '', (string) ($row['supplier_cui'] ?? ''));
        $relationSupplier = preg_replace('/\D+/', '', (string) ($row['relation_supplier_cui'] ?? ''));
        $relationClient = preg_replace('/\D+/', '', (string) ($row['relation_client_cui'] ?? ''));

        $contractsDeleted = 0;
        if (Database::tableExists('contracts') && Database::columnExists('contracts', 'required_onboarding')) {
            if ($relationSupplier !== '' && $relationClient !== '') {
                $contractsDeleted = (int) (Database::fetchValue(
                    'SELECT COUNT(*)
                     FROM contracts
                     WHERE supplier_cui = :supplier
                       AND client_cui = :client
                       AND required_onboarding = 1',
                    ['supplier' => $relationSupplier, 'client' => $relationClient]
                ) ?? 0);
                Database::execute(
                    'DELETE FROM contracts
                     WHERE supplier_cui = :supplier
                       AND client_cui = :client
                       AND required_onboarding = 1',
                    [
                        'supplier' => $relationSupplier,
                        'client' => $relationClient,
                    ]
                );
            } elseif ($type === 'supplier' && $partnerCui !== '') {
                $contractsDeleted = (int) (Database::fetchValue(
                    'SELECT COUNT(*)
                     FROM contracts
                     WHERE (partner_cui = :partner OR supplier_cui = :partner)
                       AND required_onboarding = 1',
                    ['partner' => $partnerCui]
                ) ?? 0);
                Database::execute(
                    'DELETE FROM contracts
                     WHERE (partner_cui = :partner OR supplier_cui = :partner)
                       AND required_onboarding = 1',
                    [
                        'partner' => $partnerCui,
                    ]
                );
            } elseif ($type === 'client' && $partnerCui !== '') {
                $effectiveSupplier = $relationSupplier !== '' ? $relationSupplier : $supplierCui;
                if ($effectiveSupplier !== '') {
                    $contractsDeleted = (int) (Database::fetchValue(
                        'SELECT COUNT(*)
                         FROM contracts
                         WHERE supplier_cui = :supplier
                           AND client_cui = :client
                           AND required_onboarding = 1',
                        ['supplier' => $effectiveSupplier, 'client' => $partnerCui]
                    ) ?? 0);
                    Database::execute(
                        'DELETE FROM contracts
                         WHERE supplier_cui = :supplier
                           AND client_cui = :client
                           AND required_onboarding = 1',
                        [
                            'supplier' => $effectiveSupplier,
                            'client' => $partnerCui,
                        ]
                    );
                } else {
                    $contractsDeleted = (int) (Database::fetchValue(
                        'SELECT COUNT(*)
                         FROM contracts
                         WHERE (partner_cui = :partner OR client_cui = :partner)
                           AND required_onboarding = 1',
                        ['partner' => $partnerCui]
                    ) ?? 0);
                    Database::execute(
                        'DELETE FROM contracts
                         WHERE (partner_cui = :partner OR client_cui = :partner)
                           AND required_onboarding = 1',
                        [
                            'partner' => $partnerCui,
                        ]
                    );
                }
            }
        }

        Database::execute(
            'UPDATE enrollment_links
             SET partner_cui = :partner_cui,
                 relation_client_cui = :relation_client_cui,
                 current_step = :current_step,
                 uses = :uses,
                 last_used_at = :last_used_at,
                 confirmed_at = :confirmed_at,
                 onboarding_status = :onboarding_status,
                 submitted_at = :submitted_at,
                 approved_at = :approved_at,
                 approved_by_user_id = :approved_by_user_id,
                 checkbox_confirmed = :checkbox_confirmed,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'partner_cui' => null,
                'relation_client_cui' => null,
                'current_step' => 1,
                'uses' => 0,
                'last_used_at' => null,
                'confirmed_at' => null,
                'onboarding_status' => 'draft',
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'checkbox_confirmed' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );
        Audit::record('onboarding.reset', 'enrollment_link', $id, [
            'rows_count' => 1,
            'contracts_deleted' => $contractsDeleted,
            'partner_cui_before' => $partnerCui !== '' ? $partnerCui : null,
        ]);

        Session::flash('status', 'Onboarding resetat la Pasul 1. Documentele de onboarding au fost sterse.');
        Response::redirect($this->resolveAdminReturnPath('/admin/enrollment-links'));
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

    public function uploadResource(): void
    {
        Auth::requireInternalStaff();
        $user = Auth::user();

        if (!Database::tableExists('enrollment_resources')) {
            Session::flash('error', 'Sectiunea de fisiere onboarding nu este disponibila momentan.');
            Response::redirect('/admin/enrollment-links');
        }

        $appliesTo = trim((string) ($_POST['applies_to'] ?? 'both'));
        if (!in_array($appliesTo, ['supplier', 'client', 'both'], true)) {
            $appliesTo = 'both';
        }
        $sortOrder = isset($_POST['sort_order']) ? (int) $_POST['sort_order'] : 100;
        if ($sortOrder < 0) {
            $sortOrder = 0;
        }
        if ($sortOrder > 9999) {
            $sortOrder = 9999;
        }

        $stored = $this->storeResourceUpload($_FILES['resource_file'] ?? null);
        if ($stored === null) {
            Session::flash(
                'error',
                'Fisier invalid. Sunt acceptate PDF/DOC/DOCX/XLS/XLSX/PPT/PPTX/TXT/JPG/PNG/ZIP (maxim 25MB).'
            );
            Response::redirect('/admin/enrollment-links');
        }

        $title = $this->sanitizeResourceTitle((string) ($_POST['title'] ?? ''));
        if ($title === '') {
            $title = $this->sanitizeResourceTitle($stored['title_fallback']);
        }
        if ($title === '') {
            $title = 'Document onboarding';
        }

        Database::execute(
            'INSERT INTO enrollment_resources (
                title,
                applies_to,
                file_path,
                original_name,
                mime_type,
                file_ext,
                sort_order,
                is_active,
                created_by_user_id,
                created_at,
                updated_at
            ) VALUES (
                :title,
                :applies_to,
                :file_path,
                :original_name,
                :mime_type,
                :file_ext,
                :sort_order,
                :is_active,
                :created_by_user_id,
                :created_at,
                :updated_at
            )',
            [
                'title' => $title,
                'applies_to' => $appliesTo,
                'file_path' => $stored['path'],
                'original_name' => $stored['original_name'] !== '' ? $stored['original_name'] : null,
                'mime_type' => $stored['mime_type'] !== '' ? $stored['mime_type'] : null,
                'file_ext' => $stored['file_ext'] !== '' ? $stored['file_ext'] : null,
                'sort_order' => $sortOrder,
                'is_active' => 1,
                'created_by_user_id' => $user ? (int) $user->id : null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        $resourceId = (int) Database::lastInsertId();
        Audit::record('enrollment_resource.upload', 'enrollment_resource', $resourceId > 0 ? $resourceId : null, [
            'rows_count' => 1,
            'applies_to' => $appliesTo,
        ]);

        Session::flash('status', 'Fisierul de onboarding a fost incarcat.');
        Response::redirect('/admin/enrollment-links');
    }

    public function deleteResource(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0 || !Database::tableExists('enrollment_resources')) {
            Response::redirect('/admin/enrollment-links');
        }

        $row = Database::fetchOne('SELECT * FROM enrollment_resources WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row) {
            Session::flash('error', 'Fisierul nu a fost gasit.');
            Response::redirect('/admin/enrollment-links');
        }

        Database::execute('DELETE FROM enrollment_resources WHERE id = :id', ['id' => $id]);
        $absolute = $this->resolveResourceAbsolutePath((string) ($row['file_path'] ?? ''));
        if ($absolute !== '' && is_file($absolute)) {
            @unlink($absolute);
        }

        Audit::record('enrollment_resource.delete', 'enrollment_resource', $id, ['rows_count' => 1]);
        Session::flash('status', 'Fisierul de onboarding a fost sters.');
        Response::redirect('/admin/enrollment-links');
    }

    public function downloadResource(): void
    {
        Auth::requireInternalStaff();

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0 || !Database::tableExists('enrollment_resources')) {
            Response::abort(404, 'Fisier inexistent.');
        }

        $row = Database::fetchOne('SELECT * FROM enrollment_resources WHERE id = :id LIMIT 1', ['id' => $id]);
        if (
            !$row
            || (Database::columnExists('enrollment_resources', 'is_active') && empty($row['is_active']))
        ) {
            Response::abort(404, 'Fisier inexistent.');
        }

        Audit::record('enrollment_resource.download', 'enrollment_resource', $id, ['rows_count' => 1]);
        $this->streamResourceFile($row);
    }

    private function searchSuppliers(\App\Domain\Users\Models\User $user, string $term, int $limit): array
    {
        if (!Database::tableExists('partners')) {
            return [];
        }

        $allowedCuis = $this->allowedSupplierCuisForUser($user);
        if (is_array($allowedCuis) && empty($allowedCuis)) {
            return [];
        }

        $rows = $this->querySuppliers($term, $limit, $allowedCuis, true);
        if (empty($rows)) {
            $rows = $this->querySuppliers($term, $limit, $allowedCuis, false);
        }

        return array_map(fn (array $row) => $this->mapSupplierLookupRow($row), $rows);
    }

    private function findSupplierByCui(\App\Domain\Users\Models\User $user, string $cui): ?array
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '') {
            return null;
        }

        $allowedCuis = $this->allowedSupplierCuisForUser($user);
        if (is_array($allowedCuis) && !in_array($cui, $allowedCuis, true)) {
            return null;
        }

        if (Database::tableExists('partners')) {
            $selectDefaultCommission = Database::columnExists('partners', 'default_commission')
                ? 'p.default_commission'
                : '0 AS default_commission';
            $row = Database::fetchOne(
                'SELECT p.cui, p.denumire, ' . $selectDefaultCommission . '
                 FROM partners p
                 WHERE p.cui = :cui
                 LIMIT 1',
                ['cui' => $cui]
            );
            if ($row) {
                return $this->mapSupplierLookupRow($row);
            }
        }

        if (Database::tableExists('companies')) {
            $row = Database::fetchOne(
                'SELECT cui, denumire
                 FROM companies
                 WHERE cui = :cui
                 LIMIT 1',
                ['cui' => $cui]
            );
            if ($row) {
                return $this->mapSupplierLookupRow([
                    'cui' => (string) ($row['cui'] ?? ''),
                    'denumire' => (string) ($row['denumire'] ?? ''),
                    'default_commission' => null,
                ]);
            }
        }

        return null;
    }

    private function querySuppliers(string $term, int $limit, ?array $allowedCuis, bool $enforceSupplierFlag): array
    {
        $selectDefaultCommission = Database::columnExists('partners', 'default_commission')
            ? 'p.default_commission'
            : '0 AS default_commission';

        $where = [];
        $params = [];
        if ($term !== '') {
            $where[] = '(p.cui LIKE :term OR p.denumire LIKE :term)';
            $params['term'] = '%' . $term . '%';
        }
        if ($enforceSupplierFlag && Database::columnExists('partners', 'is_supplier')) {
            $where[] = 'p.is_supplier = 1';
        }
        if (is_array($allowedCuis)) {
            if (empty($allowedCuis)) {
                return [];
            }
            $placeholders = [];
            foreach (array_values($allowedCuis) as $index => $cui) {
                $key = 'ac' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cui;
            }
            $where[] = 'p.cui IN (' . implode(',', $placeholders) . ')';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $sql = 'SELECT p.cui, p.denumire, ' . $selectDefaultCommission . '
                FROM partners p
                ' . $whereSql . '
                ORDER BY p.denumire ASC, p.cui ASC
                LIMIT ' . (int) $limit;

        return Database::fetchAll($sql, $params);
    }

    private function mapSupplierLookupRow(array $row): array
    {
        $cui = preg_replace('/\D+/', '', (string) ($row['cui'] ?? ''));
        $name = trim((string) ($row['denumire'] ?? $row['name'] ?? ''));
        if ($name === '') {
            $name = $cui;
        }

        $defaultCommission = null;
        if (array_key_exists('default_commission', $row) && $row['default_commission'] !== null && $row['default_commission'] !== '') {
            $defaultCommission = (float) $row['default_commission'];
        }

        return [
            'cui' => $cui,
            'name' => $name,
            'label' => $name . ' - ' . $cui,
            'default_commission' => $defaultCommission,
        ];
    }

    private function allowedSupplierCuisForUser(\App\Domain\Users\Models\User $user): ?array
    {
        if (!$user->isSupplierUser()) {
            return null;
        }
        UserSupplierAccess::ensureTable();
        $rows = UserSupplierAccess::suppliersForUser($user->id);
        $allowed = [];
        foreach ($rows as $row) {
            $cui = preg_replace('/\D+/', '', (string) $row);
            if ($cui !== '') {
                $allowed[$cui] = true;
            }
        }

        return array_keys($allowed);
    }

    private function enrichPrefillFromOpenApi(array $prefill): array
    {
        $cui = preg_replace('/\D+/', '', (string) ($prefill['cui'] ?? ''));
        if ($cui === '') {
            return $prefill;
        }

        $service = new CompanyLookupService();
        $response = $service->lookupByCui($cui);
        $data = is_array($response['data'] ?? null) ? $response['data'] : null;
        if (!is_array($data)) {
            return $prefill;
        }

        $fieldMap = [
            'cui' => 'cui',
            'denumire' => 'denumire',
            'nr_reg_comertului' => 'nr_reg_comertului',
            'adresa' => 'adresa',
            'localitate' => 'localitate',
            'judet' => 'judet',
            'telefon' => 'telefon',
            'email' => 'email',
        ];
        foreach ($fieldMap as $target => $source) {
            $currentValue = trim((string) ($prefill[$target] ?? ''));
            if ($currentValue !== '') {
                continue;
            }
            $candidate = trim((string) ($data[$source] ?? ''));
            if ($candidate !== '') {
                $prefill[$target] = $candidate;
            }
        }
        $prefill['cui'] = $cui;

        return $prefill;
    }

    private function defaultCommissionForSupplier(string $supplierCui): ?float
    {
        $supplierCui = preg_replace('/\D+/', '', $supplierCui);
        if ($supplierCui === '' || !Database::tableExists('partners') || !Database::columnExists('partners', 'default_commission')) {
            return null;
        }

        $row = Database::fetchOne(
            'SELECT default_commission FROM partners WHERE cui = :cui LIMIT 1',
            ['cui' => $supplierCui]
        );
        if (!$row) {
            return null;
        }

        return (float) ($row['default_commission'] ?? 0.0);
    }

    private function fetchEnrollmentResources(?string $linkType = null, bool $onlyActive = true): array
    {
        if (!Database::tableExists('enrollment_resources')) {
            return [];
        }

        $where = [];
        $params = [];

        if ($onlyActive && Database::columnExists('enrollment_resources', 'is_active')) {
            $where[] = 'is_active = :is_active';
            $params['is_active'] = 1;
        }

        if ($linkType !== null && Database::columnExists('enrollment_resources', 'applies_to')) {
            $normalizedType = trim($linkType);
            if (!in_array($normalizedType, ['supplier', 'client'], true)) {
                $normalizedType = 'client';
            }
            $where[] = '(applies_to = :applies_to OR applies_to = :applies_to_both)';
            $params['applies_to'] = $normalizedType;
            $params['applies_to_both'] = 'both';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
        $orderParts = [];
        if (Database::columnExists('enrollment_resources', 'sort_order')) {
            $orderParts[] = 'sort_order ASC';
        }
        if (Database::columnExists('enrollment_resources', 'created_at')) {
            $orderParts[] = 'created_at DESC';
        }
        $orderParts[] = 'id DESC';
        $orderBy = implode(', ', $orderParts);

        return Database::fetchAll(
            'SELECT * FROM enrollment_resources ' . $whereSql . ' ORDER BY ' . $orderBy,
            $params
        );
    }

    private function sanitizeResourceTitle(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (strlen($value) > 255) {
            return substr($value, 0, 255);
        }

        return $value;
    }

    private function storeResourceUpload(?array $file): ?array
    {
        if (!$file || !isset($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if (!is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            return null;
        }

        $size = isset($file['size']) ? (int) $file['size'] : 0;
        if ($size <= 0 || $size > self::MAX_RESOURCE_UPLOAD_BYTES) {
            return null;
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $extRaw = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $ext = preg_replace('/[^a-z0-9]/i', '', $extRaw);
        if ($ext === '' || !in_array($ext, self::ALLOWED_RESOURCE_EXTENSIONS, true)) {
            return null;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if (!is_readable($tmp)) {
            return null;
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $dir = $basePath . '/storage/uploads/' . self::RESOURCE_UPLOAD_SUBDIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return null;
        }

        try {
            $targetName = bin2hex(random_bytes(16)) . '.' . $ext;
        } catch (\Throwable $exception) {
            return null;
        }
        $targetPath = $dir . '/' . $targetName;
        if (!move_uploaded_file($tmp, $targetPath)) {
            return null;
        }

        $titleFallback = (string) pathinfo($originalName, PATHINFO_FILENAME);

        return [
            'path' => 'storage/uploads/' . self::RESOURCE_UPLOAD_SUBDIR . '/' . $targetName,
            'original_name' => $originalName,
            'mime_type' => $this->detectMimeType($targetPath),
            'file_ext' => $ext,
            'title_fallback' => $titleFallback,
        ];
    }

    private function resolveResourceAbsolutePath(string $relativePath): string
    {
        $relativePath = ltrim(trim($relativePath), '/');
        if ($relativePath === '' || !str_starts_with($relativePath, 'storage/uploads/' . self::RESOURCE_UPLOAD_SUBDIR . '/')) {
            return '';
        }

        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
        $root = realpath($basePath . '/storage/uploads/' . self::RESOURCE_UPLOAD_SUBDIR);
        if ($root === false) {
            return '';
        }

        $target = realpath($basePath . '/' . $relativePath);
        if ($target === false || !str_starts_with($target, $root)) {
            return '';
        }

        return $target;
    }

    private function detectMimeType(string $path): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return '';
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $path);
                if (is_string($detected) && trim($detected) !== '') {
                    $mime = strtolower(trim($detected));
                }
                finfo_close($finfo);
            }
        }
        if ($mime === '' && function_exists('mime_content_type')) {
            $detected = mime_content_type($path);
            if (is_string($detected) && trim($detected) !== '') {
                $mime = strtolower(trim($detected));
            }
        }

        return $mime;
    }

    private function streamResourceFile(array $resource): void
    {
        $absolute = $this->resolveResourceAbsolutePath((string) ($resource['file_path'] ?? ''));
        if ($absolute === '' || !is_file($absolute) || !is_readable($absolute)) {
            Response::abort(404, 'Fisier indisponibil.');
        }

        $filename = trim((string) ($resource['original_name'] ?? ''));
        $filename = str_replace(["\r", "\n", "\0", '"', "'", '/', '\\'], '-', $filename);
        if ($filename === '') {
            $title = $this->sanitizeResourceTitle((string) ($resource['title'] ?? 'document-onboarding'));
            $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
            if ($ext !== '') {
                $filename = $title !== '' ? ($title . '.' . $ext) : ('document-onboarding.' . $ext);
            } else {
                $filename = $title !== '' ? $title : 'document-onboarding';
            }
        }

        $mimeType = trim((string) ($resource['mime_type'] ?? ''));
        if ($mimeType === '') {
            $mimeType = $this->detectMimeType($absolute);
        }
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($absolute));
        header('X-Content-Type-Options: nosniff');
        readfile($absolute);
        exit;
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

    private function resolveAdminReturnPath(string $fallback): string
    {
        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo !== '' && str_starts_with($returnTo, '/admin/')) {
            return $returnTo;
        }

        return $fallback;
    }
}
