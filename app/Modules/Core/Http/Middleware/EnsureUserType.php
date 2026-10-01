<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * `usertype:staff` or `usertype:operator` — keeps the staff back office and the tour operator
 * portal in separate realms, and logs out deactivated accounts on their next request.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $type)
    {
        $user = $request->user();

        if ($user && ($user->status !== 'active' || ($user->isOperator() && $user->tourOperator?->status === 'suspended'))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->withErrors(['email' => 'Your account is not active. Contact the administrator.']);
        }

        if (! $user || $user->user_type !== $type) {
            if ($user) {
                return redirect()->route($user->homeRoute());
            }
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
