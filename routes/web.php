<?php

use App\Domain\Dashboard\Http\Controllers\DashboardController;
use App\Domain\Settings\Http\Controllers\InstallController;
use App\Domain\Users\Http\Controllers\AuthController;
use App\Domain\Users\Http\Controllers\SetupController;
use App\Domain\Enrollment\Http\Controllers\PublicEnrollmentController;
use App\Domain\Portal\Http\Controllers\PublicPortalController;

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
$router->get('/portal/{token}', [PublicPortalController::class, 'index']);
$router->get('/portal/{token}/download', [PublicPortalController::class, 'download']);
$router->post('/portal/{token}/upload', [PublicPortalController::class, 'upload']);

$router->get('/admin/dashboard', [DashboardController::class, 'index']);

require __DIR__ . '/erp.php';
