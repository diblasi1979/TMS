<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                => $this->id,
            'name'              => $this->name,
            'document_number'   => $this->document_number,
            'email'             => $this->email,
            'phone'             => $this->phone,
            'address'           => $this->address,
            'license_number'    => $this->license_number,
            'license_type'      => $this->license_type,
            'license_expiry'    => $this->license_expiry?->toDateString(),
            'emergency_contact' => $this->emergency_contact,
            'emergency_phone'   => $this->emergency_phone,
            'status'            => $this->status,
            'notes'             => $this->notes,
            'is_active'         => $this->is_active,
            'company_id'        => $this->company_id,
            'company'           => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'active_assignment' => $this->whenLoaded('activeAssignment', fn () => $this->activeAssignment
                ? new VehicleAssignmentResource($this->activeAssignment)
                : null
            ),
            'license_expiring_soon' => $this->isLicenseExpiringSoon(),
            'created_at'            => $this->created_at?->toISOString(),
        ];
    }
}
