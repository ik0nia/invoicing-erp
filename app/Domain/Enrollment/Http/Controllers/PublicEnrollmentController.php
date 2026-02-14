<?php

namespace App\Domain\Enrollment\Http\Controllers;

use App\Domain\Companies\Services\CompanyLookupService;
use App\Domain\Contracts\Services\ContractOnboardingService;
use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Support\Audit;
use App\Support\CompanyName;
use App\Support\Database;
use App\Support\RateLimiter;
use App\Support\Response;
use App\Support\Session;
use App\Support\TokenService;

class PublicEnrollmentController
{
    public function show(): void
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
            Response::view('public/enroll', [
                'error' => 'Link invalid sau expirat.',
            ], 'layouts/guest');
        }

        $prefill = [];
        if (!empty($link['prefill_json'])) {
            $decoded = json_decode((string) $link['prefill_json'], true);
            if (is_array($decoded)) {
                $prefill = $decoded;
            }
        }

        Response::view('public/enroll', [
            'link' => $link,
            'prefill' => $prefill,
        ], 'layouts/guest');
    }

    public function submit(): void
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
            Response::view('public/enroll', [
                'error' => 'Link invalid sau expirat.',
            ], 'layouts/guest');
        }

        $cui = preg_replace('/\D+/', '', (string) ($_POST['cui'] ?? ''));
        $denumire = CompanyName::normalize((string) ($_POST['denumire'] ?? ''));
        if ($cui === '' || $denumire === '') {
            Session::flash('error', 'Completeaza CUI si denumire.');
            Response::redirect('/enroll/' . $token);
        }

        Partner::upsert($cui, $denumire);

        $isSupplier = $link['type'] === 'supplier';
        $isClient = $link['type'] === 'client';
        Partner::updateFlags($cui, $isSupplier, $isClient);
        Audit::record('partner.upsert', 'partner', null, [
            'supplier_cui' => $isSupplier ? $cui : null,
            'selected_client_cui' => $isClient ? $cui : null,
        ]);
        Audit::record('partner.role_added', 'partner', null, [
            'supplier_cui' => $isSupplier ? $cui : null,
            'selected_client_cui' => $isClient ? $cui : null,
        ]);

        if ($link['type'] === 'client' && !empty($link['supplier_cui'])) {
            $supplierCui = (string) $link['supplier_cui'];
            $this->ensurePartnerRelation($supplierCui, $cui);
            $this->ensureCommission($supplierCui, $cui, $link['commission_percent'] ?? null);
        }

        $supplierCui = $link['type'] === 'client' ? (string) ($link['supplier_cui'] ?? '') : null;
        $clientCui = $link['type'] === 'client' ? $cui : null;
        $partnerCui = $link['type'] === 'supplier' ? $cui : $cui;
        $contractService = new ContractOnboardingService();
        $contractResult = $contractService->ensureDraftContractForEnrollment(
            (string) $link['type'],
            $partnerCui,
            $supplierCui,
            $clientCui
        );

        Database::execute(
            'UPDATE enrollment_links SET uses = uses + 1, confirmed_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => (int) $link['id']]
        );

        Audit::record('enrollment.link_use', 'enrollment_link', (int) $link['id'], [
            'rows_count' => 1,
        ]);

        $statusMessage = 'Inrolarea a fost salvata.';
        if (!empty($contractResult['has_template'])) {
            $statusMessage .= ' Contract creat in Ciorna; adminul va genera si trimite pentru semnare.';
        } else {
            $statusMessage .= ' Nu exista model activ pentru contract; adminul trebuie sa configureze.';
        }
        Session::flash('status', $statusMessage);
        Response::redirect('/enroll/' . $token);
    }

    public function lookup(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        if ($token === '') {
            $this->json(['success' => false, 'message' => 'Token invalid.'], 404);
        }

        $hash = TokenService::hashToken($token);
        if (!$this->throttle($hash)) {
            $this->json(['success' => false, 'message' => 'Prea multe cereri.'], 429);
        }

        $link = $this->findActiveLink($hash);
        if (!$link) {
            $this->json(['success' => false, 'message' => 'Link invalid sau expirat.'], 404);
        }

        $cui = preg_replace('/\D+/', '', (string) ($_GET['cui'] ?? ''));
        $service = new CompanyLookupService();
        $response = $service->lookupByCui($cui);
        if ($response['error'] !== null) {
            $this->json(['success' => false, 'message' => $response['error']]);
        }

        $this->json(['success' => true, 'data' => $response['data']]);
    }

    private function findActiveLink(string $hash): ?array
    {
        $row = Database::fetchOne(
            'SELECT * FROM enrollment_links WHERE token_hash = :hash LIMIT 1',
            ['hash' => $hash]
        );
        if (!$row) {
            return null;
        }

        if (($row['status'] ?? '') !== 'active') {
            return null;
        }
        $maxUses = (int) ($row['max_uses'] ?? 1);
        $uses = (int) ($row['uses'] ?? 0);
        if ($maxUses > 0 && $uses >= $maxUses) {
            return null;
        }
        $expires = $row['expires_at'] ?? null;
        if ($expires && strtotime((string) $expires) < time()) {
            return null;
        }

        return $row;
    }

    private function ensurePartnerRelation(string $supplierCui, string $clientCui): void
    {
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

    private function throttle(string $hash): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'enroll|' . $hash . '|' . $ip;
        return RateLimiter::hit($key, 60, 600);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
