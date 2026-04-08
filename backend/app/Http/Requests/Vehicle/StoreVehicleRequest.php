<?php

namespace App\Http\Requests\Vehicle;

use App\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id'                 => ['nullable', 'exists:companies,id'],
            'plate'                      => ['required', 'string', 'max:20', 'unique:vehicles,plate'],
            'type'                       => ['required', Rule::in(Vehicle::TYPES)],
            'brand'                      => ['required', 'string', 'max:100'],
            'model'                      => ['required', 'string', 'max:100'],
            'year'                       => ['required', 'integer', 'min:1990', 'max:' . (date('Y') + 1)],
            'color'                      => ['nullable', 'string', 'max:50'],
            'payload_kg'                 => ['nullable', 'integer', 'min:0'],
            'volume_m3'                  => ['nullable', 'numeric', 'min:0'],
            'fuel_type'                  => ['required', Rule::in(Vehicle::FUEL_TYPES)],
            'current_mileage'            => ['nullable', 'integer', 'min:0'],
            'insurance_expiry'           => ['nullable', 'date'],
            'technical_review_expiry'    => ['nullable', 'date'],
            'circulation_permit_expiry'  => ['nullable', 'date'],
            'notes'                      => ['nullable', 'string'],
            'is_active'                  => ['boolean'],
        ];
    }
}
