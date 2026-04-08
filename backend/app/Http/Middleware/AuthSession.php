<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class AuthSession
{
    public function handle(Request $request, Closure $next): mixed
    {
        $rawToken = $request->bearerToken();

        if (!$rawToken) {
            return response()->json(['message' => 'Token requerido.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken($rawToken);

        if (!$accessToken || !$accessToken->tokenable) {
            return response()->json(['message' => 'Token inválido o expirado.'], 401);
        }

        $user = $accessToken->tokenable;

        if (!$user->is_active) {
            return response()->json(['message' => 'Cuenta desactivada.'], 403);
        }

        // Permite que currentAccessToken() funcione en logout
        $user->withAccessToken($accessToken);

        // Registra el último uso del token
        $accessToken->forceFill(['last_used_at' => now()])->save();

        Auth::setUser($user);

        return $next($request);
    }
}
