<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                         => $this->id,
            'plate'                      => $this->plate,
            'type'                       => $this->type,
            'brand'                      => $this->brand,
            'model'                      => $this->model,
            'year'                       => $this->year,
            'color'                      => $this->color,
            'payload_kg'                 => $this->payload_kg,
            'volume_m3'                  => $this->volume_m3,
            'fuel_type'                  => $this->fuel_type,
            'status'                     => $this->status,
            'current_mileage'            => $this->current_mileage,
            'insurance_expiry'           => $this->insurance_expiry?->toDateString(),
            'technical_review_expiry'    => $this->technical_review_expiry?->toDateString(),
            'circulation_permit_expiry'  => $this->circulation_permit_expiry?->toDateString(),
            'notes'                      => $this->notes,
            'is_active'                  => $this->is_active,
            'company_id'                 => $this->company_id,
            'company'                    => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'active_assignment'          => $this->whenLoaded('activeAssignment', fn () => $this->activeAssignment
                ? new VehicleAssignmentResource($this->activeAssignment)
                : null
            ),
            'created_at'                 => $this->created_at?->toISOString(),
        ];
    }
}
