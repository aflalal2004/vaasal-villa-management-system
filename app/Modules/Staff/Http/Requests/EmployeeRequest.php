<?php

namespace App\Modules\Staff\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('staff.manage') ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('employee')?->id;
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'department_id' => ['required', 'exists:departments,id'],
            'job_role_id' => ['nullable', 'exists:job_roles,id'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other'])],
            'date_of_birth' => ['nullable', 'date', 'before:-16 years'],
            'national_id' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contract', 'intern'])],
            'hire_date' => ['required', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'attendance_pin' => ['nullable', 'digits_between:4,6'],
            'rfid_uid' => ['nullable', 'string', 'max:60', Rule::unique('employees', 'rfid_uid')->ignore($id)],
            'biometric_ref' => ['nullable', 'string', 'max:80', Rule::unique('employees', 'biometric_ref')->ignore($id)],
            'photo' => ['nullable', 'file', 'max:'.config('vaasal.security.upload_max_kb')],
        ];
    }

    public function messages(): array
    {
        return ['date_of_birth.before' => 'Employees must be at least 16 years old.'];
    }
}
