<?php

use App\Domain\Dashboard\Http\Controllers\DashboardController;
use App\Domain\Settings\Http\Controllers\InstallController;
use App\Domain\Users\Http\Controllers\AuthController;
use App\Domain\Users\Http\Controllers\SetupController;

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

$router->get('/admin/dashboard', [DashboardController::class, 'index']);

require __DIR__ . '/erp.php';
