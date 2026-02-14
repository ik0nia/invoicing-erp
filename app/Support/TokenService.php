<?php

namespace App\Support;

class TokenService
{
    public static function generateToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes(max(16, $bytes)));
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
