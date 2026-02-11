<?php

namespace App\Support;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable|array $handler): self
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;

        return $this;
    }

    public function post(string $path, callable|array $handler): self
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;

        return $this;
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $this->normalizePath($this->stripBasePath($uri));

        $handler = $this->routes[$method][$path] ?? null;

        if (!$handler) {
            Response::abort(404);
        }

        if ($method === 'POST' && $this->isStockImportRoute($path)) {
            if (!$this->hasValidStockToken()) {
                Response::abort(403, 'Token invalid.');
            }
        } elseif ($method === 'POST' && !Csrf::validate($_POST['_token'] ?? null)) {
            Response::abort(419, 'Token CSRF invalid.');
        }

        if (is_array($handler)) {
            [$class, $methodName] = $handler;
            $controller = new $class();
            $controller->{$methodName}();
            return;
        }

        $handler();
    }

    private function stripBasePath(string $path): string
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return $path === '' ? '/' : $path;
    }

    private function normalizePath(string $path): string
    {
        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }

    private function isStockImportRoute(string $path): bool
    {
        return $path === '/api/stock/import';
    }

    private function hasValidStockToken(): bool
    {
        $token = Env::get('STOCK_IMPORT_TOKEN', '');
        if ($token === '') {
            return false;
        }
        $provided = $_SERVER['HTTP_X_ERP_TOKEN'] ?? ($_GET['token'] ?? '');
        if ($provided === '') {
            return false;
        }

        return hash_equals($token, (string) $provided);
    }
}
