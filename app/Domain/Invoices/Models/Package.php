<?php

namespace App\Domain\Invoices\Models;

use App\Support\Database;

class Package
{
    public int $id;
    public int $invoice_in_id;
    public int $package_no;
    public ?string $label;
    public float $vat_percent;
    public ?float $saga_value = null;

    public static function create(int $invoiceId, int $packageNo, float $vatPercent, ?string $label = null): self
    {
        Database::execute(
            'INSERT INTO packages (invoice_in_id, package_no, label, vat_percent, created_at)
             VALUES (:invoice_in_id, :package_no, :label, :vat_percent, :created_at)',
            [
                'invoice_in_id' => $invoiceId,
                'package_no' => $packageNo,
                'label' => $label,
                'vat_percent' => $vatPercent,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return self::find((int) Database::lastInsertId());
    }

    public static function forInvoice(int $invoiceId): array
    {
        if (!Database::tableExists('packages')) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT * FROM packages WHERE invoice_in_id = :invoice ORDER BY package_no ASC, id ASC',
            ['invoice' => $invoiceId]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function find(int $id): ?self
    {
        if (!Database::tableExists('packages')) {
            return null;
        }

        $row = Database::fetchOne('SELECT * FROM packages WHERE id = :id LIMIT 1', [
            'id' => $id,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function deleteIfEmpty(int $packageId): void
    {
        if (!Database::tableExists('invoice_in_lines')) {
            return;
        }

        $count = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM invoice_in_lines WHERE package_id = :package',
            ['package' => $packageId]
        );

        if ($count === 0) {
            Database::execute('DELETE FROM packages WHERE id = :id', ['id' => $packageId]);
        }
    }

    public static function updateVat(int $packageId, float $vatPercent): void
    {
        Database::execute(
            'UPDATE packages SET vat_percent = :vat WHERE id = :id',
            ['vat' => $vatPercent, 'id' => $packageId]
        );
    }

    public static function updateNumber(int $packageId, int $packageNo): void
    {
        Database::execute(
            'UPDATE packages SET package_no = :package_no WHERE id = :id',
            ['package_no' => $packageNo, 'id' => $packageId]
        );
    }

    public static function updateLabel(int $packageId, ?string $label): void
    {
        Database::execute(
            'UPDATE packages SET label = :label WHERE id = :id',
            ['label' => $label, 'id' => $packageId]
        );
    }

    public static function updateSagaValue(int $packageId, ?float $value): void
    {
        Database::execute(
            'UPDATE packages SET saga_value = :value WHERE id = :id',
            ['value' => $value, 'id' => $packageId]
        );
    }

    public static function fromArray(array $row): self
    {
        $package = new self();
        $package->id = (int) $row['id'];
        $package->invoice_in_id = (int) $row['invoice_in_id'];
        $package->package_no = isset($row['package_no']) ? (int) $row['package_no'] : (int) $row['id'];
        $package->label = $row['label'];
        $package->vat_percent = isset($row['vat_percent']) ? (float) $row['vat_percent'] : 0.0;
        $package->saga_value = array_key_exists('saga_value', $row) ? (float) $row['saga_value'] : null;

        return $package;
    }
}
