<?php

namespace App\Support;

class Logger
{
    public static function logWarning(string $channel, array $context = []): void
    {
        try {
            $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
            $dir = $basePath . '/storage/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $path = $dir . '/app.log';
            $entry = '[' . date('Y-m-d H:i:s') . '] WARNING ' . $channel;
            if (!empty($context)) {
                $entry .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE);
            }
            $entry .= PHP_EOL;
            @file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $exception) {
            // fail silently
        }
    }
}
