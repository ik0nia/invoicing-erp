<?php

namespace App\Domain\Settings\Http\Controllers;

use App\Support\Response;
use App\Support\Session;

class InstallController
{
    public function show(): void
    {
        if ($this->isInstalled()) {
            Response::redirect('/setup');
        }

        Response::view('install/index', [
            'app_name' => 'ERP Laravel Romania',
            'app_url' => $this->defaultAppUrl(),
            'app_timezone' => 'Europe/Bucharest',
            'db_host' => '127.0.0.1',
            'db_port' => '3306',
            'db_database' => '',
            'db_username' => '',
            'db_password' => '',
            'db_charset' => 'utf8mb4',
        ], 'layouts/guest');
    }

    public function store(): void
    {
        if ($this->isInstalled()) {
            Response::redirect('/setup');
        }

        $data = [
            'app_name' => trim($_POST['app_name'] ?? ''),
            'app_url' => trim($_POST['app_url'] ?? ''),
            'app_timezone' => trim($_POST['app_timezone'] ?? 'Europe/Bucharest'),
            'db_host' => trim($_POST['db_host'] ?? ''),
            'db_port' => trim($_POST['db_port'] ?? '3306'),
            'db_database' => trim($_POST['db_database'] ?? ''),
            'db_username' => trim($_POST['db_username'] ?? ''),
            'db_password' => (string) ($_POST['db_password'] ?? ''),
            'db_charset' => trim($_POST['db_charset'] ?? 'utf8mb4'),
        ];

        if ($data['app_name'] === '' || $data['app_url'] === '' || $data['db_host'] === '' || $data['db_database'] === '' || $data['db_username'] === '') {
            Session::flash('error', 'Completeaza toate campurile obligatorii.');
            Response::redirect('/install');
        }

        if (!filter_var($data['app_url'], FILTER_VALIDATE_URL)) {
            Session::flash('error', 'APP_URL nu este valid.');
            Response::redirect('/install');
        }

        if (!$this->testDatabase($data)) {
            Session::flash('error', 'Nu am putut conecta baza de date. Verifica datele.');
            Response::redirect('/install');
        }

        $contents = $this->buildEnv($data);

        if (!$this->writeEnv($contents)) {
            Session::flash('error', 'Nu pot scrie fisierul .env. Verifica permisiunile.');
            Response::redirect('/install');
        }

        Session::flash('status', 'Configurarea a fost salvata. Continua cu creare admin.');
        Response::redirect('/setup');
    }

    private function isInstalled(): bool
    {
        return file_exists(BASE_PATH . '/.env');
    }

    private function defaultAppUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = \App\Support\Url::base();

        return $scheme . '://' . $host . $base;
    }

    private function buildEnv(array $data): string
    {
        $lines = [
            $this->envLine('APP_NAME', $data['app_name']),
            $this->envLine('APP_URL', rtrim($data['app_url'], '/')),
            $this->envLine('APP_TIMEZONE', $data['app_timezone'] ?: 'Europe/Bucharest'),
            '',
            $this->envLine('DB_HOST', $data['db_host']),
            $this->envLine('DB_PORT', $data['db_port'] ?: '3306'),
            $this->envLine('DB_DATABASE', $data['db_database']),
            $this->envLine('DB_USERNAME', $data['db_username']),
            $this->envLine('DB_PASSWORD', $data['db_password']),
            $this->envLine('DB_CHARSET', $data['db_charset'] ?: 'utf8mb4'),
            '',
        ];

        return implode(PHP_EOL, $lines);
    }

    private function envLine(string $key, string $value): string
    {
        $escaped = str_replace('"', '\"', $value);

        return $key . '="' . $escaped . '"';
    }

    private function writeEnv(string $contents): bool
    {
        if (file_exists(BASE_PATH . '/.env')) {
            return false;
        }

        return file_put_contents(BASE_PATH . '/.env', $contents) !== false;
    }

    private function testDatabase(array $data): bool
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $data['db_host'],
                $data['db_port'],
                $data['db_database'],
                $data['db_charset']
            );

            new \PDO($dsn, $data['db_username'], $data['db_password'], [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }
}
