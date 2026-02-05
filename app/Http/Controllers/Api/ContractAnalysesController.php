<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractAnalysesController extends Controller
{
    /**
     * Список анализов (история). Поддержка пагинации и фильтра по bot_user_id.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContractAnalysis::with(['botUser', 'telegramBot'])
            ->orderByDesc('created_at');

        if ($request->filled('bot_user_id')) {
            $query->where('bot_user_id', $request->integer('bot_user_id'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    /**
     * Один анализ по id (полный текст и JSON).
     */
    public function show(int $id): JsonResponse
    {
        $analysis = ContractAnalysis::with(['botUser', 'telegramBot'])->findOrFail($id);
        return response()->json($analysis);
    }
}
