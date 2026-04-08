<?php

namespace App\Http\Requests\Planning;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vehicle_id'  => ['required', 'integer', 'exists:vehicles,id'],
            'operator_id' => ['required', 'integer', 'exists:operators,id'],
        ];
    }
}
