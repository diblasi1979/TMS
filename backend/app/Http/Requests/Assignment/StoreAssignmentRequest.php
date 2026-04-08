<?php

namespace App\Http\Requests\Assignment;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'vehicle_id'  => ['required', 'exists:vehicles,id'],
            'operator_id' => ['required', 'exists:operators,id'],
            'notes'       => ['nullable', 'string'],
        ];
    }
}
