<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — TMS
|--------------------------------------------------------------------------
| Prefijo automático: /api (definido en bootstrap/app.php)
*/

// ── Auth pública ─────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
});

// ── Rutas protegidas (Bearer token válido) ────────────────────────────────
Route::middleware('auth.session')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::get('me',          [AuthController::class, 'me']);
        Route::post('logout',     [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });

    // ── Admin (rol: admin) ──────────────────────────────────────────────
    Route::middleware('profile:admin')->group(function () {
        Route::apiResource('companies', CompanyController::class);
        Route::apiResource('users',     UserController::class);
        Route::apiResource('clients',   ClientController::class);
    });
});
