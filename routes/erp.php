<?php

use App\Domain\Settings\Http\Controllers\LegacyImportController;
use App\Domain\Settings\Http\Controllers\SettingsController;
use App\Domain\Invoices\Http\Controllers\InvoiceController;
use App\Domain\Companies\Http\Controllers\CompanyController;
use App\Domain\Partners\Http\Controllers\AssociationsController;

$router->get('/admin/setari/branding', [SettingsController::class, 'editBranding']);
$router->post('/admin/setari/branding', [SettingsController::class, 'updateBranding']);

$router->get('/admin/setari/import-date', [LegacyImportController::class, 'show']);
$router->post('/admin/setari/import-date', [LegacyImportController::class, 'import']);

$router->get('/admin/facturi', [InvoiceController::class, 'index']);
$router->get('/admin/facturi/import', [InvoiceController::class, 'showImport']);
$router->post('/admin/facturi/import', [InvoiceController::class, 'import']);
$router->post('/admin/facturi/pachete', [InvoiceController::class, 'packages']);
$router->post('/admin/facturi/muta-linie', [InvoiceController::class, 'moveLine']);

$router->get('/admin/companii', [CompanyController::class, 'index']);
$router->get('/admin/companii/edit', [CompanyController::class, 'edit']);
$router->post('/admin/companii/save', [CompanyController::class, 'save']);

$router->get('/admin/asocieri', [AssociationsController::class, 'index']);
$router->post('/admin/asocieri/salveaza', [AssociationsController::class, 'save']);
$router->post('/admin/asocieri/sterge', [AssociationsController::class, 'delete']);
