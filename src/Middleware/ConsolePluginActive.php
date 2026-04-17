<?php

namespace Acelle\Console\Middleware;

use App\Model\Plugin;
use Closure;

class ConsolePluginActive
{
    public function handle($request, Closure $next)
    {
        try {
            $plugin = Plugin::getByName('acelle/console');
            $active = $plugin && $plugin->isActive();
        } catch (\Throwable $e) {
            $active = false;
        }

        if (!$active) {
            abort(404);
        }

        return $next($request);
    }
}
