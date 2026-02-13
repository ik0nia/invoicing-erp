<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/Support/Autoloader.php';

App\Support\Autoloader::register();
App\Support\Env::load(BASE_PATH . '/.env');
date_default_timezone_set(App\Support\Env::get('APP_TIMEZONE', 'Europe/Bucharest'));

$action = $argv[1] ?? '';
$rawIds = array_slice($argv, 2);

if (!in_array($action, ['record', 'check'], true) || empty($rawIds)) {
    echo "Usage:\n";
    echo "  php tools/regression_snapshots.php record <package_id> [package_id...]\n";
    echo "  php tools/regression_snapshots.php check  <package_id> [package_id...]\n";
    exit(1);
}

$packageIds = [];
foreach ($rawIds as $rawId) {
    $id = (int) $rawId;
    if ($id > 0) {
        $packageIds[] = $id;
    }
}

if (empty($packageIds)) {
    echo "No valid package IDs provided.\n";
    exit(1);
}

$sagaService = new App\Domain\Invoices\Services\SagaExportService();
$totalsService = new App\Domain\Invoices\Services\PackageTotalsService();

function buildPayload(int $packageId, App\Domain\Invoices\Services\SagaExportService $sagaService, App\Domain\Invoices\Services\PackageTotalsService $totalsService): array
{
    return [
        'package_id' => $packageId,
        'saga_json' => $sagaService->buildPackagePayload($packageId),
        'totals' => $totalsService->calculatePackageTotals($packageId),
    ];
}

function isList(array $array): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($array);
    }

    $i = 0;
    foreach ($array as $key => $_value) {
        if ($key !== $i) {
            return false;
        }
        $i++;
    }

    return true;
}

function normalizeForJson(mixed $data): mixed
{
    if (!is_array($data)) {
        return $data;
    }

    if (isList($data)) {
        $normalized = [];
        foreach ($data as $value) {
            $normalized[] = normalizeForJson($value);
        }
        return $normalized;
    }

    $normalized = [];
    $keys = array_keys($data);
    sort($keys, SORT_STRING);
    foreach ($keys as $key) {
        $normalized[$key] = normalizeForJson($data[$key]);
    }

    return $normalized;
}

function encodeNormalized(mixed $data): string
{
    return json_encode(
        normalizeForJson($data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    ) ?: '';
}

function topLevelDiffKeys(array $expected, array $current): array
{
    $keys = array_unique(array_merge(array_keys($expected), array_keys($current)));
    sort($keys, SORT_STRING);
    $diffs = [];
    foreach ($keys as $key) {
        if (!array_key_exists($key, $expected) || !array_key_exists($key, $current)) {
            $diffs[] = $key;
            continue;
        }
        $left = encodeNormalized($expected[$key]);
        $right = encodeNormalized($current[$key]);
        if ($left !== $right) {
            $diffs[] = $key;
        }
    }
    return $diffs;
}

$snapshotDir = BASE_PATH . '/tools/snapshots';
if (!is_dir($snapshotDir)) {
    @mkdir($snapshotDir, 0775, true);
}

$hasFailures = false;

foreach ($packageIds as $packageId) {
    $payload = buildPayload($packageId, $sagaService, $totalsService);
    $snapshotPath = $snapshotDir . '/package_' . $packageId . '.json';

    if ($action === 'record') {
        $json = encodeNormalized($payload);
        file_put_contents($snapshotPath, $json . PHP_EOL);
        echo "Recorded snapshot: package {$packageId}\n";
        continue;
    }

    if (!file_exists($snapshotPath)) {
        echo "Missing snapshot: package {$packageId}\n";
        $hasFailures = true;
        continue;
    }

    $rawSnapshot = file_get_contents($snapshotPath);
    $snapshotData = json_decode($rawSnapshot ?: '', true);
    if (!is_array($snapshotData)) {
        echo "Invalid snapshot JSON: package {$packageId}\n";
        $hasFailures = true;
        continue;
    }

    $expectedJson = encodeNormalized($snapshotData);
    $currentJson = encodeNormalized($payload);
    if ($expectedJson !== $currentJson) {
        $diffKeys = topLevelDiffKeys($snapshotData, $payload);
        $diffList = empty($diffKeys) ? 'unknown' : implode(', ', $diffKeys);
        echo "Mismatch for package {$packageId}: keys differ: {$diffList}\n";
        $hasFailures = true;
        continue;
    }

    echo "OK: package {$packageId}\n";
}

if ($hasFailures) {
    exit(1);
}
