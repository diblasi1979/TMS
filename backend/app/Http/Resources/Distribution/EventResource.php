<?php

namespace App\Http\Resources\Distribution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'delivery_order_id' => $this->delivery_order_id,
            'route_id'          => $this->route_id,
            'order'             => $this->whenLoaded('order', fn () => [
                'id'               => $this->order->id,
                'reference_number' => $this->order->reference_number,
                'status'           => $this->order->status,
            ]),
            'event_type'  => $this->event_type,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'lat'         => $this->lat,
            'lng'         => $this->lng,
            'notes'       => $this->notes,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
