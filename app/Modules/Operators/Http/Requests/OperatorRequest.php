<?php

namespace App\Modules\Operators\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OperatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route middleware enforces operators.manage / portal realm
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_no' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'country' => ['nullable', 'string', 'max:80'],
            'website' => ['nullable', 'url', 'max:190'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:180'],
            'logo' => ['nullable', 'file', 'max:2048'],
        ];
    }
}
