<?php

namespace App\Http\Requests\Distribution;

use App\Models\DeliveryEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'event_type'  => ['required', 'string', Rule::in(DeliveryEvent::EVENT_TYPES)],
            'occurred_at' => ['required', 'date'],
            'lat'         => ['nullable', 'numeric', 'between:-90,90'],
            'lng'         => ['nullable', 'numeric', 'between:-180,180'],
            'notes'       => ['nullable', 'string'],
        ];
    }
}
