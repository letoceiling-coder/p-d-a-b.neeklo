<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContractSetting extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'contract_settings';
    private const CACHE_TTL = 300;

    /**
     * Получить значение настройки (из кэша/БД с fallback на config).
     */
    public static function get(string $key)
    {
        $all = self::getAllFromStore();
        if (array_key_exists($key, $all)) {
            return self::castValue($key, $all[$key]);
        }
        return config('contract.' . $key);
    }

    /**
     * Установить значение настройки.
     */
    public static function set(string $key, $value): void
    {
        $value = $value === null ? null : (string) $value;
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Получить несколько настроек разом (для API/админки).
     */
    public static function getEditableDefaults(): array
    {
        $keys = [
            'telegram_summary_mode',
            'telegram_max_message_chars',
            'telegram_short_summary_chars',
            'max_photos_per_request',
            'analysis_retention_months',
            'default_ai_model_id',
            'ai_system_prompt',
        ];
        $fromStore = self::getAllFromStore();
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = array_key_exists($key, $fromStore)
                ? self::castValue($key, $fromStore[$key])
                : config('contract.' . $key);
        }
        return $out;
    }

    /**
     * Сохранить настройки из массива (только разрешённые ключи).
     */
    public static function setMany(array $settings): void
    {
        $allowed = [
            'telegram_summary_mode', 'telegram_max_message_chars', 'telegram_short_summary_chars',
            'max_photos_per_request', 'analysis_retention_months', 'default_ai_model_id',
            'ai_system_prompt',
        ];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $settings)) {
                self::set($key, $settings[$key]);
            }
        }
    }

    private static function getAllFromStore(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::pluck('value', 'key')->toArray();
        });
    }

    private static function castValue(string $key, $raw)
    {
        if ($raw === null) {
            return null;
        }
        $intKeys = [
            'telegram_max_message_chars', 'telegram_short_summary_chars',
            'max_photos_per_request', 'analysis_retention_months', 'default_ai_model_id',
        ];
        if (in_array($key, $intKeys, true)) {
            return (int) $raw;
        }
        return $raw;
    }
}
