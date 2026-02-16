<?php

namespace App\Domain\Public\Http\Controllers;

use App\Domain\Companies\Models\Company;
use App\Domain\Companies\Services\CompanyLookupService;
use App\Domain\Contracts\Services\ContractOnboardingService;
use App\Domain\Contracts\Services\ContractPdfService;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Domain\Public\Services\PublicLinkResolver;
use App\Support\Audit;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Response;
use App\Support\Session;

class PublicPartnerController
{
    private const MAX_UPLOAD_BYTES = 20971520;
    private const SIGNED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png'];
    private const EDITABLE_ONBOARDING_STATUSES = ['draft', 'waiting_signature', 'rejected'];
    private const CONTACT_DEPARTMENTS = ['Reprezentant legal', 'Financiar-contabil', 'Achizitii', 'Logistica'];

    public function index(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            Response::abort(404);
        }

        $context = $this->resolveContext($token);
        if (!$context) {
            $anyContext = $this->resolveAnyContext($token);
            if ($anyContext && ($anyContext['link']['status'] ?? '') === 'disabled') {
                Response::view('public/partner_portal', [
                    'error' => 'Link dezactivat. Contactati administratorul.',
                ], 'layouts/guest');
            }
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

        $onboardingStatus = $this->resolveOnboardingStatus($context['link'] ?? []);
        $partnerCui = $this->resolvePartnerCui($context);
        $prefill = $this->resolvePrefill($context, $partnerCui);
        $scope = $this->resolveScope($context, $partnerCui);

        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        if ($currentStep < 1 || $currentStep > 3) {
            $currentStep = 1;
        }
        if ($partnerCui === '' && $currentStep > 1) {
            $currentStep = 1;
        }
        if ($partnerCui !== '' && $currentStep > 1 && !$this->hasMandatoryCompanyProfile($partnerCui)) {
            $currentStep = 1;
        }
        if (in_array($onboardingStatus, ['submitted', 'approved'], true)) {
            $currentStep = 3;
        }

        if ($partnerCui !== '' && $currentStep >= 2) {
            $this->ensureRequiredOnboardingContracts($context, $scope, $partnerCui);
        }
        $contracts = $partnerCui !== '' ? $this->fetchContracts($scope) : [];
        $documentsProgress = $this->buildDocumentsProgress($contracts);
        if ($currentStep === 3 && !$documentsProgress['all_signed'] && !in_array($onboardingStatus, ['submitted', 'approved'], true)) {
            $currentStep = 2;
        }

        if (!in_array($onboardingStatus, ['submitted', 'approved'], true)) {
            $this->syncOnboardingStatus(
                (int) ($context['link']['id'] ?? 0),
                $onboardingStatus,
                $partnerCui,
                $documentsProgress
            );
        }
        $onboardingStatus = $this->refreshOnboardingStatus((int) ($context['link']['id'] ?? 0), $onboardingStatus);

        $contacts = $partnerCui !== '' ? $this->fetchPartnerContacts($partnerCui) : [];
        $relationContacts = $scope['type'] === 'relation' ? $this->fetchRelationContacts($scope) : [];
        $company = null;
        if ($partnerCui !== '' && Database::tableExists('companies')) {
            $company = Company::findByCui($partnerCui);
        }

        $this->touchLink((int) ($context['link']['id'] ?? 0), $currentStep, true);
        Audit::record('public_wizard.view', 'public_link', (int) ($context['link']['id'] ?? 0), [
            'rows_count' => 1,
            'current_step' => $currentStep,
            'onboarding_status' => $onboardingStatus,
        ]);

        Response::view('public/partner_portal', [
            'context' => $context,
            'prefill' => $prefill,
            'partnerCui' => $partnerCui,
            'company' => $company,
            'contacts' => $contacts,
            'relationContacts' => $relationContacts,
            'contracts' => $contracts,
            'documentsProgress' => $documentsProgress,
            'scope' => $scope,
            'currentStep' => $currentStep,
            'onboardingStatus' => $onboardingStatus,
            'pdfAvailable' => (new ContractPdfService())->isPdfGenerationAvailable(),
            'token' => $token,
            'contactDepartments' => self::CONTACT_DEPARTMENTS,
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
        if (empty($context['permissions']['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }
        $this->ensureEditableOnboarding($context['link'] ?? []);

        $cui = preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? ''));
        $denumire = CompanyName::normalize((string) ($_POST['denumire'] ?? ''));
        if ($cui === '' || $denumire === '') {
            Session::flash('error', 'Completeaza CUI si denumire.');
            Response::redirect('/p/' . $token);
        }

        $legalRepresentativeName = $this->sanitizeCompanyValue((string) ($_POST['legal_representative_name'] ?? ($_POST['representative_name'] ?? '')));
        $legalRepresentativeRole = $this->sanitizeCompanyValue((string) ($_POST['legal_representative_role'] ?? ($_POST['representative_function'] ?? '')));
        $bankName = $this->sanitizeCompanyValue((string) ($_POST['bank_name'] ?? ($_POST['banca'] ?? '')));
        $iban = $this->sanitizeIban((string) ($_POST['iban'] ?? ($_POST['bank_account'] ?? '')));

        if ($legalRepresentativeName === '' || $legalRepresentativeRole === '' || $bankName === '' || $iban === '') {
            Session::flash('error', 'Pentru a continua, completeaza reprezentantul legal, functia, banca si IBAN-ul companiei.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($cui) . '#pas-1');
        }
        if (!$this->isValidIban($iban)) {
            Session::flash('error', 'IBAN invalid. Folositi un IBAN cu lungime intre 15 si 34 caractere.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($cui) . '#pas-1');
        }

        $payload = $_POST;
        $payload['legal_representative_name'] = $legalRepresentativeName;
        $payload['legal_representative_role'] = $legalRepresentativeRole;
        $payload['bank_name'] = $bankName;
        $payload['iban'] = $iban;
        $payload['representative_name'] = $legalRepresentativeName;
        $payload['representative_function'] = $legalRepresentativeRole;
        $payload['banca'] = $bankName;
        $payload['bank_account'] = $iban;

        Partner::upsert($cui, $denumire);
        $this->upsertCompanyFromPayload($cui, $context, $payload);
        $this->ensureLegalRepresentativeContact(
            $cui,
            $legalRepresentativeName,
            $this->sanitizeContactValue((string) ($_POST['email'] ?? '')),
            $this->sanitizeContactValue((string) ($_POST['telefon'] ?? ''))
        );

        $linkType = (string) ($context['link']['type'] ?? '');
        $isSupplier = $linkType === 'supplier';
        $isClient = $linkType === 'client';
        Partner::updateFlags($cui, $isSupplier, $isClient);

        $supplierCui = (string) ($context['link']['relation_supplier_cui'] ?? $context['link']['supplier_cui'] ?? '');
        if ($isClient && $supplierCui !== '') {
            $this->ensurePartnerRelation($supplierCui, $cui);
            $this->ensureCommission($supplierCui, $cui, $context['link']['commission_percent'] ?? null);
        }

        $nextStep = isset($_POST['next_step']) ? (int) $_POST['next_step'] : 0;
        if ($nextStep < 1 || $nextStep > 3) {
            $nextStep = 0;
        }
        $this->updateLinkAfterCompanySave($context, $cui, $supplierCui, $nextStep);

        $scope = $this->resolveScope($context, $cui);
        if ($nextStep >= 2) {
            $this->ensureRequiredOnboardingContracts($context, $scope, $cui);
        }
        $documentsProgress = $this->buildDocumentsProgress($this->fetchContracts($scope));
        $this->syncOnboardingStatus(
            (int) ($context['link']['id'] ?? 0),
            $this->resolveOnboardingStatus($context['link'] ?? []),
            $cui,
            $documentsProgress
        );

        Audit::record('public_company.save', 'public_link', (int) ($context['link']['id'] ?? 0), [
            'supplier_cui' => $isSupplier ? $cui : ($supplierCui !== '' ? $supplierCui : null),
            'selected_client_cui' => $isClient ? $cui : null,
        ]);

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
        if (empty($context['permissions']['can_view'])) {
            Response::abort(403, 'Acces interzis.');
        }
        $this->ensureEditableOnboarding($context['link'] ?? []);

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

        $email = $this->sanitizeContactValue((string) ($_POST['email'] ?? ''));
        $phone = $this->sanitizeContactValue((string) ($_POST['phone'] ?? ''));
        $role = $this->normalizeContactDepartment((string) ($_POST['role'] ?? ($_POST['department'] ?? '')));
        if ($role === '') {
            Session::flash('error', 'Selectati departamentul contactului.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
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
                    'partner' => $partnerCui !== '' ? $partnerCui : null,
                    'supplier' => null,
                    'client' => null,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => $role !== '' ? $role : null,
                    'is_primary' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            Database::execute(
                'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, created_at)
                 VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :created_at)',
                [
                    'partner' => $partnerCui !== '' ? $partnerCui : null,
                    'supplier' => null,
                    'client' => null,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => $role !== '' ? $role : null,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        }

        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        $this->touchLink((int) ($context['link']['id'] ?? 0), max(1, min(3, $currentStep)), true);
        Audit::record('public_contact.save', 'public_link', (int) ($context['link']['id'] ?? 0), [
            'rows_count' => 1,
        ]);

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
        $this->ensureEditableOnboarding($context['link'] ?? []);

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
        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        $this->touchLink((int) ($context['link']['id'] ?? 0), max(1, min(3, $currentStep)), true);
        Audit::record('public_contact.delete', 'public_link', (int) ($context['link']['id'] ?? 0), [
            'rows_count' => 1,
        ]);
        Session::flash('status', 'Contact sters.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
    }

    public function setStep(): void
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

        $step = isset($_POST['step']) ? (int) $_POST['step'] : 1;
        if ($step < 1 || $step > 3) {
            $step = 1;
        }

        $status = $this->resolveOnboardingStatus($context['link'] ?? []);
        $partnerCui = $this->resolvePartnerCui($context);
        $scope = $this->resolveScope($context, $partnerCui);
        if ($step > 1 && $partnerCui === '') {
            Session::flash('error', 'Completeaza datele companiei pentru a continua.');
            $step = 1;
        }
        if ($step > 1 && $partnerCui !== '' && !$this->hasMandatoryCompanyProfile($partnerCui)) {
            Session::flash('error', 'Pentru a continua, completeaza reprezentantul legal, functia, banca si IBAN-ul companiei.');
            $step = 1;
        }
        if ($step === 2 && $partnerCui !== '') {
            $this->ensureRequiredOnboardingContracts($context, $scope, $partnerCui);
        }
        if ($step === 3 && !in_array($status, ['submitted', 'approved'], true)) {
            $this->ensureRequiredOnboardingContracts($context, $scope, $partnerCui);
            $documentsProgress = $this->buildDocumentsProgress($this->fetchContracts($scope));
            if (!$documentsProgress['all_signed']) {
                Session::flash('error', 'Pentru a continua, incarcati documentele semnate obligatorii.');
                $step = 2;
            }
        }
        if (in_array($status, ['submitted', 'approved'], true)) {
            $step = 3;
        }

        $this->touchLink((int) ($context['link']['id'] ?? 0), $step, false);
        Session::flash('status', 'Pas actualizat.');
        Response::redirect('/p/' . $token . '#pas-' . $step);
    }

    public function preview(): void
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

        $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        if ($id <= 0) {
            Response::abort(404);
        }

        $partnerCui = $this->resolvePartnerCui($context);
        $scope = $this->resolveScope($context, $partnerCui);
        $contract = Database::fetchOne('SELECT * FROM contracts WHERE id = :id LIMIT 1', ['id' => $id]);
        if (!$contract || !$this->contractAllowed($contract, $scope)) {
            Response::abort(403, 'Acces interzis.');
        }

        $html = (new ContractPdfService())->renderHtmlForContract($contract, 'public');
        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        $this->touchLink((int) ($context['link']['id'] ?? 0), $currentStep, false);
        Audit::record('public_contract.preview', 'contract', $id, ['rows_count' => 1]);

        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
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
            $path = (string) ($contract['signed_upload_path'] ?? '');
            if ($path === '') {
                $path = (string) ($contract['signed_file_path'] ?? '');
            }
        } else {
            $path = (string) ($contract['generated_pdf_path'] ?? '');
            if ($path === '') {
                $path = (new ContractPdfService())->generatePdfForContract((int) ($contract['id'] ?? 0), 'public');
            }
        }
        if ($path === '') {
            Session::flash('error', 'PDF indisponibil momentan. Un angajat poate verifica configurarea serverului.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-2');
        }

        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        $this->touchLink((int) ($context['link']['id'] ?? 0), $currentStep, false);
        Audit::record('public_contract.download', 'contract', $id, [
            'rows_count' => 1,
            'kind' => $kind,
        ]);
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
        $this->ensureEditableOnboarding($context['link'] ?? []);

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
            'UPDATE contracts
             SET signed_upload_path = :path,
                 signed_file_path = :path,
                 status = :status,
                 updated_at = :now
             WHERE id = :id',
            [
                'path' => $path,
                'status' => 'signed_uploaded',
                'now' => date('Y-m-d H:i:s'),
                'id' => $contractId,
            ]
        );

        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        $this->touchLink((int) ($context['link']['id'] ?? 0), $currentStep, false);
        Audit::record('public_contract.upload_signed', 'contract', $contractId, ['rows_count' => 1]);
        Audit::record('contract.signed_uploaded', 'contract', $contractId, ['rows_count' => 1]);

        $documentsProgress = $this->buildDocumentsProgress($this->fetchContracts($scope));
        $this->syncOnboardingStatus(
            (int) ($context['link']['id'] ?? 0),
            $this->resolveOnboardingStatus($context['link'] ?? []),
            $partnerCui,
            $documentsProgress
        );

        Session::flash('status', 'Contract semnat incarcat.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-2');
    }

    public function submitForActivation(): void
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
        $this->ensureEditableOnboarding($context['link'] ?? []);

        $partnerCui = $this->resolvePartnerCui($context);
        if ($partnerCui === '') {
            Session::flash('error', 'Salvati datele companiei inainte de trimitere.');
            Response::redirect('/p/' . $token);
        }
        if (Database::tableExists('companies') && !Company::findByCui($partnerCui)) {
            Session::flash('error', 'Datele companiei nu sunt complete. Salvati Pasul 1 inainte de trimitere.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui));
        }
        if (!$this->hasMandatoryCompanyProfile($partnerCui)) {
            Session::flash('error', 'Completati reprezentantul legal, functia, banca si IBAN-ul in Pasul 1.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-1');
        }

        $scope = $this->resolveScope($context, $partnerCui);
        $this->ensureRequiredOnboardingContracts($context, $scope, $partnerCui);
        $documentsProgress = $this->buildDocumentsProgress($this->fetchContracts($scope));
        if (!$documentsProgress['all_signed']) {
            Session::flash('error', 'Pentru a continua, incarcati documentele semnate obligatorii.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-2');
        }

        if (empty($_POST['checkbox_confirmed'])) {
            Session::flash('error', 'Bifati confirmarea datelor pentru a trimite inrolarea spre activare.');
            Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-3');
        }

        $linkId = (int) ($context['link']['id'] ?? 0);
        if ($linkId <= 0 || !Database::tableExists('enrollment_links')) {
            Response::abort(404);
        }

        $now = date('Y-m-d H:i:s');
        Database::execute(
            'UPDATE enrollment_links
             SET onboarding_status = :onboarding_status,
                 submitted_at = :submitted_at,
                 checkbox_confirmed = :checkbox_confirmed,
                 current_step = :current_step,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'onboarding_status' => 'submitted',
                'submitted_at' => $now,
                'checkbox_confirmed' => 1,
                'current_step' => 3,
                'updated_at' => $now,
                'id' => $linkId,
            ]
        );
        Audit::record('onboarding.submitted', 'enrollment_link', $linkId, [
            'rows_count' => 1,
            'partner_cui' => $partnerCui,
        ]);

        Session::flash('status', 'Trimis. Un angajat va activa inrolarea.');
        Response::redirect('/p/' . $token . '?cui=' . urlencode($partnerCui) . '#pas-3');
    }

    private function resolveContext(string $token): ?array
    {
        $resolver = new PublicLinkResolver();

        return $resolver->resolve($token);
    }

    private function resolveAnyContext(string $token): ?array
    {
        $resolver = new PublicLinkResolver();

        return $resolver->resolveAny($token);
    }

    private function resolvePartnerCui(array $context): string
    {
        $linkCui = preg_replace('/\D+/', '', (string) ($context['link']['partner_cui'] ?? ''));
        if ($linkCui !== '') {
            return $linkCui;
        }

        $cui = preg_replace('/\D+/', '', (string) ($_GET['cui'] ?? ''));
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
        if ($partnerCui !== '') {
            $partner = Partner::findByCui($partnerCui);
            $company = Database::tableExists('companies') ? Company::findByCui($partnerCui) : null;
            if ($partner || $company) {
                return $this->prefillFromDb($partnerCui);
            }
        }

        $prefill = [];
        if (!empty($context['link']['prefill_json'])) {
            $decoded = json_decode((string) $context['link']['prefill_json'], true);
            if (is_array($decoded)) {
                $prefill = $decoded;
            }
        }

        $lookup = trim((string) ($_GET['lookup'] ?? ''));
        $lookupCui = preg_replace('/\D+/', '', (string) ($_GET['lookup_cui'] ?? ''));
        if ($lookupCui === '' && $partnerCui !== '') {
            $lookupCui = $partnerCui;
        }
        if ($lookupCui === '' && !empty($prefill['cui'])) {
            $lookupCui = preg_replace('/\D+/', '', (string) $prefill['cui']);
        }
        if ($lookup === '1' && $lookupCui !== '') {
            $service = new CompanyLookupService();
            $response = $service->lookupByCui($lookupCui);
            if ($response['error'] === null && is_array($response['data'])) {
                $prefill = $this->mergePrefill($prefill, $response['data']);
            }
        }

        return $prefill;
    }

    private function prefillFromDb(string $cui): array
    {
        $data = [];
        $partner = Partner::findByCui($cui);
        $company = Database::tableExists('companies') ? Company::findByCui($cui) : null;
        if ($company) {
            $data['cui'] = $company->cui;
            $data['denumire'] = $company->denumire;
            $data['nr_reg_comertului'] = $company->nr_reg_comertului;
            $data['adresa'] = $company->adresa;
            $data['localitate'] = $company->localitate;
            $data['judet'] = $company->judet;
            $data['telefon'] = $company->telefon;
            $data['email'] = $company->email;
            $data['legal_representative_name'] = (string) ($company->legal_representative_name !== '' ? $company->legal_representative_name : ($company->representative_name ?? ''));
            $data['legal_representative_role'] = (string) ($company->legal_representative_role !== '' ? $company->legal_representative_role : ($company->representative_function ?? ''));
            $data['bank_name'] = (string) ($company->bank_name ?? $company->banca ?? '');
            $data['iban'] = (string) ($company->iban ?? $company->bank_account ?? '');
        }
        if ($partner) {
            if (empty($data['cui'])) {
                $data['cui'] = $partner->cui;
            }
            if (empty($data['denumire'])) {
                $data['denumire'] = $partner->denumire;
            }
            if (empty($data['legal_representative_name'])) {
                $data['legal_representative_name'] = (string) ($partner->representative_name ?? '');
            }
            if (empty($data['legal_representative_role'])) {
                $data['legal_representative_role'] = (string) ($partner->representative_function ?? '');
            }
            if (empty($data['bank_name'])) {
                $data['bank_name'] = (string) ($partner->bank_name ?? '');
            }
            if (empty($data['iban'])) {
                $data['iban'] = (string) ($partner->bank_account ?? '');
            }
        }

        $data['representative_name'] = (string) ($data['legal_representative_name'] ?? '');
        $data['representative_function'] = (string) ($data['legal_representative_role'] ?? '');
        $data['bank_account'] = (string) ($data['iban'] ?? '');

        return $data;
    }

    private function resolveScope(array $context, string $partnerCui): array
    {
        $relationSupplier = (string) ($context['link']['relation_supplier_cui'] ?? '');
        $relationClient = (string) ($context['link']['relation_client_cui'] ?? '');
        if ($relationSupplier !== '' && $relationClient !== '') {
            return [
                'type' => 'relation',
                'supplier_cui' => $relationSupplier,
                'client_cui' => $relationClient,
            ];
        }

        $type = (string) ($context['link']['type'] ?? '');
        $supplierCui = (string) ($context['link']['supplier_cui'] ?? '');
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
        $orderParts = [];
        if (Database::columnExists('contracts', 'required_onboarding')) {
            $orderParts[] = 'required_onboarding DESC';
        }
        if (Database::columnExists('contracts', 'contract_date')) {
            $orderParts[] = 'contract_date DESC';
        }
        $orderParts[] = 'created_at DESC';
        $orderParts[] = 'id DESC';
        $orderBy = implode(', ', $orderParts);

        if ($scope['type'] === 'relation') {
            return Database::fetchAll(
                'SELECT * FROM contracts WHERE supplier_cui = :supplier AND client_cui = :client ORDER BY ' . $orderBy,
                ['supplier' => $scope['supplier_cui'], 'client' => $scope['client_cui']]
            );
        }

        if ($scope['type'] === 'supplier') {
            return Database::fetchAll(
                'SELECT * FROM contracts WHERE partner_cui = :partner OR supplier_cui = :supplier ORDER BY ' . $orderBy,
                ['partner' => $scope['supplier_cui'], 'supplier' => $scope['supplier_cui']]
            );
        }

        return Database::fetchAll(
            'SELECT * FROM contracts WHERE partner_cui = :partner OR client_cui = :client ORDER BY ' . $orderBy,
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

    private function ensureRequiredOnboardingContracts(array $context, array $scope, string $partnerCui): void
    {
        if ($partnerCui === '') {
            return;
        }

        $linkType = (string) ($context['link']['type'] ?? 'client');
        $supplierCui = null;
        $clientCui = null;
        if ($scope['type'] === 'relation') {
            $supplierCui = (string) ($scope['supplier_cui'] ?? '');
            $clientCui = (string) ($scope['client_cui'] ?? '');
        } elseif ($scope['type'] === 'supplier') {
            $supplierCui = (string) ($scope['supplier_cui'] ?? '');
        } elseif ($scope['type'] === 'client') {
            $clientCui = (string) ($scope['client_cui'] ?? '');
        }

        $service = new ContractOnboardingService();
        $service->ensureDraftContractForEnrollment(
            $linkType,
            $partnerCui,
            $supplierCui !== '' ? $supplierCui : null,
            $clientCui !== '' ? $clientCui : null
        );
    }

    private function upsertCompanyFromPayload(string $cui, array $context, array $payload): void
    {
        if (!Database::tableExists('companies')) {
            return;
        }

        $existing = Company::findByCui($cui);
        $partner = Partner::findByCui($cui);
        $linkType = (string) ($context['link']['type'] ?? 'client');
        $companyType = $existing ? $existing->tip_companie : ($linkType === 'supplier' ? 'furnizor' : 'client');

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
            'legal_representative_name' => $existing ? ($existing->legal_representative_name !== '' ? $existing->legal_representative_name : ($existing->representative_name ?? '')) : ($partner?->representative_name ?? ''),
            'legal_representative_role' => $existing ? ($existing->legal_representative_role !== '' ? $existing->legal_representative_role : ($existing->representative_function ?? '')) : ($partner?->representative_function ?? ''),
            'bank_name' => $existing ? ($existing->bank_name ?? $existing->banca ?? '') : ($partner?->bank_name ?? ''),
            'iban' => $existing ? (string) ($existing->iban ?? $existing->bank_account ?? '') : (string) ($partner?->bank_account ?? ''),
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
            $value = $this->sanitizeCompanyValue((string) ($payload[$input] ?? ''));
            if ($value !== '') {
                $data[$field] = $value;
            }
        }

        $legalRepresentativeName = $this->sanitizeCompanyValue((string) ($payload['legal_representative_name'] ?? ($payload['representative_name'] ?? '')));
        if ($legalRepresentativeName !== '') {
            $data['legal_representative_name'] = $legalRepresentativeName;
        }
        $legalRepresentativeRole = $this->sanitizeCompanyValue((string) ($payload['legal_representative_role'] ?? ($payload['representative_function'] ?? '')));
        if ($legalRepresentativeRole !== '') {
            $data['legal_representative_role'] = $legalRepresentativeRole;
        }
        $bankName = $this->sanitizeCompanyValue((string) ($payload['bank_name'] ?? ($payload['banca'] ?? '')));
        if ($bankName !== '') {
            $data['bank_name'] = $bankName;
        }
        $iban = $this->sanitizeIban((string) ($payload['iban'] ?? ($payload['bank_account'] ?? '')));
        if ($iban !== '') {
            $data['iban'] = $iban;
        }

        $data['representative_name'] = (string) ($data['legal_representative_name'] ?? '');
        $data['representative_function'] = (string) ($data['legal_representative_role'] ?? '');
        $data['banca'] = (string) ($data['bank_name'] ?? '');
        $data['bank_account'] = (string) ($data['iban'] ?? '');

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

    private function updateLinkAfterCompanySave(array $context, string $partnerCui, string $supplierCui, int $nextStep): void
    {
        if (!Database::tableExists('enrollment_links')) {
            return;
        }

        $linkId = (int) ($context['link']['id'] ?? 0);
        if ($linkId <= 0) {
            return;
        }

        $currentStep = (int) ($context['link']['current_step'] ?? 1);
        if ($currentStep < 1 || $currentStep > 3) {
            $currentStep = 1;
        }
        $stepValue = $nextStep > 0 ? $nextStep : $currentStep;
        $type = (string) ($context['link']['type'] ?? '');

        $relationSupplier = (string) ($context['link']['relation_supplier_cui'] ?? '');
        $relationClient = (string) ($context['link']['relation_client_cui'] ?? '');
        if ($type === 'client' && $supplierCui !== '') {
            $relationSupplier = $relationSupplier !== '' ? $relationSupplier : $supplierCui;
            $relationClient = $partnerCui;
        }

        $now = date('Y-m-d H:i:s');
        Database::execute(
            'UPDATE enrollment_links
             SET partner_cui = :partner_cui,
                 relation_supplier_cui = :relation_supplier_cui,
                 relation_client_cui = :relation_client_cui,
                 current_step = :current_step,
                 onboarding_status = :onboarding_status,
                 checkbox_confirmed = :checkbox_confirmed,
                 submitted_at = :submitted_at,
                 approved_at = :approved_at,
                 approved_by_user_id = :approved_by_user_id,
                 uses = uses + 1,
                 last_used_at = :last_used_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'partner_cui' => $partnerCui !== '' ? $partnerCui : null,
                'relation_supplier_cui' => $relationSupplier !== '' ? $relationSupplier : null,
                'relation_client_cui' => $relationClient !== '' ? $relationClient : null,
                'current_step' => $stepValue,
                'onboarding_status' => 'draft',
                'checkbox_confirmed' => 0,
                'submitted_at' => null,
                'approved_at' => null,
                'approved_by_user_id' => null,
                'last_used_at' => $now,
                'updated_at' => $now,
                'id' => $linkId,
            ]
        );
    }

    private function touchLink(int $linkId, int $currentStep, bool $incrementUses): void
    {
        if ($linkId <= 0 || !Database::tableExists('enrollment_links')) {
            return;
        }

        if ($currentStep < 1 || $currentStep > 3) {
            $currentStep = 1;
        }
        $now = date('Y-m-d H:i:s');
        $sets = [
            'last_used_at = :last_used_at',
            'updated_at = :updated_at',
            'current_step = :current_step',
        ];
        if ($incrementUses) {
            $sets[] = 'uses = uses + 1';
        }

        $sql = 'UPDATE enrollment_links SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Database::execute($sql, [
            'last_used_at' => $now,
            'updated_at' => $now,
            'current_step' => $currentStep,
            'id' => $linkId,
        ]);
    }

    private function buildDocumentsProgress(array $contracts): array
    {
        $requiredContracts = [];
        foreach ($contracts as $contract) {
            if (!empty($contract['required_onboarding'])) {
                $requiredContracts[] = $contract;
            }
        }

        $requiredSigned = 0;
        $missing = [];
        foreach ($requiredContracts as $contract) {
            $signedPath = trim((string) ($contract['signed_upload_path'] ?? $contract['signed_file_path'] ?? ''));
            if ($signedPath !== '') {
                $requiredSigned++;
            } else {
                $missing[] = [
                    'id' => (int) ($contract['id'] ?? 0),
                    'title' => (string) ($contract['title'] ?? 'Document'),
                    'doc_type' => (string) ($contract['doc_type'] ?? 'contract'),
                ];
            }
        }

        $requiredTotal = count($requiredContracts);
        $allSigned = $requiredTotal === 0 || $requiredSigned >= $requiredTotal;

        return [
            'required_total' => $requiredTotal,
            'required_signed' => $requiredSigned,
            'all_signed' => $allSigned,
            'missing' => $missing,
        ];
    }

    private function resolveOnboardingStatus(array $link): string
    {
        $status = trim((string) ($link['onboarding_status'] ?? 'draft'));
        if (!in_array($status, ['draft', 'waiting_signature', 'submitted', 'approved', 'rejected'], true)) {
            return 'draft';
        }

        return $status;
    }

    private function refreshOnboardingStatus(int $linkId, string $fallback): string
    {
        if ($linkId <= 0 || !Database::tableExists('enrollment_links') || !Database::columnExists('enrollment_links', 'onboarding_status')) {
            return $fallback;
        }
        $row = Database::fetchOne('SELECT onboarding_status FROM enrollment_links WHERE id = :id LIMIT 1', ['id' => $linkId]);
        if (!$row) {
            return $fallback;
        }

        return $this->resolveOnboardingStatus($row);
    }

    private function syncOnboardingStatus(int $linkId, string $currentStatus, string $partnerCui, array $documentsProgress): void
    {
        if ($linkId <= 0 || !Database::tableExists('enrollment_links') || !Database::columnExists('enrollment_links', 'onboarding_status')) {
            return;
        }
        if (in_array($currentStatus, ['submitted', 'approved'], true)) {
            return;
        }

        $target = 'draft';
        if ($partnerCui !== '' && (int) ($documentsProgress['required_total'] ?? 0) > 0) {
            $target = 'waiting_signature';
        }
        if ($target === $currentStatus) {
            return;
        }

        Database::execute(
            'UPDATE enrollment_links SET onboarding_status = :onboarding_status, updated_at = :updated_at WHERE id = :id',
            [
                'onboarding_status' => $target,
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $linkId,
            ]
        );
    }

    private function ensureEditableOnboarding(array $link): void
    {
        $status = $this->resolveOnboardingStatus($link);
        if (!in_array($status, self::EDITABLE_ONBOARDING_STATUSES, true)) {
            Response::abort(403, 'Inrolarea a fost trimisa spre activare sau este deja aprobata.');
        }
    }

    private function hasMandatoryCompanyProfile(string $cui): bool
    {
        $cui = preg_replace('/\D+/', '', $cui);
        if ($cui === '' || !Database::tableExists('companies')) {
            return false;
        }

        $company = Company::findByCui($cui);
        if (!$company) {
            return false;
        }

        $legalRepresentativeName = $this->sanitizeCompanyValue((string) ($company->legal_representative_name !== '' ? $company->legal_representative_name : ($company->representative_name ?? '')));
        $legalRepresentativeRole = $this->sanitizeCompanyValue((string) ($company->legal_representative_role !== '' ? $company->legal_representative_role : ($company->representative_function ?? '')));
        $bankName = $this->sanitizeCompanyValue((string) ($company->bank_name ?? $company->banca ?? ''));
        $iban = $this->sanitizeIban((string) ($company->iban ?? $company->bank_account ?? ''));

        return $legalRepresentativeName !== ''
            && $legalRepresentativeRole !== ''
            && $bankName !== ''
            && $this->isValidIban($iban);
    }

    private function sanitizeCompanyValue(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function sanitizeContactValue(string $value): string
    {
        $value = trim(strip_tags($value));
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function normalizeContactDepartment(string $value): string
    {
        $value = strtolower($this->sanitizeContactValue($value));
        if ($value === '') {
            return '';
        }

        foreach (self::CONTACT_DEPARTMENTS as $department) {
            if ($value === strtolower($department)) {
                return $department;
            }
        }

        return '';
    }

    private function ensureLegalRepresentativeContact(string $partnerCui, string $name, string $email, string $phone): void
    {
        if (!Database::tableExists('partner_contacts')) {
            return;
        }

        $partnerCui = preg_replace('/\D+/', '', $partnerCui);
        $name = $this->sanitizeCompanyValue($name);
        $email = $this->sanitizeContactValue($email);
        $phone = $this->sanitizeContactValue($phone);
        if ($partnerCui === '' || $name === '') {
            return;
        }

        $existing = Database::fetchOne(
            'SELECT id, email, phone
             FROM partner_contacts
             WHERE partner_cui = :partner_cui
               AND LOWER(TRIM(role)) = LOWER(TRIM(:role))
             ORDER BY id DESC
             LIMIT 1',
            [
                'partner_cui' => $partnerCui,
                'role' => self::CONTACT_DEPARTMENTS[0],
            ]
        );
        if (!$existing) {
            $existing = Database::fetchOne(
                'SELECT id, email, phone
                 FROM partner_contacts
                 WHERE partner_cui = :partner_cui
                   AND LOWER(TRIM(name)) = LOWER(TRIM(:name))
                 ORDER BY id DESC
                 LIMIT 1',
                [
                    'partner_cui' => $partnerCui,
                    'name' => $name,
                ]
            );
        }

        if ($existing) {
            $resolvedEmail = $email !== '' ? $email : (string) ($existing['email'] ?? '');
            $resolvedPhone = $phone !== '' ? $phone : (string) ($existing['phone'] ?? '');
            Database::execute(
                'UPDATE partner_contacts
                 SET role = :role,
                     email = :email,
                     phone = :phone
                 WHERE id = :id',
                [
                    'role' => self::CONTACT_DEPARTMENTS[0],
                    'email' => $resolvedEmail !== '' ? $resolvedEmail : null,
                    'phone' => $resolvedPhone !== '' ? $resolvedPhone : null,
                    'id' => (int) ($existing['id'] ?? 0),
                ]
            );

            return;
        }

        $hasPrimary = Database::columnExists('partner_contacts', 'is_primary');
        if ($hasPrimary) {
            Database::execute(
                'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, is_primary, created_at)
                 VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :is_primary, :created_at)',
                [
                    'partner' => $partnerCui,
                    'supplier' => null,
                    'client' => null,
                    'name' => $name,
                    'email' => $email !== '' ? $email : null,
                    'phone' => $phone !== '' ? $phone : null,
                    'role' => self::CONTACT_DEPARTMENTS[0],
                    'is_primary' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );

            return;
        }

        Database::execute(
            'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, created_at)
             VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :created_at)',
            [
                'partner' => $partnerCui,
                'supplier' => null,
                'client' => null,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'role' => self::CONTACT_DEPARTMENTS[0],
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    private function sanitizeIban(string $value): string
    {
        $value = strtoupper($value);
        $value = preg_replace('/\s+/', '', $value);
        $value = preg_replace('/[^A-Z0-9]/', '', (string) $value);

        return (string) $value;
    }

    private function isValidIban(string $iban): bool
    {
        $length = strlen($iban);

        return $length >= 15 && $length <= 34;
    }

    private function mergePrefill(array $prefill, array $incoming): array
    {
        $map = [
            'cui' => 'cui',
            'denumire' => 'denumire',
            'nr_reg_comertului' => 'nr_reg_comertului',
            'adresa' => 'adresa',
            'localitate' => 'localitate',
            'judet' => 'judet',
            'telefon' => 'telefon',
            'email' => 'email',
            'legal_representative_name' => 'legal_representative_name',
            'legal_representative_role' => 'legal_representative_role',
            'bank_name' => 'bank_name',
            'iban' => 'iban',
            'representative_name' => 'legal_representative_name',
            'representative_function' => 'legal_representative_role',
            'banca' => 'bank_name',
            'bank_account' => 'iban',
        ];
        foreach ($map as $key => $target) {
            $value = $target === 'iban'
                ? $this->sanitizeIban((string) ($incoming[$key] ?? ''))
                : $this->sanitizeCompanyValue((string) ($incoming[$key] ?? ''));
            if ($value !== '') {
                $prefill[$target] = $value;
            }
        }

        return $prefill;
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
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $contentType = $ext === 'pdf' ? 'application/pdf' : 'application/octet-stream';
        header('Content-Type: ' . $contentType);
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
}
