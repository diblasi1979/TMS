<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'name'       => ['required', 'string', 'max:255'],
            'email'      => ['nullable', 'email'],
            'phone'      => ['nullable', 'string', 'max:30'],
            'address'    => ['nullable', 'string', 'max:500'],
            'is_active'  => ['boolean'],
        ];
    }
}
