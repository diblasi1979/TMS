<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\Transport\AlertController;
use App\Http\Controllers\Api\Transport\AssignmentController;
use App\Http\Controllers\Api\Transport\OperatorController;
use App\Http\Controllers\Api\Transport\VehicleController;
use App\Http\Controllers\Api\Distribution\OrderController;
use App\Http\Controllers\Api\Distribution\RouteController;
use App\Http\Controllers\Api\Distribution\EventController;
use App\Http\Controllers\Api\Planning\TripController;
use App\Http\Controllers\Api\Planning\MaintenanceController;
use App\Http\Controllers\Api\Planning\ShiftController;
use App\Http\Controllers\Api\Planning\AvailabilityController;
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

        // ── Módulo Distribución ─────────────────────────────────────────
        Route::prefix('distribution')->name('distribution.')->group(function () {

            // Pedidos
            Route::get('orders',              [OrderController::class, 'index'])->name('orders.index');
            Route::post('orders',             [OrderController::class, 'store'])->name('orders.store');
            Route::post('orders/export-pending', [OrderController::class, 'exportPending'])->name('orders.export-pending');
            Route::get('orders/{order}',      [OrderController::class, 'show'])->name('orders.show');
            Route::put('orders/{order}',      [OrderController::class, 'update'])->name('orders.update');
            Route::delete('orders/{order}',   [OrderController::class, 'destroy'])->name('orders.destroy');
            Route::patch('orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');

            // Eventos de entrega
            Route::get('orders/{order}/events',  [EventController::class, 'index'])->name('orders.events.index');
            Route::post('orders/{order}/events', [EventController::class, 'store'])->name('orders.events.store');

            // Rutas
            Route::get('routes',                [RouteController::class, 'index'])->name('routes.index');
            Route::post('routes',               [RouteController::class, 'store'])->name('routes.store');
            Route::get('routes/{route}',        [RouteController::class, 'show'])->name('routes.show');
            Route::put('routes/{route}',        [RouteController::class, 'update'])->name('routes.update');
            Route::delete('routes/{route}',     [RouteController::class, 'destroy'])->name('routes.destroy');
            Route::post('routes/{route}/orders',                      [RouteController::class, 'addOrder'])->name('routes.orders.add');
            Route::delete('routes/{route}/orders/{order}',            [RouteController::class, 'removeOrder'])->name('routes.orders.remove');
            Route::patch('routes/{route}/dispatch', [RouteController::class, 'dispatch'])->name('routes.dispatch');
            Route::patch('routes/{route}/complete', [RouteController::class, 'complete'])->name('routes.complete');
            Route::patch('routes/{route}/cancel',   [RouteController::class, 'cancel'])->name('routes.cancel');
        });

        // ── Módulo Planificación ────────────────────────────────────────
        Route::prefix('planning')->name('planning.')->group(function () {

            // Viajes planificados
            Route::get('trips',                     [TripController::class, 'index'])->name('trips.index');
            Route::post('trips',                    [TripController::class, 'store'])->name('trips.store');
            Route::get('trips/{trip}',              [TripController::class, 'show'])->name('trips.show');
            Route::put('trips/{trip}',              [TripController::class, 'update'])->name('trips.update');
            Route::delete('trips/{trip}',           [TripController::class, 'destroy'])->name('trips.destroy');
            Route::patch('trips/{trip}/confirm',    [TripController::class, 'confirm'])->name('trips.confirm');
            Route::patch('trips/{trip}/start',      [TripController::class, 'start'])->name('trips.start');
            Route::patch('trips/{trip}/complete',   [TripController::class, 'complete'])->name('trips.complete');
            Route::patch('trips/{trip}/cancel',     [TripController::class, 'cancel'])->name('trips.cancel');

            // Mantenimiento programado
            Route::get('maintenance',               [MaintenanceController::class, 'index'])->name('maintenance.index');
            Route::post('maintenance',              [MaintenanceController::class, 'store'])->name('maintenance.store');
            Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
            Route::put('maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
            Route::delete('maintenance/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
            Route::patch('maintenance/{maintenance}/start',    [MaintenanceController::class, 'start'])->name('maintenance.start');
            Route::patch('maintenance/{maintenance}/complete', [MaintenanceController::class, 'complete'])->name('maintenance.complete');
            Route::patch('maintenance/{maintenance}/cancel',   [MaintenanceController::class, 'cancel'])->name('maintenance.cancel');

            // Turnos de operadores
            Route::get('shifts',               [ShiftController::class, 'index'])->name('shifts.index');
            Route::post('shifts',              [ShiftController::class, 'store'])->name('shifts.store');
            Route::get('shifts/{shift}',       [ShiftController::class, 'show'])->name('shifts.show');
            Route::put('shifts/{shift}',       [ShiftController::class, 'update'])->name('shifts.update');
            Route::delete('shifts/{shift}',    [ShiftController::class, 'destroy'])->name('shifts.destroy');

            // Disponibilidad y conflictos
            Route::get('availability/vehicles',  [AvailabilityController::class, 'vehicles'])->name('availability.vehicles');
            Route::get('availability/operators', [AvailabilityController::class, 'operators'])->name('availability.operators');
            Route::get('conflicts',              [AvailabilityController::class, 'conflicts'])->name('conflicts');
        });
    });
});

