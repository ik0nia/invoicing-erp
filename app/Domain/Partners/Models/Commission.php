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

    public static function forSupplierWithPartners(string $supplierCui): array
    {
        if (!Database::tableExists('commissions')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.*, sp.denumire AS supplier_name, cp.denumire AS client_name
             FROM commissions c
             LEFT JOIN partners sp ON sp.cui = c.supplier_cui
             LEFT JOIN partners cp ON cp.cui = c.client_cui
             WHERE c.supplier_cui = :supplier
             ORDER BY client_name ASC',
            ['supplier' => $supplierCui]
        );
    }

    public static function forSupplierClient(string $supplierCui, string $clientCui): ?self
    {
        $row = Database::fetchOne(
            'SELECT * FROM commissions WHERE supplier_cui = :supplier AND client_cui = :client LIMIT 1',
            ['supplier' => $supplierCui, 'client' => $clientCui]
        );

        return $row ? self::fromArray($row) : null;
    }

    public static function allWithPartners(): array
    {
        if (!Database::tableExists('commissions')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.*, sp.denumire AS supplier_name, cp.denumire AS client_name
             FROM commissions c
             LEFT JOIN partners sp ON sp.cui = c.supplier_cui
             LEFT JOIN partners cp ON cp.cui = c.client_cui
             ORDER BY supplier_name ASC, client_name ASC'
        );
    }

    public static function clientCuisForSuppliers(array $supplierCuis): array
    {
        if (!Database::tableExists('commissions') || empty($supplierCuis)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($supplierCuis) as $index => $cui) {
            $key = 's' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $cui;
        }

        $rows = Database::fetchAll(
            'SELECT DISTINCT client_cui FROM commissions WHERE supplier_cui IN (' . implode(',', $placeholders) . ')',
            $params
        );

        return array_values(array_filter(array_map(static fn (array $row) => (string) $row['client_cui'], $rows)));
    }

    public static function createOrUpdate(string $supplierCui, string $clientCui, float $commission): void
    {
        Database::execute(
            'INSERT INTO commissions (supplier_cui, client_cui, commission, created_at, updated_at)
             VALUES (:supplier, :client, :commission, NOW(), NOW())
             ON DUPLICATE KEY UPDATE commission = VALUES(commission), updated_at = NOW()',
            [
                'supplier' => $supplierCui,
                'client' => $clientCui,
                'commission' => $commission,
            ]
        );
    }

    public static function deleteById(int $id): void
    {
        Database::execute('DELETE FROM commissions WHERE id = :id', ['id' => $id]);
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
