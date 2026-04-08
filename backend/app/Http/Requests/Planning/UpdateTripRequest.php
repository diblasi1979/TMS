<?php

namespace App\Http\Requests\Planning;

use App\Models\TripPlan;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tripId = $this->route('trip')?->id;

        return [
            'client_id'          => ['nullable', 'integer', 'exists:clients,id'],
            'trip_number'        => ['sometimes', 'string', 'max:50', 'unique:trip_plans,trip_number,' . $tripId],
            'origin'             => ['sometimes', 'string', 'max:500'],
            'origin_lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'origin_lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'destination'        => ['sometimes', 'string', 'max:500'],
            'destination_lat'    => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lng'    => ['nullable', 'numeric', 'between:-180,180'],
            'scheduled_departure'=> ['sometimes', 'date'],
            'scheduled_arrival'  => ['sometimes', 'date', 'after:scheduled_departure'],
            'vehicle_id'         => ['nullable', 'integer', 'exists:vehicles,id'],
            'operator_id'        => ['nullable', 'integer', 'exists:operators,id'],
            'cargo_type'         => ['nullable', 'string', 'in:' . implode(',', TripPlan::CARGO_TYPES)],
            'cargo_description'  => ['nullable', 'string'],
            'weight_kg'          => ['nullable', 'numeric', 'min:0'],
            'volume_m3'          => ['nullable', 'numeric', 'min:0'],
            'distance_km'        => ['nullable', 'numeric', 'min:0'],
            'priority'           => ['nullable', 'string', 'in:' . implode(',', TripPlan::PRIORITIES)],
            'notes'              => ['nullable', 'string'],
        ];
    }
}
