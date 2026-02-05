<?php

namespace App\Services\Ai;

use App\Models\AiProviderKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIApiService
{
    private const BASE_URL = 'https://api.openai.com/v1';

    public function isConfigured(): bool
    {
        return (bool) AiProviderKey::getApiKey(AiProviderKey::PROVIDER_OPENAI);
    }

    /**
     * @param string $modelId e.g. gpt-4o, gpt-4o-mini
     * @param array $messages [ ['role' => 'user'|'assistant'|'system', 'content' => '...'] ]
     * @return array { content: string, raw: array } or null on error
     */
    public function chat(string $modelId, array $messages): ?array
    {
        $key = AiProviderKey::getApiKey(AiProviderKey::PROVIDER_OPENAI);
        if (!$key) {
            Log::warning('OpenAI API key not set');
            return null;
        }

        $apiMessages = array_map(fn ($m) => [
            'role' => $m['role'] === 'model' ? 'assistant' : ($m['role'] ?? 'user'),
            'content' => $m['content'] ?? '',
        ], $messages);

        try {
            $response = Http::withToken($key)
                ->timeout(60)
                ->post(self::BASE_URL . '/chat/completions', [
                    'model' => $modelId,
                    'messages' => $apiMessages,
                    'max_tokens' => 2048,
                    'temperature' => 0.7,
                ]);

            $data = $response->json();

            if (!$response->successful()) {
                Log::error('OpenAI API error', ['response' => $response->body()]);
                return null;
            }

            $text = $data['choices'][0]['message']['content'] ?? '';
            return ['content' => $text, 'raw' => $data];
        } catch (\Throwable $e) {
            Log::error('OpenAI API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Проверить ключ: запрос к API. Возвращает ['valid' => bool, 'message' => string].
     */
    public function verifyKey(?string $key = null): array
    {
        $apiKey = $key ?? AiProviderKey::getApiKey(AiProviderKey::PROVIDER_OPENAI);
        if (!$apiKey) {
            return ['valid' => false, 'message' => 'Ключ не задан'];
        }
        try {
            $response = Http::withToken($apiKey)->timeout(10)->get(self::BASE_URL . '/models');
            if ($response->successful()) {
                return ['valid' => true, 'message' => 'Ключ действителен'];
            }
            $body = $response->json();
            $error = $body['error']['message'] ?? $body['error']['code'] ?? $response->body();
            $code = $response->status();
            if ($code === 401) {
                return ['valid' => false, 'message' => 'Неверный или отозванный ключ'];
            }
            if ($code === 429) {
                return ['valid' => false, 'message' => 'Превышен лимит запросов или исчерпан баланс'];
            }
            return ['valid' => false, 'message' => (string) $error];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * List models (common ones; OpenAI list endpoint may require different permissions).
     */
    public function listModels(): array
    {
        $key = AiProviderKey::getApiKey(AiProviderKey::PROVIDER_OPENAI);
        if (!$key) {
            return [];
        }
        try {
            $response = Http::withToken($key)->timeout(10)->get(self::BASE_URL . '/models');
            $data = $response->json();
            $list = $data['data'] ?? [];
            return array_map(fn ($m) => [
                'id' => $m['id'] ?? '',
                'name' => $m['id'] ?? '',
            ], $list);
        } catch (\Throwable $e) {
            Log::warning('OpenAI list models: ' . $e->getMessage());
            return [];
        }
    }
}
