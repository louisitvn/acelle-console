<?php

namespace Acelle\Console\Middleware;

use App\Model\Setting;
use Closure;
use Illuminate\Support\Facades\Auth;

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

        try {
            $enabled = Setting::isYes('support_debug_enabled');
        } catch (\Throwable $e) {
            // Default to enabled if setting missing (first-install path).
            $enabled = true;
        }

        if (!$enabled) {
            return response()->json(['error' => 'feature_disabled'], 503);
        }

        return $next($request);
    }
}
