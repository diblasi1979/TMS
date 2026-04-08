<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales inválidas.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Cuenta desactivada.'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => new UserResource($user->load('company')),
        ]);
    }

    public function me(): UserResource
    {
        return new UserResource(Auth::user()->load('company'));
    }

    public function logout(): JsonResponse
    {
        Auth::user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function logoutAll(): JsonResponse
    {
        Auth::user()->tokens()->delete();

        return response()->json(['message' => 'Todas las sesiones cerradas.']);
    }
}
