<?php

namespace App\Http\Resources\Planning;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'company_id'     => $this->company_id,
            'operator_id'    => $this->operator_id,
            'operator'       => $this->whenLoaded('operator', fn () => [
                'id'     => $this->operator->id,
                'name'   => $this->operator->name,
                'status' => $this->operator->status,
            ]),
            'shift_type'     => $this->shift_type,
            'start_datetime' => $this->start_datetime?->toISOString(),
            'end_datetime'   => $this->end_datetime?->toISOString(),
            'notes'          => $this->notes,
            'created_by'     => $this->created_by,
            'created_at'     => $this->created_at?->toISOString(),
            'updated_at'     => $this->updated_at?->toISOString(),
        ];
    }
}
