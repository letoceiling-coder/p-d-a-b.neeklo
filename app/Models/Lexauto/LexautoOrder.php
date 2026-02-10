<?php

namespace App\Models\Lexauto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LexautoOrder extends Model
{
    protected $table = 'lexauto_orders';

    public const STATUS_RESERVED = 'reserved';
    public const STATUS_REVIEW = 'review';
    public const STATUS_SOLD = 'sold';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id', 'status', 'reserved_until', 'quantity', 'amount', 'check_file_id',
    ];

    protected $casts = [
        'reserved_until' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LexautoUser::class, 'user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(LexautoTicket::class, 'order_id');
    }

    public function isReserved(): bool
    {
        return $this->status === self::STATUS_RESERVED;
    }

    public function isReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    public function isExpired(): bool
    {
        return $this->reserved_until && $this->reserved_until->isPast();
    }
}
