<?php

namespace App\Domain\Dashboard\Http\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class DashboardController
{
    public function index(): void
    {
        Auth::requireAdmin();

        $latestInvoices = [];
        $pendingPackages = [];

        if (Database::tableExists('invoices_in')) {
            try {
                $latestInvoices = Database::fetchAll(
                    'SELECT id, invoice_number, supplier_name, issue_date, total_with_vat
                     FROM invoices_in
                     ORDER BY created_at DESC, id DESC
                     LIMIT 10'
                );
            } catch (\Throwable $exception) {
                $latestInvoices = [];
            }
        }

        $hasPackages = Database::tableExists('packages') && Database::tableExists('invoices_in');
        $hasConfirmed = $hasPackages && Database::columnExists('invoices_in', 'packages_confirmed');
        $hasFgoNumber = $hasPackages && Database::columnExists('invoices_in', 'fgo_number');
        $hasConfirmedAt = $hasPackages && Database::columnExists('invoices_in', 'packages_confirmed_at');

        if ($hasConfirmed && $hasFgoNumber) {
            $orderBy = $hasConfirmedAt ? 'i.packages_confirmed_at' : 'i.issue_date';

            try {
                $pendingPackages = Database::fetchAll(
                    'SELECT p.id, p.package_no, p.label, p.invoice_in_id,
                            i.invoice_number, i.supplier_name, i.packages_confirmed_at
                     FROM packages p
                     JOIN invoices_in i ON i.id = p.invoice_in_id
                     WHERE i.packages_confirmed = 1
                       AND (i.fgo_number IS NULL OR i.fgo_number = "")
                     ORDER BY ' . $orderBy . ' DESC, p.package_no ASC, p.id ASC
                     LIMIT 10'
                );
            } catch (\Throwable $exception) {
                $pendingPackages = [];
            }
        }

        Response::view('admin/dashboard/index', [
            'user' => Auth::user(),
            'latestInvoices' => $latestInvoices,
            'pendingPackages' => $pendingPackages,
        ]);
    }
}
