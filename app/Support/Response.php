<?php

namespace App\Support;

class Response
{
    public static function view(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        echo View::render($view, $data, $layout);
        exit;
    }

    public static function redirect(string $url): void
    {
        if (str_starts_with($url, '/')) {
            $url = Url::to($url);
        }

        header('Location: ' . $url);
        exit;
    }

    public static function abort(int $code, ?string $message = null): void
    {
        http_response_code($code);

        $view = 'errors/' . $code;
        $fallback = $message ?? 'A aparut o eroare.';

        if (file_exists(BASE_PATH . '/resources/views/' . $view . '.php')) {
            echo View::render($view, ['message' => $fallback], null);
        } else {
            echo htmlspecialchars($fallback);
        }

        exit;
    }
}
