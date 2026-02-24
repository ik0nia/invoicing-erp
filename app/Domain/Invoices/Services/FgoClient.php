<?php

namespace App\Domain\Invoices\Services;

use App\Support\Url;

class FgoClient
{
    private string $baseUrl;

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function hashForEmitere(string $codUnic, string $secret, string $clientName): string
    {
        return strtoupper(sha1($codUnic . $secret . $clientName));
    }

    public static function hashForNumber(string $codUnic, string $secret, string $number): string
    {
        return strtoupper(sha1($codUnic . $secret . $number));
    }

    public static function platformUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $base = Url::base();

        if ($host === '') {
            return $base;
        }

        return $scheme . '://' . $host . $base;
    }

    public function get(string $endpoint): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'Success' => false,
                'Message' => 'Nu pot initializa conexiunea catre FGO.',
            ];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'Success' => false,
                'Message' => 'Eroare conectare FGO: ' . ($error ?: 'necunoscuta'),
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'Success' => false,
                'Message' => 'Raspuns FGO invalid (HTTP ' . $status . ').',
            ];
        }

        return $decoded;
    }

    public function post(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $ch = curl_init($url);

        if ($ch === false) {
            return [
                'Success' => false,
                'Message' => 'Nu pot initializa conexiunea catre FGO.',
            ];
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return [
                'Success' => false,
                'Message' => 'Eroare conectare FGO: ' . ($error ?: 'necunoscuta'),
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'Success' => false,
                'Message' => 'Raspuns FGO invalid (HTTP ' . $status . ').',
            ];
        }

        return $decoded;
    }
}
