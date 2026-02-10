<?php

namespace App\Models\Lexauto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class LexautoSetting extends Model
{
    protected $table = 'lexauto_settings';

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'lexauto_settings';
    private const CACHE_TTL = 300;

    public static function get(string $key, $default = null)
    {
        $all = self::getAll();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }

    public static function getDecimal(string $key, float $default = 0): float
    {
        $v = self::get($key);
        return $v === null ? $default : (float) $v;
    }
}
