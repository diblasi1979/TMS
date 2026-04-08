<?php

namespace App\Http\Controllers\Api\Transport;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assignment\StoreAssignmentRequest;
use App\Http\Resources\VehicleAssignmentResource;
use App\Models\Vehicle;
use App\Models\Operator;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssignmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $assignments = VehicleAssignment::with(['vehicle', 'operator', 'assignedBy'])
            ->latest()
            ->paginate(15);

        return VehicleAssignmentResource::collection($assignments);
    }

    public function active(): AnonymousResourceCollection
    {
        $assignments = VehicleAssignment::with(['vehicle.company', 'operator', 'assignedBy'])
            ->whereNull('released_at')
            ->latest('assigned_at')
            ->get();

        return VehicleAssignmentResource::collection($assignments);
    }

    public function store(StoreAssignmentRequest $request): JsonResponse
    {
        $vehicle  = Vehicle::findOrFail($request->vehicle_id);
        $operator = Operator::findOrFail($request->operator_id);

        // Validaciones de negocio
        if (!$vehicle->isAvailable()) {
            return response()->json(['message' => 'El vehículo no está disponible para ser asignado.'], 422);
        }

        if (!$operator->isAvailable()) {
            return response()->json(['message' => 'El operador no está disponible para ser asignado.'], 422);
        }

        if ($vehicle->activeAssignment()->exists()) {
            return response()->json(['message' => 'El vehículo ya tiene una asignación activa.'], 422);
        }

        if ($operator->activeAssignment()->exists()) {
            return response()->json(['message' => 'El operador ya tiene una asignación activa.'], 422);
        }

        $assignment = DB::transaction(function () use ($vehicle, $operator, $request) {
            $assignment = VehicleAssignment::create([
                'vehicle_id'  => $vehicle->id,
                'operator_id' => $operator->id,
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'notes'       => $request->notes,
            ]);

            $vehicle->update(['status' => 'on_route']);
            $operator->update(['status' => 'on_duty']);

            return $assignment;
        });

        return response()->json(
            new VehicleAssignmentResource($assignment->load(['vehicle', 'operator', 'assignedBy'])),
            201
        );
    }

    public function release(VehicleAssignment $assignment): JsonResponse
    {
        if (!$assignment->isActive()) {
            return response()->json(['message' => 'Esta asignación ya fue liberada.'], 422);
        }

        DB::transaction(function () use ($assignment) {
            $assignment->update(['released_at' => now()]);
            $assignment->vehicle->update(['status' => 'available']);
            $assignment->operator->update(['status' => 'available']);
        });

        return response()->json(
            new VehicleAssignmentResource($assignment->fresh(['vehicle', 'operator', 'assignedBy']))
        );
    }
}
