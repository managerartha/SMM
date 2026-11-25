<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan yang bisa diubah dari layar Admin. Nilai bawaan ada di config/smm.php (kunci 'defaults').
 */
class Settings
{
    private const CACHE_KEY = 'smm.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = self::all();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = [];
            if (Schema::hasTable('settings')) {
                $stored = Setting::query()->pluck('value', 'key')->all();
            }

            return array_merge(config('smm.defaults', []), $stored);
        });
    }

    public static function set(string $key, mixed $value, ?int $userId = null): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $userId]);
        self::flush();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
