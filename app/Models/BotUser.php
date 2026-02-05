<?php

namespace App\Models;

use App\Casts\EncryptedIfConfigured;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotUser extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ROLE_ADMIN = 'admin';
    public const ROLE_USER = 'user';

    protected $fillable = [
        'telegram_bot_id',
        'telegram_user_id',
        'username',
        'first_name',
        'last_name',
        'role',
        'status',
        'requested_at',
        'decided_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'first_name' => EncryptedIfConfigured::class,
        'last_name' => EncryptedIfConfigured::class,
        'username' => EncryptedIfConfigured::class,
    ];

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function displayName(): string
    {
        $parts = array_filter([$this->first_name, $this->last_name]);
        if (!empty($parts)) {
            return trim(implode(' ', $parts));
        }
        return $this->username ? '@' . $this->username : (string) $this->telegram_user_id;
    }
}
