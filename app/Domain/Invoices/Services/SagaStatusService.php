<?php

namespace App\Domain\Invoices\Services;

use App\Domain\Invoices\Rules\SagaStatusRules;
use App\Support\Database;
use App\Support\Logger;

class SagaStatusService
{
    public function markProcessing(int $packageId): void
    {
        $from = Database::fetchValue(
            'SELECT saga_status FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        $this->logTransition($from !== null ? (string) $from : null, 'processing', [
            'package_id' => $packageId,
            'action' => 'markProcessing',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'processing', 'id' => $packageId]
        );
    }

    public function markImported(int $packageId): void
    {
        $from = Database::fetchValue(
            'SELECT saga_status FROM packages WHERE package_no = :no LIMIT 1',
            ['no' => $packageId]
        );
        $this->logTransition($from !== null ? (string) $from : null, 'imported', [
            'package_no' => $packageId,
            'action' => 'markImported',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE package_no = :no AND saga_status = :current',
            ['status' => 'imported', 'no' => $packageId, 'current' => 'processing']
        );
    }

    public function markExecuted(int $packageId): void
    {
        $from = Database::fetchValue(
            'SELECT saga_status FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        $this->logTransition($from !== null ? (string) $from : null, 'executed', [
            'package_id' => $packageId,
            'action' => 'markExecuted',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'executed', 'id' => $packageId]
        );
    }

    public function markExecutedByPackageNo(int $packageNo): void
    {
        $from = Database::fetchValue(
            'SELECT saga_status FROM packages WHERE package_no = :no LIMIT 1',
            ['no' => $packageNo]
        );
        $this->logTransition($from !== null ? (string) $from : null, 'executed', [
            'package_no' => $packageNo,
            'action' => 'markExecutedByPackageNo',
        ]);
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE package_no = :no',
            ['status' => 'executed', 'no' => $packageNo]
        );
    }

    public function ensureSagaStatusColumn(): bool
    {
        if (!Database::tableExists('packages')) {
            return false;
        }
        if (!Database::columnExists('packages', 'saga_status')) {
            Database::execute('ALTER TABLE packages ADD COLUMN saga_status VARCHAR(16) NULL AFTER saga_value');
        }
        return Database::columnExists('packages', 'saga_status');
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
