<?php

namespace Acelle\Console\Controllers;

use Acelle\Console\Models\SupportDebugLog;
use Acelle\Console\Support\DebugFlag;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user()->admin;
        $apiToken = $request->user()->api_token;
        $enabled = DebugFlag::isEnabled();

        // Use request-host-aware url() rather than config('app.url') — APP_URL
        // is often set to the canonical/production hostname while the same
        // installation may be reached via staging/SIT/IP hosts. The terminal JS
        // does a same-origin fetch from the page, so the API must resolve to
        // the SAME hostname the user loaded the page from.
        $baseUrl = rtrim(url('/'), '/');
        $endpoints = [
            // `whoami` is core (`/api/v1/whoami`), not under the plugin's
            // `/support/*` prefix — example URL must reflect that.
            'whoami' => $baseUrl . '/api/v1/whoami',
            'bundle' => $baseUrl . '/api/v1/support/bundle',
            'exec'   => $baseUrl . '/api/v1/support/exec',
            'logs'   => $baseUrl . '/api/v1/support/logs',
        ];

        $recentLogs = SupportDebugLog::orderBy('created_at', 'desc')->limit(10)->get();

        return view('console::dashboard', compact(
            'admin',
            'apiToken',
            'enabled',
            'endpoints',
            'recentLogs'
        ));
    }

    public function toggle(Request $request)
    {
        $enabled = DebugFlag::set((bool) $request->input('enabled'));

        return redirect()
            ->route('plugin.acelle.console.dashboard')
            ->with('alert-success', trans('console::messages.flash.toggle_' . ($enabled ? 'yes' : 'no')));
    }

    public function logs(Request $request)
    {
        $query = SupportDebugLog::query()->orderBy('created_at', 'desc');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $logs = $query->paginate(50)->withQueryString();

        return view('console::logs', compact('logs'));
    }

    public function terminal(Request $request)
    {
        $user = $request->user();

        if (empty($user->api_token)) {
            $user->api_token = Str::random(60);
            $user->save();
        }

        $enabled = DebugFlag::isEnabled();

        // url('/api/v1/support') resolves against the actual request host
        // (Laravel's URL generator uses Request::root()). config('app.url') is
        // not safe here — instances often have APP_URL stale (canonical
        // production URL) while being accessed via staging/IP/SIT hostnames,
        // which would make the JS fetch hit a different origin (404 / CORS).
        $baseUrl = rtrim(url('/api/v1/support'), '/');
        // whoami lives in core, not under /support — JS hits this directly.
        $whoamiUrl = rtrim(url('/api/v1/whoami'), '/');

        return view('console::terminal', [
            'apiToken'  => $user->api_token,
            'baseUrl'   => $baseUrl,
            'whoamiUrl' => $whoamiUrl,
            'user'      => $user,
            'enabled'   => $enabled,
        ]);
    }
}
