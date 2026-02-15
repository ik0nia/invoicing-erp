<?php

namespace App\Support;

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded || !file_exists($path)) {
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, null);

            if ($key === null) {
                continue;
            }

            $key = trim($key);
            $value = $value === null ? '' : trim($value);

            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            $fileValue = self::readFromFile($key);
            if ($fileValue !== null && $fileValue !== '') {
                return $fileValue;
            }
            return $default;
        }

        return $value;
    }

    private static function readFromFile(string $key): ?string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $path = $basePath . '/.env';
        if (!file_exists($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!$lines) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            [$envKey, $value] = array_pad(explode('=', $line, 2), 2, null);
            if ($envKey === null) {
                continue;
            }
            $envKey = trim($envKey);
            if ($envKey !== $key) {
                continue;
            }
            $value = $value === null ? '' : trim($value);
            return trim($value, "\"'");
        }

        return null;
    }
}
