<?php

namespace App\Services\Lexauto;

use App\Models\Lexauto\LexautoOrder;
use App\Models\Lexauto\LexautoSetting;
use App\Models\Lexauto\LexautoTicket;
use Illuminate\Support\Facades\DB;

class LexautoSeatsService
{
    /**
     * Свободных мест = total_seats - (проданные билеты) - (забронировано в reserved/review).
     */
    public static function freeSeats(): int
    {
        $total = LexautoSetting::getInt('total_seats', 0);
        $sold = LexautoTicket::count();
        $reserved = LexautoOrder::whereIn('status', [LexautoOrder::STATUS_RESERVED, LexautoOrder::STATUS_REVIEW])
            ->where(function ($q) {
                $q->whereNull('reserved_until')->orWhere('reserved_until', '>', now());
            })
            ->sum('quantity');
        return max(0, $total - $sold - (int) $reserved);
    }

    /**
     * Забронировать места. В транзакции с блокировкой. Возвращает order или null при нехватке мест.
     */
    public static function reserve(int $userId, int $quantity): ?LexautoOrder
    {
        $price = LexautoSetting::getDecimal('price', 0);
        $minutes = LexautoSetting::getInt('reservation_minutes', 30);
        $reservedUntil = now()->addMinutes($minutes);

        return DB::transaction(function () use ($userId, $quantity, $price, $reservedUntil) {
            $total = LexautoSetting::getInt('total_seats', 0);
            $sold = LexautoTicket::lockForUpdate()->count();
            $reservedQty = LexautoOrder::whereIn('status', [LexautoOrder::STATUS_RESERVED, LexautoOrder::STATUS_REVIEW])
                ->where(function ($q) {
                    $q->whereNull('reserved_until')->orWhere('reserved_until', '>', now());
                })
                ->lockForUpdate()
                ->sum('quantity');
            $free = $total - $sold - (int) $reservedQty;
            if ($quantity > $free) {
                return null;
            }
            $amount = $quantity * $price;
            return LexautoOrder::create([
                'user_id' => $userId,
                'status' => LexautoOrder::STATUS_RESERVED,
                'reserved_until' => $reservedUntil,
                'quantity' => $quantity,
                'amount' => $amount,
            ]);
        });
    }
}
