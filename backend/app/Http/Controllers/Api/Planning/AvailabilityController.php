<?php

namespace App\Http\Controllers\Api\Planning;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceSchedule;
use App\Models\OperatorShift;
use App\Models\TripPlan;
use App\Models\Vehicle;
use App\Models\Operator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AvailabilityController extends Controller
{
    public function vehicles(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from      = $request->from;
        $to        = $request->to;
        $companyId = Auth::user()->company_id;

        $allVehicles = Vehicle::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $available   = [];
        $unavailable = [];

        foreach ($allVehicles as $vehicle) {
            $reason = null;

            // En mantenimiento programado en el período
            $maintenance = MaintenanceSchedule::where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('scheduled_date', '<=', $to)
                ->whereRaw("DATE_ADD(scheduled_date, INTERVAL estimated_duration_days DAY) >= ?", [$from])
                ->first();

            if ($maintenance) {
                $reason = ['type' => 'maintenance', 'maintenance_id' => $maintenance->id];
            }

            // Viaje confirmado o en curso en el período
            if (!$reason) {
                $trip = TripPlan::where('vehicle_id', $vehicle->id)
                    ->whereIn('status', ['confirmed', 'in_progress'])
                    ->where('scheduled_departure', '<=', $to)
                    ->where('scheduled_arrival', '>=', $from)
                    ->first();

                if ($trip) {
                    $reason = ['type' => 'trip', 'trip_id' => $trip->id];
                }
            }

            if ($reason) {
                $unavailable[] = array_merge(
                    ['id' => $vehicle->id, 'plate' => $vehicle->plate, 'type' => $vehicle->type],
                    $reason
                );
            } else {
                $available[] = [
                    'id'         => $vehicle->id,
                    'plate'      => $vehicle->plate,
                    'type'       => $vehicle->type,
                    'payload_kg' => $vehicle->payload_kg,
                    'volume_m3'  => $vehicle->volume_m3,
                    'status'     => $vehicle->status,
                ];
            }
        }

        return response()->json(compact('available', 'unavailable'));
    }

    public function operators(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
        ]);

        $from      = $request->from;
        $to        = $request->to;
        $companyId = Auth::user()->company_id;

        $allOperators = Operator::where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $available   = [];
        $unavailable = [];

        foreach ($allOperators as $operator) {
            $reason = null;

            // Turno bloqueante en el período
            $shift = OperatorShift::where('operator_id', $operator->id)
                ->whereIn('shift_type', ['rest', 'vacation', 'leave'])
                ->where('start_datetime', '<=', $to)
                ->where('end_datetime', '>=', $from)
                ->first();

            if ($shift) {
                $reason = ['type' => 'shift', 'shift_id' => $shift->id, 'shift_type' => $shift->shift_type];
            }

            // Viaje confirmado o en curso en el período
            if (!$reason) {
                $trip = TripPlan::where('operator_id', $operator->id)
                    ->whereIn('status', ['confirmed', 'in_progress'])
                    ->where('scheduled_departure', '<=', $to)
                    ->where('scheduled_arrival', '>=', $from)
                    ->first();

                if ($trip) {
                    $reason = ['type' => 'trip', 'trip_id' => $trip->id];
                }
            }

            if ($reason) {
                $unavailable[] = array_merge(
                    ['id' => $operator->id, 'name' => $operator->name],
                    $reason
                );
            } else {
                $available[] = [
                    'id'           => $operator->id,
                    'name'         => $operator->name,
                    'license_type' => $operator->license_type,
                    'status'       => $operator->status,
                ];
            }
        }

        return response()->json(compact('available', 'unavailable'));
    }

    public function conflicts(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        $conflicts = [];

        // Viajes confirmados cuyos vehículos tienen mantenimiento en el mismo período
        $trips = TripPlan::with('vehicle')
            ->where('company_id', $companyId)
            ->whereIn('status', ['confirmed', 'in_progress'])
            ->whereNotNull('vehicle_id')
            ->get();

        foreach ($trips as $trip) {
            $maintenance = MaintenanceSchedule::where('vehicle_id', $trip->vehicle_id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->where('scheduled_date', '<=', $trip->scheduled_arrival->toDateString())
                ->whereRaw("DATE_ADD(scheduled_date, INTERVAL estimated_duration_days DAY) >= ?", [$trip->scheduled_departure->toDateString()])
                ->first();

            if ($maintenance) {
                $conflicts[] = [
                    'type'           => 'vehicle_maintenance_vs_trip',
                    'trip_id'        => $trip->id,
                    'trip_number'    => $trip->trip_number,
                    'vehicle_plate'  => $trip->vehicle->plate ?? null,
                    'maintenance_id' => $maintenance->id,
                    'message'        => "El vehículo del viaje {$trip->trip_number} tiene mantenimiento #{$maintenance->id} solapado.",
                ];
            }
        }

        // Operadores con turno bloqueante y viaje asignado en el mismo período
        foreach ($trips as $trip) {
            if (!$trip->operator_id) continue;

            $shift = OperatorShift::where('operator_id', $trip->operator_id)
                ->whereIn('shift_type', ['rest', 'vacation', 'leave'])
                ->where('start_datetime', '<', $trip->scheduled_arrival)
                ->where('end_datetime', '>', $trip->scheduled_departure)
                ->first();

            if ($shift) {
                $conflicts[] = [
                    'type'        => 'operator_shift_vs_trip',
                    'trip_id'     => $trip->id,
                    'trip_number' => $trip->trip_number,
                    'operator_id' => $trip->operator_id,
                    'shift_id'    => $shift->id,
                    'shift_type'  => $shift->shift_type,
                    'message'     => "El operador del viaje {$trip->trip_number} tiene turno de {$shift->shift_type} solapado.",
                ];
            }
        }

        return response()->json(['conflicts' => $conflicts, 'total' => count($conflicts)]);
    }
}
