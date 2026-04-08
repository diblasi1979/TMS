<?php

namespace App\Http\Requests\Company;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:255'],
            'tax_id'    => ['nullable', 'string', 'max:50', 'unique:companies,tax_id'],
            'address'   => ['nullable', 'string', 'max:500'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email'],
            'is_active' => ['boolean'],
        ];
    }
}
