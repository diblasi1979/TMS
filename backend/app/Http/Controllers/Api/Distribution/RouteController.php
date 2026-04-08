<?php

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\StoreRouteRequest;
use App\Http\Requests\Distribution\UpdateRouteRequest;
use App\Http\Resources\Distribution\RouteResource;
use App\Models\DeliveryOrder;
use App\Models\DeliveryRoute;
use App\Models\Operator;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DeliveryRoute::withCount('orders')
            ->with(['vehicle', 'operator', 'tripPlan'])
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('planned_date')) {
            $query->whereDate('planned_date', $request->planned_date);
        }
        if ($request->filled('vehicle_id')) {
            $query->where('vehicle_id', $request->vehicle_id);
        }
        if ($request->filled('trip_plan_id')) {
            $query->where('trip_plan_id', $request->trip_plan_id);
        }

        return RouteResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $route = DeliveryRoute::create([
            ...$request->validated(),
            'company_id' => Auth::user()->company_id,
        ]);

        return response()->json(
            new RouteResource($route->load(['vehicle', 'operator'])),
            201
        );
    }

    public function show(DeliveryRoute $route): RouteResource
    {
        return new RouteResource($route->load(['vehicle', 'operator', 'tripPlan', 'orders.client']));
    }

    public function update(UpdateRouteRequest $request, DeliveryRoute $route): JsonResponse
    {
        if ($route->isInTransit() || $route->isCompleted() || $route->isCancelled()) {
            return response()->json(['message' => 'No se puede modificar una ruta en tránsito, completada o cancelada.'], 422);
        }
        $route->update($request->validated());
        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator'])));
    }

    public function destroy(DeliveryRoute $route): JsonResponse
    {
        if (!$route->isDraft()) {
            return response()->json(['message' => 'Solo se pueden eliminar rutas en borrador.'], 422);
        }
        // Devolver los pedidos a pendiente
        $route->orders()->update(['status' => 'pending', 'route_id' => null, 'sort_order' => null]);
        $route->delete();
        return response()->json(null, 204);
    }

    public function addOrder(Request $request, DeliveryRoute $route): JsonResponse
    {
        $request->validate(['order_id' => ['required', 'integer', 'exists:delivery_orders,id']]);

        if ($route->isInTransit() || $route->isCompleted() || $route->isCancelled()) {
            return response()->json(['message' => 'No se pueden agregar pedidos a esta ruta.'], 422);
        }

        $order = DeliveryOrder::findOrFail($request->order_id);

        if (!$order->isPending()) {
            return response()->json(['message' => 'El pedido no está en estado pendiente.'], 422);
        }

        // Verificar capacidad si el vehículo tiene límites definidos
        if ($route->vehicle_id && $route->vehicle) {
            $vehicle = $route->vehicle;
            if ($vehicle->payload_kg !== null) {
                $totalWeight = $route->orders()->sum('weight_kg') + ($order->weight_kg ?? 0);
                if ($totalWeight > $vehicle->payload_kg) {
                    return response()->json(['message' => "Peso total ({$totalWeight} kg) supera la capacidad del vehículo ({$vehicle->payload_kg} kg)."], 422);
                }
            }
            if ($vehicle->volume_m3 !== null) {
                $totalVolume = $route->orders()->sum('volume_m3') + ($order->volume_m3 ?? 0);
                if ($totalVolume > $vehicle->volume_m3) {
                    return response()->json(['message' => "Volumen total ({$totalVolume} m³) supera la capacidad del vehículo ({$vehicle->volume_m3} m³)."], 422);
                }
            }
        }

        $sortOrder = $route->orders()->max('sort_order') + 1;
        $order->update(['status' => 'scheduled', 'route_id' => $route->id, 'sort_order' => $sortOrder]);

        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator', 'orders.client'])));
    }

    public function removeOrder(DeliveryRoute $route, DeliveryOrder $order): JsonResponse
    {
        if ($route->isInTransit() || $route->isCompleted()) {
            return response()->json(['message' => 'No se pueden quitar pedidos de una ruta en tránsito o completada.'], 422);
        }

        if ($order->route_id !== $route->id) {
            return response()->json(['message' => 'El pedido no pertenece a esta ruta.'], 422);
        }

        $order->update(['status' => 'pending', 'route_id' => null, 'sort_order' => null]);
        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator', 'orders.client'])));
    }

    public function dispatch(DeliveryRoute $route): JsonResponse
    {
        if (!$route->isPlanned()) {
            return response()->json(['message' => 'Solo se puede despachar una ruta planificada.'], 422);
        }
        if (!$route->vehicle_id || !$route->operator_id) {
            return response()->json(['message' => 'La ruta debe tener vehículo y operador asignados.'], 422);
        }

        $scheduledOrders = $route->orders()->where('status', 'scheduled')->count();
        if ($scheduledOrders === 0) {
            return response()->json(['message' => 'La ruta no tiene pedidos programados.'], 422);
        }

        $vehicle  = Vehicle::findOrFail($route->vehicle_id);
        $operator = Operator::findOrFail($route->operator_id);

        if (!$vehicle->isAvailable()) {
            return response()->json(['message' => 'El vehículo no está disponible.'], 422);
        }
        if (!$operator->isAvailable()) {
            return response()->json(['message' => 'El operador no está disponible.'], 422);
        }

        DB::transaction(function () use ($route, $vehicle, $operator) {
            $assignment = VehicleAssignment::create([
                'vehicle_id'  => $vehicle->id,
                'operator_id' => $operator->id,
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
                'notes'       => "Ruta de distribución: {$route->name}",
            ]);

            $vehicle->update(['status' => 'on_route']);
            $operator->update(['status' => 'on_duty']);

            $route->update([
                'status'        => 'in_transit',
                'assignment_id' => $assignment->id,
                'dispatched_at' => now(),
            ]);

            $route->orders()->where('status', 'scheduled')->update(['status' => 'in_transit']);
        });

        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator', 'orders'])));
    }

    public function complete(DeliveryRoute $route): JsonResponse
    {
        if (!$route->isInTransit()) {
            return response()->json(['message' => 'Solo se puede cerrar una ruta en tránsito.'], 422);
        }

        DB::transaction(function () use ($route) {
            if ($route->assignment_id) {
                $assignment = VehicleAssignment::find($route->assignment_id);
                if ($assignment && $assignment->isActive()) {
                    $assignment->update(['released_at' => now()]);
                    if ($route->vehicle) $route->vehicle->update(['status' => 'available']);
                    if ($route->operator) $route->operator->update(['status' => 'available']);
                }
            }

            $route->update(['status' => 'completed', 'completed_at' => now()]);

            // Pedidos aún en tránsito los marcamos como fallidos automáticamente
            $route->orders()->where('status', 'in_transit')->update(['status' => 'failed']);
        });

        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator', 'orders'])));
    }

    public function cancel(DeliveryRoute $route): JsonResponse
    {
        if ($route->isInTransit() || $route->isCompleted()) {
            return response()->json(['message' => 'No se puede cancelar una ruta en tránsito o completada.'], 422);
        }

        $route->orders()->where('status', 'scheduled')->update([
            'status'     => 'pending',
            'route_id'   => null,
            'sort_order' => null,
        ]);
        $route->update(['status' => 'cancelled']);

        return response()->json(new RouteResource($route->fresh()->load(['vehicle', 'operator'])));
    }
}
