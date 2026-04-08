<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['sometimes', 'nullable', 'exists:companies,id'],
            'name'       => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'nullable', 'email'],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'address'    => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
