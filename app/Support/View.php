<?php

namespace App\Support;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $viewPath = self::path($view);

        if (!file_exists($viewPath)) {
            return 'View lipsa: ' . htmlspecialchars($view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        include $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            return $content;
        }

        $layoutPath = self::path($layout);

        if (!file_exists($layoutPath)) {
            return $content;
        }

        ob_start();
        include $layoutPath;

        return ob_get_clean();
    }

    private static function path(string $view): string
    {
        $view = trim($view, '/');

        return BASE_PATH . '/resources/views/' . $view . '.php';
    }
}
