<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use App\Models\AiModel;
use App\Models\AiProviderKey;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiKeysController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * Ключи (маскированные) и список моделей
     */
    public function index(): JsonResponse
    {
        $keys = AiProviderKey::all()->keyBy('provider');
        $providers = [
            AiProviderKey::PROVIDER_GEMINI => [
                'label' => 'Gemini',
                'url' => 'https://ai.google.dev/gemini-api/docs/quickstart?hl=ru',
                'key' => $keys->get(AiProviderKey::PROVIDER_GEMINI)?->toArray(),
            ],
            AiProviderKey::PROVIDER_OPENAI => [
                'label' => 'OpenAI',
                'url' => 'https://platform.openai.com/api-keys',
                'key' => $keys->get(AiProviderKey::PROVIDER_OPENAI)?->toArray(),
            ],
        ];

        $models = AiModel::ordered()->get();

        return response()->json([
            'providers' => $providers,
            'models' => $models,
        ]);
    }

    /**
     * Обновить ключ одного провайдера (gemini или openai).
     * В теле передаётся только один ключ, например: { "gemini": "..." } или { "openai": "..." }.
     * Обновляется только переданный провайдер, остальные не изменяются.
     */
    public function updateKeys(Request $request): JsonResponse
    {
        $request->validate([
            'gemini' => 'nullable|string|max:500',
            'openai' => 'nullable|string|max:500',
        ]);

        $updated = [];
        if ($request->has('gemini')) {
            AiProviderKey::setKey(AiProviderKey::PROVIDER_GEMINI, $request->filled('gemini') ? $request->input('gemini') : null);
            $updated[] = 'gemini';
        }
        if ($request->has('openai')) {
            AiProviderKey::setKey(AiProviderKey::PROVIDER_OPENAI, $request->filled('openai') ? $request->input('openai') : null);
            $updated[] = 'openai';
        }

        $message = count($updated) === 0
            ? 'Не указан ключ для сохранения'
            : (count($updated) === 1 ? 'Ключ ' . ($updated[0] === 'gemini' ? 'Gemini' : 'OpenAI') . ' сохранён.' : 'Ключи сохранены.');

        if (count($updated) > 0) {
            AdminActionLog::log('ai.keys_updated', null, null, ['providers' => $updated]);
        }
        return response()->json(['message' => $message]);
    }

    /**
     * Добавить модель
     */
    public function storeModel(Request $request): JsonResponse
    {
        $request->validate([
            'provider' => 'required|in:gemini,openai',
            'name' => 'required|string|max:255',
            'model_id' => 'required|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $model = AiModel::create([
            'provider' => $request->provider,
            'name' => $request->name,
            'model_id' => $request->model_id,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => $request->integer('sort_order', 0),
        ]);
        AdminActionLog::log('ai.model_created', 'ai_model', $model->id, ['name' => $model->name]);
        return response()->json(['model' => $model], 201);
    }

    /**
     * Обновить модель
     */
    public function updateModel(Request $request, int $id): JsonResponse
    {
        $model = AiModel::findOrFail($id);
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'model_id' => 'sometimes|string|max:255',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $model->update($request->only(['name', 'model_id', 'is_active', 'sort_order']));
        AdminActionLog::log('ai.model_updated', 'ai_model', $model->id, ['name' => $model->name]);
        return response()->json(['model' => $model->fresh()]);
    }

    /**
     * Удалить модель
     */
    public function destroyModel(int $id): JsonResponse
    {
        $model = AiModel::findOrFail($id);
        $name = $model->name;
        $model->delete();
        AdminActionLog::log('ai.model_deleted', 'ai_model', $id, ['name' => $name]);
        return response()->json(['message' => 'Модель удалена']);
    }

    /**
     * Запрос к AI (для использования в приложении)
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'model_id' => 'required_without:ai_model_id|nullable|integer', // id из ai_models
            'ai_model_id' => 'required_without:model_id|nullable|integer',
            'messages' => 'required|array',
            'messages.*.role' => 'required|string|in:user,model,assistant,system',
            'messages.*.content' => 'required|string',
        ]);

        $aiModelId = $request->input('ai_model_id') ?? $request->input('model_id');
        $messages = $request->input('messages');

        $result = $this->aiService->chat((int) $aiModelId, $messages);
        if ($result === null) {
            return response()->json(['error' => 'Ошибка запроса к AI или модель не настроена'], 502);
        }

        return response()->json([
            'content' => $result['content'],
            'raw' => $result['raw'] ?? null,
        ]);
    }

    /**
     * Проверить сохранённый ключ OpenAI (доступ и активность).
     */
    public function verifyOpenai(): JsonResponse
    {
        $result = $this->aiService->openai()->verifyKey();
        return response()->json($result);
    }

    /**
     * Проверить сохранённый ключ Gemini (доступ и активность).
     */
    public function verifyGemini(): JsonResponse
    {
        $result = $this->aiService->gemini()->verifyKey();
        return response()->json($result);
    }
}
