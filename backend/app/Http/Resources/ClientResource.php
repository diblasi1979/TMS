<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'address'    => $this->address,
            'is_active'  => $this->is_active,
            'company_id' => $this->company_id,
            'company'    => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
