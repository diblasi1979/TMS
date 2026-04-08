<?php

namespace App\Http\Requests\Planning;

use App\Models\OperatorShift;
use Illuminate\Foundation\Http\FormRequest;

class StoreShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operator_id'    => ['required', 'integer', 'exists:operators,id'],
            'shift_type'     => ['required', 'string', 'in:' . implode(',', OperatorShift::TYPES)],
            'start_datetime' => ['required', 'date'],
            'end_datetime'   => ['required', 'date', 'after:start_datetime'],
            'notes'          => ['nullable', 'string'],
        ];
    }
}
