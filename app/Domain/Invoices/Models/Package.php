<?php

namespace App\Domain\Invoices\Models;

use App\Support\Database;

class Package
{
    public int $id;
    public int $invoice_in_id;
    public ?string $label;

    public static function create(int $invoiceId, ?string $label = null): self
    {
        Database::execute(
            'INSERT INTO packages (invoice_in_id, label, created_at)
             VALUES (:invoice_in_id, :label, :created_at)',
            [
                'invoice_in_id' => $invoiceId,
                'label' => $label,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        return self::find((int) Database::lastInsertId());
    }

    public static function forInvoice(int $invoiceId): array
    {
        $rows = Database::fetchAll(
            'SELECT * FROM packages WHERE invoice_in_id = :invoice ORDER BY id ASC',
            ['invoice' => $invoiceId]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM packages WHERE id = :id LIMIT 1', [
            'id' => $id,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function deleteIfEmpty(int $packageId): void
    {
        $count = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM invoice_in_lines WHERE package_id = :package',
            ['package' => $packageId]
        );

        if ($count === 0) {
            Database::execute('DELETE FROM packages WHERE id = :id', ['id' => $packageId]);
        }
    }

    public static function fromArray(array $row): self
    {
        $package = new self();
        $package->id = (int) $row['id'];
        $package->invoice_in_id = (int) $row['invoice_in_id'];
        $package->label = $row['label'];

        return $package;
    }
}
