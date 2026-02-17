<?php

use App\Domain\Dashboard\Http\Controllers\DashboardController;
use App\Domain\Settings\Http\Controllers\InstallController;
use App\Domain\Users\Http\Controllers\AuthController;
use App\Domain\Users\Http\Controllers\SetupController;
use App\Domain\Enrollment\Http\Controllers\PublicEnrollmentController;
use App\Domain\Public\Http\Controllers\PublicPartnerController;

$router->get('/', function (): void {
    if (!file_exists(BASE_PATH . '/.env')) {
        App\Support\Response::redirect('/install');
    }

    if (!App\Domain\Users\Models\User::exists()) {
        App\Support\Response::redirect('/setup');
    }

    App\Support\Response::redirect('/admin/dashboard');
});

$router->get('/install', [InstallController::class, 'show']);
$router->post('/install', [InstallController::class, 'store']);

$router->get('/setup', [SetupController::class, 'show']);
$router->post('/setup', [SetupController::class, 'create']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/enroll/{token}', [PublicEnrollmentController::class, 'show']);
$router->post('/enroll/{token}', [PublicEnrollmentController::class, 'submit']);
$router->get('/enroll/{token}/lookup', [PublicEnrollmentController::class, 'lookup']);
$router->get('/p/{token}', [PublicPartnerController::class, 'index']);
$router->post('/p/{token}/save-company', [PublicPartnerController::class, 'saveCompany']);
$router->post('/p/{token}/save-contact', [PublicPartnerController::class, 'saveContact']);
$router->post('/p/{token}/delete-contact', [PublicPartnerController::class, 'deleteContact']);
$router->post('/p/{token}/set-step', [PublicPartnerController::class, 'setStep']);
$router->get('/p/{token}/preview', [PublicPartnerController::class, 'preview']);
$router->get('/p/{token}/preview-draft', [PublicPartnerController::class, 'previewDraft']);
$router->get('/p/{token}/download', [PublicPartnerController::class, 'download']);
$router->get('/p/{token}/download-draft', [PublicPartnerController::class, 'downloadDraft']);
$router->get('/p/{token}/resource', [PublicPartnerController::class, 'downloadOnboardingResource']);
$router->get('/p/{token}/download-dosar', [PublicPartnerController::class, 'downloadDossier']);
$router->post('/p/{token}/upload-signed', [PublicPartnerController::class, 'uploadSigned']);
$router->post('/p/{token}/submit-activation', [PublicPartnerController::class, 'submitForActivation']);

$router->get('/admin/dashboard', [DashboardController::class, 'index']);

require __DIR__ . '/erp.php';
