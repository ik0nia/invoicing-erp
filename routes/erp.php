<?php

use App\Domain\Settings\Http\Controllers\SettingsController;

$router->get('/admin/setari/branding', [SettingsController::class, 'editBranding']);
$router->post('/admin/setari/branding', [SettingsController::class, 'updateBranding']);
