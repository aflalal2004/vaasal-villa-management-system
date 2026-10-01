<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LoginLog;
use App\Models\User;
use App\Modules\Auth\Support\DemoLogin;
use App\Modules\Core\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Username-or-email + password sign-in for staff, POS users and tour operators.
 * Protection: per-account lockout after N failures, per-IP rate limit, session regeneration,
 * uniform error messages (no account enumeration), and a login log for every attempt.
 */
class LoginController extends Controller
{
    public function show(Request $request)
    {
        // ?return=/path — set by the browser when a session ends mid-task. Same-application paths only (no open redirect).
        $return = (string) $request->query('return', '');
        $base = rtrim($request->getBasePath(), '/').'/';
        if ($return !== '' && str_starts_with($return, $base) && ! str_starts_with($return, '//') && ! str_contains($return, '\\')) {
            $request->session()->put('url.intended', $request->getSchemeAndHttpHost().$return);
        }
        return view('auth.login', ['demoRoles' => DemoLogin::roles()]);
    }

    public function login(Request $request)
    {
        // `login` accepts a username or an email; `email` is still accepted for older clients.
        $field = $request->has('login') ? 'login' : 'email';
        $request->merge(['login' => $request->input('login', $request->input('email'))]);
        $data = $request->validate([
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
        ], [], ['login' => 'username or email']);
        if ($field === 'email' && ! str_contains($data['login'], '@')) {
            throw ValidationException::withMessages(['email' => 'Enter a valid email address.']);
        }
        $email = Str::lower(trim($data['login']));
        $ipKey = 'login-ip:'.$request->ip();

        if (RateLimiter::tooManyAttempts($ipKey, 20)) {
            $this->log(null, $email, $request, false, 'ip_throttled');
            throw ValidationException::withMessages([$field => 'Too many sign-in attempts from this network. Try again in '.ceil(RateLimiter::availableIn($ipKey) / 60).' minute(s).']);
        }
        RateLimiter::hit($ipKey, 60 * 10);

        $user = User::findForLogin($email);

        if ($user && $user->isLocked()) {
            $this->log($user, $email, $request, false, 'locked');
            throw ValidationException::withMessages([$field => 'This account is temporarily locked after repeated failed sign-ins. Try again after '.$user->locked_until->format('H:i').' or reset your password.']);
        }

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $attempts = $user->failed_attempts + 1;
                $max = config('vaasal.security.login_max_attempts');
                $user->forceFill([
                    'failed_attempts' => $attempts >= $max ? 0 : $attempts,
                    'locked_until' => $attempts >= $max ? now()->addMinutes(config('vaasal.security.login_lock_minutes')) : $user->locked_until,
                ])->save();
                if ($attempts >= $max) {
                    AuditService::log('auth', 'account_locked', $user, 'Locked after '.$max.' failed sign-ins from '.$request->ip());
                }
            }
            $this->log($user, $email, $request, false, $user ? 'bad_password' : 'unknown_email');
            throw ValidationException::withMessages([$field => 'The username/email or password is incorrect.']);
        }

        if ($user->status !== 'active') {
            $this->log($user, $email, $request, false, 'inactive');
            throw ValidationException::withMessages([$field => 'This account is not active. Contact the administrator.']);
        }
        if ($user->isOperator() && $user->tourOperator?->status === 'suspended') {
            $this->log($user, $email, $request, false, 'operator_suspended');
            throw ValidationException::withMessages([$field => 'Your company account is suspended. Contact Vaasal Villa reservations.']);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        RateLimiter::clear($ipKey);
        $user->forceFill(['failed_attempts' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->log($user, $email, $request, true, 'ok');

        return redirect()->intended(route($user->homeRoute()));
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            AuditService::log('auth', 'logout', $request->user());
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    private function log(?User $user, string $email, Request $request, bool $success, string $reason): void
    {
        LoginLog::create([
            'user_id' => $user?->id, 'email' => $email, 'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500), 'success' => $success, 'reason' => $reason,
        ]);
    }
}
