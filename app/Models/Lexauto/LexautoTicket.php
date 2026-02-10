<?php

namespace App\Models\Lexauto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LexautoTicket extends Model
{
    protected $table = 'lexauto_tickets';

    public $timestamps = false;

    protected $fillable = ['number', 'user_id', 'order_id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(LexautoUser::class, 'user_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(LexautoOrder::class, 'order_id');
    }

    public static function nextNumber(): int
    {
        $max = self::max('number');
        return ($max ?? 0) + 1;
    }
}
