<?php

namespace App\Services\Ai;

use App\Models\AiProviderKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiApiService
{
    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta';

    public function isConfigured(): bool
    {
        return (bool) AiProviderKey::getApiKey(AiProviderKey::PROVIDER_GEMINI);
    }

    /**
     * @param string $modelId e.g. gemini-1.5-flash, gemini-1.5-pro
     * @param array $messages [ ['role' => 'user'|'model', 'content' => '...'] ]
     * @return array { content: string, raw: array } or null on error
     */
    public function chat(string $modelId, array $messages): ?array
    {
        $key = AiProviderKey::getApiKey(AiProviderKey::PROVIDER_GEMINI);
        if (!$key) {
            Log::warning('Gemini API key not set');
            return null;
        }

        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            $content = $msg['content'] ?? '';
            if ($role === 'model') {
                $contents[] = ['role' => 'model', 'parts' => [['text' => $content]]];
            } else {
                $contents[] = ['role' => 'user', 'parts' => [['text' => $content]]];
            }
        }

        $url = self::BASE_URL . '/models/' . $modelId . ':generateContent?key=' . $key;
        $body = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2048,
            ],
        ];

        try {
            $response = Http::timeout(60)->post($url, $body);
            $data = $response->json();

            if (!$response->successful()) {
                Log::error('Gemini API error', ['response' => $response->body()]);
                return null;
            }

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return ['content' => $text, 'raw' => $data];
        } catch (\Throwable $e) {
            Log::error('Gemini API exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Проверить ключ: запрос к API. Возвращает ['valid' => bool, 'message' => string].
     */
    public function verifyKey(?string $key = null): array
    {
        $apiKey = $key ?? AiProviderKey::getApiKey(AiProviderKey::PROVIDER_GEMINI);
        if (!$apiKey) {
            return ['valid' => false, 'message' => 'Ключ не задан'];
        }
        try {
            $response = Http::timeout(10)->get(self::BASE_URL . '/models?key=' . urlencode($apiKey));
            if ($response->successful()) {
                return ['valid' => true, 'message' => 'Ключ действителен'];
            }
            $body = $response->json();
            $error = $body['error']['message'] ?? $body['error']['status'] ?? $response->body();
            $code = $response->status();
            if ($code === 400 || $code === 403) {
                return ['valid' => false, 'message' => 'Неверный ключ или нет доступа'];
            }
            if ($code === 429) {
                return ['valid' => false, 'message' => 'Превышена квота или лимит'];
            }
            return ['valid' => false, 'message' => (string) $error];
        } catch (\Throwable $e) {
            return ['valid' => false, 'message' => 'Ошибка: ' . $e->getMessage()];
        }
    }

    /**
     * List available models (from API).
     */
    public function listModels(): array
    {
        $key = AiProviderKey::getApiKey(AiProviderKey::PROVIDER_GEMINI);
        if (!$key) {
            return [];
        }
        try {
            $response = Http::timeout(10)->get(self::BASE_URL . '/models?key=' . $key);
            $data = $response->json();
            $list = $data['models'] ?? [];
            return array_map(fn ($m) => [
                'id' => $m['name'] ?? '',
                'name' => $m['displayName'] ?? $m['name'] ?? '',
            ], $list);
        } catch (\Throwable $e) {
            Log::warning('Gemini list models: ' . $e->getMessage());
            return [];
        }
    }
}
