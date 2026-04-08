<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:255'],
            'tax_id'    => ['sometimes', 'nullable', 'string', 'max:50', Rule::unique('companies', 'tax_id')->ignore($this->route('company'))],
            'address'   => ['sometimes', 'nullable', 'string', 'max:500'],
            'phone'     => ['sometimes', 'nullable', 'string', 'max:30'],
            'email'     => ['sometimes', 'nullable', 'email'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
