<?php

namespace App\Http\Controllers\Api\Lexauto;

use App\Http\Controllers\Controller;
use App\Models\Lexauto\LexautoOrder;
use App\Models\Lexauto\LexautoSetting;
use App\Models\Lexauto\LexautoUser;
use App\Models\Lexauto\LexautoUserState;
use App\Services\Lexauto\LexautoSeatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LexautoWebhookController extends Controller
{
    private const STATE_START = 'start';
    private const STATE_ASK_FIO = 'ask_fio';
    private const STATE_ASK_PHONE = 'ask_phone';
    private const STATE_ASK_QUANTITY = 'ask_quantity';
    private const STATE_WAIT_RECEIPT = 'wait_receipt';

    public function handle(Request $request): JsonResponse
    {
        $token = config('lexauto.bot_token');
        if (!$token) {
            return response()->json(['ok' => true]);
        }

        $update = $request->all();
        try {
            if (isset($update['message'])) {
                $msg = $update['message'];
                if (!empty($msg['document'])) {
                    $this->handleDocument($token, $msg);
                } else {
                    $this->handleMessage($token, $msg);
                }
            }
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Lexauto webhook: ' . $e->getMessage());
            return response()->json(['ok' => true]);
        }
    }

    private function handleMessage(string $token, array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? [];
        $tgId = $from['id'] ?? null;
        $text = trim($message['text'] ?? '');
        $username = $from['username'] ?? null;

        if (!$chatId || !$tgId) {
            return;
        }

        if ($text === '/start') {
            $this->handleStart($token, $chatId, $tgId, $username);
            return;
        }

        if (in_array($text, ['Заполнить анкету', 'Купить ещё'], true)) {
            $this->handleCallback($token, $chatId, $tgId, $text);
            return;
        }

        $state = LexautoUserState::getState($tgId);
        $payload = LexautoUserState::getPayload($tgId);

        if ($state === self::STATE_ASK_FIO) {
            LexautoUserState::setState($tgId, self::STATE_ASK_PHONE, ['fio' => $text]);
            $this->send($token, $chatId, "Напиши свой номер телефона для связи:");
            return;
        }

        if ($state === self::STATE_ASK_PHONE) {
            $fio = $payload['fio'] ?? '';
            $user = LexautoUser::create([
                'tg_id' => $tgId,
                'username' => $username,
                'fio' => $fio,
                'phone' => $text,
            ]);
            LexautoUserState::clear($tgId);
            $this->askQuantity($token, $chatId, $tgId, $user->id);
            return;
        }

        if ($state === self::STATE_ASK_QUANTITY) {
            if ($text === 'Купить ещё') {
                $user = LexautoUser::where('tg_id', $tgId)->first();
                if ($user) {
                    $this->askQuantity($token, $chatId, $tgId, $user->id);
                }
                return;
            }
            $userId = (int) ($payload['user_id'] ?? 0);
            $user = LexautoUser::find($userId);
            if (!$user) {
                LexautoUserState::clear($tgId);
                $this->send($token, $chatId, "Ошибка. Напиши /start заново.");
                return;
            }
            $quantity = (int) $text;
            if ($quantity < 1) {
                $this->send($token, $chatId, "Введите число больше 0.");
                return;
            }
            $order = LexautoSeatsService::reserve($user->id, $quantity);
            if (!$order) {
                $free = LexautoSeatsService::freeSeats();
                $this->send($token, $chatId, "Вы хотите {$quantity}, но осталось всего {$free}. Введите другое число.");
                return;
            }
            LexautoUserState::setState($tgId, self::STATE_WAIT_RECEIPT, ['order_id' => $order->id]);
            $this->sendPaymentInstructions($token, $chatId, $order);
            return;
        }

        if ($state === self::STATE_WAIT_RECEIPT) {
            $this->send($token, $chatId, "Ожидаю чек в формате PDF. Пришлите файл.");
            return;
        }

        $this->handleStart($token, $chatId, $tgId, $username);
    }

    private function handleStart(string $token, int $chatId, int $tgId, ?string $username): void
    {
        LexautoUserState::clear($tgId);
        $free = LexautoSeatsService::freeSeats();
        $user = LexautoUser::where('tg_id', $tgId)->first();

        if ($free <= 0) {
            if ($user) {
                $numbers = $user->ticketNumbers();
                $numStr = empty($numbers) ? '—' : implode(', ', $numbers);
                $this->send($token, $chatId, "⛔️ Места закончились!\nТы уже в игре, твои номера: {$numStr}. Следи за розыгрышем!");
            } else {
                $this->send($token, $chatId, "⛔️ К сожалению, все места уже заняты.\nЕсли кто-то не оплатит бронь, место освободится. Следи за новостями.");
            }
            return;
        }

        if (!$user) {
            $this->send($token, $chatId, "Привет! Рад, что ты решил поучаствовать в нашей движухе! 🤝\nДля начала давай познакомимся, чтобы я мог записать тебя в список участников.\nНажми кнопку ниже, чтобы начать регистрацию 👇", [
                'keyboard' => [['Заполнить анкету']],
                'resize_keyboard' => true,
            ]);
            LexautoUserState::setState($tgId, self::STATE_ASK_FIO, []);
            return;
        }

        $name = $user->fio ? explode(' ', $user->fio)[0] : 'друг';
        $numbers = $user->ticketNumbers();
        $numStr = empty($numbers) ? '' : "\nТвои текущие номера: " . implode(', ', $numbers) . ".";
        $this->send($token, $chatId, "Рад видеть тебя снова, {$name}! 🤝\nХочешь увеличить шансы и докупить ещё наклеек?{$numStr}\nНажми кнопку, чтобы оформить новую заявку 👇", [
            'keyboard' => [['Купить ещё']],
            'resize_keyboard' => true,
        ]);
        LexautoUserState::setState($tgId, self::STATE_ASK_QUANTITY, ['user_id' => $user->id]);
    }

    public function handleCallback(string $token, int $chatId, int $tgId, string $data): void
    {
        if ($data === 'Заполнить анкету') {
            LexautoUserState::setState($tgId, self::STATE_ASK_FIO, []);
            $this->send($token, $chatId, "Напиши своё ФИО полностью (например: Иванов Иван Иванович):");
            return;
        }
        if ($data === 'Купить ещё') {
            $user = LexautoUser::where('tg_id', $tgId)->first();
            if (!$user) {
                $this->handleStart($token, $chatId, $tgId, null);
                return;
            }
            LexautoUserState::setState($tgId, self::STATE_ASK_QUANTITY, ['user_id' => $user->id]);
            $this->askQuantity($token, $chatId, $user->id);
        }
    }

    private function askQuantity(string $token, int $chatId, int $tgId, int $userId): void
    {
        $price = LexautoSetting::get('price') ?: config('lexauto.price', 500);
        $this->send($token, $chatId, "Стоимость одной наклейки: {$price} руб.\nВведите количество наклеек, которые хотите приобрести (цифрой):");
        LexautoUserState::setState($tgId, self::STATE_ASK_QUANTITY, ['user_id' => $userId]);
    }

    private function sendPaymentInstructions(string $token, int $chatId, LexautoOrder $order): void
    {
        $price = LexautoSetting::get('price');
        $qrImage = LexautoSetting::get('qr_image');
        $text = "✅ Заявка сформирована! Бронь на 30 минут.\nКоличество: {$order->quantity} шт.\nК оплате: {$order->amount} руб.\n👇 Реквизиты для оплаты:\n\n⚠️ ВНИМАНИЕ! ОЧЕНЬ ВАЖНО:\n1. Оплачивайте сумму СТРОГО ОДНИМ ПЛАТЕЖОМ. Не разбивайте оплату на части!\n2. В назначении платежа укажите: «Оплата наклейки».\n3. Мы принимаем чек только в формате PDF (выгрузка из банка).\n\nПришли мне чек в формате PDF-ФАЙЛА в ответ на это сообщение!";
        $this->send($token, $chatId, $text);
        if ($qrImage) {
            $this->sendPhoto($token, $chatId, $qrImage);
        }
    }

    /**
     * Обработка документа (PDF чека). Вызывается из роута при получении document.
     */
    public function handleDocument(string $token, array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        $from = $message['from'] ?? [];
        $tgId = $from['id'] ?? null;
        $doc = $message['document'] ?? [];
        $mime = $doc['mime_type'] ?? '';
        $fileName = $doc['file_name'] ?? '';
        $fileId = $doc['file_id'] ?? '';

        if (!$chatId || !$tgId || !$fileId) {
            return;
        }

        $isPdf = $mime === 'application/pdf' || str_ends_with(strtolower($fileName), '.pdf');
        if (!$isPdf) {
            $this->send($token, $chatId, "Пришлите чек в формате PDF.");
            return;
        }

        $state = LexautoUserState::getState($tgId);
        $payload = LexautoUserState::getPayload($tgId);
        if ($state !== self::STATE_WAIT_RECEIPT) {
            $this->send($token, $chatId, "Сначала оформите заявку: напишите /start.");
            return;
        }

        $orderId = (int) ($payload['order_id'] ?? 0);
        $order = LexautoOrder::find($orderId);
        if (!$order || $order->status !== LexautoOrder::STATUS_RESERVED) {
            LexautoUserState::clear($tgId);
            $this->send($token, $chatId, "Заявка не найдена или уже обработана. Напишите /start.");
            return;
        }

        $order->update(['status' => LexautoOrder::STATUS_REVIEW, 'check_file_id' => $fileId]);
        LexautoUserState::clear($tgId);
        $this->send($token, $chatId, "Чек получен! ✅\nСтатус: На проверке у администратора.");
    }

    /**
     * Отправка текста (в т.ч. с reply keyboard).
     */
    private function send(string $token, int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($replyMarkup) {
            $payload['reply_markup'] = json_encode($replyMarkup);
        }
        Http::post("https://api.telegram.org/bot{$token}/sendMessage", $payload);
    }

    private function sendPhoto(string $token, int $chatId, string $photoFileIdOrUrl): void
    {
        Http::post("https://api.telegram.org/bot{$token}/sendPhoto", [
            'chat_id' => $chatId,
            'photo' => $photoFileIdOrUrl,
        ]);
    }
}
