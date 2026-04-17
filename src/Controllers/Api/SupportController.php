<?php

namespace Acelle\Console\Controllers\Api;

use Acelle\Console\Models\SupportDebugLog;
use Acelle\Console\Services\SupportBundleService;
use Acelle\Console\Services\SupportDebugService;
use App\Http\Controllers\Controller;
use App\Model\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class SupportController extends Controller
{
    public function whoami(Request $request)
    {
        $user = Auth::guard('api')->user();
        $versionPath = base_path('VERSION');
        $version = is_readable($versionPath) ? trim((string) file_get_contents($versionPath)) : null;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_admin' => !is_null($user->admin),
            ],
            'instance' => [
                'url' => config('app.url'),
                'version' => $version,
            ],
            'feature_enabled' => $this->featureEnabled(),
            'endpoints' => [
                'whoami' => url('/api/v1/support/whoami'),
                'bundle' => url('/api/v1/support/bundle'),
                'exec'   => url('/api/v1/support/exec'),
                'logs'   => url('/api/v1/support/logs'),
            ],
        ]);
    }

    public function bundle(Request $request, SupportBundleService $service)
    {
        $start = microtime(true);
        $bundle = $service->build();
        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $json = json_encode($bundle);
        $bytes = $json === false ? 0 : strlen($json);

        $this->auditLog($request, [
            'type' => SupportDebugLog::TYPE_BUNDLE,
            'command' => 'bundle',
            'exit_code' => 0,
            'duration_ms' => $durationMs,
            'output_bytes' => $bytes,
            'truncated' => false,
        ]);

        return response()->json($bundle);
    }

    public function exec(Request $request, SupportDebugService $service)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:shell,tinker,artisan',
            'command' => 'required|string',
            'timeout' => 'sometimes|integer|min:1|max:' . SupportDebugService::TIMEOUT_MAX,
            'redact' => 'sometimes|boolean',
        ]);

        $type = $validated['type'];
        $command = $validated['command'];
        $timeout = (int) ($validated['timeout'] ?? SupportDebugService::TIMEOUT_DEFAULT);
        $redact = (bool) ($validated['redact'] ?? false);

        try {
            $result = $service->execute($type, $command, $timeout, $redact);
        } catch (Throwable $e) {
            $result = [
                'type' => $type,
                'command' => $command,
                'stdout' => '',
                'stderr' => get_class($e) . ': ' . $e->getMessage(),
                'exit_code' => 1,
                'duration_ms' => 0,
                'output_bytes' => 0,
                'truncated' => false,
            ];
        }

        $this->auditLog($request, [
            'type' => $type,
            'command' => $command,
            'exit_code' => $result['exit_code'] ?? null,
            'duration_ms' => $result['duration_ms'] ?? 0,
            'output_bytes' => $result['output_bytes'] ?? 0,
            'truncated' => $result['truncated'] ?? false,
        ]);

        return response()->json($result);
    }

    public function logs(Request $request)
    {
        $query = SupportDebugLog::query()->orderBy('created_at', 'desc');

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }
        if ($since = $request->input('since')) {
            try {
                $query->where('created_at', '>=', \Carbon\Carbon::parse($since));
            } catch (Throwable $e) {
                // ignore invalid
            }
        }
        if ($until = $request->input('until')) {
            try {
                $query->where('created_at', '<=', \Carbon\Carbon::parse($until));
            } catch (Throwable $e) {
                // ignore
            }
        }

        $limit = max(1, min((int) $request->input('limit', 50), 500));
        $rows = $query->limit($limit)->get(['id', 'user_id', 'ip', 'type', 'command', 'exit_code', 'duration_ms', 'output_bytes', 'truncated', 'created_at']);

        return response()->json([
            'count' => $rows->count(),
            'limit' => $limit,
            'items' => $rows->values(),
        ]);
    }

    private function featureEnabled(): bool
    {
        try {
            return Setting::isYes('support_debug_enabled');
        } catch (Throwable $e) {
            return true;
        }
    }

    private function auditLog(Request $request, array $data): void
    {
        try {
            SupportDebugLog::create(array_merge([
                'user_id' => Auth::guard('api')->id(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ], $data));
        } catch (Throwable $e) {
            // swallow — audit failure must not break the main request
            \Log::warning('SupportDebugLog write failed: ' . $e->getMessage());
        }
    }
}
