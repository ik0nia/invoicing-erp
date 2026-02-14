<?php

namespace App\Domain\Public\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Contracts\Services\ContractOnboardingService;
use App\Domain\Contracts\Services\ContractTemplateVariables;
use App\Domain\Contracts\Services\TemplateRenderer;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Public\Services\PublicLinkResolver;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Response;
use App\Support\Session;
use App\Support\TokenService;

class PublicPartnerController
{
    private const MAX_UPLOAD_BYTES = 20971520;
    private const SIGNED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];

    public function index(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::view('public/partner_portal', [
                'error' => 'Link invalid sau expirat.',
            ], 'layouts/guest');
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        if (empty($context['permissions']['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }

        $partnerCui = $this->resolvePartnerCui($context);
        $prefill = $this->resolvePrefill($context, $partnerCui);
        $scope = $this->resolveScope($context, $partnerCui);

        $contacts = $partnerCui !== '' ? $this->fetchPartnerContacts($partnerCui) : [];
        $relationContacts = $scope['type'] === 'relation' ? $this->fetchRelationContacts($scope) : [];
        $contracts = $partnerCui !== '' ? $this->fetchContracts($scope) : [];

        $previewHtml = '';
        $previewContract = null;
        $previewId = isset($_GET['preview']) ? (int) $_GET['preview'] : 0;
        if ($previewId > 0) {
            $previewContract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $previewId]);
            if ($previewContract && $this->contractAllowed($previewContract, $scope)) {
                $previewHtml = $this->buildContractPreview($previewContract);
            } else {
                $previewContract = null;
            }
        }

        Response::view('public/partner_portal', [
            'context' => $context,
            'prefill' => $prefill,
            'partnerCui' => $partnerCui,
            'contacts' => $contacts,
            'relationContacts' => $relationContacts,
            'contracts' => $contracts,
            'scope' => $scope,
            'previewHtml' => $previewHtml,
            'previewContract' => $previewContract,
            'token' => $token,
        ], 'layouts/guest');
    }

    public function saveCompany(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::abort(404);
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $cui = preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? ''));
        $denumire = CompanyName::normalize((string) ($_POST['denumire'] ?? ''));
        if ($cui === '' || $denumire === '') {
            Session::flash('error', 'Completeaza CUI si denumire.');
            Response::redirect('/p/' . $token);
        }

        Partner::upsert($cui, $denumire);
        $this->upsertCompanyFromPayload($cui, $context, $_POST);

        if ($context['mode'] === 'enrollment') {
            $isSupplier = ($context['enroll_type'] ?? '') === 'supplier';
            $isClient = ($context['enroll_type'] ?? '') === 'client';
            Partner::updateFlags($cui, $isSupplier, $isClient);

            $supplierCui = (string) ($context['supplier_cui'] ?? '');
            if ($isClient && $supplierCui !== '') {
                $this->ensurePartnerRelation($supplierCui, $cui);
                $this->ensureCommission($supplierCui, $cui, $context['link']['commission_percent'] ?? null);
            }

            $contractService = new ContractOnboardingService();
            $contractService->ensureDraftContractForEnrollment(
                (string) ($context['enroll_type'] ?? ''),
                $cui,
                $supplierCui !== '' ? $supplierCui : null,
                $isClient ? $cui : null
            );

            if (empty($context['link']['confirmed_at'])) {
                Database::execute(
                    'UPDATE enrollment_links SET uses = uses + 1, confirmed_at = :now WHERE id = :id',
                    [
                        'now' => date('Y-m-d H:i:s'),
                        'id' => (int) ($context['link']['id'] ?? 0),
                    ]
                );
                $this->ensurePortalLink($context, $cui);
            }
        }

        Session::put($this->contextSessionKey($context), $cui);
        Session::flash('status', 'Datele companiei au fost salvate.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($cui));
    }

    public function saveContact(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::abort(404);
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        if ($partnerCui === '') {
            $partnerCui = $this->resolvePartnerCui($context);
        }
        if ($partnerCui === '') {
            Session::flash('error', 'Completeaza datele companiei inainte de contacte.');
            Response::redirect('/p/' . $token);
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            Session::flash('error', 'Completeaza numele contactului.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));
        $isPrimary = !empty($_POST['is_primary']) ? 1 : 0;
        $scope = $this->resolveScope($context, $partnerCui);
        $contactScope = trim((string) ($_POST['contact_scope'] ?? 'partner'));

        $supplierCui = null;
        $clientCui = null;
        $partnerValue = $partnerCui;

        if ($contactScope === 'relation' && $scope['type'] === 'relation') {
            $partnerValue = null;
            $supplierCui = $scope['supplier_cui'];
            $clientCui = $scope['client_cui'];
        }

        if (!Database::tableExists('partner_contacts')) {
            Session::flash('error', 'Contactele nu sunt disponibile momentan.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
        }

        $hasPrimary = Database::columnExists('partner_contacts', 'is_primary');
        if ($hasPrimary) {
            Database::execute(
                'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, is_primary, created_at)
                 VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :is_primary, :created_at)',
                [
                    'partner' => $partnerValue !== '' ? $partnerValue : null,
                    'supplier' => $supplierCui !== '' ? $supplierCui : null,
                    'client' => $clientCui !== '' ? $clientCui : null,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => $role !== '' ? $role : null,
                    'is_primary' => $isPrimary,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            Database::execute(
                'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, created_at)
                 VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :created_at)',
                [
                    'partner' => $partnerValue !== '' ? $partnerValue : null,
                    'supplier' => $supplierCui !== '' ? $supplierCui : null,
                    'client' => $clientCui !== '' ? $clientCui : null,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => $role !== '' ? $role : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        Session::flash('status', 'Contact adaugat.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
    }

    public function deleteContact(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::abort(404);
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if (!$id) {
            Response::redirect('/p/' . $token);
        }

        $partnerCui = $this->resolvePartnerCui($context);
        $scope = $this->resolveScope($context, $partnerCui);
        $row = Database::fetchOne('SELECT * FROM partner_contacts WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$row || !$this->contactAllowed($row, $partnerCui, $scope)) {
            Response::abort(403, 'Acces interzis.');
        }

        Database::execute('DELETE FROM partner_contacts WHERE id = :id', ['id' => $id]);
        Session::flash('status', 'Contact sters.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
    }

    public function download(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::abort(404);
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        if (empty($context['permissions']['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }

        $kind = trim((string) ($_GET['kind'] ?? ''));
        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if (!in_array($kind, ['generated', 'signed'], true) || $id <= 0) {
            Response::abort(404);
        }

        $partnerCui = $this->resolvePartnerCui($context);
        $scope = $this->resolveScope($context, $partnerCui);
        $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$contract || !$this->contractAllowed($contract, $scope)) {
            Response::abort(403, 'Acces interzis.');
        }

        $path = '';
        if ($kind === 'signed') {
            $path = (string) ($contract['signed_file_path'] ?? '');
        } else {
            $path = (string) ($contract['generated_file_path'] ?? '');
            if ($path === '') {
                $path = (string) ($this->ensureGeneratedFile($contract) ?? '');
            }
        }

        if ($path === '') {
            Response::abort(404);
        }

        $this->streamFile($path);
    }

    public function uploadSigned(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            Response::abort(404);
        }

        if (!$this->throttle($context['hash'])) {
            Response::abort(429, 'Prea multe cereri. Incearca din nou.');
        }

        if (empty($context['permissions']['can_upload_signed'])) {
            Response::abort(403, 'Acces interzis.');
        }

        $contractId = isset($_POST['contract_id']) ? (int) $_POST['contract_id'] : 0;
        if (!$contractId) {
            Response::abort(404);
        }

        $partnerCui = $this->resolvePartnerCui($context);
        $scope = $this->resolveScope($context, $partnerCui);
        $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $contractId]);
        if (!$contract || !$this->contractAllowed($contract, $scope)) {
            Response::abort(403, 'Acces interzis.');
        }

        $path = $this->storeUpload($_FILES['file'] ?? null, 'contracts/signed', self::SIGNED_EXTENSIONS);
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

        Session::flash('status', 'Contract semnat incarcat.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
    }

    private function resolveContext(string $token): ?array
    {
        $resolver = new PublicLinkResolver();

        return $resolver->resolve($token);
    }

    private function resolvePartnerCui(array $context): string
    {
        if (($context['mode'] ?? '') === 'portal') {
            return preg_replace('/\D+/', '', (string) ($context['owner_cui'] ?? ''));
        }

        $key = $this->contextSessionKey($context);
        $cui = preg_replace('/\D+/', '', (string) ($_GET['cui'] ?? ''));
        if ($cui === '') {
            $cui = (string) Session::get($key, '');
        }
        if ($cui === '' && !empty($context['link']['prefill_json'])) {
            $decoded = json_decode((string) $context['link']['prefill_json'], true);
            if (is_array($decoded) && !empty($decoded['cui'])) {
                $cui = preg_replace('/\D+/', '', (string) $decoded['cui']);
            }
        }

        return $cui;
    }

    private function resolvePrefill(array $context, string $partnerCui): array
    {
        if ($partnerCui !== '' && Partner::findByCui($partnerCui)) {
            return $this->prefillFromDb($partnerCui);
        }

        $prefill = [];
        if (!empty($context['link']['prefill_json'])) {
            $decoded = json_decode((string) $context['link']['prefill_json'], true);
            if (is_array($decoded)) {
                $prefill = $decoded;
            }
        }

        return $prefill;
    }

    private function prefillFromDb(string $cui): array
    {
        $data = [];
        $partner = Partner::findByCui($cui);
        if ($partner) {
            $data['cui'] = $partner->cui;
            $data['denumire'] = $partner->denumire;
        }

        if (Database::tableExists('companies')) {
            $company = Company::findByCui($cui);
            if ($company) {
                $data['nr_reg_comertului'] = $company->nr_reg_comertului;
                $data['adresa'] = $company->adresa;
                $data['localitate'] = $company->localitate;
                $data['judet'] = $company->judet;
                $data['telefon'] = $company->telefon;
                $data['email'] = $company->email;
            }
        }

        return $data;
    }

    private function resolveScope(array $context, string $partnerCui): array
    {
        if (($context['mode'] ?? '') === 'portal') {
            if (!empty($context['relation_supplier_cui']) && !empty($context['relation_client_cui'])) {
                return [
                    'type' => 'relation',
                    'supplier_cui' => (string) $context['relation_supplier_cui'],
                    'client_cui' => (string) $context['relation_client_cui'],
                ];
            }
            if (($context['owner_type'] ?? '') === 'supplier') {
                return [
                    'type' => 'supplier',
                    'supplier_cui' => (string) ($context['owner_cui'] ?? ''),
                ];
            }

            return [
                'type' => 'client',
                'client_cui' => (string) ($context['owner_cui'] ?? ''),
            ];
        }

        $type = (string) ($context['enroll_type'] ?? '');
        $supplierCui = (string) ($context['supplier_cui'] ?? '');
        if ($type === 'client' && $supplierCui !== '' && $partnerCui !== '') {
            return [
                'type' => 'relation',
                'supplier_cui' => $supplierCui,
                'client_cui' => $partnerCui,
            ];
        }
        if ($type === 'supplier') {
            return [
                'type' => 'supplier',
                'supplier_cui' => $partnerCui,
            ];
        }

        return [
            'type' => 'client',
            'client_cui' => $partnerCui,
        ];
    }

    private function fetchPartnerContacts(string $partnerCui): array
    {
        if (!Database::tableExists('partner_contacts')) {
            return [];
        }

        $order = Database::columnExists('partner_contacts', 'is_primary') ? 'is_primary DESC, created_at DESC' : 'created_at DESC';

        return Database::fetchAll(
            'SELECT * FROM partner_contacts WHERE partner_cui = :cui ORDER BY ' . $order,
            ['cui' => $partnerCui]
        );
    }

    private function fetchRelationContacts(array $scope): array
    {
        if (!Database::tableExists('partner_contacts')) {
            return [];
        }

        $order = Database::columnExists('partner_contacts', 'is_primary') ? 'is_primary DESC, created_at DESC' : 'created_at DESC';

        return Database::fetchAll(
            'SELECT * FROM partner_contacts WHERE supplier_cui = :supplier AND client_cui = :client ORDER BY ' . $order,
            ['supplier' => $scope['supplier_cui'], 'client' => $scope['client_cui']]
        );
    }

    private function contactAllowed(array $row, string $partnerCui, array $scope): bool
    {
        if (!empty($row['partner_cui']) && $partnerCui !== '') {
            return (string) $row['partner_cui'] === $partnerCui;
        }
        if ($scope['type'] === 'relation') {
            return (string) ($row['supplier_cui'] ?? '') === $scope['supplier_cui']
                && (string) ($row['client_cui'] ?? '') === $scope['client_cui'];
        }

        return false;
    }

    private function fetchContracts(array $scope): array
    {
        if (!Database::tableExists('contracts')) {
            return [];
        }

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

    private function buildContractPreview(array $contract): string
    {
        $title = (string) ($contract['title'] ?? 'Contract');
        $templateId = isset($contract['template_id']) ? (int) $contract['template_id'] : 0;
        $html = '';

        if ($templateId > 0) {
            $template = Database::fetchOne(
                'SELECT html_content FROM contract_templates WHERE id = :id LIMIT 1',
                ['id' => $templateId]
            );
            $html = $template['html_content'] ?? '';
        }

        if ($html === '') {
            $html = '<html><body><h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1></body></html>';
        }

        $createdAt = $contract['created_at'] ?? null;
        $createdDate = $createdAt ? date('Y-m-d', strtotime((string) $createdAt)) : date('Y-m-d');
        $variablesService = new ContractTemplateVariables();
        $renderer = new TemplateRenderer();
        $vars = $variablesService->buildVariables(
            $contract['partner_cui'] ?? null,
            $contract['supplier_cui'] ?? null,
            $contract['client_cui'] ?? null,
            ['title' => $title, 'created_at' => $createdDate]
        );

        return $renderer->render($html, $vars);
    }

    private function ensureGeneratedFile(array $contract): ?string
    {
        $status = (string) ($contract['status'] ?? '');
        if (!in_array($status, ['generated', 'sent', 'signed_uploaded', 'approved'], true)) {
            return null;
        }

        $html = $this->buildContractPreview($contract);
        $path = $this->storeGeneratedFile($html);
        if ($path === null) {
            return null;
        }

        Database::execute(
            'UPDATE contracts SET generated_file_path = :path, updated_at = :now WHERE id = :id',
            [
                'path' => $path,
                'now' => date('Y-m-d H:i:s'),
                'id' => (int) ($contract['id'] ?? 0),
            ]
        );

        return $path;
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

    private function upsertCompanyFromPayload(string $cui, array $context, array $payload): void
    {
        if (!Database::tableExists('companies')) {
            return;
        }

        $existing = Company::findByCui($cui);
        $companyType = $existing ? $existing->tip_companie : (($context['enroll_type'] ?? '') === 'supplier' ? 'furnizor' : 'client');

        $data = [
            'denumire' => $existing ? $existing->denumire : '',
            'tip_firma' => $existing ? $existing->tip_firma : 'SRL',
            'cui' => $cui,
            'nr_reg_comertului' => $existing ? $existing->nr_reg_comertului : '',
            'platitor_tva' => $existing ? (int) $existing->platitor_tva : 0,
            'adresa' => $existing ? $existing->adresa : '',
            'localitate' => $existing ? $existing->localitate : '',
            'judet' => $existing ? $existing->judet : '',
            'tara' => $existing ? $existing->tara : 'Romania',
            'email' => $existing ? $existing->email : '',
            'telefon' => $existing ? $existing->telefon : '',
            'banca' => $existing ? $existing->banca : null,
            'iban' => $existing ? $existing->iban : null,
            'tip_companie' => $companyType,
            'activ' => $existing ? (int) $existing->activ : 1,
        ];

        $map = [
            'denumire' => 'denumire',
            'nr_reg_comertului' => 'nr_reg_comertului',
            'adresa' => 'adresa',
            'localitate' => 'localitate',
            'judet' => 'judet',
            'email' => 'email',
            'telefon' => 'telefon',
        ];
        foreach ($map as $input => $field) {
            $value = trim((string) ($payload[$input] ?? ''));
            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        Company::save($data);
    }

    private function ensurePartnerRelation(string $supplierCui, string $clientCui): void
    {
        if (!Database::tableExists('partner_relations')) {
            return;
        }

        Database::execute(
            'INSERT IGNORE INTO partner_relations (supplier_cui, client_cui, created_at)
             VALUES (:supplier, :client, :created_at)',
            [
                'supplier' => $supplierCui,
                'client' => $clientCui,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function ensureCommission(string $supplierCui, string $clientCui, mixed $commission): void
    {
        if (!Database::tableExists('commissions')) {
            return;
        }

        $existing = Commission::forSupplierClient($supplierCui, $clientCui);
        if ($existing) {
            return;
        }
        $value = $commission !== null ? (float) $commission : 0.0;
        Database::execute(
            'INSERT INTO commissions (supplier_cui, client_cui, commission, created_at, updated_at)
             VALUES (:supplier, :client, :commission, :created_at, :updated_at)',
            [
                'supplier' => $supplierCui,
                'client' => $clientCui,
                'commission' => $value,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function ensurePortalLink(array $context, string $partnerCui): void
    {
        if (!Database::tableExists('portal_links')) {
            return;
        }

        $ownerType = ($context['enroll_type'] ?? '') === 'supplier' ? 'supplier' : 'client';
        $relationSupplier = null;
        $relationClient = null;
        if (($context['enroll_type'] ?? '') === 'client' && !empty($context['supplier_cui'])) {
            $relationSupplier = (string) $context['supplier_cui'];
            $relationClient = $partnerCui;
        }

        $where = 'owner_type = :owner_type AND owner_cui = :owner_cui';
        $params = [
            'owner_type' => $ownerType,
            'owner_cui' => $partnerCui,
        ];
        if ($relationSupplier !== null && $relationClient !== null) {
            $where .= ' AND relation_supplier_cui = :relation_supplier_cui AND relation_client_cui = :relation_client_cui';
            $params['relation_supplier_cui'] = $relationSupplier;
            $params['relation_client_cui'] = $relationClient;
        } else {
            $where .= ' AND relation_supplier_cui IS NULL AND relation_client_cui IS NULL';
        }

        $existing = Database::fetchOne('SELECT id FROM portal_links WHERE ' . $where . ' LIMIT 1', $params);
        if ($existing) {
            return;
        }

        $token = TokenService::generateToken(32);
        $tokenHash = TokenService::hashToken($token);
        $permissions = json_encode([
            'can_view' => true,
            'can_upload_signed' => true,
            'can_upload_custom' => false,
        ], JSON_UNESCAPED_UNICODE);

        Database::execute(
            'INSERT INTO portal_links (token_hash, owner_type, owner_cui, relation_supplier_cui, relation_client_cui, permissions_json, status, expires_at, created_by_user_id, created_at)
             VALUES (:token_hash, :owner_type, :owner_cui, :relation_supplier_cui, :relation_client_cui, :permissions_json, :status, :expires_at, :created_by_user_id, :created_at)',
            [
                'token_hash' => $tokenHash,
                'owner_type' => $ownerType,
                'owner_cui' => $partnerCui,
                'relation_supplier_cui' => $relationSupplier,
                'relation_client_cui' => $relationClient,
                'permissions_json' => $permissions,
                'status' => 'active',
                'expires_at' => null,
                'created_by_user_id' => null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function storeUpload(?array $file, string $subdir, array $allowedExtensions): ?string
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
        if ($ext === '' || !in_array($ext, $allowedExtensions, true)) {
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

    private function throttle(string $hash): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'public|' . $hash . '|' . $ip;
        return RateLimiter::hit($key, 60, 600);
    }

    private function contextSessionKey(array $context): string
    {
        $id = (int) ($context['link']['id'] ?? 0);
        $mode = (string) ($context['mode'] ?? 'public');

        return 'public_partner_cui_' . $mode . '_' . $id;
    }
}
