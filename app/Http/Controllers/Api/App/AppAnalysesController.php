<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessContractAnalysisJob;
use App\Models\ContractAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API списка и создания анализов для приложения «Анализ договора» (Фаза 2).
 */
class AppAnalysesController extends Controller
{
    /**
     * Список анализов: для Employee — свои, для Admin — все с фильтром; поиск по названию/дате.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = ContractAnalysis::query()
            ->whereNotNull('user_id')
            ->orderByDesc('created_at');

        if (!$user->isAdmin()) {
            $query->where('user_id', $user->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->where(function ($qb) use ($q) {
                $qb->where('title', 'like', '%' . $q . '%')
                    ->orWhere('created_at', 'like', '%' . $q . '%');
            });
        }

        $perPage = max(1, min(100, $request->integer('per_page', 50)));
        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(fn (ContractAnalysis $a) => [
            'id' => $a->id,
            'title' => $a->title ?? ('— ' . $a->created_at->format('d.m.Y H:i')),
            'status' => $a->status,
            'created_at' => $a->created_at->toIso8601String(),
        ]);

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ]);
    }

    /**
     * Создание нового анализа (новый чат).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $analysis = ContractAnalysis::create([
            'user_id' => $user->id,
            'title' => '— ' . now()->format('d.m.Y H:i'),
            'status' => ContractAnalysis::STATUS_DRAFT,
        ]);

        return response()->json([
            'data' => [
                'id' => $analysis->id,
                'title' => $analysis->title,
                'status' => $analysis->status,
                'created_at' => $analysis->created_at->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Один анализ по id (доступ: владелец или admin).
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (!$request->user()->isAdmin() && !$analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        return response()->json([
            'data' => [
                'id' => $analysis->id,
                'title' => $analysis->title ?? ('— ' . $analysis->created_at->format('d.m.Y H:i')),
                'status' => $analysis->status,
                'processing_step' => $analysis->processing_step,
                'summary_text' => $analysis->summary_text,
                'summary_json' => $analysis->summary_json,
                'counterparty_check' => $analysis->counterparty_check,
                'file_info' => $analysis->file_info,
                'pdf_path' => $analysis->pdf_path,
                'created_at' => $analysis->created_at->toIso8601String(),
                'updated_at' => $analysis->updated_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Статус анализа для индикатора обработки (Фаза 4.2). Этапы: Извлечение текста, Проверка контрагента, Формирование отчёта.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        $stepLabels = [
            'extracting' => 'Извлечение текста',
            'counterparty' => 'Проверка контрагента',
            'report' => 'Формирование отчёта',
        ];

        return response()->json([
            'data' => [
                'status' => $analysis->status,
                'processing_step' => $analysis->processing_step,
                'step_label' => $analysis->processing_step ? ($stepLabels[$analysis->processing_step] ?? 'Идёт анализ договора') : null,
            ],
        ]);
    }

    /**
     * Запуск анализа после загрузки (Фаза 4.3). Ставит задачу в очередь.
     */
    public function start(Request $request, string $id): JsonResponse
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        if ($analysis->status === ContractAnalysis::STATUS_PROCESSING) {
            return response()->json(['message' => 'Анализ уже выполняется.'], 422);
        }

        if (empty($analysis->temp_upload_path) || ! is_dir($analysis->temp_upload_path)) {
            return response()->json(['message' => 'Сначала загрузите документы.'], 422);
        }

        $analysis->update([
            'status' => ContractAnalysis::STATUS_PROCESSING,
            'processing_step' => 'extracting',
        ]);

        ProcessContractAnalysisJob::dispatch($analysis->id);

        return response()->json([
            'message' => 'Анализ запущен.',
            'data' => [
                'status' => $analysis->status,
                'processing_step' => $analysis->processing_step,
            ],
        ], 202);
    }

    /**
     * Скачать PDF-отчёт (Фаза 7.3). Доступ: владелец или admin.
     */
    public function downloadPdf(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $analysis = ContractAnalysis::whereNotNull('user_id')->findOrFail($id);

        if (! $request->user()->isAdmin() && ! $analysis->isOwnedBy($request->user()->id)) {
            abort(403, 'Доступ запрещён.');
        }

        if (! $analysis->pdf_path) {
            return response()->json(['message' => 'PDF ещё не сформирован.'], 404);
        }

        if (! Storage::disk('local')->exists($analysis->pdf_path)) {
            return response()->json(['message' => 'Файл PDF не найден.'], 404);
        }

        $filename = 'otchet-analiz-' . $analysis->id . '.pdf';
        return Storage::disk('local')->download($analysis->pdf_path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
