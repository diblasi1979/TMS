<?php

namespace App\Http\Controllers\Api\Planning;

use App\Http\Controllers\Controller;
use App\Http\Requests\Planning\ConfirmTripRequest;
use App\Http\Requests\Planning\StoreTripRequest;
use App\Http\Requests\Planning\UpdateTripRequest;
use App\Http\Resources\Planning\TripResource;
use App\Models\MaintenanceSchedule;
use App\Models\OperatorShift;
use App\Models\TripPlan;
use App\Models\Vehicle;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class TripController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = TripPlan::with(['client', 'vehicle', 'operator'])
            ->withCount('routes')
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            count($statuses) > 1
                ? $query->whereIn('status', $statuses)
                : $query->where('status', $statuses[0]);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('operator_id')) {
            $query->where('operator_id', $request->operator_id);
        }
        if ($request->filled('from')) {
            $query->where('scheduled_departure', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->where('scheduled_departure', '<=', $request->to);
        }

        return TripResource::collection($query->latest('scheduled_departure')->paginate((int) ($request->per_page ?? 20)));
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $trip = TripPlan::create([
            ...$request->validated(),
            'company_id' => Auth::user()->company_id,
        ]);

        return response()->json(new TripResource($trip->load(['client', 'vehicle', 'operator'])), 201);
    }

    public function show(TripPlan $trip): TripResource
    {
        return new TripResource($trip->load(['client', 'vehicle', 'operator', 'routes']));
    }

    public function update(UpdateTripRequest $request, TripPlan $trip): JsonResponse
    {
        if (in_array($trip->status, ['in_progress', 'completed'])) {
            return response()->json(['message' => 'No se puede modificar un viaje en curso o completado.'], 422);
        }

        $trip->update($request->validated());
        return response()->json(new TripResource($trip->fresh()->load(['client', 'vehicle', 'operator'])));
    }

    public function destroy(TripPlan $trip): JsonResponse
    {
        if (!$trip->canBeDeleted()) {
            return response()->json(['message' => 'Solo se pueden eliminar viajes en estado borrador.'], 422);
        }
        $trip->delete();
        return response()->json(null, 204);
    }

    public function confirm(ConfirmTripRequest $request, TripPlan $trip): JsonResponse
    {
        if (!$trip->isDraft()) {
            return response()->json(['message' => 'Solo se pueden confirmar viajes en estado borrador.'], 422);
        }

        $vehicleId  = $request->validated('vehicle_id');
        $operatorId = $request->validated('operator_id');
        $conflicts  = $this->detectConflicts($vehicleId, $operatorId, $trip->scheduled_departure, $trip->scheduled_arrival, $trip->id);

        if (!empty($conflicts)) {
            return response()->json(['message' => 'Se detectaron conflictos de recursos.', 'conflicts' => $conflicts], 422);
        }

        $trip->update([
            'vehicle_id'  => $vehicleId,
            'operator_id' => $operatorId,
            'status'      => 'confirmed',
        ]);

        return response()->json(new TripResource($trip->fresh()->load(['vehicle', 'operator'])));
    }

    public function start(Request $request, TripPlan $trip): JsonResponse
    {
        if (!$trip->isConfirmed()) {
            return response()->json(['message' => 'Solo se pueden iniciar viajes confirmados.'], 422);
        }

        $trip->update([
            'status'           => 'in_progress',
            'actual_departure' => now(),
        ]);

        // Actualizar estado de vehículo y operador
        if ($trip->vehicle_id) {
            Vehicle::find($trip->vehicle_id)?->update(['status' => 'on_route']);
        }
        if ($trip->operator_id) {
            Operator::find($trip->operator_id)?->update(['status' => 'on_duty']);
        }

        return response()->json(new TripResource($trip->fresh()->load(['vehicle', 'operator'])));
    }

    public function complete(Request $request, TripPlan $trip): JsonResponse
    {
        if ($trip->status !== 'in_progress') {
            return response()->json(['message' => 'Solo se pueden completar viajes en curso.'], 422);
        }

        $trip->update([
            'status'         => 'completed',
            'actual_arrival' => now(),
        ]);

        // Liberar vehículo y operador
        if ($trip->vehicle_id) {
            Vehicle::find($trip->vehicle_id)?->update(['status' => 'available']);
        }
        if ($trip->operator_id) {
            Operator::find($trip->operator_id)?->update(['status' => 'available']);
        }

        return response()->json(new TripResource($trip->fresh()->load(['vehicle', 'operator'])));
    }

    public function cancel(TripPlan $trip): JsonResponse
    {
        if (!$trip->canBeCancelled()) {
            return response()->json(['message' => 'El viaje no puede ser cancelado en su estado actual.'], 422);
        }
        $trip->update(['status' => 'cancelled']);
        return response()->json(new TripResource($trip->fresh()->load(['vehicle', 'operator'])));
    }

    private function detectConflicts(int $vehicleId, int $operatorId, $from, $to, int $excludeTripId = 0): array
    {
        $conflicts = [];

        // Mantenimiento solapado para el vehículo
        $maintenance = MaintenanceSchedule::where('vehicle_id', $vehicleId)
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->where('scheduled_date', '<=', $to->toDateString())
            ->whereRaw("DATE(scheduled_date, '+' || estimated_duration_days || ' days') >= ?", [$from->toDateString()])
            ->first();

        if ($maintenance) {
            $conflicts[] = [
                'type'           => 'vehicle_maintenance',
                'message'        => 'El vehículo tiene mantenimiento programado en ese período.',
                'maintenance_id' => $maintenance->id,
            ];
        }

        // Viaje solapado para el vehículo
        $vehicleTrip = TripPlan::where('vehicle_id', $vehicleId)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('id', '!=', $excludeTripId)
            ->where('scheduled_departure', '<', $to)
            ->where('scheduled_arrival', '>', $from)
            ->first();

        if ($vehicleTrip) {
            $conflicts[] = [
                'type'    => 'vehicle_trip',
                'message' => 'El vehículo ya está asignado a otro viaje en ese período.',
                'trip_id' => $vehicleTrip->id,
            ];
        }

        // Turno bloqueante para el operador
        $blockedShift = OperatorShift::where('operator_id', $operatorId)
            ->whereIn('shift_type', ['rest', 'vacation', 'leave'])
            ->where('start_datetime', '<', $to)
            ->where('end_datetime', '>', $from)
            ->first();

        if ($blockedShift) {
            $conflicts[] = [
                'type'     => 'operator_shift',
                'message'  => 'El operador tiene un turno de descanso o licencia en ese período.',
                'shift_id' => $blockedShift->id,
            ];
        }

        // Viaje solapado para el operador
        $operatorTrip = TripPlan::where('operator_id', $operatorId)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->where('id', '!=', $excludeTripId)
            ->where('scheduled_departure', '<', $to)
            ->where('scheduled_arrival', '>', $from)
            ->first();

        if ($operatorTrip) {
            $conflicts[] = [
                'type'    => 'operator_trip',
                'message' => 'El operador ya está asignado a otro viaje en ese período.',
                'trip_id' => $operatorTrip->id,
            ];
        }

        return $conflicts;
    }
}
