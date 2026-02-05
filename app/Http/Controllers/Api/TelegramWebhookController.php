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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    private const MSG_AUTHORIZE = "Для работы с ботом необходимо авторизоваться.\n\nОтправьте команду /admin для запроса доступа к боту. После одобрения администратором вы сможете пользоваться ботом.";

    private const BTN_UPLOAD = '📄 Загрузка договоров';
    private const BTN_HISTORY = '📂 История анализов';
    private const BTN_COMPARE = '📊 Сравнение договоров';
    private const BTN_INFO = 'ℹ️ Информация';
    private const BTN_SUPPORT = '💬 Поддержка';
    private const BTN_HOME = '🏠 Главная';
    private const BTN_BACK = '◀️ Назад';
    private const BTN_CANCEL = '❌ Отмена';

    private const CACHE_PROCESSING_PREFIX = 'bot_processing_';
    private const CACHE_PREV_SCREEN_PREFIX = 'bot_prev_screen_';
    private const CACHE_PROCESSING_TTL = 300;

    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        try {
            if (isset($update['update_id'])) {
                $dedupeKey = 'telegram_update_' . $update['update_id'];
                if (Cache::has($dedupeKey)) {
                    return response()->json(['ok' => true]);
                }
                Cache::put($dedupeKey, true, 600);
            }

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

        $botUser = BotUser::where('telegram_bot_id', $bot->id)
            ->where('telegram_user_id', $telegramUserId)
            ->first();

        // Команда /admin — создаём или обновляем запрос доступа
        if ($text === '/admin') {
            $this->handleAdminCommand($bot, $chatId, $telegramUserId, $username, $firstName, $lastName);
            return;
        }

        // /start — главный экран или доступ ограничен (TZ 4.1, 4.2)
        if ($text === '/start') {
            $this->clearPrevScreen($chatId);
            if ($botUser && $botUser->isApproved()) {
                $this->showIdleScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        // OTP: пользователь ввёл код доступа (TZ 2.2A)
        $otpCode = ContractSetting::get('bot_otp_code');
        if ($otpCode && $text === (string) $otpCode) {
            if (!$botUser || !$botUser->isApproved()) {
                $botUser = BotUser::firstOrCreate(
                    [
                        'telegram_bot_id' => $bot->id,
                        'telegram_user_id' => $telegramUserId,
                    ],
                    [
                        'username' => $username,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'status' => BotUser::STATUS_APPROVED,
                        'role' => BotUser::ROLE_USER,
                        'requested_at' => now(),
                        'decided_at' => now(),
                    ]
                );
                if ($botUser->status !== BotUser::STATUS_APPROVED) {
                    $botUser->update([
                        'username' => $username,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'status' => BotUser::STATUS_APPROVED,
                        'role' => BotUser::ROLE_USER,
                        'decided_at' => now(),
                    ]);
                }
                $this->showIdleScreen($bot->token, $chatId);
                return;
            }
        }

        // PROCESSING: при любом нажатии меню во время обработки — BUSY (TZ 4.5)
        if ($this->isProcessing($chatId)) {
            $this->showBusyScreen($bot->token, $chatId);
            return;
        }

        // Кнопки меню (TZ 7.2)
        $allowPublic = (bool) (ContractSetting::get('allow_public_info') ?? config('contract.allow_public_info', true));
        $isApproved = $botUser && $botUser->isApproved();

        if ($text === self::BTN_HOME) {
            $this->clearPrevScreen($chatId);
            if ($isApproved) {
                $this->showIdleScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        if ($text === self::BTN_BACK) {
            $prev = $this->getPrevScreen($chatId);
            $this->clearPrevScreen($chatId);
            if ($isApproved) {
                $this->showIdleScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        if ($text === self::BTN_CANCEL) {
            $this->clearPrevScreen($chatId);
            if ($isApproved) {
                $this->showIdleScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        if ($text === self::BTN_INFO) {
            if ($isApproved || $allowPublic) {
                $this->showInfoScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        if ($text === self::BTN_SUPPORT) {
            if ($isApproved || $allowPublic) {
                $this->showSupportScreen($bot->token, $chatId);
            } else {
                $this->showUnauthorizedScreen($bot->token, $chatId);
            }
            return;
        }

        if ($text === self::BTN_COMPARE) {
            $this->showCompareStubScreen($bot->token, $chatId);
            return;
        }

        if ($text === self::BTN_UPLOAD) {
            if (!$isApproved) {
                $this->showUnauthorizedScreen($bot->token, $chatId);
                return;
            }
            $this->showUploadScreen($bot->token, $chatId);
            return;
        }

        if ($text === self::BTN_HISTORY) {
            if (!$isApproved) {
                $this->showUnauthorizedScreen($bot->token, $chatId);
                return;
            }
            $this->showHistoryScreen($bot->token, $chatId, $botUser->id);
            return;
        }

        // Документ или фото — только для одобренных (TZ 4.3)
        $hasDocument = !empty($message['document']);
        $hasPhoto = !empty($message['photo']) && is_array($message['photo']);
        if ($hasDocument || $hasPhoto) {
            if (!$isApproved) {
                $this->showUnauthorizedScreen($bot->token, $chatId);
                return;
            }
            $this->handleDocumentOrPhoto($bot, $chatId, $botUser, $message);
            return;
        }

        // Любое другое сообщение — если не авторизован
        if (!$isApproved) {
            $this->showUnauthorizedScreen($bot->token, $chatId);
            return;
        }

        // Неизвестная команда — показать главный экран
        $this->showIdleScreen($bot->token, $chatId);
    }

    private function getReplyKeyboardMarkup(): array
    {
        return [
            'keyboard' => [
                [self::BTN_UPLOAD, self::BTN_HISTORY],
                [self::BTN_COMPARE, self::BTN_INFO],
                [self::BTN_SUPPORT, self::BTN_HOME],
                [self::BTN_BACK, self::BTN_CANCEL],
            ],
            'resize_keyboard' => true,
        ];
    }

    private function getPrevScreen(int $chatId): ?string
    {
        return Cache::get(self::CACHE_PREV_SCREEN_PREFIX . $chatId);
    }

    private function setPrevScreen(int $chatId, string $screen): void
    {
        Cache::put(self::CACHE_PREV_SCREEN_PREFIX . $chatId, $screen, 600);
    }

    private function clearPrevScreen(int $chatId): void
    {
        Cache::forget(self::CACHE_PREV_SCREEN_PREFIX . $chatId);
    }

    private function sendMessageWithMenu(string $botToken, int $chatId, string $text, ?string $parseMode = null): void
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => json_encode($this->getReplyKeyboardMarkup()),
        ];
        if ($parseMode) {
            $payload['parse_mode'] = $parseMode;
        }
        Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", $payload);
    }

    private function isProcessing(int $chatId): bool
    {
        return Cache::has(self::CACHE_PROCESSING_PREFIX . $chatId);
    }

    private function setProcessing(int $chatId): void
    {
        Cache::put(self::CACHE_PROCESSING_PREFIX . $chatId, true, self::CACHE_PROCESSING_TTL);
    }

    private function clearProcessing(int $chatId): void
    {
        Cache::forget(self::CACHE_PROCESSING_PREFIX . $chatId);
    }

    private function getScreenText(string $key): string
    {
        return (string) (ContractSetting::get($key) ?? config('contract.' . $key, ''));
    }

    private function showIdleScreen(string $botToken, int $chatId): void
    {
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('welcome_text'));
    }

    private function showUnauthorizedScreen(string $botToken, int $chatId): void
    {
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('unauthorized_text'));
    }

    private function showUploadScreen(string $botToken, int $chatId): void
    {
        $this->setPrevScreen($chatId, 'idle');
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('upload_text'));
    }

    private function showBusyScreen(string $botToken, int $chatId): void
    {
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('busy_text'));
    }

    private function showInfoScreen(string $botToken, int $chatId): void
    {
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('info_text'));
    }

    private function showSupportScreen(string $botToken, int $chatId): void
    {
        $text = str_replace(
            ['{support_name}', '{support_tg}', '{support_email}', '{support_hours}'],
            [
                $this->getScreenText('support_name') ?: '—',
                $this->getScreenText('support_tg') ?: '—',
                $this->getScreenText('support_email') ?: '—',
                $this->getScreenText('support_hours') ?: '—',
            ],
            $this->getScreenText('support_text')
        );
        $this->sendMessageWithMenu($botToken, $chatId, $text);
    }

    private function showCompareStubScreen(string $botToken, int $chatId): void
    {
        $this->sendMessageWithMenu($botToken, $chatId, $this->getScreenText('compare_stub_text'));
    }

    private function showHistoryScreen(string $botToken, int $chatId, int $botUserId): void
    {
        $limit = (int) (ContractSetting::get('history_limit') ?? config('contract.history_limit', 10));
        $analyses = ContractAnalysis::where('bot_user_id', $botUserId)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $this->setPrevScreen($chatId, 'idle');
        if ($analyses->isEmpty()) {
            $text = $this->getScreenText('history_empty_text');
        } else {
            $lines = ["История анализов (последние {$limit}):"];
            foreach ($analyses as $i => $a) {
                $date = $a->created_at->format('d.m.Y H:i');
                $name = 'документ';
                if (!empty($a->file_info) && is_array($a->file_info)) {
                    $first = $a->file_info[0] ?? null;
                    $name = $first['name'] ?? $name;
                }
                $lines[] = ($i + 1) . ") {$date} — {$name}";
            }
            $text = implode("\n", $lines);
        }
        $this->sendMessageWithMenu($botToken, $chatId, $text);
    }

    /**
     * Обработка загруженного документа или фото договора (ТЗ п.4–7).
     * При ошибке формата/чтения — единое сообщение из config('contract.error_upload_message').
     */
    private function handleDocumentOrPhoto(TelegramBot $bot, int $chatId, BotUser $botUser, array $message): void
    {
        $errorMessage = $this->getScreenText('error_file_text') ?: config('contract.error_upload_message', 'Пожалуйста, загрузите договор или выбранные страницы договора.');
        $paths = [];

        $this->setProcessing($chatId);
        try {
            $processingText = $this->getScreenText('processing_text') ?: '📄 Документ получен. Извлекаю текст и выполняю анализ…';
            $this->sendMessageWithMenu($bot->token, $chatId, $processingText);

            $handler = new ContractFileHandler();
            $paths = $handler->downloadAndValidate($bot->token, $message);

            $extractor = new DocumentTextExtractor();
            $fullText = $extractor->extractFromPaths($paths);

            if (trim($fullText) === '') {
                $this->sendMessageWithMenu($bot->token, $chatId, $errorMessage);
                return;
            }

            $analysisService = new ContractAnalysisService(app(\App\Services\Ai\AiService::class));
            $result = $analysisService->analyze($fullText);
            $summary = $result['summary_text'];
            $summaryJson = $result['summary_json'] ?? null;
            $fileInfo = $this->extractFileInfoFromMessage($message, $paths);

            ContractAnalysis::create([
                'telegram_bot_id' => $bot->id,
                'bot_user_id' => $botUser->id,
                'summary_text' => $summary,
                'summary_json' => $summaryJson,
                'file_info' => $fileInfo,
            ]);

            $this->sendSummaryToTelegram($bot->token, $chatId, $summary);
        } catch (ContractFileException|DocumentTextException|ContractAnalysisException $e) {
            Log::info('Contract file/text error: ' . $e->getMessage());
            $this->sendMessageWithMenu($bot->token, $chatId, $errorMessage);
        } catch (\Throwable $e) {
            Log::error('Contract document/photo handling error: ' . $e->getMessage());
            $this->sendMessageWithMenu($bot->token, $chatId, $errorMessage);
        } finally {
            $this->clearProcessing($chatId);
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
     * Извлечь метаданные обработанных файлов из сообщения для сохранения в истории.
     */
    private function extractFileInfoFromMessage(array $message, array $paths): array
    {
        $fileInfo = [];
        if (!empty($message['document']['file_name'])) {
            $fileInfo[] = ['type' => 'document', 'name' => $message['document']['file_name']];
        } elseif (!empty($message['photo'])) {
            $fileInfo[] = ['type' => 'photo', 'name' => 'Изображение'];
        }
        if (empty($fileInfo) && !empty($paths)) {
            foreach ($paths as $path) {
                $base = basename($path);
                if ($base !== '') {
                    $fileInfo[] = ['type' => 'file', 'name' => $base];
                }
            }
        }
        return $fileInfo;
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

        $footer = "\n\nВы можете открыть «История анализов», чтобы вернуться к результату позже.";
        $sendMsg = function (string $body, string $prefix = "📋 Выжимка по договору:\n\n") use ($botToken, $chatId, $footer) {
            $text = $prefix . $body . $footer;
            $this->sendMessageWithMenu($botToken, $chatId, $text);
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
            $this->sendMessageWithMenu($bot->token, $chatId, "Не удалось определить ваш аккаунт. Попробуйте снова.");
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
            $this->sendMessageWithMenu($bot->token, $chatId, "✅ Вы уже являетесь администратором бота.");
            return;
        }

        if ($botUser->status === BotUser::STATUS_PENDING) {
            $this->sendMessageWithMenu($bot->token, $chatId, "📋 Ваш запрос на доступ уже отправлен и ожидает рассмотрения.\n\nО результатах вам придёт уведомление.");
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

        $this->sendMessageWithMenu($bot->token, $chatId, "📩 Запрос на доступ отправлен.\n\nОжидайте рассмотрения. О результате вам придёт уведомление.");
    }
}
