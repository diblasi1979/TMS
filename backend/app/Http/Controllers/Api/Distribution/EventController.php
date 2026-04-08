<?php

namespace App\Http\Controllers\Api\Distribution;

use App\Http\Controllers\Controller;
use App\Http\Requests\Distribution\StoreEventRequest;
use App\Http\Resources\Distribution\EventResource;
use App\Models\DeliveryEvent;
use App\Models\DeliveryOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EventController extends Controller
{
    public function index(DeliveryOrder $order): AnonymousResourceCollection
    {
        return EventResource::collection(
            $order->events()->latest('occurred_at')->get()
        );
    }

    public function store(StoreEventRequest $request, DeliveryOrder $order): JsonResponse
    {
        if (!$order->isInTransit()) {
            return response()->json(['message' => 'Solo se pueden registrar eventos en pedidos en tránsito.'], 422);
        }

        $event = DB::transaction(function () use ($request, $order) {
            $event = DeliveryEvent::create([
                ...$request->validated(),
                'delivery_order_id' => $order->id,
                'route_id'          => $order->route_id,
            ]);

            // Actualizar estado del pedido según el evento
            $newStatus = match ($request->event_type) {
                'delivered'       => 'delivered',
                'failed'          => 'failed',
                'retry_scheduled' => 'pending',
                default           => null,
            };

            if ($newStatus) {
                $updates = ['status' => $newStatus];
                if ($newStatus === 'pending') {
                    $updates['route_id']   = null;
                    $updates['sort_order'] = null;
                }
                $order->update($updates);
            }

            return $event;
        });

        return response()->json(
            new EventResource($event->load('order')),
            201
        );
    }
}
