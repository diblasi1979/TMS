<?php

namespace App\Http\Resources\Distribution;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'company_id'       => $this->company_id,
            'client_id'        => $this->client_id,
            'client'           => $this->whenLoaded('client', fn () => [
                'id'   => $this->client->id,
                'name' => $this->client->name,
            ]),
            'reference_number' => $this->reference_number,
            'description'      => $this->description,
            'weight_kg'        => $this->weight_kg,
            'volume_m3'        => $this->volume_m3,
            'delivery_address' => $this->delivery_address,
            'delivery_lat'     => $this->delivery_lat,
            'delivery_lng'     => $this->delivery_lng,
            'contact_name'     => $this->contact_name,
            'contact_phone'    => $this->contact_phone,
            'requested_date'   => $this->requested_date?->toDateString(),
            'status'           => $this->status,
            'route_id'         => $this->route_id,
            'route'            => $this->whenLoaded('route', fn () => [
                'id'     => $this->route->id,
                'name'   => $this->route->name,
                'status' => $this->route->status,
            ]),
            'sort_order'       => $this->sort_order,
            'notes'            => $this->notes,
            'optimizer_exported_at' => $this->optimizer_exported_at?->toISOString(),
            'optimizer_external_id' => $this->optimizer_external_id,
            'optimizer_last_payload' => $this->optimizer_last_payload,
            'optimizer_last_response' => $this->optimizer_last_response,
            'optimizer_last_error' => $this->optimizer_last_error,
            'events'           => EventResource::collection($this->whenLoaded('events')),
            'created_at'       => $this->created_at?->toISOString(),
            'updated_at'       => $this->updated_at?->toISOString(),
        ];
    }
}
