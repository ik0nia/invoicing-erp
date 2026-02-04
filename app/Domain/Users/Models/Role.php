<?php

namespace App\Domain\Users\Models;

use App\Support\Database;

class Role
{
    public int $id;
    public string $key;
    public string $label;

    public static function fromArray(array $row): self
    {
        $role = new self();
        $role->id = (int) $row['id'];
        $role->key = $row['key'];
        $role->label = $row['label'];

        return $role;
    }

    public static function findByKey(string $key): ?self
    {
        $row = Database::fetchOne('SELECT * FROM roles WHERE `key` = :key LIMIT 1', [
            'key' => $key,
        ]);

        return $row ? self::fromArray($row) : null;
    }

    public static function forUser(int $userId): array
    {
        $rows = Database::fetchAll(
            'SELECT r.* FROM roles r INNER JOIN role_user ru ON ru.role_id = r.id WHERE ru.user_id = :user_id',
            ['user_id' => $userId]
        );

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function assignToUser(int $userId, string $roleKey): void
    {
        $role = self::findByKey($roleKey);

        if (!$role) {
            return;
        }

        Database::execute(
            'INSERT IGNORE INTO role_user (user_id, role_id) VALUES (:user_id, :role_id)',
            ['user_id' => $userId, 'role_id' => $role->id]
        );
    }

    public static function ensureDefaults(): void
    {
        if (!Database::tableExists('roles')) {
            return;
        }

        $defaults = [
            ['key' => 'super_admin', 'label' => 'Super admin'],
            ['key' => 'admin', 'label' => 'Administrator'],
            ['key' => 'contabil', 'label' => 'Contabil'],
            ['key' => 'operator', 'label' => 'Operator'],
            ['key' => 'supplier_user', 'label' => 'Utilizator furnizor'],
            ['key' => 'staff', 'label' => 'Angajat firma'],
            ['key' => 'client_user', 'label' => 'Utilizator client'],
            ['key' => 'intermediary_user', 'label' => 'Utilizator intermediar'],
        ];

        foreach ($defaults as $role) {
            Database::execute(
                'INSERT INTO roles (`key`, `label`) VALUES (:key, :label)
                 ON DUPLICATE KEY UPDATE `label` = VALUES(`label`)',
                $role
            );
        }

        if (!Database::tableExists('role_user')) {
            return;
        }

        $superAdmin = self::findByKey('super_admin');
        $admin = self::findByKey('admin');

        if (!$superAdmin || !$admin) {
            return;
        }

        $count = (int) Database::fetchValue(
            'SELECT COUNT(*) FROM role_user WHERE role_id = :role',
            ['role' => $superAdmin->id]
        );

        if ($count === 0) {
            Database::execute(
                'INSERT IGNORE INTO role_user (user_id, role_id)
                 SELECT user_id, :super_role FROM role_user WHERE role_id = :admin_role',
                [
                    'super_role' => $superAdmin->id,
                    'admin_role' => $admin->id,
                ]
            );
        }
    }

    public static function all(): array
    {
        if (!Database::tableExists('roles')) {
            return [];
        }

        $rows = Database::fetchAll('SELECT * FROM roles ORDER BY id ASC');

        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function clearForUser(int $userId): void
    {
        if (!Database::tableExists('role_user')) {
            return;
        }

        Database::execute('DELETE FROM role_user WHERE user_id = :user_id', ['user_id' => $userId]);
    }
}
