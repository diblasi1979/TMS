<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleStatusRequest;
use App\Http\Resources\VehicleResource;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VehicleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Vehicle::with(['company', 'activeAssignment.operator'])
            ->when($request->status,     fn ($q) => $q->where('status', $request->status))
            ->when($request->type,       fn ($q) => $q->where('type', $request->type))
            ->when($request->company_id, fn ($q) => $q->where('company_id', $request->company_id))
            ->latest();

        return VehicleResource::collection($query->paginate(15));
    }

    public function store(StoreVehicleRequest $request): JsonResponse
    {
        $vehicle = Vehicle::create($request->validated());

        return response()->json(
            new VehicleResource($vehicle->refresh()->load('company')),
            201
        );
    }

    public function show(Vehicle $vehicle): VehicleResource
    {
        return new VehicleResource(
            $vehicle->load(['company', 'activeAssignment.operator', 'activeAssignment.assignedBy'])
        );
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): VehicleResource
    {
        $vehicle->update($request->validated());

        return new VehicleResource($vehicle->fresh(['company', 'activeAssignment.operator']));
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        if ($vehicle->activeAssignment()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un vehículo con una asignación activa.',
            ], 422);
        }

        $vehicle->delete();

        return response()->json(['message' => 'Vehículo eliminado.']);
    }

    public function updateStatus(UpdateVehicleStatusRequest $request, Vehicle $vehicle): VehicleResource
    {
        // Transiciones permitidas
        $allowed = [
            'available'   => ['on_route', 'maintenance', 'inactive'],
            'on_route'    => ['available'],
            'maintenance' => ['available', 'inactive'],
            'inactive'    => [],
        ];

        $newStatus = $request->status;

        if (!in_array($newStatus, $allowed[$vehicle->status] ?? [])) {
            abort(422, "Transición de estado '{$vehicle->status}' → '{$newStatus}' no permitida.");
        }

        $vehicle->update(['status' => $newStatus]);

        return new VehicleResource($vehicle->fresh(['company', 'activeAssignment.operator']));
    }

    public function expiring(): AnonymousResourceCollection
    {
        $threshold = now()->addDays(30);

        $vehicles = Vehicle::with('company')
            ->where('is_active', true)
            ->where(function ($q) use ($threshold) {
                $q->where('insurance_expiry', '<=', $threshold)
                  ->orWhere('technical_review_expiry', '<=', $threshold)
                  ->orWhere('circulation_permit_expiry', '<=', $threshold);
            })
            ->get();

        return VehicleResource::collection($vehicles);
    }
}
