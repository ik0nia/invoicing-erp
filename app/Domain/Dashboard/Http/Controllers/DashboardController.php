<?php

namespace App\Domain\Dashboard\Http\Controllers;

use App\Domain\Users\Models\UserSupplierAccess;
use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();

        $user = Auth::user();
        if (!$user) {
            Response::abort(403, 'Acces interzis.');
        }

        $isPlatform = $user->isPlatformUser();
        $isSupplierUser = $user->isSupplierUser();
        if (!$isPlatform && !$isSupplierUser) {
            Response::abort(403, 'Acces interzis.');
        }

        $latestInvoices = [];
        $pendingPackages = [];
        $supplierFilter = '';
        $supplierPlaceholders = '';
        $params = [];

        if ($isSupplierUser) {
            UserSupplierAccess::ensureTable();
            $suppliers = UserSupplierAccess::suppliersForUser($user->id);
            if (empty($suppliers)) {
                Response::view('admin/dashboard/index', [
                    'user' => $user,
                    'latestInvoices' => $latestInvoices,
                    'pendingPackages' => $pendingPackages,
                ]);
                return;
            }
            $placeholders = [];
            foreach (array_values($suppliers) as $index => $supplier) {
                $key = 's' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $supplier;
            }
            $supplierPlaceholders = implode(',', $placeholders);
            $supplierFilter = ' AND supplier_cui IN (' . implode(',', $placeholders) . ')';
        }

        if (Database::tableExists('invoices_in')) {
            try {
                $latestInvoices = Database::fetchAll(
                    'SELECT id, invoice_number, supplier_name, issue_date, total_with_vat
                     FROM invoices_in
                     WHERE 1=1' . $supplierFilter . '
                     ORDER BY created_at DESC, id DESC
                     LIMIT 10',
                    $params
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
                       AND (i.fgo_number IS NULL OR i.fgo_number = "")' . ($supplierPlaceholders !== '' ? ' AND i.supplier_cui IN (' . $supplierPlaceholders . ')' : '') . '
                     ORDER BY ' . $orderBy . ' DESC, p.package_no ASC, p.id ASC
                     LIMIT 10',
                    $params
                );
            } catch (\Throwable $exception) {
                $pendingPackages = [];
            }
        }

        Response::view('admin/dashboard/index', [
            'user' => $user,
            'latestInvoices' => $latestInvoices,
            'pendingPackages' => $pendingPackages,
        ]);
    }
}
