<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminActionLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActionLogsController extends Controller
{
    /**
     * Список логов действий (пагинация, фильтр по действию).
     */
    public function index(Request $request): JsonResponse
    {
        $query = AdminActionLog::with('user:id,name,email')
            ->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->input('action'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $items = $query->paginate($perPage);

        return response()->json($items);
    }
}
