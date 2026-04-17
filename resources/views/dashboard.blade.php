@extends('refactor.layouts.admin')

@section('title', trans('console::messages.page_title'))

@section('page-header')
    <div class="mc-page-header">
        <div>
            <h1 class="mc-page-title">{{ trans('console::messages.page_title') }}</h1>
            <p class="mc-page-subtitle">{{ trans('console::messages.page_subtitle') }}</p>
        </div>
        <div class="mc-page-actions">
            <a href="{{ route('plugin.acelle.console.terminal') }}" class="mc-btn mc-btn-primary">
                @include('refactor.components.icons.mc-icon', ['icon' => 'terminal', 'size' => 16])
                {{ trans('console::messages.open_terminal') }}
            </a>
            <a href="{{ route('plugin.acelle.console.logs') }}" class="mc-btn mc-btn-secondary">
                @include('refactor.components.icons.mc-icon', ['icon' => 'list', 'size' => 16])
                {{ trans('console::messages.view_all_logs') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="mc-card">
    <div class="mc-card-body">
        {{-- Toggle section --}}
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.toggle.label') }}</h3>
            <p class="mc-settings-section-subtitle">{{ trans('console::messages.toggle.help') }}</p>

            @if ($enabled)
                <div class="mc-alert mc-alert-success" style="margin-bottom:var(--space-5);">
                    <div class="mc-alert-icon">@include('refactor.components.icons.mc-icon', ['icon' => 'check-circle', 'size' => 18])</div>
                    <div class="mc-alert-content">
                        <div class="mc-alert-text">{{ trans('console::messages.toggle.enabled_note') }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('plugin.acelle.console.toggle') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="0">
                    <button type="submit" class="mc-btn mc-btn-secondary">{{ trans('console::messages.toggle.button_disable') }}</button>
                </form>
            @else
                <div class="mc-alert mc-alert-warning" style="margin-bottom:var(--space-5);">
                    <div class="mc-alert-icon">@include('refactor.components.icons.mc-icon', ['icon' => 'warning', 'size' => 18])</div>
                    <div class="mc-alert-content">
                        <div class="mc-alert-text">{{ trans('console::messages.toggle.disabled_note') }}</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('plugin.acelle.console.toggle') }}">
                    @csrf
                    <input type="hidden" name="enabled" value="1">
                    <button type="submit" class="mc-btn mc-btn-primary">{{ trans('console::messages.toggle.button_enable') }}</button>
                </form>
            @endif
        </div>

        {{-- Token section --}}
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.token.heading') }}</h3>
            <p class="mc-settings-section-subtitle">{{ trans('console::messages.token.help') }}</p>

            <div class="mc-form-group">
                <div style="display:flex;gap:var(--space-2);align-items:stretch;">
                    <input type="password"
                           id="support-api-token"
                           class="mc-form-input"
                           value="{{ $apiToken }}"
                           readonly
                           style="flex:1;font-family:var(--font-mono);">
                    <button type="button" class="mc-btn mc-btn-secondary" data-support-reveal>
                        {{ trans('console::messages.token.reveal') }}
                    </button>
                    <button type="button" class="mc-btn mc-btn-secondary" data-support-copy data-copy-target="support-api-token">
                        {{ trans('console::messages.token.copy') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Endpoints section --}}
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.endpoints.heading') }}</h3>

            <table class="mc-table">
                <tbody>
                    @foreach ($endpoints as $key => $url)
                    <tr>
                        <td style="width:180px;font-weight:600;">{{ trans('console::messages.endpoints.' . $key) }}</td>
                        <td style="font-family:var(--font-mono);font-size:var(--text-sm);">{{ $url }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Examples section --}}
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.examples.heading') }}</h3>

            <div class="mc-form-group">
                <label class="mc-form-label">{{ trans('console::messages.examples.curl_whoami') }}</label>
                <pre style="background:var(--color-hover-bg);border:1px solid var(--color-border);border-radius:var(--radius-input);padding:var(--space-4);font-size:var(--text-xs);overflow:auto;">curl -H "Authorization: Bearer {API_TOKEN}" {{ $endpoints['whoami'] }}</pre>
            </div>

            <div class="mc-form-group">
                <label class="mc-form-label">{{ trans('console::messages.examples.curl_bundle') }}</label>
                <pre style="background:var(--color-hover-bg);border:1px solid var(--color-border);border-radius:var(--radius-input);padding:var(--space-4);font-size:var(--text-xs);overflow:auto;">curl -H "Authorization: Bearer {API_TOKEN}" {{ $endpoints['bundle'] }}</pre>
            </div>

            <div class="mc-form-group">
                <label class="mc-form-label">{{ trans('console::messages.examples.curl_exec_shell') }}</label>
                <pre style="background:var(--color-hover-bg);border:1px solid var(--color-border);border-radius:var(--radius-input);padding:var(--space-4);font-size:var(--text-xs);overflow:auto;">curl -X POST -H "Authorization: Bearer {API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"type":"shell","command":"cat VERSION"}' \
  {{ $endpoints['exec'] }}</pre>
            </div>

            <div class="mc-form-group">
                <label class="mc-form-label">{{ trans('console::messages.examples.curl_exec_tinker') }}</label>
                <pre style="background:var(--color-hover-bg);border:1px solid var(--color-border);border-radius:var(--radius-input);padding:var(--space-4);font-size:var(--text-xs);overflow:auto;">curl -X POST -H "Authorization: Bearer {API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d '{"type":"tinker","command":"Setting::get(\"license\")"}' \
  {{ $endpoints['exec'] }}</pre>
            </div>
        </div>

        {{-- Recent audit log --}}
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.logs_heading') }}</h3>

            @if ($recentLogs->isEmpty())
                <p style="color:var(--color-text-muted);">{{ trans('console::messages.audit.empty') }}</p>
            @else
                <table class="mc-table">
                    <thead>
                        <tr>
                            <th>{{ trans('console::messages.audit.column_time') }}</th>
                            <th>{{ trans('console::messages.audit.column_type') }}</th>
                            <th>{{ trans('console::messages.audit.column_command') }}</th>
                            <th>{{ trans('console::messages.audit.column_exit') }}</th>
                            <th>{{ trans('console::messages.audit.column_duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recentLogs as $log)
                        <tr>
                            <td style="white-space:nowrap;font-size:var(--text-xs);">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td><span class="mc-badge mc-badge-blue">{{ $log->type }}</span></td>
                            <td style="font-family:var(--font-mono);font-size:var(--text-xs);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ \Illuminate\Support\Str::limit($log->command, 80) }}</td>
                            <td>{{ $log->exit_code ?? '—' }}</td>
                            <td>{{ $log->duration_ms }}ms</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <p style="margin-top:var(--space-3);"><a href="{{ route('plugin.acelle.console.logs') }}">{{ trans('console::messages.view_all_logs') }}</a></p>
            @endif
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    document.querySelectorAll('[data-support-reveal]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById('support-api-token');
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.textContent = @json(trans('console::messages.token.hide'));
            } else {
                input.type = 'password';
                btn.textContent = @json(trans('console::messages.token.reveal'));
            }
        });
    });

    document.querySelectorAll('[data-support-copy]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-copy-target');
            var input = document.getElementById(targetId);
            if (!input) return;
            var originalType = input.type;
            input.type = 'text';
            input.select();
            try {
                document.execCommand('copy');
            } catch (e) {
                if (navigator.clipboard) navigator.clipboard.writeText(input.value);
            }
            input.type = originalType;
            var originalLabel = btn.textContent;
            btn.textContent = @json(trans('console::messages.token.copied'));
            setTimeout(function () { btn.textContent = originalLabel; }, 1500);
        });
    });
})();
</script>
@endsection
