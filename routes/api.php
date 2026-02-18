<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BotController;
use App\Http\Controllers\Api\TelegramWebhookController;
use App\Http\Controllers\Api\AccessRequestController;
use App\Http\Controllers\Api\AiKeysController;
use App\Http\Controllers\Api\ActionLogsController;
use App\Http\Controllers\Api\ContractAnalysesController;
use App\Http\Controllers\Api\ContractSettingsController;
use App\Http\Controllers\Api\Lexauto\LexautoWebhookController;
use App\Http\Controllers\Api\Lexauto\LexautoOrderController;
use App\Http\Controllers\Api\App\AppAnalysesController;
use App\Http\Controllers\Api\App\AppAnalysisMessagesController;
use App\Http\Controllers\Api\App\AppAnalysisUploadController;
use App\Http\Controllers\Api\InviteCodesController;
use App\Http\Controllers\Api\UsersController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhook Telegram (публичный)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Webhook LEXAUTO (розыгрыш)
Route::post('/telegram/lexauto-webhook', [LexautoWebhookController::class, 'handle']);

// Публичные роуты авторизации
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Защищённые роуты (только для авторизованных)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Бот (один на приложение)
    Route::get('/bot', [BotController::class, 'show']);
    Route::post('/bot', [BotController::class, 'store']);
    Route::put('/bot/settings', [BotController::class, 'updateSettings']);
    Route::get('/bot/description', [BotController::class, 'getDescription']);
    Route::put('/bot/description', [BotController::class, 'updateDescription']);
    Route::post('/bot/test-webhook', [BotController::class, 'testWebhook']);

    // Запросы доступа к боту
    Route::get('/access-requests', [AccessRequestController::class, 'index']);
    Route::post('/access-requests/{id}/approve', [AccessRequestController::class, 'approve']);
    Route::post('/access-requests/{id}/reject', [AccessRequestController::class, 'reject']);
    Route::post('/access-requests/{id}/revoke', [AccessRequestController::class, 'revoke']);

    // Ключи AI и модели
    Route::get('/ai', [AiKeysController::class, 'index']);
    Route::put('/ai/keys', [AiKeysController::class, 'updateKeys']);
    Route::post('/ai/models', [AiKeysController::class, 'storeModel']);
    Route::put('/ai/models/{id}', [AiKeysController::class, 'updateModel']);
    Route::delete('/ai/models/{id}', [AiKeysController::class, 'destroyModel']);
    Route::post('/ai/chat', [AiKeysController::class, 'chat']);
    Route::get('/ai/verify/openai', [AiKeysController::class, 'verifyOpenai']);
    Route::get('/ai/verify/gemini', [AiKeysController::class, 'verifyGemini']);

    // Настройки анализа договоров
    Route::get('/contract-settings', [ContractSettingsController::class, 'index']);
    Route::put('/contract-settings', [ContractSettingsController::class, 'update']);

    // Логи действий
    Route::get('/action-logs', [ActionLogsController::class, 'index']);

    // LEXAUTO: заявки на розыгрыш
    Route::get('/lexauto/orders', [LexautoOrderController::class, 'index']);
    Route::get('/lexauto/orders/{id}', [LexautoOrderController::class, 'show']);
    Route::post('/lexauto/orders/{id}/approve', [LexautoOrderController::class, 'approve']);
    Route::post('/lexauto/orders/{id}/reject', [LexautoOrderController::class, 'reject']);
    Route::put('/lexauto/orders/{id}', [LexautoOrderController::class, 'update']);

    // Приложение «Анализ договора» (GPT-style): анализы и сообщения чата
    Route::get('/app/analyses', [AppAnalysesController::class, 'index']);
    Route::post('/app/analyses', [AppAnalysesController::class, 'store']);
    Route::get('/app/analyses/{id}', [AppAnalysesController::class, 'show']);
    Route::get('/app/analyses/{id}/status', [AppAnalysesController::class, 'status']);
    Route::get('/app/analyses/{id}/download-pdf', [AppAnalysesController::class, 'downloadPdf']);
    Route::post('/app/analyses/{id}/start', [AppAnalysesController::class, 'start']);
    Route::post('/app/analyses/{id}/upload', [AppAnalysisUploadController::class, '__invoke']);
    Route::get('/app/analyses/{id}/messages', [AppAnalysisMessagesController::class, 'index']);
    Route::post('/app/analyses/{id}/messages', [AppAnalysisMessagesController::class, 'store']);

    // Админ: invite-коды, пользователи, история анализов (все анализы + фильтры)
    Route::middleware('admin')->group(function () {
        Route::get('/invite-codes', [InviteCodesController::class, 'index']);
        Route::post('/invite-codes', [InviteCodesController::class, 'store']);
        Route::get('/users', [UsersController::class, 'index']);
        Route::put('/users/{id}', [UsersController::class, 'update']);
        Route::get('/contract-analyses', [ContractAnalysesController::class, 'index']);
        Route::get('/contract-analyses/{id}', [ContractAnalysesController::class, 'show']);
    });
});
