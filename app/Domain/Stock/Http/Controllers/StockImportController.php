<?php

namespace App\Domain\Stock\Http\Controllers;

use App\Support\Audit;
use App\Support\Database;
use App\Support\Env;
use App\Support\Response;

class StockImportController
{
    public function status(): void
    {
        $this->json([
            'success' => true,
            'message' => 'Trimite CSV prin POST cu token.',
            'columns' => ['cod', 'denumire', 'stoc'],
            'file_field' => 'stock_csv',
            'auth' => 'X-ERP-TOKEN header sau ?token=',
        ]);
    }

    public function import(): void
    {
        $token = (string) Env::get('STOCK_IMPORT_TOKEN', '');
        $provided = $_SERVER['HTTP_X_ERP_TOKEN'] ?? ($_GET['token'] ?? '');
        if ($token === '' || $provided === '' || !hash_equals($token, (string) $provided)) {
            $this->json(['success' => false, 'message' => 'Token invalid.'], 403);
        }

        $this->ensureStockTables();

        $rows = $this->readRowsFromRequest();
        if (empty($rows)) {
            $this->json(['success' => false, 'message' => 'Fisier CSV gol sau invalid.'], 400);
        }

        $header = array_shift($rows);
        $columns = $this->mapStockColumns($header);
        if ($columns['denumire'] === null || $columns['cod'] === null || $columns['stoc'] === null) {
            $this->json([
                'success' => false,
                'message' => 'CSV trebuie sa contina coloanele: cod, denumire, stoc.',
            ], 422);
        }

        $items = [];
        foreach ($rows as $row) {
            $code = trim((string) ($row[$columns['cod']] ?? ''));
            $name = trim((string) ($row[$columns['denumire']] ?? ''));
            $stock = $this->parseNumber((string) ($row[$columns['stoc']] ?? ''));
            if ($code === '' || $name === '' || $stock === null) {
                continue;
            }
            $key = $this->normalizeNameKey($name);
            if ($key === '') {
                continue;
            }
            $items[$key] = [
                'name' => $name,
                'code' => $code,
                'stock' => $stock,
            ];
        }

        if (empty($items)) {
            $this->json(['success' => false, 'message' => 'Nu exista linii valide in CSV.'], 400);
        }

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $upsert = $pdo->prepare(
            'INSERT INTO saga_products (name_key, name, cod_saga, stock_qty, updated_at)
             VALUES (:key, :name, :code, :stock, :updated_at)
             ON DUPLICATE KEY UPDATE name = VALUES(name), cod_saga = VALUES(cod_saga),
                 stock_qty = VALUES(stock_qty), updated_at = VALUES(updated_at)'
        );
        $lineIdsByKey = [];
        if (Database::tableExists('invoice_in_lines')) {
            $lineRows = Database::fetchAll('SELECT id, product_name FROM invoice_in_lines');
            foreach ($lineRows as $row) {
                $nameKey = $this->normalizeNameKey((string) ($row['product_name'] ?? ''));
                if ($nameKey === '') {
                    continue;
                }
                $lineIdsByKey[$nameKey][] = (int) $row['id'];
            }
        }
        $updateLine = $pdo->prepare(
            'UPDATE invoice_in_lines
             SET cod_saga = :code, stock_saga = :stock
             WHERE id = :id'
        );

        $updatedProducts = 0;
        $updatedLines = 0;

        foreach ($items as $key => $item) {
            $upsert->execute([
                'key' => $key,
                'name' => $item['name'],
                'code' => $item['code'],
                'stock' => $item['stock'],
                'updated_at' => $now,
            ]);
            $updatedProducts++;

            if (!empty($lineIdsByKey[$key])) {
                foreach ($lineIdsByKey[$key] as $lineId) {
                    $updateLine->execute([
                        'code' => $item['code'],
                        'stock' => $item['stock'],
                        'id' => $lineId,
                    ]);
                    $updatedLines += $updateLine->rowCount();
                }
            }
        }

        Audit::record('stock.import', 'stock_import', null, [
            'rows_count' => count($items),
            'updated_count' => $updatedLines,
        ]);
        $this->json([
            'success' => true,
            'products' => $updatedProducts,
            'updated_lines' => $updatedLines,
        ]);
    }

