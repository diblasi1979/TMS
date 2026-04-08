<?php

namespace App\Http\Controllers\Api\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\StoreMaintenanceRequest;
use App\Http\Requests\Planning\UpdateMaintenanceRequest;
use App\Http\Resources\Planning\MaintenanceResource;
use App\Models\MaintenanceSchedule;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class MaintenanceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = MaintenanceSchedule::with('vehicle')
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('from')) {
            $query->where('scheduled_date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('scheduled_date', '<=', $request->to);
        }

        return MaintenanceResource::collection($query->latest('scheduled_date')->paginate(20));
    }

    public function store(StoreMaintenanceRequest $request): JsonResponse
    {
        // No puede haber otro mantenimiento in_progress para el mismo vehículo
        $inProgress = MaintenanceSchedule::where('vehicle_id', $request->vehicle_id)
            ->where('status', 'in_progress')
            ->exists();

        if ($inProgress) {
            return response()->json(['message' => 'El vehículo ya tiene un mantenimiento en curso.'], 422);
        }

        $maintenance = MaintenanceSchedule::create([
            ...$request->validated(),
            'company_id' => Auth::user()->company_id,
        ]);

        return response()->json(new MaintenanceResource($maintenance->load('vehicle')), 201);
    }

    public function show(MaintenanceSchedule $maintenance): MaintenanceResource
    {
        return new MaintenanceResource($maintenance->load('vehicle'));
    }

    public function update(UpdateMaintenanceRequest $request, MaintenanceSchedule $maintenance): MaintenanceResource
    {
        $maintenance->update($request->validated());
        return new MaintenanceResource($maintenance->fresh()->load('vehicle'));
    }

    public function destroy(MaintenanceSchedule $maintenance): JsonResponse
    {
        if (!$maintenance->canBeDeleted()) {
            return response()->json(['message' => 'Solo se pueden eliminar mantenimientos programados.'], 422);
        }
        $maintenance->delete();
        return response()->json(null, 204);
    }

    public function start(MaintenanceSchedule $maintenance): JsonResponse
    {
        if (!$maintenance->isScheduled()) {
            return response()->json(['message' => 'Solo se pueden iniciar mantenimientos programados.'], 422);
        }

        $maintenance->update([
            'status'       => 'in_progress',
            'actual_start' => now()->toDateString(),
        ]);

        // Pasar vehículo a mantenimiento
        Vehicle::find($maintenance->vehicle_id)?->update(['status' => 'maintenance']);

        return response()->json(new MaintenanceResource($maintenance->fresh()->load('vehicle')));
    }

    public function complete(MaintenanceSchedule $maintenance): JsonResponse
    {
        if (!$maintenance->isInProgress()) {
            return response()->json(['message' => 'Solo se pueden completar mantenimientos en curso.'], 422);
        }

        $updates = [
            'status'     => 'completed',
            'actual_end' => now()->toDateString(),
        ];

        $maintenance->update($updates);

        // Actualizar kilometraje del vehículo si se registró mileage_at_service
        $vehicle = Vehicle::find($maintenance->vehicle_id);
        if ($vehicle) {
            $vehicleUpdates = ['status' => 'available'];
            if ($maintenance->mileage_at_service) {
                $vehicleUpdates['current_mileage'] = $maintenance->mileage_at_service;
            }
            $vehicle->update($vehicleUpdates);
        }

        return response()->json(new MaintenanceResource($maintenance->fresh()->load('vehicle')));
    }

    public function cancel(MaintenanceSchedule $maintenance): JsonResponse
    {
        if (!$maintenance->isScheduled()) {
            return response()->json(['message' => 'Solo se pueden cancelar mantenimientos programados.'], 422);
        }
        $maintenance->update(['status' => 'cancelled']);
        return response()->json(new MaintenanceResource($maintenance->fresh()->load('vehicle')));
    }
}
