<?php

namespace App\Http\Requests\Planning;

use App\Models\MaintenanceSchedule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_type'        => ['sometimes', 'string', 'in:' . implode(',', MaintenanceSchedule::TYPES)],
            'description'             => ['sometimes', 'string'],
            'scheduled_date'          => ['sometimes', 'date'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1'],
            'workshop'                => ['nullable', 'string', 'max:255'],
            'estimated_cost'          => ['nullable', 'numeric', 'min:0'],
            'actual_cost'             => ['nullable', 'numeric', 'min:0'],
            'mileage_at_service'      => ['nullable', 'integer', 'min:0'],
            'next_service_km'         => ['nullable', 'integer', 'min:0'],
            'next_service_date'       => ['nullable', 'date'],
            'notes'                   => ['nullable', 'string'],
        ];
    }
}
