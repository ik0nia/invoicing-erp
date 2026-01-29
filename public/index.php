<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

$autoload = __DIR__ . '/../vendor/autoload.php';
$bootstrap = __DIR__ . '/../bootstrap/app.php';

if (!file_exists($autoload) || !file_exists($bootstrap)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Lipseste vendor/autoload.php sau bootstrap/app.php. Instaleaza dependintele Laravel local si incarca proiectul complet.";
    exit;
}

require $autoload;

$app = require_once $bootstrap;

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
