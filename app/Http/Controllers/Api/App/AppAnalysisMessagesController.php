<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Models\AnalysisMessage;
use App\Models\AiModel;
use App\Models\ContractAnalysis;
use App\Models\ContractSetting;
use App\Services\Ai\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API сообщений чата внутри анализа. Фаза 6.2: уточняющий вопрос → AI (контекст: выжимка + история) → ответ в чат.
 */
class AppAnalysisMessagesController extends Controller
{
    public function __construct(
        protected AiService $aiService
    ) {}

    /**
     * История сообщений анализа (доступ: владелец или admin).
     */
    public function index(Request $request, string $id): JsonResponse
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        $messages = $analysis->messages()->orderBy('created_at')->get()->map(fn (AnalysisMessage $m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => $m->content,
            'created_at' => $m->created_at->toIso8601String(),
        ]);

        return response()->json(['data' => $messages]);
    }

    /**
     * Отправка сообщения (уточняющий вопрос). Сохраняем user, вызываем AI (контекст: выжимка + история), сохраняем assistant.
     */
    public function store(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:16000'],
        ]);

        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        $userMessage = $analysis->messages()->create([
            'role' => 'user',
            'content' => $request->input('content'),
        ]);

        $assistantContent = '';
        $model = $this->resolveModel();
        if ($model && $analysis->summary_text) {
            $messages = $this->buildMessagesForAi($analysis, $userMessage->id, $userMessage->content);
            $result = $this->aiService->chat($model->id, $messages);
            $assistantContent = trim($result['content'] ?? '') ?: 'Не удалось получить ответ.';
        } else {
            $assistantContent = 'Нет выжимки договора или не настроена модель AI для ответов.';
        }

        $assistantMessage = $analysis->messages()->create([
            'role' => 'assistant',
            'content' => $assistantContent,
        ]);

        return response()->json([
            'data' => [
                [
                    'id' => $userMessage->id,
                    'role' => $userMessage->role,
                    'content' => $userMessage->content,
                    'created_at' => $userMessage->created_at->toIso8601String(),
                ],
                [
                    'id' => $assistantMessage->id,
                    'role' => $assistantMessage->role,
                    'content' => $assistantMessage->content,
                    'created_at' => $assistantMessage->created_at->toIso8601String(),
                ],
            ],
        ], 201);
    }

    private function resolveModel(): ?AiModel
    {
        $id = (int) (ContractSetting::get('default_ai_model_id') ?? config('contract.default_ai_model_id', 0));
        if ($id > 0) {
            $model = AiModel::where('id', $id)->active()->first();
            if ($model) {
                return $model;
            }
        }
        return AiModel::active()->ordered()->first();
    }

    /**
     * Собрать массив сообщений для AI: system (выжимка) + история (user/assistant) + последний user.
     */
    private function buildMessagesForAi(ContractAnalysis $analysis, int $excludeMessageId, string $lastUserContent): array
    {
        $summary = $analysis->summary_text ?? '';
        $systemContent = 'Ты — помощник по договору. Ниже выжимка договора. Отвечай кратко по выжимке и заданным вопросам. Не придумывай факты.';
        $systemContent .= "\n\n--- Выжимка договора ---\n\n" . mb_substr($summary, 0, 12000);

        $history = $analysis->messages()
            ->where('id', '<', $excludeMessageId)
            ->orderBy('created_at')
            ->get();

        $messages = [
            ['role' => 'system', 'content' => $systemContent],
        ];
        foreach ($history as $m) {
            $role = $m->role === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => $m->content];
        }
        $messages[] = ['role' => 'user', 'content' => $lastUserContent];

        return $messages;
    }
}
