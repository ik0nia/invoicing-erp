<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register();
App\Support\Env::load(BASE_PATH . '/.env');
date_default_timezone_set(App\Support\Env::get('APP_TIMEZONE', 'Europe/Bucharest'));

$packageId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($packageId <= 0) {
    echo "Usage: php tools/diagnose_package.php <package_id>\n";
    exit(1);
}

$validation = App\Domain\Invoices\Rules\PackageSagaRules::validateForSaga($packageId);
$totalsService = new App\Domain\Invoices\Services\PackageTotalsService();
$totals = $totalsService->calculatePackageTotals($packageId);
$vatValue = $totals['sum_gross'] - $totals['sum_net'];

$output = [
    'package_id' => $packageId,
    'validation' => $validation,
    'totals' => [
        'sum_net' => $totals['sum_net'],
        'sum_gross' => $totals['sum_gross'],
        'vat_value' => $vatValue,
        'vat_percent' => $totals['vat_percent'],
        'line_count' => $totals['line_count'],
    ],
];

echo json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
