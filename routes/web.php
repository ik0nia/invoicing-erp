<?php

use App\Domain\Users\Http\Controllers\AuthController;
use App\Domain\Users\Http\Controllers\SetupController;

$router->get('/', function (): void {
    if (!App\Domain\Users\Models\User::exists()) {
        App\Support\Response::redirect('/setup');
    }

    App\Support\Response::redirect('/admin/setari/branding');
});

$router->get('/setup', [SetupController::class, 'show']);
$router->post('/setup', [SetupController::class, 'create']);

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

require __DIR__ . '/erp.php';
