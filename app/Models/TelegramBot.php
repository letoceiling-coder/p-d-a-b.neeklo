<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramBot extends Model
{
    protected $fillable = [
        'token',
        'webhook_url',
        'is_active',
        'welcome_message',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public const DEFAULT_WELCOME_MESSAGE = "👋 Привет!\n\nОтправьте команду /start для начала работы.";

    public function getWelcomeMessageText(): string
    {
        return $this->welcome_message ?: self::DEFAULT_WELCOME_MESSAGE;
    }

    public function getTokenForApi(): string
    {
        return $this->token;
    }

    public function botUsers(): HasMany
    {
        return $this->hasMany(BotUser::class);
    }
}
