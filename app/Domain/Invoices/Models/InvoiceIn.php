<?php

namespace App\Domain\Invoices\Models;

use App\Support\Database;

class InvoiceIn
{
    public int $id;
    public string $invoice_number;
    public string $invoice_series;
    public string $invoice_no;
    public string $supplier_cui;
    public string $supplier_name;
    public string $customer_cui;
    public string $customer_name;
    public ?string $selected_client_cui = null;
    public string $issue_date;
    public ?string $due_date;
    public string $currency;
    public float $total_without_vat;
    public float $total_vat;
    public float $total_with_vat;
    public ?string $xml_path;
    public bool $packages_confirmed;
    public ?string $packages_confirmed_at;
    public ?string $fgo_series = null;
    public ?string $fgo_number = null;
    public ?string $fgo_link = null;
    public ?string $fgo_storno_series = null;
    public ?string $fgo_storno_number = null;
    public ?string $fgo_storno_link = null;

    public static function create(array $data): self
    {
        $now = date('Y-m-d H:i:s');

        Database::execute(
            'INSERT INTO invoices_in (
                invoice_number,
                invoice_series,
                invoice_no,
                supplier_cui,
                supplier_name,
                customer_cui,
                customer_name,
                issue_date,
                due_date,
                currency,
                total_without_vat,
                total_vat,
                total_with_vat,
                xml_path,
                created_at,
                updated_at
            ) VALUES (
                :invoice_number,
                :invoice_series,
                :invoice_no,
                :supplier_cui,
                :supplier_name,
                :customer_cui,
                :customer_name,
                :issue_date,
                :due_date,
                :currency,
                :total_without_vat,
                :total_vat,
                :total_with_vat,
                :xml_path,
                :created_at,
                :updated_at
            )',
            [
                'invoice_number' => $data['invoice_number'],
                'invoice_series' => $data['invoice_series'] ?? '',
                'invoice_no' => $data['invoice_no'] ?? '',
                'supplier_cui' => $data['supplier_cui'],
                'supplier_name' => $data['supplier_name'],
                'customer_cui' => $data['customer_cui'],
                'customer_name' => $data['customer_name'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $data['currency'],
                'total_without_vat' => $data['total_without_vat'],
                'total_vat' => $data['total_vat'],
                'total_with_vat' => $data['total_with_vat'],
                'xml_path' => $data['xml_path'],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return self::find((int) Database::lastInsertId());
    }

    public static function find(int $id): ?self
    {
        if (!Database::tableExists('invoices_in')) {
            return null;
        }

        $row = Database::fetchOne('SELECT * FROM invoices_in WHERE id = :id LIMIT 1', [
            'id' => $id,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function all(): array
    {
        if (!Database::tableExists('invoices_in')) {
            return [];
        }

        $rows = Database::fetchAll('SELECT * FROM invoices_in ORDER BY issue_date DESC, id DESC');

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function fromArray(array $row): self
    {
        $invoice = new self();
        $invoice->id = (int) $row['id'];
        $invoice->invoice_number = $row['invoice_number'];
        $invoice->invoice_series = $row['invoice_series'] ?? '';
        $invoice->invoice_no = $row['invoice_no'] ?? '';
        $invoice->supplier_cui = $row['supplier_cui'];
        $invoice->supplier_name = $row['supplier_name'];
        $invoice->customer_cui = $row['customer_cui'];
        $invoice->customer_name = $row['customer_name'];
        $invoice->selected_client_cui = $row['selected_client_cui'] ?? null;
        $invoice->issue_date = $row['issue_date'];
        $invoice->due_date = $row['due_date'];
        $invoice->currency = $row['currency'];
        $invoice->total_without_vat = (float) $row['total_without_vat'];
        $invoice->total_vat = (float) $row['total_vat'];
        $invoice->total_with_vat = (float) $row['total_with_vat'];
        $invoice->xml_path = $row['xml_path'];
        $invoice->packages_confirmed = !empty($row['packages_confirmed']);
        $invoice->packages_confirmed_at = $row['packages_confirmed_at'] ?? null;
        $invoice->fgo_series = $row['fgo_series'] ?? null;
        $invoice->fgo_number = $row['fgo_number'] ?? null;
        $invoice->fgo_link = $row['fgo_link'] ?? null;
        $invoice->fgo_storno_series = $row['fgo_storno_series'] ?? null;
        $invoice->fgo_storno_number = $row['fgo_storno_number'] ?? null;
        $invoice->fgo_storno_link = $row['fgo_storno_link'] ?? null;

        return $invoice;
    }
}
