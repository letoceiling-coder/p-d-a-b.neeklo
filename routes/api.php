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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhook Telegram (публичный)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle']);

// Webhook LEXAUTO (розыгрыш)
Route::post('/telegram/lexauto-webhook', [LexautoWebhookController::class, 'handle']);

// Публичные роуты авторизации (только вход)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
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

    // История анализов договоров
    Route::get('/contract-analyses', [ContractAnalysesController::class, 'index']);
    Route::get('/contract-analyses/{id}', [ContractAnalysesController::class, 'show']);

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
});
