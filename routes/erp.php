<?php

use App\Domain\Settings\Http\Controllers\SettingsController;
use App\Domain\Invoices\Http\Controllers\InvoiceController;
use App\Domain\Companies\Http\Controllers\CompanyController;
use App\Domain\Partners\Http\Controllers\AssociationsController;
use App\Domain\Payments\Http\Controllers\PaymentsController;

$router->get('/admin/setari', [SettingsController::class, 'edit']);
$router->post('/admin/setari', [SettingsController::class, 'update']);

$router->get('/admin/facturi', [InvoiceController::class, 'index']);
$router->get('/admin/pachete-confirmate', [InvoiceController::class, 'confirmedPackages']);
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
$router->post('/admin/facturi/genereaza', [InvoiceController::class, 'generateInvoice']);
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
$router->get('/admin/plati', [PaymentsController::class, 'indexOut']);
$router->get('/admin/plati/adauga', [PaymentsController::class, 'createOut']);
$router->post('/admin/plati/adauga', [PaymentsController::class, 'storeOut']);
$router->get('/admin/plati/istoric', [PaymentsController::class, 'historyOut']);
$router->get('/admin/plati/export', [PaymentsController::class, 'exportOut']);
$router->post('/admin/plati/email-azi', [PaymentsController::class, 'sendDailyEmails']);

$router->get('/admin/companii', [CompanyController::class, 'index']);
$router->get('/admin/companii/edit', [CompanyController::class, 'edit']);
$router->post('/admin/companii/save', [CompanyController::class, 'save']);

$router->get('/admin/asocieri', [AssociationsController::class, 'index']);
$router->post('/admin/asocieri/salveaza', [AssociationsController::class, 'save']);
$router->post('/admin/asocieri/sterge', [AssociationsController::class, 'delete']);
