<?php

use App\Domain\Settings\Http\Controllers\LegacyImportController;
use App\Domain\Settings\Http\Controllers\SettingsController;
use App\Domain\Invoices\Http\Controllers\InvoiceController;

$router->get('/admin/setari/branding', [SettingsController::class, 'editBranding']);
$router->post('/admin/setari/branding', [SettingsController::class, 'updateBranding']);

$router->get('/admin/setari/import-date', [LegacyImportController::class, 'show']);
$router->post('/admin/setari/import-date', [LegacyImportController::class, 'import']);

$router->get('/admin/facturi', [InvoiceController::class, 'index']);
$router->get('/admin/facturi/import', [InvoiceController::class, 'showImport']);
$router->post('/admin/facturi/import', [InvoiceController::class, 'import']);
$router->post('/admin/facturi/pachete', [InvoiceController::class, 'packages']);
$router->post('/admin/facturi/muta-linie', [InvoiceController::class, 'moveLine']);
