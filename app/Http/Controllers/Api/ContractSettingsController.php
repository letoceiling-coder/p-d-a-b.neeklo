<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\AiModel;
use App\Models\ContractSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractSettingsController extends Controller
{
    /**
     * Текущие настройки анализа (для админки + список моделей для выбора).
     */
    public function index(): JsonResponse
    {
        $settings = ContractSetting::getEditableDefaults();
        $models = AiModel::ordered()->get(['id', 'name', 'provider', 'model_id', 'is_active']);
        return response()->json([
            'settings' => $settings,
            'ai_models' => $models,
        ]);
    }

    /**
     * Сохранить настройки анализа.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'telegram_summary_mode' => 'nullable|string|in:full,short,both',
            'telegram_max_message_chars' => 'nullable|integer|min:100|max:4096',
            'telegram_short_summary_chars' => 'nullable|integer|min:100|max:4096',
            'max_photos_per_request' => 'nullable|integer|min:1|max:20',
            'analysis_retention_months' => 'nullable|integer|min:1|max:120',
            'default_ai_model_id' => 'nullable|integer|min:0',
        ]);

        $payload = $request->only([
            'telegram_summary_mode', 'telegram_max_message_chars', 'telegram_short_summary_chars',
            'max_photos_per_request', 'analysis_retention_months', 'default_ai_model_id',
        ]);
        ContractSetting::setMany(array_filter($payload, fn ($v) => $v !== null));

        AdminActionLog::log('contract_settings.updated', null, null, ['payload' => $payload]);

        return response()->json([
            'message' => 'Настройки сохранены.',
            'settings' => ContractSetting::getEditableDefaults(),
        ]);
    }
}
