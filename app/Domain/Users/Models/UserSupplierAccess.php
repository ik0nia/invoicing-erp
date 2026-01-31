<?php

namespace App\Domain\Users\Models;

use App\Support\Database;

class UserSupplierAccess
{
    public static function ensureTable(): void
    {
        if (!Database::tableExists('users')) {
            return;
        }

        Database::execute(
            'CREATE TABLE IF NOT EXISTS user_suppliers (
                user_id BIGINT UNSIGNED NOT NULL,
                supplier_cui VARCHAR(32) NOT NULL,
                created_at DATETIME NULL,
                PRIMARY KEY (user_id, supplier_cui),
                CONSTRAINT fk_user_suppliers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function suppliersForUser(int $userId): array
    {
        if (!Database::tableExists('user_suppliers')) {
            return [];
        }

        $rows = Database::fetchAll(
            'SELECT supplier_cui FROM user_suppliers WHERE user_id = :user_id ORDER BY supplier_cui ASC',
            ['user_id' => $userId]
        );

        return array_map(static fn (array $row) => (string) $row['supplier_cui'], $rows);
    }

    public static function userHasSupplier(int $userId, string $supplierCui): bool
    {
        if (!Database::tableExists('user_suppliers')) {
            return false;
        }

        $row = Database::fetchOne(
            'SELECT supplier_cui FROM user_suppliers WHERE user_id = :user_id AND supplier_cui = :supplier LIMIT 1',
            ['user_id' => $userId, 'supplier' => $supplierCui]
        );

        return $row !== null;
    }

    public static function replaceForUser(int $userId, array $supplierCuis): void
    {
        if (!Database::tableExists('user_suppliers')) {
            self::ensureTable();
        }

        Database::execute('DELETE FROM user_suppliers WHERE user_id = :user_id', ['user_id' => $userId]);

        $now = date('Y-m-d H:i:s');
        foreach ($supplierCuis as $cui) {
            $value = preg_replace('/\D+/', '', (string) $cui);
            if ($value === '') {
                continue;
            }
            Database::execute(
                'INSERT IGNORE INTO user_suppliers (user_id, supplier_cui, created_at)
                 VALUES (:user_id, :supplier, :created_at)',
                [
                    'user_id' => $userId,
                    'supplier' => $value,
                    'created_at' => $now,
                ]
            );
        }
    }

    public static function supplierMapForUsers(array $userIds): array
    {
        if (empty($userIds) || !Database::tableExists('user_suppliers')) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($userIds as $index => $userId) {
            $key = 'u' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $userId;
        }

        $rows = Database::fetchAll(
            'SELECT user_id, supplier_cui FROM user_suppliers
             WHERE user_id IN (' . implode(',', $placeholders) . ')
             ORDER BY supplier_cui ASC',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']][] = (string) $row['supplier_cui'];
        }

        return $map;
    }
}
