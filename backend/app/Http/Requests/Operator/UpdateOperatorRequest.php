<?php

namespace App\Http\Requests\Operator;

use App\Models\Operator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOperatorRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'company_id'        => ['sometimes', 'nullable', 'exists:companies,id'],
            'name'              => ['sometimes', 'string', 'max:255'],
            'document_number'   => ['sometimes', 'string', 'max:30', Rule::unique('operators', 'document_number')->ignore($this->route('operator'))],
            'email'             => ['sometimes', 'nullable', 'email'],
            'phone'             => ['sometimes', 'nullable', 'string', 'max:30'],
            'address'           => ['sometimes', 'nullable', 'string', 'max:500'],
            'license_number'    => ['sometimes', 'string', 'max:50', Rule::unique('operators', 'license_number')->ignore($this->route('operator'))],
            'license_type'      => ['sometimes', Rule::in(Operator::LICENSE_TYPES)],
            'license_expiry'    => ['sometimes', 'date'],
            'emergency_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_phone'   => ['sometimes', 'nullable', 'string', 'max:30'],
            'notes'             => ['sometimes', 'nullable', 'string'],
            'is_active'         => ['sometimes', 'boolean'],
        ];
    }
}
