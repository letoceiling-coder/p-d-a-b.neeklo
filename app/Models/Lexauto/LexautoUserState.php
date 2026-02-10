<?php

namespace App\Models\Lexauto;

use Illuminate\Database\Eloquent\Model;

class LexautoUserState extends Model
{
    protected $table = 'lexauto_user_states';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = ['tg_id', 'state', 'payload'];

    protected $casts = [
        'payload' => 'array',
        'updated_at' => 'datetime',
    ];

    public static function getState(int $tgId): ?string
    {
        $row = self::find($tgId);
        return $row?->state;
    }

    public static function getPayload(int $tgId): array
    {
        $row = self::find($tgId);
        return $row?->payload ?? [];
    }

    public static function setState(int $tgId, string $state, array $payload = []): void
    {
        self::updateOrCreate(
            ['tg_id' => $tgId],
            ['state' => $state, 'payload' => $payload, 'updated_at' => now()]
        );
    }

    public static function clear(int $tgId): void
    {
        self::where('tg_id', $tgId)->delete();
    }
}
