<?php

namespace App\Http\Requests\Planning;

use App\Models\MaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id'              => ['required', 'integer', 'exists:vehicles,id'],
            'maintenance_type'        => ['required', 'string', 'in:' . implode(',', MaintenanceSchedule::TYPES)],
            'description'             => ['required', 'string'],
            'scheduled_date'          => ['required', 'date'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1'],
            'workshop'                => ['nullable', 'string', 'max:255'],
            'estimated_cost'          => ['nullable', 'numeric', 'min:0'],
            'mileage_at_service'      => ['nullable', 'integer', 'min:0'],
            'next_service_km'         => ['nullable', 'integer', 'min:0'],
            'next_service_date'       => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string'],
        ];
    }
}
