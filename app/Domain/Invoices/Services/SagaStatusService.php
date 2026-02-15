<?php

namespace App\Domain\Invoices\Services;

use App\Domain\Invoices\Rules\SagaStatusRules;
use App\Support\Audit;
use App\Support\Database;
use App\Support\Logger;
use App\Support\SchemaEnsurer;

class SagaStatusService
{
    public function markProcessing(int $packageId): void
    {
        $row = Database::fetchOne(
            'SELECT saga_status, package_no FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        $from = $row ? (string) ($row['saga_status'] ?? '') : null;
        $packageNo = $row['package_no'] ?? null;
        $this->logTransition($from !== null ? (string) $from : null, 'processing', [
            'package_id' => $packageId,
            'action' => 'markProcessing',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'processing', 'id' => $packageId]
        );
        Audit::record('saga.status.processing', 'package', $packageId, [
            'from_status' => $from,
            'to_status' => 'processing',
            'package_no' => $packageNo !== null ? (int) $packageNo : null,
        ]);
    }

    public function markImported(int $packageId): void
    {
        $row = Database::fetchOne(
            'SELECT id, saga_status, package_no FROM packages WHERE package_no = :no LIMIT 1',
            ['no' => $packageId]
        );
        $from = $row ? (string) ($row['saga_status'] ?? '') : null;
        $entityId = $row ? (int) ($row['id'] ?? 0) : null;
        $packageNo = $row['package_no'] ?? null;
        $this->logTransition($from !== null ? (string) $from : null, 'imported', [
            'package_no' => $packageId,
            'action' => 'markImported',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE package_no = :no AND saga_status = :current',
            ['status' => 'imported', 'no' => $packageId, 'current' => 'processing']
        );
        Audit::record('saga.status.imported', 'package', $entityId ?: null, [
            'from_status' => $from,
            'to_status' => 'imported',
            'package_no' => $packageNo !== null ? (int) $packageNo : $packageId,
        ]);
    }

    public function markExecuted(int $packageId): void
    {
        $row = Database::fetchOne(
            'SELECT saga_status, package_no FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        $from = $row ? (string) ($row['saga_status'] ?? '') : null;
        $packageNo = $row['package_no'] ?? null;
        $this->logTransition($from !== null ? (string) $from : null, 'executed', [
            'package_id' => $packageId,
            'action' => 'markExecuted',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'executed', 'id' => $packageId]
        );
        Audit::record('saga.status.executed', 'package', $packageId, [
            'from_status' => $from,
            'to_status' => 'executed',
            'package_no' => $packageNo !== null ? (int) $packageNo : null,
        ]);
    }

    public function markExecutedByPackageNo(int $packageNo): void
    {
        $row = Database::fetchOne(
            'SELECT id, saga_status, package_no FROM packages WHERE package_no = :no LIMIT 1',
            ['no' => $packageNo]
        );
        $from = $row ? (string) ($row['saga_status'] ?? '') : null;
        $entityId = $row ? (int) ($row['id'] ?? 0) : null;
        $packageValue = $row['package_no'] ?? null;
        $this->logTransition($from !== null ? (string) $from : null, 'executed', [
            'package_no' => $packageNo,
            'action' => 'markExecutedByPackageNo',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE package_no = :no',
            ['status' => 'executed', 'no' => $packageNo]
        );
        Audit::record('saga.status.executed', 'package', $entityId ?: null, [
            'from_status' => $from,
            'to_status' => 'executed',
            'package_no' => $packageValue !== null ? (int) $packageValue : $packageNo,
        ]);
    }

    public function ensureSagaStatusColumn(): bool
    {
        return SchemaEnsurer::ensurePackagesSagaStatusColumn();
    }

    private function logTransition(?string $from, string $to, array $context): void
    {
        if (!SagaStatusRules::canTransition($from, $to)) {
            Logger::logWarning('saga_status_transition', array_merge([
                'from' => $from,
                'to' => $to,
            ], $context));
        }
    }
}
