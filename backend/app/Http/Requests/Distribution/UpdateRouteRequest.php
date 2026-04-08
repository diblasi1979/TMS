<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRouteRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'string', 'max:100'],
            'planned_date'      => ['sometimes', 'date'],
            'vehicle_id'        => ['nullable', 'integer', 'exists:vehicles,id'],
            'operator_id'       => ['nullable', 'integer', 'exists:operators,id'],
            'total_distance_km' => ['nullable', 'numeric', 'min:0'],
            'notes'             => ['nullable', 'string'],
        ];
    }
}
