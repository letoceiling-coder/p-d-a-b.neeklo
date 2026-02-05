<?php

namespace App\Services\Ai;

use App\Models\AiModel;
use App\Models\AiProviderKey;

class AiService
{
    public function __construct(
        protected GeminiApiService $gemini,
        protected OpenAIApiService $openai
    ) {}

    /**
     * Отправить запрос в модель по id записи AiModel.
     * @param int $aiModelId id из таблицы ai_models
     * @param array $messages [ ['role' => 'user'|'model', 'content' => '...'] ]
     */
    public function chat(int $aiModelId, array $messages): ?array
    {
        $model = AiModel::where('id', $aiModelId)->active()->first();
        if (!$model) {
            return null;
        }

        return match ($model->provider) {
            AiProviderKey::PROVIDER_GEMINI => $this->gemini->chat($model->model_id, $messages),
            AiProviderKey::PROVIDER_OPENAI => $this->openai->chat($model->model_id, $messages),
            default => null,
        };
    }

    /**
     * Отправить запрос по provider и model_id (строки).
     */
    public function chatWithModel(string $provider, string $modelId, array $messages): ?array
    {
        return match ($provider) {
            AiProviderKey::PROVIDER_GEMINI => $this->gemini->chat($modelId, $messages),
            AiProviderKey::PROVIDER_OPENAI => $this->openai->chat($modelId, $messages),
            default => null,
        };
    }

    public function gemini(): GeminiApiService
    {
        return $this->gemini;
    }

    public function openai(): OpenAIApiService
    {
        return $this->openai;
    }
}
