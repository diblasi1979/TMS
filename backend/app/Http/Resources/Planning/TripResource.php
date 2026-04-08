<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'company_id'          => $this->company_id,
            'client_id'           => $this->client_id,
            'client'              => $this->whenLoaded('client', fn () => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ]),
            'trip_number'         => $this->trip_number,
            'origin'              => $this->origin,
            'origin_lat'          => $this->origin_lat,
            'origin_lng'          => $this->origin_lng,
            'destination'         => $this->destination,
            'destination_lat'     => $this->destination_lat,
            'destination_lng'     => $this->destination_lng,
            'scheduled_departure' => $this->scheduled_departure?->toISOString(),
            'scheduled_arrival'   => $this->scheduled_arrival?->toISOString(),
            'actual_departure'    => $this->actual_departure?->toISOString(),
            'actual_arrival'      => $this->actual_arrival?->toISOString(),
            'vehicle_id'          => $this->vehicle_id,
            'vehicle'             => $this->whenLoaded('vehicle', fn () => [
                'id'    => $this->vehicle->id,
                'plate' => $this->vehicle->plate,
                'type'  => $this->vehicle->type,
                'status'=> $this->vehicle->status,
            ]),
            'operator_id'         => $this->operator_id,
            'operator'            => $this->whenLoaded('operator', fn () => [
                'id'     => $this->operator->id,
                'name'   => $this->operator->name,
                'status' => $this->operator->status,
            ]),
            'cargo_type'          => $this->cargo_type,
            'cargo_description'   => $this->cargo_description,
            'weight_kg'           => $this->weight_kg,
            'volume_m3'           => $this->volume_m3,
            'distance_km'         => $this->distance_km,
            'status'              => $this->status,
            'priority'            => $this->priority,
            'notes'               => $this->notes,
            'routes_count'        => $this->whenCounted('routes'),
            'routes'              => $this->whenLoaded('routes', fn () =>
                $this->routes->map(fn ($r) => [
                    'id'          => $r->id,
                    'name'        => $r->name,
                    'planned_date'=> $r->planned_date?->toDateString(),
                    'status'      => $r->status,
                    'orders_count'=> $r->orders_count ?? null,
                ])
            ),
            'created_at'          => $this->created_at?->toISOString(),
            'updated_at'          => $this->updated_at?->toISOString(),
        ];
    }
}
