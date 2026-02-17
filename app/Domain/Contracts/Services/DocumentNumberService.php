<?php

namespace App\Domain\Contracts\Services;

use App\Support\Database;
use PDO;
use PDOException;

class DocumentNumberService
{
    public const REGISTRY_SCOPE_CLIENT = 'client';
    public const REGISTRY_SCOPE_SUPPLIER = 'supplier';

    private const CLIENT_REGISTRY_KEY = 'global';
    private const SUPPLIER_REGISTRY_KEY = 'global_supplier';

    public function registryKey(?string $registryScope = null): string
    {
        $registryScope = $this->normalizeRegistryScope((string) $registryScope);

        return $registryScope === self::REGISTRY_SCOPE_SUPPLIER
            ? self::SUPPLIER_REGISTRY_KEY
            : self::CLIENT_REGISTRY_KEY;
    }

    public function ensureRegistryRow(string $docType, array $defaults = []): array
    {
        $requestedDocType = $this->normalizeDocType($docType);
        if ($requestedDocType === '') {
            throw new \InvalidArgumentException('Tipul documentului este obligatoriu.');
        }
        if (!Database::tableExists('document_registry')) {
            throw new \RuntimeException('Registrul documentelor nu este disponibil.');
        }
        $registryScope = $this->normalizeRegistryScope((string) ($defaults['registry_scope'] ?? self::REGISTRY_SCOPE_CLIENT));
        $registryDocType = $this->registryKey($registryScope);

        $startNo = max(1, (int) ($defaults['start_no'] ?? 1));
        $series = $this->normalizeSeries($defaults['series'] ?? null);

        Database::execute(
            'INSERT INTO document_registry (doc_type, series, next_no, start_no, updated_at)
             VALUES (:doc_type, :series, :next_no, :start_no, :updated_at)
             ON DUPLICATE KEY UPDATE doc_type = doc_type',
            [
                'doc_type' => $registryDocType,
                'series' => $series !== '' ? $series : null,
                'next_no' => $startNo,
                'start_no' => $startNo,
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );

        $row = Database::fetchOne(
            'SELECT doc_type, series, next_no, start_no, updated_at
             FROM document_registry
             WHERE doc_type = :doc_type
             LIMIT 1',
            ['doc_type' => $registryDocType]
        );

        if (!$row) {
            throw new \RuntimeException('Nu s-a putut initializa registrul pentru tipul de document.');
        }

        $row['requested_doc_type'] = $requestedDocType;
        $row['registry_doc_type'] = $registryDocType;
        $row['registry_scope'] = $registryScope;

        return $row;
    }

    public function allocateNumber(string $docType, array $defaults = []): array
    {
        $requestedDocType = $this->normalizeDocType($docType);
        if ($requestedDocType === '') {
            throw new \InvalidArgumentException('Tipul documentului este obligatoriu pentru alocarea numarului.');
        }
        if (!Database::tableExists('document_registry')) {
            throw new \RuntimeException('Registrul documentelor nu este disponibil.');
        }
        $registryScope = $this->normalizeRegistryScope((string) ($defaults['registry_scope'] ?? self::REGISTRY_SCOPE_CLIENT));
        $registryDocType = $this->registryKey($registryScope);

        $startNo = max(1, (int) ($defaults['start_no'] ?? 1));
        $defaultSeries = $this->normalizeSeries($defaults['series'] ?? null);
        $pdo = Database::pdo();

        for ($attempt = 0; $attempt < 4; $attempt++) {
            try {
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }

                $select = $pdo->prepare(
                    'SELECT doc_type, series, next_no, start_no
                     FROM document_registry
                     WHERE doc_type = :doc_type
                     LIMIT 1
                     FOR UPDATE'
                );
                $select->execute(['doc_type' => $registryDocType]);
                $row = $select->fetch(PDO::FETCH_ASSOC);

                if (!$row) {
                    $insert = $pdo->prepare(
                        'INSERT INTO document_registry (doc_type, series, next_no, start_no, updated_at)
                         VALUES (:doc_type, :series, :next_no, :start_no, :updated_at)'
                    );
                    $insert->execute([
                        'doc_type' => $registryDocType,
                        'series' => $defaultSeries !== '' ? $defaultSeries : null,
                        'next_no' => $startNo,
                        'start_no' => $startNo,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $pdo->commit();
                    continue;
                }

                $rowStart = max(1, (int) ($row['start_no'] ?? 1));
                $currentNo = (int) ($row['next_no'] ?? 0);
                if ($currentNo < $rowStart) {
                    $currentNo = $rowStart;
                }
                if ($currentNo < 1) {
                    $currentNo = 1;
                }

                $nextNo = $currentNo + 1;
                $update = $pdo->prepare(
                    'UPDATE document_registry
                     SET next_no = :next_no,
                         updated_at = :updated_at
                     WHERE doc_type = :doc_type'
                );
                $update->execute([
                    'next_no' => $nextNo,
                    'updated_at' => date('Y-m-d H:i:s'),
                    'doc_type' => $registryDocType,
                ]);

                $pdo->commit();
                $series = $this->normalizeSeries($row['series'] ?? $defaultSeries);
                $fullNo = $this->formatFullNo($series, $currentNo);

                return [
                    'doc_type' => $requestedDocType,
                    'registry_doc_type' => $registryDocType,
                    'registry_scope' => $registryScope,
                    'series' => $series,
                    'no' => $currentNo,
                    'full_no' => $fullNo,
                ];
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($this->isDuplicateKey($exception) && $attempt < 3) {
                    continue;
                }

                throw new \RuntimeException('Nu am putut aloca numarul de document.', 0, $exception);
            }
        }

        throw new \RuntimeException('Nu am putut aloca numarul de document dupa mai multe incercari.');
    }

    private function normalizeDocType(string $docType): string
    {
        $docType = trim($docType);
        $docType = preg_replace('/[^a-zA-Z0-9_.-]/', '', $docType ?? '');

        return strtolower((string) $docType);
    }

    private function normalizeRegistryScope(string $registryScope): string
    {
        $registryScope = strtolower(trim($registryScope));

        return $registryScope === self::REGISTRY_SCOPE_SUPPLIER
            ? self::REGISTRY_SCOPE_SUPPLIER
            : self::REGISTRY_SCOPE_CLIENT;
    }

    private function normalizeSeries(?string $series): string
    {
        $series = trim((string) $series);
        if ($series === '') {
            return '';
        }
        $series = preg_replace('/[^a-zA-Z0-9._-]/', '', $series);
        if ($series === null) {
            return '';
        }

        return strtoupper(substr($series, 0, 16));
    }

    private function formatFullNo(string $series, int $no): string
    {
        $padded = str_pad((string) max(1, $no), 6, '0', STR_PAD_LEFT);
        if ($series === '') {
            return $padded;
        }

        return $series . '-' . $padded;
    }

    private function isDuplicateKey(\Throwable $exception): bool
    {
        if ($exception instanceof PDOException) {
            $sqlState = $exception->getCode();
            if ($sqlState === '23000') {
                return true;
            }
        }

        return str_contains(strtolower($exception->getMessage()), 'duplicate');
    }
}
