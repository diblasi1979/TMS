<?php

namespace App\Http\Requests\Operator;

use App\Models\Operator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOperatorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id'        => ['nullable', 'exists:companies,id'],
            'name'              => ['required', 'string', 'max:255'],
            'document_number'   => ['required', 'string', 'max:30', 'unique:operators,document_number'],
            'email'             => ['nullable', 'email'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'address'           => ['nullable', 'string', 'max:500'],
            'license_number'    => ['required', 'string', 'max:50', 'unique:operators,license_number'],
            'license_type'      => ['required', Rule::in(Operator::LICENSE_TYPES)],
            'license_expiry'    => ['required', 'date'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'emergency_phone'   => ['nullable', 'string', 'max:30'],
            'notes'             => ['nullable', 'string'],
            'is_active'         => ['boolean'],
        ];
    }
}
