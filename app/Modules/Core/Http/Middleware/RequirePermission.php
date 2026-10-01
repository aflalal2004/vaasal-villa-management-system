<?php

namespace App\Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Route guard: `perm:bookings.view|bookings.manage` passes when the user holds ANY listed permission.
 * Enforced server-side; hiding menu items in the UI is cosmetic only.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string $permissions)
    {
        $user = $request->user();
        if (! $user || ! $user->hasPermission($permissions)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'You do not have permission to perform this action.'], 403);
            }
            abort(403, 'You do not have permission to access this page.');
        }
        return $next($request);
    }
}
