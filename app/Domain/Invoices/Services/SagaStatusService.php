<?php

namespace App\Domain\Invoices\Services;

use App\Support\Database;

class SagaStatusService
{
    public function markProcessing(int $packageId): void
    {
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'processing', 'id' => $packageId]
        );
    }

    public function markImported(int $packageId): void
    {
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE package_no = :no AND saga_status = :current',
            ['status' => 'imported', 'no' => $packageId, 'current' => 'processing']
        );
    }

    public function markExecuted(int $packageId): void
    {
        Database::execute(
            'UPDATE packages SET saga_status = :status WHERE id = :id',
            ['status' => 'executed', 'id' => $packageId]
        );
    }

    public function markExecutedByPackageNo(int $packageNo): void
    {
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
}
