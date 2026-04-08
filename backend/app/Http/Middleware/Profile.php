<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Profile
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!empty($roles) && !in_array($user->role, $roles)) {
            return response()->json([
                'message' => 'Acceso denegado. Rol insuficiente.',
                'required' => $roles,
                'current'  => $user->role,
            ], 403);
        }

        return $next($request);
    }
}
