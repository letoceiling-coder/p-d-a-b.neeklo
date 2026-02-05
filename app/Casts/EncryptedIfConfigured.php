<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Facades\Crypt;

/**
 * Шифрует значение при записи и расшифровывает при чтении, если включено в config (ТЗ п.13).
 * Если шифрование выключено или расшифровка не удалась — возвращает значение как есть.
 */
class EncryptedIfConfigured implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (!config('contract.encrypt_bot_user_pii', false)) {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        if (!config('contract.encrypt_bot_user_pii', false)) {
            return $value;
        }
        return Crypt::encryptString($value);
    }
}
