<?php

namespace App\Domain\Settings\Models;

use App\Support\Database;

class Setting
{
    public int $id;
    public string $key;
    public mixed $value;

    public static function findByKey(string $key): ?self
    {
        $row = Database::fetchOne('SELECT * FROM settings WHERE `key` = :key LIMIT 1', [
            'key' => $key,
        ]);

        if (!$row) {
            return null;
        }

        $setting = new self();
        $setting->id = (int) $row['id'];
        $setting->key = $row['key'];
        $setting->value = json_decode($row['value'], true);

        return $setting;
    }
}
