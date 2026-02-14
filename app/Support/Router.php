<?php

namespace App\Support;

class Router
{
    private array $routes = [
        'GET' => [],
        'POST' => [],
    ];
    private array $patternRoutes = [
        'GET' => [],
        'POST' => [],
    ];

    public function get(string $path, callable|array $handler): self
    {
        $normalized = $this->normalizePath($path);
        if (str_contains($normalized, '{')) {
            $this->patternRoutes['GET'][] = $this->compilePattern($normalized, $handler);
        } else {
            $this->routes['GET'][$normalized] = $handler;
        }

        return $this;
    }

    public function post(string $path, callable|array $handler): self
    {
        $normalized = $this->normalizePath($path);
        if (str_contains($normalized, '{')) {
            $this->patternRoutes['POST'][] = $this->compilePattern($normalized, $handler);
        } else {
            $this->routes['POST'][$normalized] = $handler;
        }

        return $this;
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $path = $this->normalizePath($this->stripBasePath($uri));

        $handler = $this->routes[$method][$path] ?? null;
        if (!$handler) {
            $match = $this->matchPattern($method, $path);
            if ($match) {
                $handler = $match['handler'];
                foreach ($match['params'] as $key => $value) {
                    if (!isset($_GET[$key])) {
                        $_GET[$key] = $value;
                    }
                }
            }
        }

        if (!$handler) {
            Response::abort(404);
        }

        if ($method === 'POST' && $this->isStockImportRoute($path)) {
            if (!$this->hasValidStockToken()) {
                Response::abort(403, 'Token invalid.');
            }
        } elseif ($method === 'POST' && $this->isSagaApiRoute($path)) {
            if (!$this->hasValidSagaToken()) {
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

    private function compilePattern(string $path, callable|array $handler): array
    {
        $paramNames = [];
        $pattern = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];
            return '([^/]+)';
        }, preg_quote($path, '#'));

        return [
            'regex' => '#^' . $pattern . '$#',
            'params' => $paramNames,
            'handler' => $handler,
        ];
    }

    private function matchPattern(string $method, string $path): ?array
    {
        foreach ($this->patternRoutes[$method] ?? [] as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                array_shift($matches);
                $params = [];
                foreach ($route['params'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }
                return [
                    'handler' => $route['handler'],
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    private function isStockImportRoute(string $path): bool
    {
        return $path === '/api/stock/import';
    }

    private function isSagaApiRoute(string $path): bool
    {
        return str_starts_with($path, '/api/saga/');
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

    private function hasValidSagaToken(): bool
    {
        $token = Env::get('SAGA_EXPORT_TOKEN', '');
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
