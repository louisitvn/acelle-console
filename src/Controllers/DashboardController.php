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

        $baseUrl = rtrim(config('app.url'), '/');
        $endpoints = [
            'whoami' => $baseUrl . '/api/v1/support/whoami',
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

        $baseUrl = rtrim(config('app.url'), '/') . '/api/v1/support';

        return view('console::terminal', [
            'apiToken' => $user->api_token,
            'baseUrl'  => $baseUrl,
            'user'     => $user,
            'enabled'  => $enabled,
        ]);
    }
}
