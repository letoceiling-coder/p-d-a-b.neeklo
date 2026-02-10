<?php

namespace App\Models\Lexauto;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LexautoUser extends Model
{
    protected $table = 'lexauto_users';

    protected $fillable = ['tg_id', 'username', 'fio', 'phone'];

    public function orders(): HasMany
    {
        return $this->hasMany(LexautoOrder::class, 'user_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(LexautoTicket::class, 'user_id');
    }

    public function ticketNumbers(): array
    {
        return $this->tickets()->orderBy('number')->pluck('number')->toArray();
    }
}
