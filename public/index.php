<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
define('BASE_URL', $scriptDir === '/' ? '' : $scriptDir);

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register();
App\Support\Env::load(BASE_PATH . '/.env');

date_default_timezone_set(App\Support\Env::get('APP_TIMEZONE', 'Europe/Bucharest'));

session_start();

$router = new App\Support\Router();

require BASE_PATH . '/routes/web.php';

$router->dispatch();
