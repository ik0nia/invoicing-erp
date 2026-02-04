<?php

namespace App\Domain\Partners\Models;

use App\Support\CompanyName;
use App\Support\Database;

class Partner
{
    public int $id;
    public string $cui;
    public string $denumire;
    public float $default_commission = 0.0;

    public static function fromArray(array $row): self
    {
        $partner = new self();
        $partner->id = (int) $row['id'];
        $partner->cui = (string) $row['cui'];
        $partner->denumire = CompanyName::normalize((string) $row['denumire']);
        $partner->default_commission = (float) ($row['default_commission'] ?? 0);

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

        self::ensureDefaultCommissionColumn();

        $rows = Database::fetchAll('SELECT * FROM partners ORDER BY denumire ASC');

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function defaultCommissionFor(string $cui): float
    {
        if (!Database::tableExists('partners')) {
            return 0.0;
        }

        self::ensureDefaultCommissionColumn();

        $row = Database::fetchOne(
            'SELECT default_commission FROM partners WHERE cui = :cui LIMIT 1',
            ['cui' => $cui]
        );

        return $row ? (float) ($row['default_commission'] ?? 0.0) : 0.0;
    }

    public static function updateDefaultCommission(string $cui, float $commission): void
    {
        if (!Database::tableExists('partners')) {
            return;
        }

        self::ensureDefaultCommissionColumn();

        Database::execute(
            'UPDATE partners SET default_commission = :commission, updated_at = :updated_at WHERE cui = :cui',
            [
                'commission' => $commission,
                'updated_at' => date('Y-m-d H:i:s'),
                'cui' => $cui,
            ]
        );
    }

    private static function ensureDefaultCommissionColumn(): void
    {
        if (!Database::tableExists('partners')) {
            return;
        }

        if (!Database::columnExists('partners', 'default_commission')) {
            Database::execute(
                'ALTER TABLE partners ADD COLUMN default_commission DECIMAL(6,2) NOT NULL DEFAULT 0'
            );
        }
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
