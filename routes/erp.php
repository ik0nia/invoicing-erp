<?php

use App\Domain\Settings\Http\Controllers\SettingsController;
use App\Domain\Invoices\Http\Controllers\InvoiceController;
use App\Domain\Companies\Http\Controllers\CompanyController;
use App\Domain\Partners\Http\Controllers\AssociationsController;
use App\Domain\Payments\Http\Controllers\PaymentsController;
use App\Domain\Reports\Http\Controllers\ReportsController;
use App\Domain\Users\Http\Controllers\UsersController;
use App\Domain\Stock\Http\Controllers\StockImportController;

$router->get('/admin/setari', [SettingsController::class, 'edit']);
$router->post('/admin/setari', [SettingsController::class, 'update']);
$router->post('/admin/setari/demo-generate', [SettingsController::class, 'generateDemo']);
$router->post('/admin/setari/demo-reset', [SettingsController::class, 'resetDemo']);
$router->get('/admin/manual', [SettingsController::class, 'manual']);
$router->get('/admin/changelog', [SettingsController::class, 'changelog']);

$router->get('/admin/facturi', [InvoiceController::class, 'index']);
$router->get('/admin/facturi/search', [InvoiceController::class, 'search']);
$router->get('/admin/facturi/export', [InvoiceController::class, 'export']);
$router->get('/admin/facturi/fisier', [InvoiceController::class, 'showSupplierFile']);
$router->get('/admin/facturi/print-situatie', [InvoiceController::class, 'printSituation']);
$router->get('/admin/facturi/lookup-suppliers', [InvoiceController::class, 'lookupSuppliers']);
$router->get('/admin/facturi/lookup-clients', [InvoiceController::class, 'lookupClients']);
$router->get('/admin/pachete-confirmate', [InvoiceController::class, 'confirmedPackages']);
$router->post('/admin/pachete-confirmate/import-saga', [InvoiceController::class, 'importSagaCsv']);
$router->post('/api/stock/import', [StockImportController::class, 'import']);
$router->get('/admin/facturi/adauga', [InvoiceController::class, 'showManual']);
$router->post('/admin/facturi/adauga', [InvoiceController::class, 'storeManual']);
$router->post('/admin/facturi/calc-totals', [InvoiceController::class, 'calcManualTotals']);
$router->get('/admin/facturi/anexa', [InvoiceController::class, 'showAviz']);
$router->get('/admin/facturi/aviz', function (): void {
    $invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;
    if ($invoiceId) {
        App\Support\Response::redirect('/admin/facturi/anexa?invoice_id=' . $invoiceId);
    }
    App\Support\Response::redirect('/admin/facturi/anexa');
});
$router->get('/admin/facturi/nota-comanda', [InvoiceController::class, 'showOrderNote']);
$router->get('/admin/facturi/import', [InvoiceController::class, 'showImport']);
$router->post('/admin/facturi/import', [InvoiceController::class, 'import']);
$router->post('/admin/facturi/pachete', [InvoiceController::class, 'packages']);
$router->post('/admin/facturi/muta-linie', [InvoiceController::class, 'moveLine']);
$router->post('/admin/facturi/split-linie', [InvoiceController::class, 'splitLine']);
$router->post('/admin/facturi/genereaza', [InvoiceController::class, 'generateInvoice']);
$router->post('/admin/facturi/incarca-fisier', [InvoiceController::class, 'uploadSupplierFile']);
$router->post('/admin/facturi/deblocheaza-client', [InvoiceController::class, 'unlockClient']);
$router->post('/admin/facturi/redenumeste-pachet', [InvoiceController::class, 'renamePackage']);
$router->post('/admin/facturi/saga/pachet', [InvoiceController::class, 'downloadPackageSaga']);
$router->post('/admin/facturi/saga/factura', [InvoiceController::class, 'downloadInvoiceSaga']);
$router->post('/admin/pachete-confirmate/descarca', [InvoiceController::class, 'downloadSelectedSaga']);
$router->post('/admin/facturi/print', [InvoiceController::class, 'printInvoice']);
$router->post('/admin/facturi/print-storno', [InvoiceController::class, 'printStornoInvoice']);
$router->post('/admin/facturi/storno', [InvoiceController::class, 'stornoInvoice']);
$router->post('/admin/facturi/sterge', [InvoiceController::class, 'delete']);

$router->get('/admin/incasari', [PaymentsController::class, 'indexIn']);
$router->get('/admin/incasari/adauga', [PaymentsController::class, 'createIn']);
$router->post('/admin/incasari/adauga', [PaymentsController::class, 'storeIn']);
$router->get('/admin/incasari/istoric', [PaymentsController::class, 'historyIn']);
$router->post('/admin/incasari/sterge', [PaymentsController::class, 'deleteIn']);
$router->get('/admin/plati', [PaymentsController::class, 'indexOut']);
$router->get('/admin/plati/adauga', [PaymentsController::class, 'createOut']);
$router->post('/admin/plati/adauga', [PaymentsController::class, 'storeOut']);
$router->get('/admin/plati/istoric', [PaymentsController::class, 'historyOut']);
$router->get('/admin/plati/print', [PaymentsController::class, 'printOut']);
$router->get('/admin/plati/export', [PaymentsController::class, 'exportOut']);
$router->post('/admin/plati/ordine-plata', [PaymentsController::class, 'exportPaymentOrder']);
$router->post('/admin/plati/sterge', [PaymentsController::class, 'deleteOut']);
$router->post('/admin/plati/email-azi', [PaymentsController::class, 'sendDailyEmails']);

$router->get('/admin/rapoarte/cashflow', [ReportsController::class, 'cashflow']);
$router->get('/admin/rapoarte/cashflow/export', [ReportsController::class, 'exportCashflow']);
$router->get('/admin/rapoarte/cashflow/pdf', [ReportsController::class, 'cashflowPdf']);

$router->get('/admin/utilizatori', [UsersController::class, 'index']);
$router->get('/admin/utilizatori/adauga', [UsersController::class, 'create']);
$router->post('/admin/utilizatori/adauga', [UsersController::class, 'store']);
$router->get('/admin/utilizatori/edit', [UsersController::class, 'edit']);
$router->post('/admin/utilizatori/update', [UsersController::class, 'update']);
$router->post('/admin/utilizatori/sterge', [UsersController::class, 'delete']);

$router->get('/admin/companii', [CompanyController::class, 'index']);
$router->get('/admin/companii/edit', [CompanyController::class, 'edit']);
$router->post('/admin/companii/save', [CompanyController::class, 'save']);
$router->post('/admin/companii/openapi', [CompanyController::class, 'lookupOpenApi']);

$router->get('/admin/asocieri', [AssociationsController::class, 'index']);
$router->post('/admin/asocieri/salveaza', [AssociationsController::class, 'save']);
$router->post('/admin/asocieri/comision-default', [AssociationsController::class, 'saveDefaultCommission']);
$router->post('/admin/asocieri/sterge', [AssociationsController::class, 'delete']);
