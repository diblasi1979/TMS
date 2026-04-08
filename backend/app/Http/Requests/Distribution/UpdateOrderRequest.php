<?php

namespace App\Http\Requests\Distribution;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'client_id'        => ['sometimes', 'integer', 'exists:clients,id'],
            'reference_number' => ['sometimes', 'string', 'max:50',
                Rule::unique('delivery_orders', 'reference_number')->ignore($this->route('order'))],
            'description'      => ['nullable', 'string'],
            'weight_kg'        => ['nullable', 'numeric', 'min:0'],
            'volume_m3'        => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['sometimes', 'string', 'max:500'],
            'delivery_lat'     => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_lng'     => ['nullable', 'numeric', 'between:-180,180'],
            'contact_name'     => ['nullable', 'string', 'max:255'],
            'contact_phone'    => ['nullable', 'string', 'max:30'],
            'requested_date'   => ['sometimes', 'date'],
            'notes'            => ['nullable', 'string'],
        ];
    }
}
