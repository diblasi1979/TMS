<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Transport\AlertController;
use App\Http\Controllers\Api\Transport\AssignmentController;
use App\Http\Controllers\Api\Transport\OperatorController;
use App\Http\Controllers\Api\Transport\VehicleController;
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

        // ── Módulo Transporte ───────────────────────────────────────────
        Route::prefix('transport')->name('transport.')->group(function () {

            // Alertas
            Route::get('alerts', [AlertController::class, 'index'])->name('alerts');

            // Vehículos
            Route::get('vehicles/expiring',                [VehicleController::class, 'expiring'])->name('vehicles.expiring');
            Route::patch('vehicles/{vehicle}/status',      [VehicleController::class, 'updateStatus'])->name('vehicles.status');
            Route::apiResource('vehicles', VehicleController::class);

            // Operadores
            Route::get('operators/expiring-licenses',       [OperatorController::class, 'expiringLicenses'])->name('operators.expiring-licenses');
            Route::patch('operators/{operator}/status',     [OperatorController::class, 'updateStatus'])->name('operators.status');
            Route::apiResource('operators', OperatorController::class);

            // Asignaciones
            Route::get('assignments/active',               [AssignmentController::class, 'active'])->name('assignments.active');
            Route::get('assignments',                      [AssignmentController::class, 'index'])->name('assignments.index');
            Route::post('assignments',                     [AssignmentController::class, 'store'])->name('assignments.store');
            Route::patch('assignments/{assignment}/release', [AssignmentController::class, 'release'])->name('assignments.release');
        });
    });
});

