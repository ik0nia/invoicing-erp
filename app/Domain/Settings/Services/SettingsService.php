<?php

namespace App\Domain\Settings\Services;

use App\Support\Database;

class SettingsService
{
    private string $cacheFile;

    public function __construct()
    {
        $this->cacheFile = BASE_PATH . '/storage/cache/settings.php';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $cache = $this->readCache();

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $row = Database::fetchOne('SELECT value FROM settings WHERE `key` = :key LIMIT 1', [
                'key' => $key,
            ]);
        } catch (\Throwable $exception) {
            return $default;
        }

        if (!$row) {
            return $default;
        }

        $value = json_decode($row['value'], true);

        if ($value === null && $row['value'] !== 'null') {
            $value = $row['value'];
        }

        $cache[$key] = $value;
        $this->writeCache($cache);

        return $value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $payload = json_encode($value, JSON_UNESCAPED_UNICODE);

        Database::execute(
            'INSERT INTO settings (`key`, `value`, `created_at`, `updated_at`)
             VALUES (:key, :value, NOW(), NOW())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `updated_at` = NOW()',
            [
                'key' => $key,
                'value' => $payload,
            ]
        );

        $cache = $this->readCache();
        $cache[$key] = $value;
        $this->writeCache($cache);
    }

    private function readCache(): array
    {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $data = include $this->cacheFile;

        return is_array($data) ? $data : [];
    }

    private function writeCache(array $cache): void
    {
        $dir = dirname($this->cacheFile);

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $content = '<?php return ' . var_export($cache, true) . ';';
        file_put_contents($this->cacheFile, $content);
    }
}
