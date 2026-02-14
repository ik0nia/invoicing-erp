<?php

namespace App\Support;

class RateLimiter
{
    public static function hit(string $key, int $limit, int $windowSeconds): bool
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2);
        $dir = $basePath . '/storage/cache/rate_limits';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $file = $dir . '/rl_' . sha1($key) . '.json';
        $now = time();

        $data = [
            'start' => $now,
            'count' => 0,
        ];

        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return true;
        }

        $locked = flock($handle, LOCK_EX);
        if (!$locked) {
            fclose($handle);
            return true;
        }

        $contents = stream_get_contents($handle);
        if ($contents !== false && $contents !== '') {
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && isset($decoded['start'], $decoded['count'])) {
                $data['start'] = (int) $decoded['start'];
                $data['count'] = (int) $decoded['count'];
            }
        }

        if ($now - $data['start'] >= $windowSeconds) {
            $data['start'] = $now;
            $data['count'] = 0;
        }

        if ($data['count'] >= $limit) {
            flock($handle, LOCK_UN);
            fclose($handle);
            return false;
        }

        $data['count']++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($data));
        fflush($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }
}
