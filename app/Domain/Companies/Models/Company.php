<?php

namespace App\Domain\Companies\Models;

use App\Support\CompanyName;
use App\Support\Database;

class Company
{
    public int $id;
    public string $denumire;
    public string $tip_firma;
    public string $cui;
    public string $nr_reg_comertului;
    public bool $platitor_tva;
    public string $adresa;
    public string $localitate;
    public string $judet;
    public string $tara;
    public string $email;
    public string $telefon;
    public string $tip_companie;
    public bool $activ;

    public static function fromArray(array $row): self
    {
        $company = new self();
        $company->id = (int) $row['id'];
        $company->denumire = CompanyName::normalize((string) $row['denumire']);
        $company->tip_firma = $row['tip_firma'];
        $company->cui = $row['cui'];
        $company->nr_reg_comertului = $row['nr_reg_comertului'];
        $company->platitor_tva = (bool) $row['platitor_tva'];
        $company->adresa = $row['adresa'];
        $company->localitate = $row['localitate'];
        $company->judet = $row['judet'];
        $company->tara = $row['tara'];
        $company->email = $row['email'];
        $company->telefon = $row['telefon'];
        $company->tip_companie = $row['tip_companie'];
        $company->activ = (bool) $row['activ'];

        return $company;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne('SELECT * FROM companies WHERE id = :id LIMIT 1', [
            'id' => $id,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function findByCui(string $cui): ?self
    {
        $row = Database::fetchOne('SELECT * FROM companies WHERE cui = :cui LIMIT 1', [
            'cui' => $cui,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function save(array $data): self
    {
        $now = date('Y-m-d H:i:s');
        $data['denumire'] = CompanyName::normalize((string) ($data['denumire'] ?? ''));

        Database::execute(
            'INSERT INTO companies (
                denumire,
                tip_firma,
                cui,
                nr_reg_comertului,
                platitor_tva,
                adresa,
                localitate,
                judet,
                tara,
                email,
                telefon,
                tip_companie,
                activ,
                created_at,
                updated_at
            ) VALUES (
                :denumire,
                :tip_firma,
                :cui,
                :nr_reg_comertului,
                :platitor_tva,
                :adresa,
                :localitate,
                :judet,
                :tara,
                :email,
                :telefon,
                :tip_companie,
                :activ,
                :created_at,
                :updated_at
            )
            ON DUPLICATE KEY UPDATE
                denumire = VALUES(denumire),
                tip_firma = VALUES(tip_firma),
                nr_reg_comertului = VALUES(nr_reg_comertului),
                platitor_tva = VALUES(platitor_tva),
                adresa = VALUES(adresa),
                localitate = VALUES(localitate),
                judet = VALUES(judet),
                tara = VALUES(tara),
                email = VALUES(email),
                telefon = VALUES(telefon),
                tip_companie = VALUES(tip_companie),
                activ = VALUES(activ),
                updated_at = VALUES(updated_at)',
            [
                'denumire' => $data['denumire'],
                'tip_firma' => $data['tip_firma'],
                'cui' => $data['cui'],
                'nr_reg_comertului' => $data['nr_reg_comertului'],
                'platitor_tva' => $data['platitor_tva'],
                'adresa' => $data['adresa'],
                'localitate' => $data['localitate'],
                'judet' => $data['judet'],
                'tara' => $data['tara'],
                'email' => $data['email'],
                'telefon' => $data['telefon'],
                'tip_companie' => $data['tip_companie'],
                'activ' => $data['activ'],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return self::findByCui($data['cui']);
    }
}
