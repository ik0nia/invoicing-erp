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
        $judet = self::stripDiacritics($judet);
        $judet = str_replace('Bucuresti', 'Bucuresti', $judet); // already normalized via stripDiacritics

        return $judet;
    }

    /**
     * Strip Romanian diacritics from a string.
     * Handles both comma-below (correct: ș/ț) and cedilla (legacy: ş/ţ) variants.
     */
    public static function stripDiacritics(string $value): string
    {
        return str_replace(
            ['ă', 'Ă', 'â', 'Â', 'î', 'Î', 'ș', 'Ș', 'ț', 'Ț', 'ş', 'Ş', 'ţ', 'Ţ'],
            ['a', 'A', 'a', 'A', 'i', 'I', 's', 'S', 't', 'T', 's', 'S', 't', 'T'],
            $value
        );
    }
}
