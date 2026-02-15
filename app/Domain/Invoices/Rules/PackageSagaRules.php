<?php

namespace App\Domain\Invoices\Rules;

use App\Support\Database;

class PackageSagaRules
{
    public static function validateForSaga(int $packageId): array
    {
        $result = [
            'ok' => true,
            'errors' => [],
            'warnings' => [],
        ];

        if ($packageId <= 0 || !Database::tableExists('packages')) {
            $result['errors'][] = 'Pachetul nu exista.';
            $result['ok'] = false;
            return $result;
        }

        $packageRow = Database::fetchOne(
            'SELECT id, invoice_in_id, vat_percent FROM packages WHERE id = :id LIMIT 1',
            ['id' => $packageId]
        );
        if (!$packageRow) {
            $result['errors'][] = 'Pachetul nu exista.';
            $result['ok'] = false;
            return $result;
        }

        $invoiceRow = null;
        if (Database::tableExists('invoices_in')) {
            $invoiceRow = Database::fetchOne(
                'SELECT id, selected_client_cui, supplier_cui FROM invoices_in WHERE id = :id LIMIT 1',
                ['id' => (int) ($packageRow['invoice_in_id'] ?? 0)]
            );
        }
        if (!$invoiceRow) {
            $result['errors'][] = 'Factura asociata lipseste.';
        } else {
            $clientCui = trim((string) ($invoiceRow['selected_client_cui'] ?? ''));
            $supplierCui = trim((string) ($invoiceRow['supplier_cui'] ?? ''));
            if ($clientCui === '' || $supplierCui === '') {
                $result['warnings'][] = 'Factura lipseste CUI client sau furnizor.';
            }
        }

        $lineCount = 0;
        if (Database::tableExists('invoice_in_lines')) {
            $lineCount = (int) (Database::fetchValue(
                'SELECT COUNT(*) FROM invoice_in_lines WHERE package_id = :id',
                ['id' => $packageId]
            ) ?? 0);
        }
        if ($lineCount <= 0) {
            $result['errors'][] = 'Pachetul nu are produse.';
        }

        if ($lineCount > 0 && Database::tableExists('invoice_in_lines')) {
            $vatRow = Database::fetchOne(
                'SELECT MIN(tax_percent) AS min_vat, MAX(tax_percent) AS max_vat
                 FROM invoice_in_lines
                 WHERE package_id = :id',
                ['id' => $packageId]
            ) ?? [];
            $minVat = (float) ($vatRow['min_vat'] ?? 0);
            $maxVat = (float) ($vatRow['max_vat'] ?? 0);
            if (abs($maxVat - $minVat) > 0.01) {
                $result['warnings'][] = 'Cote TVA mixte in pachet.';
            } else {
                $packageVat = (float) ($packageRow['vat_percent'] ?? 0);
                if (abs($packageVat - $minVat) > 0.01) {
                    $result['warnings'][] = 'Cota TVA pachet nu corespunde produselor.';
                }
            }

            if (Database::columnExists('invoice_in_lines', 'cod_saga')) {
                $missingSaga = (int) (Database::fetchValue(
                    'SELECT COUNT(*) FROM invoice_in_lines
                     WHERE package_id = :id AND (cod_saga IS NULL OR cod_saga = \'\')',
                    ['id' => $packageId]
                ) ?? 0);
                if ($missingSaga > 0) {
                    $result['warnings'][] = 'Exista produse fara cod SAGA.';
                }
            }

            $invalidQty = (int) (Database::fetchValue(
                'SELECT COUNT(*) FROM invoice_in_lines
                 WHERE package_id = :id AND quantity <= 0',
                ['id' => $packageId]
            ) ?? 0);
            if ($invalidQty > 0) {
                $result['warnings'][] = 'Exista produse cu cantitate zero sau negativa.';
            }
        }

        $result['ok'] = empty($result['errors']);

        return $result;
    }
}
