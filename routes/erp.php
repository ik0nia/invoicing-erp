<?php

use App\Domain\Settings\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/admin/setari/branding', [SettingsController::class, 'editBranding'])
        ->name('admin.settings.branding');

    Route::post('/admin/setari/branding', [SettingsController::class, 'updateBranding'])
        ->name('admin.settings.branding.update');
});
