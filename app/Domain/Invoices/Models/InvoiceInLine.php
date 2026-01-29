<?php

namespace App\Domain\Invoices\Models;

use App\Support\Database;

class InvoiceInLine
{
    public int $id;
    public int $invoice_in_id;
    public string $line_no;
    public string $product_name;
    public float $quantity;
    public string $unit_code;
    public float $unit_price;
    public float $line_total;
    public float $tax_percent;
    public float $line_total_vat;
    public ?int $package_id;

    public static function create(int $invoiceId, array $data): void
    {
        Database::execute(
            'INSERT INTO invoice_in_lines (
                invoice_in_id,
                line_no,
                product_name,
                quantity,
                unit_code,
                unit_price,
                line_total,
                tax_percent,
                line_total_vat,
                package_id,
                created_at
            ) VALUES (
                :invoice_in_id,
                :line_no,
                :product_name,
                :quantity,
                :unit_code,
                :unit_price,
                :line_total,
                :tax_percent,
                :line_total_vat,
                :package_id,
                :created_at
            )',
            [
                'invoice_in_id' => $invoiceId,
                'line_no' => $data['line_no'],
                'product_name' => $data['product_name'],
                'quantity' => $data['quantity'],
                'unit_code' => $data['unit_code'],
                'unit_price' => $data['unit_price'],
                'line_total' => $data['line_total'],
                'tax_percent' => $data['tax_percent'],
                'line_total_vat' => $data['line_total_vat'],
                'package_id' => $data['package_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );
    }

    public static function forInvoice(int $invoiceId): array
    {
        if (!Database::tableExists('invoice_in_lines')) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT * FROM invoice_in_lines WHERE invoice_in_id = :invoice ORDER BY id ASC',
            ['invoice' => $invoiceId]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function updatePackage(int $lineId, ?int $packageId): void
    {
        Database::execute(
            'UPDATE invoice_in_lines SET package_id = :package_id WHERE id = :id',
            [
                'package_id' => $packageId,
                'id' => $lineId,
            ]
        );
    }

    public static function find(int $lineId): ?self
    {
        if (!Database::tableExists('invoice_in_lines')) {
            return null;
        }

        $row = Database::fetchOne('SELECT * FROM invoice_in_lines WHERE id = :id LIMIT 1', [
            'id' => $lineId,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function fromArray(array $row): self
    {
        $line = new self();
        $line->id = (int) $row['id'];
        $line->invoice_in_id = (int) $row['invoice_in_id'];
        $line->line_no = $row['line_no'];
        $line->product_name = $row['product_name'];
        $line->quantity = (float) $row['quantity'];
        $line->unit_code = $row['unit_code'];
        $line->unit_price = (float) $row['unit_price'];
        $line->line_total = (float) $row['line_total'];
        $line->tax_percent = (float) $row['tax_percent'];
        $line->line_total_vat = (float) $row['line_total_vat'];
        $line->package_id = $row['package_id'] !== null ? (int) $row['package_id'] : null;

        return $line;
    }
}
