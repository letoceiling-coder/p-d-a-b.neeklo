<?php

namespace App\Console\Commands;

use App\Models\Lexauto\LexautoOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LexautoCleanReservationsCommand extends Command
{
    protected $signature = 'lexauto:clean-reservations';
    protected $description = 'Снять просроченные брони LEXAUTO и уведомить пользователей';

    public function handle(): int
    {
        $token = config('lexauto.bot_token');
        if (!$token) {
            return self::SUCCESS;
        }

        $expired = LexautoOrder::where('status', LexautoOrder::STATUS_RESERVED)
            ->where('reserved_until', '<', now())
            ->with('user')
            ->get();

        foreach ($expired as $order) {
            $order->update(['status' => LexautoOrder::STATUS_REJECTED]);
            $chatId = $order->user?->tg_id;
            if ($chatId) {
                try {
                    Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => "Время брони вышло. Оформите заявку заново: /start",
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Lexauto expired notify: ' . $e->getMessage());
                }
            }
        }

        if ($expired->isNotEmpty()) {
            $this->info('Снято броней: ' . $expired->count());
        }

        return self::SUCCESS;
    }
}
