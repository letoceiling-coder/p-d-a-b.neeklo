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
            'ai_system_prompt' => 'nullable|string|max:32000',
            'welcome_text' => 'nullable|string|max:2000',
            'unauthorized_text' => 'nullable|string|max:1000',
            'upload_text' => 'nullable|string|max:1000',
            'processing_text' => 'nullable|string|max:500',
            'busy_text' => 'nullable|string|max:500',
            'error_file_text' => 'nullable|string|max:500',
            'info_text' => 'nullable|string|max:1000',
            'compare_stub_text' => 'nullable|string|max:500',
            'support_name' => 'nullable|string|max:200',
            'support_tg' => 'nullable|string|max:200',
            'support_email' => 'nullable|string|max:200',
            'support_hours' => 'nullable|string|max:200',
            'support_text' => 'nullable|string|max:1000',
            'allow_public_info' => 'nullable|boolean',
            'bot_otp_code' => 'nullable|string|max:50',
            'history_limit' => 'nullable|integer|min:1|max:50',
        ]);

        $allowed = [
            'telegram_summary_mode', 'telegram_max_message_chars', 'telegram_short_summary_chars',
            'max_photos_per_request', 'analysis_retention_months', 'default_ai_model_id',
            'ai_system_prompt',
            'welcome_text', 'unauthorized_text', 'upload_text', 'processing_text', 'busy_text',
            'error_file_text', 'info_text', 'compare_stub_text',
            'support_name', 'support_tg', 'support_email', 'support_hours', 'support_text',
            'allow_public_info', 'bot_otp_code', 'history_limit',
        ];
        $payload = array_intersect_key($request->all(), array_flip($allowed));
        ContractSetting::setMany($payload);

        AdminActionLog::log('contract_settings.updated', null, null, ['payload' => $payload]);

        return response()->json([
            'message' => 'Настройки сохранены.',
            'settings' => ContractSetting::getEditableDefaults(),
        ]);
    }
}
