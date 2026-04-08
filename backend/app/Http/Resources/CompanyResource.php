<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'tax_id'     => $this->tax_id,
            'address'    => $this->address,
            'phone'      => $this->phone,
            'email'      => $this->email,
            'is_active'  => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
