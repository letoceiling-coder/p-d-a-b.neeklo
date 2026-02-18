<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContractAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractAnalysesController extends Controller
{
    /**
     * Список всех анализов для Admin (Фаза 9.3). Веб (user_id) и бот (bot_user_id).
     * Фильтры: user_id, date_from, date_to.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ContractAnalysis::with(['user', 'botUser', 'telegramBot'])
            ->orderByDesc('created_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('bot_user_id')) {
            $query->where('bot_user_id', $request->integer('bot_user_id'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $items = $query->paginate($perPage);

        return response()->json($items);
    }

    /**
     * Один анализ по id (полный текст и JSON). Веб и бот.
     */
    public function show(int $id): JsonResponse
    {
        $analysis = ContractAnalysis::with(['user', 'botUser', 'telegramBot'])->findOrFail($id);
        return response()->json($analysis);
    }
}
