<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    private string $cachePrefix = 'settings.';

    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever($this->cacheKey($key), function () use ($key, $default) {
            $setting = Setting::query()->where('key', $key)->first();

            return $setting?->value ?? $default;
        });
    }

    public function set(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        Cache::forever($this->cacheKey($key), $value);
    }

    private function cacheKey(string $key): string
    {
        return $this->cachePrefix . $key;
    }
}
