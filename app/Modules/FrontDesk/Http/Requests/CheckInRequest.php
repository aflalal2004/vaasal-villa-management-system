<?php

namespace App\Modules\FrontDesk\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('frontdesk.checkin') ?? false;
    }

    public function rules(): array
    {
        return [
            'id_type' => ['required', Rule::in(['passport', 'nic', 'driving_licence'])],
            'id_number' => [Rule::requiredIf(fn () => ! $this->route('booking')->guest->id_number), 'nullable', 'string', 'max:40'],
            'id_expiry' => ['nullable', 'date', 'after:today'],
            'nationality' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'id_document' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
            'villa_assignments' => ['nullable', 'array'],
            'villa_assignments.*' => ['nullable', 'integer', 'exists:villas,id'],
            'allow_not_ready' => ['nullable', 'boolean'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer'])],
            'card_uids' => ['nullable', 'array'],
            'card_uids.*' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9:\- ]+$/'],
            'stay_guests' => ['nullable', 'array'],
            'stay_guests.*.*.first_name' => ['nullable', 'string', 'max:80'],
            'stay_guests.*.*.last_name' => ['nullable', 'string', 'max:80'],
            'stay_guests.*.*.nationality' => ['nullable', 'string', 'max:80'],
            'stay_guests.*.*.passport_no' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return ['id_number.required' => 'Enter the guest passport / ID number to register the arrival.', 'card_uids.*.regex' => 'Card UIDs may contain only letters, numbers, dashes and colons.'];
    }
}
