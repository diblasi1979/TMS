<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVehicleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id'                 => ['sometimes', 'nullable', 'exists:companies,id'],
            'plate'                      => ['sometimes', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($this->route('vehicle'))],
            'type'                       => ['sometimes', Rule::in(Vehicle::TYPES)],
            'brand'                      => ['sometimes', 'string', 'max:100'],
            'model'                      => ['sometimes', 'string', 'max:100'],
            'year'                       => ['sometimes', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'color'                      => ['sometimes', 'nullable', 'string', 'max:50'],
            'payload_kg'                 => ['sometimes', 'nullable', 'integer', 'min:0'],
            'volume_m3'                  => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fuel_type'                  => ['sometimes', Rule::in(Vehicle::FUEL_TYPES)],
            'current_mileage'            => ['sometimes', 'integer', 'min:0'],
            'insurance_expiry'           => ['sometimes', 'nullable', 'date'],
            'technical_review_expiry'    => ['sometimes', 'nullable', 'date'],
            'circulation_permit_expiry'  => ['sometimes', 'nullable', 'date'],
            'notes'                      => ['sometimes', 'nullable', 'string'],
            'is_active'                  => ['sometimes', 'boolean'],
        ];
    }
}
