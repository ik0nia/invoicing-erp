<?php

namespace App\Support;

class CompanyName
{
    public static function normalize(string $value): string
    {
        $name = trim($value);
        if ($name === '') {
            return '';
        }

        $name = preg_replace('/^\s*S\s*\.?\s*C\s*\.?\s*/iu', '', $name);
        $name = trim($name);

        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($name, 'UTF-8');
        }

        return strtoupper($name);
    }
}
