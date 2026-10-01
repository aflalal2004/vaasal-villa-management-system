<?php

namespace App\Modules\Booking\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('bookings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'source' => ['required', Rule::in(['admin', 'walk_in', 'phone', 'email', 'tour_operator'])],
            'status' => ['required', Rule::in(['confirmed', 'tentative'])],
            'arrival' => ['required', 'date', 'after_or_equal:'.now()->subDays(1)->toDateString()],
            'departure' => ['required', 'date', 'after:arrival'],
            'rate_plan_id' => ['nullable', 'exists:rate_plans,id'],
            'promo_code' => ['nullable', 'string', 'max:40'],
            'tour_operator_id' => ['nullable', 'required_if:source,tour_operator', 'exists:tour_operators,id'],
            'group_name' => ['nullable', 'string', 'max:120'],
            'guest.id' => ['nullable', 'exists:guests,id'],
            'guest.first_name' => ['required_without:guest.id', 'nullable', 'string', 'max:80'],
            'guest.last_name' => ['required_without:guest.id', 'nullable', 'string', 'max:80'],
            'guest.email' => ['nullable', 'email', 'max:190'],
            'guest.phone' => ['nullable', 'string', 'max:40'],
            'guest.country' => ['nullable', 'string', 'max:80'],
            'villas' => ['required', 'array', 'min:1'],
            'villas.*.villa_id' => ['required', 'exists:villas,id'],
            'villas.*.adults' => ['required', 'integer', 'min:1', 'max:8'],
            'villas.*.children' => ['nullable', 'integer', 'min:0', 'max:6'],
            'rate_override' => ['nullable', 'numeric', 'min:0'],
            'override_reason' => ['nullable', 'required_with:rate_override', 'string', 'max:200'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'arrival_time' => ['nullable', 'string', 'max:20'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'deposit_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'online'])],
        ];
    }

    public function messages(): array
    {
        return [
            'villas.required' => 'Select at least one available villa.',
            'guest.first_name.required_without' => 'Enter the guest first name or choose an existing guest.',
            'guest.last_name.required_without' => 'Enter the guest last name or choose an existing guest.',
            'arrival.after_or_equal' => 'Arrival cannot be in the past.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Only people allowed to override rates may send an override.
        if (! $this->user()?->hasPermission('bookings.override_rate')) {
            $this->merge(['rate_override' => null]);
        }
        $this->merge(['villas' => array_values(array_filter($this->input('villas', []), fn ($v) => ! empty($v['selected'])))]);
    }
}
