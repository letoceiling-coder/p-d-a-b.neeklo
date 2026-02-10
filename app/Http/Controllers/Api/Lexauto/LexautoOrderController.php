<?php

namespace App\Http\Controllers\Api\Lexauto;

use App\Http\Controllers\Controller;
use App\Models\Lexauto\LexautoOrder;
use App\Models\Lexauto\LexautoSetting;
use App\Models\Lexauto\LexautoTicket;
use App\Services\Lexauto\LexautoGoogleSheetsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LexautoOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->get('status');
        $query = LexautoOrder::with('user')->orderByDesc('created_at');
        if (in_array($status, ['reserved', 'review', 'sold', 'rejected'], true)) {
            $query->where('status', $status);
        }
        $orders = $query->get()->map(fn (LexautoOrder $o) => $this->orderToArray($o));
        return response()->json(['orders' => $orders]);
    }

    public function show(int $id): JsonResponse
    {
        $order = LexautoOrder::with('user', 'tickets')->findOrFail($id);
        return response()->json(['order' => $this->orderToArray($order)]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $order = LexautoOrder::with('user')->findOrFail($id);
        if ($order->status !== LexautoOrder::STATUS_REVIEW) {
            return response()->json(['message' => 'Можно одобрить только заявку на проверке'], 422);
        }

        $quantity = (int) ($request->input('quantity') ?? $order->quantity);
        if ($quantity < 1) {
            $quantity = $order->quantity;
        }

        $startNumber = LexautoTicket::nextNumber();
        for ($i = 0; $i < $quantity; $i++) {
            LexautoTicket::create([
                'number' => $startNumber + $i,
                'user_id' => $order->user_id,
                'order_id' => $order->id,
            ]);
        }

        $order->update(['status' => LexautoOrder::STATUS_SOLD, 'quantity' => $quantity]);

        LexautoGoogleSheetsService::sendOrder($order);

        $numbers = $order->tickets()->orderBy('number')->pluck('number')->implode(', ');
        $token = config('lexauto.bot_token');
        $chatId = $order->user->tg_id;
        if ($token && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "Платёж подтверждён! 🎉\nВаши номерки: {$numbers}",
                ]);
            } catch (\Throwable $e) {
                Log::warning('Lexauto notify approve: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Заявка одобрена',
            'order' => $this->orderToArray($order->fresh(['user', 'tickets'])),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $order = LexautoOrder::with('user')->findOrFail($id);
        if (!in_array($order->status, [LexautoOrder::STATUS_RESERVED, LexautoOrder::STATUS_REVIEW], true)) {
            return response()->json(['message' => 'Заявка уже обработана'], 422);
        }

        $order->update(['status' => LexautoOrder::STATUS_REJECTED]);

        $token = config('lexauto.bot_token');
        $chatId = $order->user->tg_id;
        if ($token && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => "Чек не принят. Проверьте оплату и оформите заявку заново.",
                ]);
            } catch (\Throwable $e) {
                Log::warning('Lexauto notify reject: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => 'Заявка отклонена',
            'order' => $this->orderToArray($order->fresh()),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $order = LexautoOrder::findOrFail($id);
        if ($order->status !== LexautoOrder::STATUS_REVIEW) {
            return response()->json(['message' => 'Редактировать можно только заявку на проверке'], 422);
        }
        $request->validate([
            'quantity' => 'nullable|integer|min:1',
            'amount' => 'nullable|numeric|min:0',
        ]);
        $data = [];
        if ($request->has('quantity')) {
            $data['quantity'] = $request->integer('quantity');
        }
        if ($request->has('amount')) {
            $data['amount'] = $request->input('amount');
        }
        $order->update($data);
        return response()->json(['order' => $this->orderToArray($order->fresh(['user', 'tickets']))]);
    }

    private function orderToArray(LexautoOrder $o): array
    {
        $user = $o->user;
        return [
            'id' => $o->id,
            'user_id' => $o->user_id,
            'fio' => $user?->fio,
            'phone' => $user?->phone,
            'tg_id' => $user?->tg_id,
            'status' => $o->status,
            'reserved_until' => $o->reserved_until?->toIso8601String(),
            'quantity' => $o->quantity,
            'amount' => (float) $o->amount,
            'check_file_id' => $o->check_file_id,
            'created_at' => $o->created_at->toIso8601String(),
            'updated_at' => $o->updated_at->toIso8601String(),
            'ticket_numbers' => $o->relationLoaded('tickets') ? $o->tickets->pluck('number')->toArray() : [],
        ];
    }
}
