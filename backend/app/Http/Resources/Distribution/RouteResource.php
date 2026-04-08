<?php

namespace App\Http\Resources\Distribution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'name'               => $this->name,
            'vehicle_id'         => $this->vehicle_id,
            'vehicle'            => $this->whenLoaded('vehicle', fn () => [
                'id'     => $this->vehicle->id,
                'plate'  => $this->vehicle->plate,
                'brand'  => $this->vehicle->brand,
                'model'  => $this->vehicle->model,
                'status' => $this->vehicle->status,
            ]),
            'operator_id'        => $this->operator_id,
            'operator'           => $this->whenLoaded('operator', fn () => [
                'id'     => $this->operator->id,
                'name'   => $this->operator->name,
                'status' => $this->operator->status,
            ]),
            'assignment_id'      => $this->assignment_id,
            'planned_date'       => $this->planned_date?->toDateString(),
            'dispatched_at'      => $this->dispatched_at?->toISOString(),
            'completed_at'       => $this->completed_at?->toISOString(),
            'status'             => $this->status,
            'total_distance_km'  => $this->total_distance_km,
            'notes'              => $this->notes,
            'orders_count'       => $this->whenCounted('orders'),
            'orders'             => OrderResource::collection($this->whenLoaded('orders')),
            'created_at'         => $this->created_at?->toISOString(),
            'updated_at'         => $this->updated_at?->toISOString(),
        ];
    }
}
