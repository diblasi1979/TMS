<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'company_id'              => $this->company_id,
            'vehicle_id'              => $this->vehicle_id,
            'vehicle'                 => $this->whenLoaded('vehicle', fn () => [
                'id'    => $this->vehicle->id,
                'plate' => $this->vehicle->plate,
                'brand' => $this->vehicle->brand,
                'model' => $this->vehicle->model,
                'status'=> $this->vehicle->status,
            ]),
            'maintenance_type'        => $this->maintenance_type,
            'description'             => $this->description,
            'scheduled_date'          => $this->scheduled_date?->toDateString(),
            'estimated_duration_days' => $this->estimated_duration_days,
            'actual_start'            => $this->actual_start?->toDateString(),
            'actual_end'              => $this->actual_end?->toDateString(),
            'workshop'                => $this->workshop,
            'estimated_cost'          => $this->estimated_cost,
            'actual_cost'             => $this->actual_cost,
            'mileage_at_service'      => $this->mileage_at_service,
            'next_service_km'         => $this->next_service_km,
            'next_service_date'       => $this->next_service_date?->toDateString(),
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'created_at'              => $this->created_at?->toISOString(),
            'updated_at'              => $this->updated_at?->toISOString(),
        ];
    }
}
