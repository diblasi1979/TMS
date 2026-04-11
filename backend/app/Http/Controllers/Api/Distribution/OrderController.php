<?php

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\ExportPendingOrdersRequest;
use App\Http\Requests\Distribution\StoreOrderRequest;
use App\Http\Requests\Distribution\UpdateOrderRequest;
use App\Http\Resources\Distribution\OrderResource;
use App\Models\DeliveryOrder;
use App\Services\Distribution\PendingOrderExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DeliveryOrder::with(['client', 'route'])
            ->where('company_id', Auth::user()->company_id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }
        if ($request->filled('requested_date')) {
            $query->whereDate('requested_date', $request->requested_date);
        }
        if ($request->filled('route_id')) {
            $query->where('route_id', $request->route_id);
        }

        return OrderResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = DeliveryOrder::create([
            ...$request->validated(),
            'company_id' => Auth::user()->company_id,
        ]);

        return response()->json(
            new OrderResource($order->load('client')),
            201
        );
    }

    public function show(DeliveryOrder $order): OrderResource
    {
        return new OrderResource($order->load(['client', 'route', 'events']));
    }

    public function update(UpdateOrderRequest $request, DeliveryOrder $order): OrderResource
    {
        if ($order->isInTransit() || $order->isDelivered()) {
            abort(422, 'No se puede modificar un pedido en tránsito o entregado.');
        }

        $order->update($request->validated());
        return new OrderResource($order->fresh()->load(['client', 'route']));
    }

    public function destroy(DeliveryOrder $order): JsonResponse
    {
        if (!$order->canBeDeleted()) {
            return response()->json(['message' => 'Solo se pueden eliminar pedidos en estado pendiente o cancelado.'], 422);
        }
        $order->delete();
        return response()->json(null, 204);
    }

    public function cancel(DeliveryOrder $order): JsonResponse
    {
        if (!$order->canBeCancelled()) {
            return response()->json(['message' => 'El pedido no puede ser cancelado en su estado actual.'], 422);
        }
        $order->update(['status' => 'cancelled', 'route_id' => null, 'sort_order' => null]);
        return response()->json(new OrderResource($order->fresh()->load('client')));
    }

    public function exportPending(ExportPendingOrdersRequest $request, PendingOrderExporter $exporter): JsonResponse
    {
        $ordersQuery = DeliveryOrder::query()
            ->where('company_id', Auth::user()->company_id)
            ->where('status', 'pending')
            ->whereNull('optimizer_exported_at');

        if ($request->filled('client_id')) {
            $ordersQuery->where('client_id', $request->integer('client_id'));
        }

        if ($request->filled('requested_date')) {
            $ordersQuery->whereDate('requested_date', $request->input('requested_date'));
        }

        $orders = $ordersQuery->orderBy('id')->get();

        $result = $exporter->export($orders);

        return response()->json([
            'message' => $result['sent_count'] > 0
                ? 'Pedidos pendientes enviados al servicio externo.'
                : 'No se enviaron pedidos pendientes al servicio externo.',
            'filters' => $request->validated(),
            ...$result,
        ]);
    }
}
