<?php

namespace App\Domain\Contracts\Services;

class TemplateRenderer
{
    public function render(string $html, array $vars): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $matches) use ($vars): string {
            $key = $matches[1] ?? '';
            if ($key === '') {
                return '';
            }
            $value = $vars[$key] ?? '';
            return is_scalar($value) ? (string) $value : '';
        }, $html);
    }
}
