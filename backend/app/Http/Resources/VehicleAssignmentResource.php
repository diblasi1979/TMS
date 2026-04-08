<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'vehicle_id'  => $this->vehicle_id,
            'operator_id' => $this->operator_id,
            'vehicle'     => $this->whenLoaded('vehicle',  fn () => new VehicleResource($this->vehicle)),
            'operator'    => $this->whenLoaded('operator', fn () => new OperatorResource($this->operator)),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => [
                'id'   => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ]),
            'assigned_at' => $this->assigned_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'is_active'   => $this->isActive(),
            'notes'       => $this->notes,
            'created_at'  => $this->created_at?->toISOString(),
        ];
    }
}
