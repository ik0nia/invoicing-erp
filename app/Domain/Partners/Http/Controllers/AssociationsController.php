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

        Response::view('admin/associations/index', [
            'partners' => $partners,
            'associations' => $associations,
            'hasPartners' => Database::tableExists('partners'),
            'canDeleteAssociations' => $canDeleteAssociations,
            'relationContacts' => $relationContacts,
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
}
