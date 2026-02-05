<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProviderKey extends Model
{
    protected $fillable = ['provider', 'api_key'];

    protected $hidden = ['api_key'];

    protected $appends = ['masked_key'];

    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_OPENAI = 'openai';

    public static function getApiKey(string $provider): ?string
    {
        $row = self::where('provider', $provider)->first();
        return $row ? $row->getRawOriginal('api_key') : null;
    }

    public static function setKey(string $provider, ?string $apiKey): void
    {
        self::updateOrCreate(
            ['provider' => $provider],
            ['api_key' => $apiKey]
        );
    }

    public function getMaskedKeyAttribute(): string
    {
        $key = $this->attributes['api_key'] ?? null;
        if (!$key || strlen($key) < 8) {
            return '••••••••';
        }
        return substr($key, 0, 6) . '…' . substr($key, -4);
    }
}
