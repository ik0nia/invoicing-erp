<?php

namespace App\Domain\Partners\Http\Controllers;

use App\Domain\Partners\Models\Commission;
use App\Domain\Partners\Models\Partner;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class AssociationsController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $user = Auth::user();
        $canDeleteAssociations = $user ? !$user->isOperator() : false;

        $partners = Partner::all();
        $associations = Commission::allWithPartners();
        $relationContacts = [];
        if (Database::tableExists('partner_contacts')) {
            $relationContacts = Database::fetchAll(
                'SELECT * FROM partner_contacts WHERE supplier_cui IS NOT NULL AND client_cui IS NOT NULL ORDER BY created_at DESC'
            );
        }
        $pendingAssociationRequests = $this->fetchPendingAssociationRequests();

        Response::view('admin/associations/index', [
            'partners' => $partners,
            'associations' => $associations,
            'hasPartners' => Database::tableExists('partners'),
            'canDeleteAssociations' => $canDeleteAssociations,
            'relationContacts' => $relationContacts,
            'pendingAssociationRequests' => $pendingAssociationRequests,
        ]);
    }

    public function saveDefaultCommission(): void
    {
        Auth::requireAdmin();

        $supplier = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $commissionInput = str_replace(',', '.', (string) ($_POST['default_commission'] ?? ''));

        if ($supplier === '') {
            Session::flash('error', 'Selecteaza furnizorul.');
            Response::redirect('/admin/asocieri');
        }

        if ($commissionInput === '' || !is_numeric($commissionInput)) {
            Session::flash('error', 'Comision default invalid.');
            Response::redirect('/admin/asocieri');
        }

        Partner::updateDefaultCommission($supplier, (float) $commissionInput);

        Session::flash('status', 'Comisionul default a fost salvat.');
        Response::redirect('/admin/asocieri');
    }

    public function save(): void
    {
        Auth::requireAdmin();

        $supplier = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $client = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $commissionInput = str_replace(',', '.', (string) ($_POST['commission'] ?? ''));

        if ($supplier === '' || $client === '') {
            Session::flash('error', 'Selecteaza furnizorul si clientul.');
            Response::redirect('/admin/asocieri');
        }

        if ($supplier === $client) {
            Session::flash('error', 'Furnizorul si clientul trebuie sa fie diferiti.');
            Response::redirect('/admin/asocieri');
        }

        if ($commissionInput === '') {
            $commission = Partner::defaultCommissionFor($supplier);
        } elseif (!is_numeric($commissionInput)) {
            Session::flash('error', 'Comision invalid.');
            Response::redirect('/admin/asocieri');
        } else {
            $commission = (float) $commissionInput;
        }

        Commission::createOrUpdate($supplier, $client, $commission);

        Session::flash('status', 'Asocierea a fost salvata.');
        Response::redirect('/admin/asocieri');
    }

    public function delete(): void
    {
        Auth::requireAdminWithoutOperator();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            Commission::deleteById($id);
            Session::flash('status', 'Asocierea a fost stearsa.');
        }

        Response::redirect('/admin/asocieri');
    }

    public function approveRequest(): void
    {
        Auth::requireAdmin();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/asocieri');
        }

        $request = $this->findAssociationRequestById($id);
        if (!$request || (string) ($request['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Solicitarea nu mai este disponibila.');
            Response::redirect('/admin/asocieri');
        }

        $supplierCui = preg_replace('/\D+/', '', (string) ($request['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($request['client_cui'] ?? ''));
        if ($supplierCui === '' || $clientCui === '' || $supplierCui === $clientCui) {
            Session::flash('error', 'Solicitare invalida.');
            Response::redirect('/admin/asocieri');
        }

        $commission = $request['commission_percent'] !== null && $request['commission_percent'] !== ''
            ? (float) $request['commission_percent']
            : Partner::defaultCommissionFor($supplierCui);

        Commission::createOrUpdate($supplierCui, $clientCui, $commission);
        Partner::updateFlags($supplierCui, true, false);
        Partner::updateFlags($clientCui, false, true);

        $user = Auth::user();
        Database::execute(
            'UPDATE association_requests
             SET status = :status,
                 decided_by_user_id = :decided_by_user_id,
                 decided_at = :decided_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'status' => 'approved',
                'decided_by_user_id' => $user ? (int) $user->id : null,
                'decided_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Session::flash('status', 'Solicitarea de asociere a fost aprobata.');
        Response::redirect('/admin/asocieri');
    }

    public function rejectRequest(): void
    {
        Auth::requireAdmin();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id <= 0) {
            Response::redirect('/admin/asocieri');
        }

        $request = $this->findAssociationRequestById($id);
        if (!$request || (string) ($request['status'] ?? '') !== 'pending') {
            Session::flash('error', 'Solicitarea nu mai este disponibila.');
            Response::redirect('/admin/asocieri');
        }

        $user = Auth::user();
        Database::execute(
            'UPDATE association_requests
             SET status = :status,
                 decided_by_user_id = :decided_by_user_id,
                 decided_at = :decided_at,
                 updated_at = :updated_at
             WHERE id = :id',
            [
                'status' => 'rejected',
                'decided_by_user_id' => $user ? (int) $user->id : null,
                'decided_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'id' => $id,
            ]
        );

        Session::flash('status', 'Solicitarea de asociere a fost refuzata.');
        Response::redirect('/admin/asocieri');
    }

    private function fetchPendingAssociationRequests(): array
    {
        if (!Database::tableExists('association_requests')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT ar.*,
                    sp.denumire AS supplier_name,
                    cp.denumire AS client_name
             FROM association_requests ar
             LEFT JOIN partners sp ON sp.cui = ar.supplier_cui
             LEFT JOIN partners cp ON cp.cui = ar.client_cui
             WHERE ar.status = :status
             ORDER BY ar.requested_at DESC, ar.id DESC',
            ['status' => 'pending']
        );
    }

    private function findAssociationRequestById(int $id): ?array
    {
        if ($id <= 0 || !Database::tableExists('association_requests')) {
            return null;
        }

        return Database::fetchOne(
            'SELECT * FROM association_requests WHERE id = :id LIMIT 1',
            ['id' => $id]
        );
    }
}
