<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Support\Auth;
use App\Support\Database;
use App\Support\Response;
use App\Support\Session;

class LegacyImportController
{
    public function show(): void
    {
        Auth::requireAdmin();

        $legacyPartnersExists = Database::tableExists('parteneri');
        $legacyCommissionsExists = Database::tableExists('comisioane');

        $stats = [
            'legacy_partners' => $legacyPartnersExists ? (int) Database::fetchValue('SELECT COUNT(*) FROM parteneri') : 0,
            'legacy_commissions' => $legacyCommissionsExists ? (int) Database::fetchValue('SELECT COUNT(*) FROM comisioane') : 0,
            'partners' => Database::tableExists('partners') ? (int) Database::fetchValue('SELECT COUNT(*) FROM partners') : 0,
            'commissions' => Database::tableExists('commissions') ? (int) Database::fetchValue('SELECT COUNT(*) FROM commissions') : 0,
        ];

        Response::view('admin/settings/legacy_import', [
            'legacyPartnersExists' => $legacyPartnersExists,
            'legacyCommissionsExists' => $legacyCommissionsExists,
            'stats' => $stats,
        ]);
    }

    public function import(): void
    {
        Auth::requireAdmin();

        if (!Database::tableExists('parteneri') || !Database::tableExists('comisioane')) {
            Session::flash('error', 'Nu exista tabelele parteneri/comisioane in baza de date.');
            Response::redirect('/admin/setari/import-date');
        }

        $this->ensureTables();

        try {
            Database::execute(
                'INSERT INTO partners (cui, denumire, created_at, updated_at)
                 SELECT CAST(p.cui AS CHAR), p.denumire_firma, NOW(), NOW()
                 FROM parteneri p
                 ON DUPLICATE KEY UPDATE denumire = VALUES(denumire), updated_at = NOW()'
            );

            Database::execute(
                'INSERT INTO commissions (supplier_cui, client_cui, commission, created_at, updated_at)
                 SELECT CAST(c.furnizor AS CHAR), CAST(c.client AS CHAR), c.comision, NOW(), NOW()
                 FROM comisioane c
                 ON DUPLICATE KEY UPDATE commission = VALUES(commission), updated_at = NOW()'
            );
        } catch (\Throwable $exception) {
            Session::flash('error', 'Import esuat: ' . $exception->getMessage());
            Response::redirect('/admin/setari/import-date');
        }

        Session::flash('status', 'Importul din baza veche a fost finalizat.');
        Response::redirect('/admin/setari/import-date');
    }

    private function ensureTables(): void
    {
        Database::execute(
            'CREATE TABLE IF NOT EXISTS partners (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                cui VARCHAR(32) NOT NULL UNIQUE,
                denumire VARCHAR(255) NOT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        Database::execute(
            'CREATE TABLE IF NOT EXISTS commissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                supplier_cui VARCHAR(32) NOT NULL,
                client_cui VARCHAR(32) NOT NULL,
                commission DECIMAL(8,2) NOT NULL DEFAULT 0,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                UNIQUE KEY commissions_supplier_client_unique (supplier_cui, client_cui)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
