<?php

use App\Domain\Settings\Http\Controllers\SettingsController;
use App\Domain\Invoices\Http\Controllers\InvoiceController;
use App\Domain\Companies\Http\Controllers\CompanyController;
use App\Domain\Partners\Http\Controllers\AssociationsController;

$router->get('/admin/setari', [SettingsController::class, 'edit']);
$router->post('/admin/setari', [SettingsController::class, 'update']);

$router->get('/admin/facturi', [InvoiceController::class, 'index']);
$router->get('/admin/pachete-confirmate', [InvoiceController::class, 'confirmedPackages']);
$router->get('/admin/facturi/adauga', [InvoiceController::class, 'showManual']);
$router->post('/admin/facturi/adauga', [InvoiceController::class, 'storeManual']);
$router->get('/admin/facturi/import', [InvoiceController::class, 'showImport']);
$router->post('/admin/facturi/import', [InvoiceController::class, 'import']);
$router->post('/admin/facturi/pachete', [InvoiceController::class, 'packages']);
$router->post('/admin/facturi/muta-linie', [InvoiceController::class, 'moveLine']);
$router->post('/admin/facturi/genereaza', [InvoiceController::class, 'generateInvoice']);
$router->post('/admin/facturi/saga/pachet', [InvoiceController::class, 'downloadPackageSaga']);
$router->post('/admin/facturi/saga/factura', [InvoiceController::class, 'downloadInvoiceSaga']);
$router->post('/admin/pachete-confirmate/descarca', [InvoiceController::class, 'downloadSelectedSaga']);
$router->post('/admin/facturi/print', [InvoiceController::class, 'printInvoice']);
$router->post('/admin/facturi/storno', [InvoiceController::class, 'stornoInvoice']);
$router->post('/admin/facturi/sterge', [InvoiceController::class, 'delete']);

$router->get('/admin/companii', [CompanyController::class, 'index']);
$router->get('/admin/companii/edit', [CompanyController::class, 'edit']);
$router->post('/admin/companii/save', [CompanyController::class, 'save']);

$router->get('/admin/asocieri', [AssociationsController::class, 'index']);
$router->post('/admin/asocieri/salveaza', [AssociationsController::class, 'save']);
$router->post('/admin/asocieri/sterge', [AssociationsController::class, 'delete']);
