<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Core\Services\AuditService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot()
    {
        return view('auth.forgot');
    }

    /** Always returns the same message, whether or not the email exists. */
    public function sendLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);
        $key = 'pw-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many requests. Try again in a few minutes.']);
        }
        RateLimiter::hit($key, 600);
        Password::sendResetLink(['email' => Str::lower($request->input('email'))]);
        return back()->with('success', 'If an account exists for that email, a reset link has been sent. The link expires in 60 minutes.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'password_changed_at' => now(), 'failed_attempts' => 0, 'locked_until' => null])->save();
                event(new PasswordReset($user));
                AuditService::log('auth', 'password_reset', $user);
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Your password has been reset. Sign in with the new password.')
            : back()->withErrors(['email' => 'This reset link is invalid or has expired. Request a new one.']);
    }

    public function changeForm()
    {
        return view('auth.change-password');
    }

    public function change(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::defaults()],
        ], ['current_password.current_password' => 'The current password is incorrect.']);

        $user = $request->user();
        $user->forceFill(['password' => $request->input('password'), 'password_changed_at' => now()])->save();
        // Sign out every other device/session using the old password.
        Auth::logoutOtherDevices($request->input('password'));
        $request->session()->regenerate();
        AuditService::log('auth', 'password_changed', $user);

        return redirect()->route($user->homeRoute())->with('success', 'Password changed. Other sessions have been signed out.');
    }
}
