<?php

namespace Acelle\Console\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Require an authenticated admin (via api_token) on support API endpoints.
 * Does NOT check the support-debug feature flag — pair with SupportFlag
 * middleware when the endpoint should be gated on the flag.
 */
class SupportAdmin
{
    public function handle($request, Closure $next)
    {
        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        // Delegate to the canonical admin policy (same rule as the `backend` middleware).
        if (!$user->can('admin_access', $user)) {
            return response()->json(['error' => 'admin_required'], 403);
        }

        if ($user->admin && !$user->admin->isActive()) {
            return response()->json(['error' => 'admin_disabled'], 403);
        }

        return $next($request);
    }
}
