<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BotController extends Controller
{
    /**
     * Получить единственного бота (бот может быть только один)
     */
    public function show(Request $request): JsonResponse
    {
        $bot = TelegramBot::first();

        if (!$bot) {
            return response()->json(['bot' => null], 200);
        }

        $data = $bot->toArray();
        $data['token'] = $bot->token;
        $data['token_masked'] = $this->maskToken($bot->token);
        $data['welcome_message'] = $bot->welcome_message;
        $data['default_welcome_message'] = TelegramBot::DEFAULT_WELCOME_MESSAGE;

        return response()->json(['bot' => $data]);
    }

    /**
     * Создать или обновить бота (один на приложение). При сохранении токена — автоматически создать webhook.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $webhookUrl = rtrim(config('app.url'), '/') . '/api/telegram/webhook';

        $bot = TelegramBot::first();

        if ($bot) {
            $bot->update([
                'token' => $request->token,
                'webhook_url' => $webhookUrl,
                'is_active' => true,
            ]);
            $this->registerWebhook($bot);
            AdminActionLog::log('bot.updated', 'telegram_bot', $bot->id);
            return response()->json(['bot' => $bot->fresh(), 'message' => 'Бот обновлён. Webhook зарегистрирован.']);
        }

        $bot = TelegramBot::create([
            'token' => $request->token,
            'webhook_url' => $webhookUrl,
            'is_active' => true,
        ]);

        $this->registerWebhook($bot);
        AdminActionLog::log('bot.created', 'telegram_bot', $bot->id);
        return response()->json(['bot' => $bot, 'message' => 'Бот создан. Webhook зарегистрирован.'], 201);
    }

    /**
     * Обновить настройки (приветственное сообщение)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $bot = TelegramBot::firstOrFail();

        $request->validate([
            'welcome_message' => 'nullable|string|max:4000',
        ]);

        $bot->update([
            'welcome_message' => $request->welcome_message ?: null,
        ]);
        AdminActionLog::log('bot.settings_updated', 'telegram_bot', $bot->id);
        return response()->json([
            'message' => 'Приветственное сообщение сохранено.',
            'welcome_message' => $bot->welcome_message,
        ]);
    }

    /**
     * Получить описание бота из Telegram API
     */
    public function getDescription(Request $request): JsonResponse
    {
        $bot = TelegramBot::firstOrFail();

        try {
            $descResponse = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$bot->token}/getMyDescription");
            $shortDescResponse = Http::timeout(10)
                ->get("https://api.telegram.org/bot{$bot->token}/getMyShortDescription");

            $description = $descResponse->successful() ? ($descResponse->json()['result']['description'] ?? '') : '';
            $shortDescription = $shortDescResponse->successful() ? ($shortDescResponse->json()['result']['short_description'] ?? '') : '';

            return response()->json([
                'description' => $description,
                'short_description' => $shortDescription,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting bot description: ' . $e->getMessage());
            return response()->json(['error' => 'Ошибка получения описания: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Обновить описание бота через Telegram API
     */
    public function updateDescription(Request $request): JsonResponse
    {
        $bot = TelegramBot::firstOrFail();

        $request->validate([
            'description' => 'nullable|string|max:512',
            'short_description' => 'nullable|string|max:120',
        ]);

        $errors = [];

        try {
            if ($request->has('description')) {
                $response = Http::timeout(10)->post("https://api.telegram.org/bot{$bot->token}/setMyDescription", [
                    'description' => $request->description ?: '',
                ]);
                if (!$response->successful() || !($response->json()['ok'] ?? false)) {
                    $errors[] = 'Ошибка описания: ' . ($response->json()['description'] ?? 'Unknown');
                }
            }
            if ($request->has('short_description')) {
                $response = Http::timeout(10)->post("https://api.telegram.org/bot{$bot->token}/setMyShortDescription", [
                    'short_description' => $request->short_description ?: '',
                ]);
                if (!$response->successful() || !($response->json()['ok'] ?? false)) {
                    $errors[] = 'Ошибка краткого описания: ' . ($response->json()['description'] ?? 'Unknown');
                }
            }

            if (!empty($errors)) {
                return response()->json(['message' => implode('. ', $errors)], 400);
            }

            return response()->json(['message' => 'Описание бота обновлено в Telegram.']);
        } catch (\Exception $e) {
            Log::error('Error updating bot description: ' . $e->getMessage());
            return response()->json(['error' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Тест webhook
     */
    public function testWebhook(Request $request): JsonResponse
    {
        $bot = TelegramBot::firstOrFail();

        try {
            $botInfoResponse = Http::timeout(10)->get("https://api.telegram.org/bot{$bot->token}/getMe");
            if (!$botInfoResponse->successful()) {
                return response()->json([
                    'message' => 'Неверный токен бота или бот не найден',
                    'error' => $botInfoResponse->json()['description'] ?? 'Unknown',
                ], 400);
            }

            $botUsername = $botInfoResponse->json()['result']['username'] ?? 'unknown';
            $getWebhook = Http::timeout(10)->get("https://api.telegram.org/bot{$bot->token}/getWebhookInfo");
            $webhookInfo = $getWebhook->successful() ? $getWebhook->json()['result'] : [];

            return response()->json([
                'message' => 'Webhook настроен. Для проверки найдите бота @' . $botUsername . ' в Telegram и отправьте /start.',
                'bot_username' => $botUsername,
                'webhook_url' => $webhookInfo['url'] ?? null,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhook test error: ' . $e->getMessage());
            return response()->json(['message' => 'Ошибка: ' . $e->getMessage()], 500);
        }
    }

    private function registerWebhook(TelegramBot $bot): void
    {
        try {
            $response = Http::post("https://api.telegram.org/bot{$bot->token}/setWebhook", [
                'url' => $bot->webhook_url,
            ]);
            if ($response->successful()) {
                Log::info("Webhook registered for bot: {$bot->webhook_url}");
            } else {
                Log::error("Failed to register webhook: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Error registering webhook: " . $e->getMessage());
        }
    }

    private function maskToken(string $token): string
    {
        if (strlen($token) < 12) {
            return '***';
        }
        return substr($token, 0, 6) . '...' . substr($token, -4);
    }
}
