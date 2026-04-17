<?php

namespace Acelle\Console\Middleware;

use Acelle\Console\Support\DebugFlag;
use Closure;

/**
 * Gate an API endpoint on the support-debug feature flag.
 * Returns 503 when the flag is off. Applied to every endpoint
 * EXCEPT the toggle itself (otherwise a disabled flag would lock
 * out the only call that can re-enable it).
 */
class SupportFlag
{
    public function handle($request, Closure $next)
    {
        if (!DebugFlag::isEnabled()) {
            return response()->json(['error' => 'feature_disabled'], 503);
        }

        return $next($request);
    }
}
