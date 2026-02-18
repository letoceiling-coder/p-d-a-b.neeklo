<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InviteCode extends Model
{
    protected $fillable = [
        'code',
        'created_by',
        'used_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isValid(): bool
    {
        if ($this->used_by !== null) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    public static function generate(?int $createdBy = null, ?\DateTimeInterface $expiresAt = null): self
    {
        return self::create([
            'code' => strtoupper(Str::random(8)),
            'created_by' => $createdBy,
            'expires_at' => $expiresAt,
        ]);
    }
}
