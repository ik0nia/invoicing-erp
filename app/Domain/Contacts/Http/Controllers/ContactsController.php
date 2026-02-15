<?php

namespace App\Domain\Contacts\Http\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class ContactsController
{
    public function create(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || (!$user->isPlatformUser() && !$user->hasRole('operator') && !$user->isSupplierUser())) {
            Response::abort(403, 'Acces interzis.');
        }

        $partnerCui = preg_replace('/\D+/', '', (string) ($_POST['partner_cui'] ?? ''));
        $supplierCui = preg_replace('/\D+/', '', (string) ($_POST['supplier_cui'] ?? ''));
        $clientCui = preg_replace('/\D+/', '', (string) ($_POST['client_cui'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $role = trim((string) ($_POST['role'] ?? ''));

        if ($name === '') {
            Session::flash('error', 'Completeaza numele contactului.');
            $this->redirectBack();
        }

        if ($partnerCui === '' && ($supplierCui === '' || $clientCui === '')) {
            Session::flash('error', 'Completeaza partenerul sau relatia.');
            $this->redirectBack();
        }

        Database::execute(
            'INSERT INTO partner_contacts (partner_cui, supplier_cui, client_cui, name, email, phone, role, created_at)
             VALUES (:partner, :supplier, :client, :name, :email, :phone, :role, :created_at)',
            [
                'partner' => $partnerCui !== '' ? $partnerCui : null,
                'supplier' => $supplierCui !== '' ? $supplierCui : null,
                'client' => $clientCui !== '' ? $clientCui : null,
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'role' => $role !== '' ? $role : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        Session::flash('status', 'Contact adaugat.');
        $this->redirectBack();
    }

    public function delete(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (!$user || (!$user->isPlatformUser() && !$user->hasRole('operator') && !$user->isSupplierUser())) {
            Response::abort(403, 'Acces interzis.');
        }

        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
        if ($id) {
            Database::execute('DELETE FROM partner_contacts WHERE id = :id', ['id' => $id]);
        }

        Session::flash('status', 'Contact sters.');
        $this->redirectBack();
    }

    private function redirectBack(): void
    {
        $back = $_SERVER['HTTP_REFERER'] ?? '/admin/companii';
        Response::redirect($back);
    }
}
