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

        $partners = Partner::all();
        $associations = Commission::allWithPartners();

        Response::view('admin/associations/index', [
            'partners' => $partners,
            'associations' => $associations,
            'hasPartners' => Database::tableExists('partners'),
        ]);
    }

    public function save(): void
    {
        Auth::requireAdmin();

        $supplier = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $client = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $commission = str_replace(',', '.', (string) ($_POST['commission'] ?? '0'));

        if ($supplier === '' || $client === '') {
            Session::flash('error', 'Selecteaza furnizorul si clientul.');
            Response::redirect('/admin/asocieri');
        }

        if ($supplier === $client) {
            Session::flash('error', 'Furnizorul si clientul trebuie sa fie diferiti.');
            Response::redirect('/admin/asocieri');
        }

        if (!is_numeric($commission)) {
            Session::flash('error', 'Comision invalid.');
            Response::redirect('/admin/asocieri');
        }

        Commission::createOrUpdate($supplier, $client, (float) $commission);

        Session::flash('status', 'Asocierea a fost salvata.');
        Response::redirect('/admin/asocieri');
    }

    public function delete(): void
    {
        Auth::requireAdmin();

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id > 0) {
            Commission::deleteById($id);
            Session::flash('status', 'Asocierea a fost stearsa.');
        }

        Response::redirect('/admin/asocieri');
    }
}
