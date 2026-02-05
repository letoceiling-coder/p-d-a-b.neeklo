<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAnalysis extends Model
{
    protected $fillable = [
        'telegram_bot_id',
        'bot_user_id',
        'summary_text',
        'summary_json',
    ];

    protected $casts = [
        'summary_json' => 'array',
    ];

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }
}
