<?php

namespace App\Domain\Companies\Services;

use App\Support\CompanyName;
use App\Domain\Settings\Services\SettingsService;

class CompanyLookupService
{
    public function lookupByCui(string $cui): array
    {
        $cui = preg_replace('/\D+/', '', (string) $cui);
        if ($cui === '') {
            return ['error' => 'Completeaza CUI-ul.', 'data' => null];
        }

        $settings = new SettingsService();
        $apiKey = trim((string) $settings->get('openapi.api_key', ''));
        if ($apiKey === '') {
            return ['error' => 'Completeaza cheia OpenAPI in setari.', 'data' => null];
        }

        $response = $this->fetchOpenApiCompany($cui, $apiKey);
        if ($response['error'] !== null) {
            return ['error' => $response['error'], 'data' => null];
        }

        $data = $response['data'];
        $denumire = trim((string) ($data['denumire'] ?? ''));
        $denumire = CompanyName::normalize($denumire);
        $adresa = trim((string) ($data['adresa'] ?? ''));
        $localitate = trim((string) ($data['localitate'] ?? ''));
        if ($localitate === '' && $adresa !== '') {
            $parts = array_map('trim', explode(',', $adresa));
            $localitate = end($parts) ?: '';
        }

        $tva = $data['tva'] ?? null;
        $platitorTva = !empty($tva) && strtolower((string) $tva) !== 'null';
        $radiata = $data['radiata'] ?? null;

        $payload = [
            'cui' => (string) ($data['cif'] ?? $cui),
            'denumire' => $denumire,
            'nr_reg_comertului' => (string) ($data['numar_reg_com'] ?? ''),
            'adresa' => $adresa,
            'localitate' => $localitate,
            'judet' => (string) ($data['judet'] ?? ''),
            'telefon' => (string) ($data['telefon'] ?? ''),
            'platitor_tva' => $platitorTva,
            'activ' => $radiata === null ? null : !$radiata,
        ];

        return ['error' => null, 'data' => $payload];
    }

    private function fetchOpenApiCompany(string $cui, string $apiKey): array
    {
        $url = 'https://api.openapi.ro/api/companies/' . urlencode($cui);
        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => 'Nu pot initia conexiunea OpenAPI.', 'data' => null];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['x-api-key: ' . $apiKey]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false || $status >= 500) {
            return ['error' => 'Eroare OpenAPI: ' . ($error ?: 'server indisponibil'), 'data' => null];
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            return ['error' => 'Raspuns OpenAPI invalid.', 'data' => null];
        }

        if ($status >= 400) {
            $message = $decoded['error']['Attributes']['description'] ?? $decoded['error']['Attributes']['title'] ?? 'Eroare OpenAPI.';
            return ['error' => (string) $message, 'data' => null];
        }

        return ['error' => null, 'data' => $decoded];
    }
}
