<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteCodesController extends Controller
{
    /**
     * Список invite-кодов (для админа).
     */
    public function index(Request $request): JsonResponse
    {
        $items = InviteCode::with(['creator', 'usedByUser'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 20))));

        return response()->json($items);
    }

    /**
     * Генерация нового invite-кода (только admin).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $expiresAt = null;
        if ($request->filled('expires_in_days')) {
            $expiresAt = now()->addDays($request->integer('expires_in_days'));
        }

        $invite = InviteCode::generate($request->user()->id, $expiresAt);

        return response()->json([
            'message' => 'Invite-код создан',
            'invite_code' => $invite->fresh(),
        ], 201);
    }
}
