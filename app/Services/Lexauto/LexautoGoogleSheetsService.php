<?php

namespace App\Services\Lexauto;

use App\Models\Lexauto\LexautoOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LexautoGoogleSheetsService
{
    /**
     * Отправить данные заказа в Google Sheets после одобрения.
     * Колонки: ID заказа | ФИО | Телефон | Сумма | Номера | Дата.
     * URL может быть webhook формы или API — здесь заглушка под GET/POST с query/body.
     */
    public static function sendOrder(LexautoOrder $order): bool
    {
        $url = \App\Models\Lexauto\LexautoSetting::get('google_sheet_url') ?: config('lexauto.google_sheet_url');
        if (!$url) {
            Log::info('Lexauto: google_sheet_url не задан, пропуск отправки');
            return false;
        }
        $user = $order->user;
        $numbers = $order->tickets()->orderBy('number')->pluck('number')->implode(', ');
        $payload = [
            'order_id' => $order->id,
            'fio' => $user->fio,
            'phone' => $user->phone,
            'amount' => $order->amount,
            'numbers' => $numbers,
            'date' => $order->updated_at->format('Y-m-d H:i'),
        ];
        try {
            $response = Http::timeout(10)->asForm()->post($url, $payload);
            if (!$response->successful()) {
                Log::warning('Lexauto Google Sheets: ' . $response->body());
                return false;
            }
            return true;
        } catch (\Throwable $e) {
            Log::error('Lexauto Google Sheets: ' . $e->getMessage());
            return false;
        }
    }
}
