<?php

namespace App\Http\Requests\Planning;

use App\Models\OperatorShift;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_type'     => ['sometimes', 'string', 'in:' . implode(',', OperatorShift::TYPES)],
            'start_datetime' => ['sometimes', 'date'],
            'end_datetime'   => ['sometimes', 'date', 'after:start_datetime'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
