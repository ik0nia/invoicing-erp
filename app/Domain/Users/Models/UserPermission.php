<?php

namespace App\Domain\Users\Models;

use App\Support\Database;

class UserPermission
{
    public const RENAME_PACKAGES = 'rename_packages';

    public static function ensureTable(): void
    {
        Database::execute(
            'CREATE TABLE IF NOT EXISTS user_permissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                permission_key VARCHAR(64) NOT NULL,
                created_at DATETIME NULL,
                UNIQUE KEY uniq_user_permission (user_id, permission_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function userHas(int $userId, string $permissionKey): bool
    {
        if (!Database::tableExists('user_permissions')) {
            return false;
        }

        $row = Database::fetchOne(
            'SELECT id FROM user_permissions WHERE user_id = :user AND permission_key = :perm LIMIT 1',
            [
                'user' => $userId,
                'perm' => $permissionKey,
            ]
        );

        return $row !== null;
    }

    public static function setForUser(int $userId, string $permissionKey, bool $enabled): void
    {
        self::ensureTable();

        if ($enabled) {
            Database::execute(
                'INSERT IGNORE INTO user_permissions (user_id, permission_key, created_at)
                 VALUES (:user, :perm, :created_at)',
                [
                    'user' => $userId,
                    'perm' => $permissionKey,
                    'created_at' => date('Y-m-d H:i:s'),
                ]
            );
        } else {
            Database::execute(
                'DELETE FROM user_permissions WHERE user_id = :user AND permission_key = :perm',
                [
                    'user' => $userId,
                    'perm' => $permissionKey,
                ]
            );
        }
    }

    public static function permissionMapForUsers(array $userIds, string $permissionKey): array
    {
        if (!Database::tableExists('user_permissions') || empty($userIds)) {
            return [];
        }

        $placeholders = [];
        $params = ['perm' => $permissionKey];
        foreach (array_values($userIds) as $index => $userId) {
            $key = 'u' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $userId;
        }

        $rows = Database::fetchAll(
            'SELECT user_id FROM user_permissions
             WHERE permission_key = :perm AND user_id IN (' . implode(',', $placeholders) . ')',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['user_id']] = true;
        }

        return $map;
    }
}
