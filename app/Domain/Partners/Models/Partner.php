<?php

namespace App\Domain\Partners\Models;

use App\Support\CompanyName;
use App\Support\Database;

class Partner
{
    public int $id;
    public string $cui;
    public string $denumire;

    public static function fromArray(array $row): self
    {
        $partner = new self();
        $partner->id = (int) $row['id'];
        $partner->cui = (string) $row['cui'];
        $partner->denumire = CompanyName::normalize((string) $row['denumire']);

        return $partner;
    }

    public static function findByCui(string $cui): ?self
    {
        $row = Database::fetchOne('SELECT * FROM partners WHERE cui = :cui LIMIT 1', [
            'cui' => $cui,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function all(): array
    {
        if (!Database::tableExists('partners')) {
            return [];
        }

        $rows = Database::fetchAll('SELECT * FROM partners ORDER BY denumire ASC');

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function createIfMissing(string $cui, string $denumire): self
    {
        $existing = self::findByCui($cui);

        if ($existing) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $denumire = CompanyName::normalize($denumire);

        Database::execute(
            'INSERT INTO partners (cui, denumire, created_at, updated_at)
             VALUES (:cui, :denumire, :created_at, :updated_at)',
            [
                'cui' => $cui,
                'denumire' => $denumire,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return self::findByCui($cui);
    }

    public static function upsert(string $cui, string $denumire): self
    {
        $now = date('Y-m-d H:i:s');
        $denumire = CompanyName::normalize($denumire);

        Database::execute(
            'INSERT INTO partners (cui, denumire, created_at, updated_at)
             VALUES (:cui, :denumire, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE denumire = VALUES(denumire), updated_at = VALUES(updated_at)',
            [
                'cui' => $cui,
                'denumire' => $denumire,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        return self::findByCui($cui);
    }
}
