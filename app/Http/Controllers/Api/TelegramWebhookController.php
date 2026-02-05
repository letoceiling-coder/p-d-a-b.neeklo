<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BotUser;
use App\Models\ContractAnalysis;
use App\Models\ContractSetting;
use App\Models\TelegramBot;
use App\Services\Contract\ContractAnalysisException;
use App\Services\Contract\ContractAnalysisService;
use App\Services\Contract\ContractFileException;
use App\Services\Contract\ContractFileHandler;
use App\Services\Contract\DocumentTextException;
use App\Services\Contract\DocumentTextExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    private const MSG_AUTHORIZE = "Для работы с ботом необходимо авторизоваться.\n\nОтправьте команду /admin для запроса доступа к боту. После одобрения администратором вы сможете пользоваться ботом.";

    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        try {
            $bot = TelegramBot::where('is_active', true)->first();
            if (!$bot) {
                return response()->json(['ok' => true]);
            }

            if (isset($update['message'])) {
                $this->handleMessage($bot, $update['message']);
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Telegram webhook error: ' . $e->getMessage());
            return response()->json(['ok' => true]);
        }
    }

    private function handleMessage(TelegramBot $bot, array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $from = $message['from'] ?? [];

        if (!$chatId) {
            return;
        }

        $telegramUserId = $from['id'] ?? null;
        $username = $from['username'] ?? null;
        $firstName = $from['first_name'] ?? null;
        $lastName = $from['last_name'] ?? null;

        // Команда /admin — создаём или обновляем запрос доступа
        if ($text === '/admin') {
            $this->handleAdminCommand($bot, $chatId, $telegramUserId, $username, $firstName, $lastName);
            return;
        }

        // /start — приветствие (доступно всем)
        if ($text === '/start') {
            $welcome = $bot->getWelcomeMessageText();
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $welcome,
                'parse_mode' => 'HTML',
            ]);
            return;
        }

        // Документ или фото — только для одобренных администраторов (ТЗ: загрузка договоров)
        $hasDocument = !empty($message['document']);
        $hasPhoto = !empty($message['photo']) && is_array($message['photo']);
        if ($hasDocument || $hasPhoto) {
            $botUser = BotUser::where('telegram_bot_id', $bot->id)
                ->where('telegram_user_id', $telegramUserId)
                ->first();
            if (!$botUser || !$botUser->isApproved()) {
                Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => self::MSG_AUTHORIZE,
                ]);
                return;
            }
            $this->handleDocumentOrPhoto($bot, $chatId, $botUser, $message);
            return;
        }

        // Любое другое сообщение — только для одобренных администраторов
        $botUser = BotUser::where('telegram_bot_id', $bot->id)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        if (!$botUser || !$botUser->isApproved()) {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => self::MSG_AUTHORIZE,
            ]);
            return;
        }

        // Текстовые команды для одобренных администраторов (при необходимости)
    }

    /**
     * Обработка загруженного документа или фото договора (ТЗ п.4–7).
     * При ошибке формата/чтения — единое сообщение из config('contract.error_upload_message').
     */
    private function handleDocumentOrPhoto(TelegramBot $bot, int $chatId, BotUser $botUser, array $message): void
    {
        $errorMessage = config('contract.error_upload_message', 'Пожалуйста, загрузите договор или выбранные страницы договора.');
        $paths = [];

        try {
            $this->sendStatusMessage($bot->token, $chatId, '📄 Документ получен. Загружаю и извлекаю текст...');

            $handler = new ContractFileHandler();
            $paths = $handler->downloadAndValidate($bot->token, $message);

            $extractor = new DocumentTextExtractor();
            $fullText = $extractor->extractFromPaths($paths);

            if (trim($fullText) === '') {
                Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $errorMessage,
                ]);
                return;
            }

            $this->sendStatusMessage($bot->token, $chatId, '🤖 Анализирую текст. Для больших документов это может занять 1–2 минуты, подождите...');

            $analysisService = new ContractAnalysisService(app(\App\Services\Ai\AiService::class));
            $result = $analysisService->analyze($fullText);
            $summary = $result['summary_text'];
            $summaryJson = $result['summary_json'] ?? null;

            ContractAnalysis::create([
                'telegram_bot_id' => $bot->id,
                'bot_user_id' => $botUser->id,
                'summary_text' => $summary,
                'summary_json' => $summaryJson,
            ]);

            $this->sendSummaryToTelegram($bot->token, $chatId, $summary);
        } catch (ContractFileException|DocumentTextException|ContractAnalysisException $e) {
            Log::info('Contract file/text error: ' . $e->getMessage());
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);
        } catch (\Throwable $e) {
            Log::error('Contract document/photo handling error: ' . $e->getMessage());
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $errorMessage,
            ]);
        } finally {
            if (!empty($paths)) {
                try {
                    ContractFileHandler::cleanup($paths);
                } catch (\Throwable $e) {
                    Log::warning('Contract temp cleanup: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Отправить пользователю служебное сообщение о ходе обработки.
     */
    private function sendStatusMessage(string $botToken, int $chatId, string $text): void
    {
        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
            ]);
        } catch (\Throwable $e) {
            Log::debug('Telegram status message failed: ' . $e->getMessage());
        }
    }

    /**
     * Отправить выжимку в Telegram в формате согласно config (ТЗ п.8: текст, краткая, расширенная).
     */
    private function sendSummaryToTelegram(string $botToken, int $chatId, string $summary): void
    {
        $mode = ContractSetting::get('telegram_summary_mode') ?? config('contract.telegram_summary_mode', 'full');
        $maxChars = (int) (ContractSetting::get('telegram_max_message_chars') ?? config('contract.telegram_max_message_chars', 4090));
        $shortChars = (int) (ContractSetting::get('telegram_short_summary_chars') ?? config('contract.telegram_short_summary_chars', 600));

        $full = mb_strlen($summary) > $maxChars ? mb_substr($summary, 0, $maxChars) . '…' : $summary;
        $short = mb_strlen($summary) > $shortChars ? mb_substr($summary, 0, $shortChars) . '…' : $summary;

        $sendMsg = function (string $body, string $prefix = "📋 Выжимка по договору:\n\n") use ($botToken, $chatId) {
            Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $prefix . $body,
            ]);
        };

        if ($mode === 'short') {
            $sendMsg($short);
            return;
        }
        if ($mode === 'both') {
            $sendMsg($short, "📋 Краткая выжимка:\n\n");
            $sendMsg($full, "📋 Полная выжимка:\n\n");
            return;
        }
        $sendMsg($full);
    }

    private function handleAdminCommand(
        TelegramBot $bot,
        int $chatId,
        ?int $telegramUserId,
        ?string $username,
        ?string $firstName,
        ?string $lastName
    ): void {
        if (!$telegramUserId) {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "Не удалось определить ваш аккаунт. Попробуйте снова.",
            ]);
            return;
        }

        $botUser = BotUser::firstOrCreate(
            [
                'telegram_bot_id' => $bot->id,
                'telegram_user_id' => $telegramUserId,
            ],
            [
                'username' => $username,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'status' => BotUser::STATUS_PENDING,
                'role' => BotUser::ROLE_ADMIN,
                'requested_at' => now(),
            ]
        );

        if ($botUser->status === BotUser::STATUS_APPROVED) {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "✅ Вы уже являетесь администратором бота.",
            ]);
            return;
        }

        if ($botUser->status === BotUser::STATUS_PENDING) {
            Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "📋 Ваш запрос на доступ уже отправлен и ожидает рассмотрения.\n\nО результатах вам придёт уведомление.",
            ]);
            return;
        }

        // Был отклонён — разрешаем подать запрос снова
        $botUser->update([
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'status' => BotUser::STATUS_PENDING,
            'requested_at' => now(),
            'decided_at' => null,
        ]);

        Http::post("https://api.telegram.org/bot{$bot->token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => "📩 Запрос на доступ отправлен.\n\nОжидайте рассмотрения. О результате вам придёт уведомление.",
        ]);
    }
}
