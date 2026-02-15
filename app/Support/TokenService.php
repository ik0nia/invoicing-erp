<?php

namespace App\Support;

class TokenService
{
    public static function generateToken(int $bytes = 32): string
    {
        $size = max(32, $bytes);
        $raw = random_bytes($size);
        $base64 = base64_encode($raw);
        return rtrim(strtr($base64, '+/', '-_'), '=');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
