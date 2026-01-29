<?php

namespace App\Domain\Partners\Models;

use App\Support\Database;

class Commission
{
    public int $id;
    public string $supplier_cui;
    public string $client_cui;
    public float $commission;

    public static function forSupplier(string $supplierCui): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM commissions WHERE supplier_cui = :supplier ORDER BY client_cui ASC',
            ['supplier' => $supplierCui]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function forSupplierClient(string $supplierCui, string $clientCui): ?self
    {
        $row = Database::fetchOne(
            'SELECT * FROM commissions WHERE supplier_cui = :supplier AND client_cui = :client LIMIT 1',
            ['supplier' => $supplierCui, 'client' => $clientCui]
        );

        return $row ? self::fromArray($row) : null;
    }

    public static function fromArray(array $row): self
    {
        $commission = new self();
        $commission->id = (int) $row['id'];
        $commission->supplier_cui = (string) $row['supplier_cui'];
        $commission->client_cui = (string) $row['client_cui'];
        $commission->commission = (float) $row['commission'];

        return $commission;
    }
}
