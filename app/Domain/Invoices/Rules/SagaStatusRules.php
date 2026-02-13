<?php

namespace App\Domain\Invoices\Rules;

class SagaStatusRules
{
    public static function canTransition(?string $from, string $to): bool
    {
        $fromValue = strtolower(trim((string) $from));
        $toValue = strtolower(trim($to));

        if ($fromValue === $toValue) {
            return true;
        }

        $allowed = [
            '' => ['processing'],
            'pending' => ['processing'],
            'processing' => ['imported', 'executed'],
            'imported' => ['executed'],
            'executed' => [],
        ];

        if (!array_key_exists($fromValue, $allowed)) {
            return false;
        }

        return in_array($toValue, $allowed[$fromValue], true);
    }
}
