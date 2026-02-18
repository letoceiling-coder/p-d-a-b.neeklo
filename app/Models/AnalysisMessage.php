<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisMessage extends Model
{
    protected $fillable = [
        'contract_analysis_id',
        'role',
        'content',
    ];

    public function contractAnalysis(): BelongsTo
    {
        return $this->belongsTo(ContractAnalysis::class);
    }
}
