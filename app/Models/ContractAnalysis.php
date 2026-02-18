<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractAnalysis extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';

    protected $fillable = [
        'user_id',
        'telegram_bot_id',
        'bot_user_id',
        'title',
        'status',
        'processing_step',
        'summary_text',
        'summary_json',
        'counterparty_check',
        'file_info',
        'pdf_path',
        'temp_upload_path',
    ];

    protected $casts = [
        'summary_json' => 'array',
        'counterparty_check' => 'array',
        'file_info' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function telegramBot(): BelongsTo
    {
        return $this->belongsTo(TelegramBot::class);
    }

    public function botUser(): BelongsTo
    {
        return $this->belongsTo(BotUser::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AnalysisMessage::class, 'contract_analysis_id');
    }

    public function isOwnedBy(?int $userId): bool
    {
        return $userId && $this->user_id === $userId;
    }
}
