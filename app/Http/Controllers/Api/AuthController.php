<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InviteCode;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Регистрация по invite-коду.
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'invite_code' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $invite = InviteCode::where('code', $request->input('invite_code'))->first();
        if (!$invite || !$invite->isValid()) {
            throw ValidationException::withMessages([
                'invite_code' => ['Недействительный или уже использованный invite-код.'],
            ]);
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => 'employee',
            'invite_code_id' => $invite->id,
        ]);

        $invite->update(['used_by' => $user->id]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Регистрация успешна',
        ]);
    }

    /**
     * Авторизация пользователя
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => ['Неверный email или пароль.'],
            ]);
        }

        $user = Auth::user();
        if ($user && !$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Доступ отключён. Обратитесь к администратору.'],
            ]);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
            'message' => 'Успешный вход в систему',
        ]);
    }

    /**
     * Выход из системы
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();
        }

        return response()->json(['message' => 'Успешный выход из системы']);
    }

    /**
     * Получить текущего пользователя (включая роль).
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->makeVisible(['role']);
        }
        return response()->json(['user' => $user]);
    }
}
