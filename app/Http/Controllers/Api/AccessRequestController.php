<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\BotUser;
use App\Models\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccessRequestController extends Controller
{
    /**
     * Список запросов доступа: ожидающие и при необходимости история
     */
    public function index(Request $request): JsonResponse
    {
        $query = BotUser::with('telegramBot')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('requested_at', 'desc');

        $filter = $request->get('status');
        if ($filter === 'pending') {
            $query->pending();
        } elseif (in_array($filter, ['approved', 'rejected'], true)) {
            $query->where('status', $filter);
        }

        $requests = $query->get()->map(fn (BotUser $u) => [
            'id' => $u->id,
            'telegram_user_id' => $u->telegram_user_id,
            'username' => $u->username,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'display_name' => $u->displayName(),
            'status' => $u->status,
            'role' => $u->role,
            'requested_at' => $u->requested_at?->toIso8601String(),
            'decided_at' => $u->decided_at?->toIso8601String(),
        ]);

        return response()->json(['requests' => $requests]);
    }

    /**
     * Одобрить запрос: выдать роль администратора и отправить уведомление
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $botUser = BotUser::findOrFail($id);
        if ($botUser->status !== BotUser::STATUS_PENDING) {
            return response()->json(['message' => 'Запрос уже обработан'], 422);
        }

        $botUser->update([
            'status' => BotUser::STATUS_APPROVED,
            'role' => BotUser::ROLE_ADMIN,
            'decided_at' => now(),
        ]);

        AdminActionLog::log('access_request.approved', 'bot_user', $botUser->id, ['bot_user_id' => $botUser->id]);

        $this->sendTelegramNotification(
            $botUser->telegramBot,
            $botUser->telegram_user_id,
            "✅ Ваш запрос на доступ одобрен.\n\nВам выдана роль администратора. Теперь вы можете пользоваться ботом."
        );

        return response()->json([
            'message' => 'Запрос одобрен',
            'request' => $this->requestToArray($botUser->fresh()),
        ]);
    }

    /**
     * Отклонить запрос и отправить уведомление
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $botUser = BotUser::findOrFail($id);
        if ($botUser->status !== BotUser::STATUS_PENDING) {
            return response()->json(['message' => 'Запрос уже обработан'], 422);
        }

        $botUser->update([
            'status' => BotUser::STATUS_REJECTED,
            'decided_at' => now(),
        ]);

        AdminActionLog::log('access_request.rejected', 'bot_user', $botUser->id, ['bot_user_id' => $botUser->id]);

        $this->sendTelegramNotification(
            $botUser->telegramBot,
            $botUser->telegram_user_id,
            "❌ Ваш запрос на доступ отклонён.\n\nОбратитесь к администратору, если считаете это ошибкой."
        );

        return response()->json([
            'message' => 'Запрос отклонён',
            'request' => $this->requestToArray($botUser->fresh()),
        ]);
    }

    /**
     * Отменить права администратора у одобренного пользователя
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $botUser = BotUser::findOrFail($id);
        if ($botUser->status !== BotUser::STATUS_APPROVED) {
            return response()->json(['message' => 'Можно отменить только у одобренных пользователей'], 422);
        }

        $botUser->update([
            'status' => BotUser::STATUS_REJECTED,
            'decided_at' => now(),
        ]);

        AdminActionLog::log('access_request.revoked', 'bot_user', $botUser->id, ['bot_user_id' => $botUser->id]);

        $this->sendTelegramNotification(
            $botUser->telegramBot,
            $botUser->telegram_user_id,
            "❌ Ваши права администратора отменены.\n\nДоступ к боту закрыт. Для повторного запроса отправьте /admin в боте."
        );

        return response()->json([
            'message' => 'Права администратора отменены',
            'request' => $this->requestToArray($botUser->fresh()),
        ]);
    }

    private function sendTelegramNotification(TelegramBot $bot, int $chatId, string $text): void
    {
        try {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Exception $e) {
            Log::error('Access request notification failed: ' . $e->getMessage());
        }
    }

    private function requestToArray(BotUser $u): array
    {
        return [
            'id' => $u->id,
            'telegram_user_id' => $u->telegram_user_id,
            'username' => $u->username,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'display_name' => $u->displayName(),
            'status' => $u->status,
            'role' => $u->role,
            'requested_at' => $u->requested_at?->toIso8601String(),
            'decided_at' => $u->decided_at?->toIso8601String(),
        ];
    }
}
