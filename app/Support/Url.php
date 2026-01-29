<?php

namespace App\Support;

class Url
{
    public static function base(): string
    {
        return defined('BASE_URL') ? (string) BASE_URL : '';
    }

    public static function to(string $path = ''): string
    {
        $base = self::base();

        if ($path === '' || $path === '/') {
            return $base !== '' ? $base . '/' : '/';
        }

        $path = '/' . ltrim($path, '/');

        return $base . $path;
    }

    public static function asset(string $path): string
    {
        return self::to($path);
    }
}