    private function ensureStockTables(): void
    {
        Database::execute(
            'CREATE TABLE IF NOT EXISTS saga_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name_key VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                cod_saga VARCHAR(64) NOT NULL,
                stock_qty DECIMAL(12,3) NOT NULL DEFAULT 0,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        if (Database::tableExists('invoice_in_lines') && !Database::columnExists('invoice_in_lines', 'cod_saga')) {
            Database::execute('ALTER TABLE invoice_in_lines ADD COLUMN cod_saga VARCHAR(64) NULL AFTER product_name');
        }
        if (Database::tableExists('invoice_in_lines') && !Database::columnExists('invoice_in_lines', 'stock_saga')) {
            Database::execute('ALTER TABLE invoice_in_lines ADD COLUMN stock_saga DECIMAL(12,3) NULL AFTER cod_saga');
        }
    }

    private function readRowsFromRequest(): array
    {
        $file = $_FILES['stock_csv'] ?? ($_FILES['file'] ?? null);
        if ($file && isset($file['tmp_name']) && is_readable($file['tmp_name'])) {
            return $this->readCsvRows($file['tmp_name']);
        }

        $raw = (string) file_get_contents('php://input');
        if ($raw !== '') {
            return $this->readCsvRowsFromString($raw);
        }

        return [];
    }

    private function readCsvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $headerLine = fgets($handle);
        if ($headerLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = $this->detectDelimiter($headerLine);
        $rows = [str_getcsv($headerLine, $delimiter)];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }

    private function readCsvRowsFromString(string $raw): array
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $raw);
        rewind($stream);

        $headerLine = fgets($stream);
        if ($headerLine === false) {
            fclose($stream);
            return [];
        }
        $delimiter = $this->detectDelimiter($headerLine);
        $rows = [str_getcsv($headerLine, $delimiter)];
        while (($row = fgetcsv($stream, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($stream);

        return $rows;
    }

    private function detectDelimiter(string $line): string
    {
        $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];
        foreach ($delimiters as $delimiter => $count) {
            $delimiters[$delimiter] = substr_count($line, $delimiter);
        }
        arsort($delimiters);
        $best = array_key_first($delimiters);
        return $best ?? ',';
    }

    private function mapStockColumns(array $header): array
    {
        $columns = [
            'cod' => null,
            'denumire' => null,
            'stoc' => null,
        ];

        foreach ($header as $index => $value) {
            $key = $this->normalizeHeader((string) $value);
            if ($columns['denumire'] === null && str_contains($key, 'denumire')) {
                $columns['denumire'] = $index;
                continue;
            }
            if ($columns['cod'] === null && str_contains($key, 'cod')) {
                $columns['cod'] = $index;
                continue;
            }
            if ($columns['stoc'] === null && (str_contains($key, 'stoc') || str_contains($key, 'cant'))) {
                $columns['stoc'] = $index;
            }
        }

        return $columns;
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        if (function_exists('mb_strtolower')) {
            $value = mb_strtolower($value, 'UTF-8');
        } else {
            $value = strtolower($value);
        }
        $value = str_replace([' ', '-'], '_', $value);
        return $value;
    }

    private function normalizeNameKey(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s+/', ' ', $value);
        if (function_exists('mb_substr')) {
            $value = (string) mb_substr($value, 0, 55, 'UTF-8');
        } else {
            $value = substr($value, 0, 55);
        }
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($value, 'UTF-8');
        }
        return strtoupper($value);
    }

    private function parseNumber(string $value): ?float
    {
        $raw = trim($value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(["\xc2\xa0", ' '], '', $raw);
        $raw = str_replace(',', '.', $raw);
        $raw = preg_replace('/[^0-9\.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || $raw === '.') {
            return null;
        }
        return (float) $raw;
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
