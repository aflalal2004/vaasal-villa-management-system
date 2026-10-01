<?php

namespace App\Modules\Operators\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Operators\Services\OperatorService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/** Public partner application: company profile + first portal login (pending approval). */
class RegistrationController extends Controller
{
    public function create()
    {
        return view('operator.register');
    }

    public function store(Request $request, OperatorService $operators)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:150'], 'legal_name' => ['nullable', 'string', 'max:150'],
            'registration_no' => ['required', 'string', 'max:60'], 'tax_id' => ['nullable', 'string', 'max:60'],
            'contact_name' => ['required', 'string', 'max:100'], 'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:300'],
            'country' => ['required', 'string', 'max:80'], 'website' => ['nullable', 'url', 'max:190'],
            'login_email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
            'company_website_url' => ['prohibited'], // honeypot
        ], ['terms.accepted' => 'Please accept the partner terms.', 'login_email.unique' => 'An account with this email already exists. Sign in instead.']);

        $operators->register(
            collect($data)->only(['company_name', 'legal_name', 'registration_no', 'tax_id', 'contact_name', 'email', 'phone', 'address', 'country', 'website'])->all(),
            ['name' => $data['contact_name'], 'email' => $data['login_email'], 'password' => $data['password']]
        );
        return redirect()->route('login')->with('success', 'Thank you! Your partner application was received. You can sign in now; bookings open once our team approves your account (usually within one working day).');
    }
}
