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

    public static function normalizeJudet(string $value): string
    {
        $judet = trim($value);
        if ($judet === '') {
            return '';
        }

        $judet = preg_replace('/^municipiul\s+/iu', '', $judet);
        $judet = trim($judet);

        $judet = str_replace(
            ['ș', 'Ș', 'ş', 'Ş', 'ț', 'Ț', 'ţ', 'Ţ', 'ă', 'Ă', 'â', 'Â', 'î', 'Î'],
            ['s', 'S', 's', 'S', 't', 'T', 't', 'T', 'a', 'A', 'a', 'A', 'i', 'I'],
            $judet
        );

        return $judet;
    }
}
