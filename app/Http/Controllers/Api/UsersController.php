<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Управление пользователями (Фаза 9.1). Только для admin.
 */
class UsersController extends Controller
{
    /**
     * Список пользователей (роль, активность).
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->orderBy('created_at', 'desc');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 20)));
        $items = $query->paginate($perPage);

        $items->getCollection()->transform(function (User $u) {
            $u->makeVisible(['role', 'is_active']);
            return $u;
        });

        return response()->json($items);
    }

    /**
     * Обновить пользователя (отключение/включение доступа без удаления записей).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'is_active' => 'nullable|boolean',
            'role' => 'nullable|string|in:admin,employee',
        ]);

        $user = User::findOrFail($id);

        if ($user->id === $request->user()->id && $request->has('is_active') && !$request->boolean('is_active')) {
            return response()->json(['message' => 'Нельзя отключить собственный доступ.'], 422);
        }

        if ($request->has('is_active')) {
            $user->is_active = $request->boolean('is_active');
        }
        if ($request->has('role')) {
            $user->role = $request->input('role');
        }
        $user->save();

        return response()->json([
            'message' => 'Обновлено.',
            'user' => $user->makeVisible(['role', 'is_active']),
        ]);
    }
}
