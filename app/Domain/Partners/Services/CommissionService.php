<?php

namespace App\Domain\Partners\Services;

use App\Domain\Partners\Models\Commission;
use App\Support\Database;

class CommissionService
{
    public function resolveCommissionPercent(?float $invoicePercent, string $supplierCui, string $clientCui): ?float
    {
        if ($invoicePercent !== null) {
            return (float) $invoicePercent;
        }

        $supplierCui = preg_replace('/\D+/', '', (string) $supplierCui);
        $clientCui = preg_replace('/\D+/', '', (string) $clientCui);

        if ($supplierCui !== '' && $clientCui !== '') {
            $commission = Commission::forSupplierClient($supplierCui, $clientCui);
            if ($commission) {
                return (float) $commission->commission;
            }
        }

        if ($supplierCui !== '' && Database::tableExists('partners') && Database::columnExists('partners', 'default_commission')) {
            $value = Database::fetchValue(
                'SELECT default_commission FROM partners WHERE cui = :cui LIMIT 1',
                ['cui' => $supplierCui]
            );
            if ($value !== null && $value !== '') {
                return (float) $value;
            }
        }

        return null;
    }

    public function applyCommission(float $amount, float $percent): float
    {
        $factor = 1 + (abs($percent) / 100);

        if ($percent >= 0) {
            return round($amount * $factor, 2);
        }

        return round($amount / $factor, 2);
    }
}
