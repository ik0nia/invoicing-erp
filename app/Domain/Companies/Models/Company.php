<?php

namespace App\Domain\Companies\Models;

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
        $company->denumire = $row['denumire'];
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
}
