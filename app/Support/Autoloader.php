<?php

namespace App\Support;

class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        if (!str_starts_with($class, 'App\\')) {
            return;
        }

        $relative = substr($class, 4);
        $path = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

        if (file_exists($path)) {
            require $path;
        }
    }
}
