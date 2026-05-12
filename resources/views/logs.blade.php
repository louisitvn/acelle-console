@extends('refactor.layouts.admin')

@section('title', trans('console::messages.audit.page_title'))

@section('page-header')
    <div class="mc-page-header">
        <div>
            <h1 class="mc-page-title">{{ trans('console::messages.audit.page_title') }}</h1>
            <p class="mc-page-subtitle">{{ trans('console::messages.page_subtitle') }}</p>
        </div>
        <div class="mc-page-actions">
            <a href="{{ route('plugin.acelle.console.dashboard') }}" class="mc-btn mc-btn-secondary">
                @include('refactor.components.icons.mc-icon', ['icon' => 'arrow-left', 'size' => 16])
                {{ trans('console::messages.page_title') }}
            </a>
        </div>
    </div>
@endsection

@section('content')
<div class="mc-card">
    <div class="mc-card-body">
        <div class="mc-settings-section">
            <h3 class="mc-settings-section-title">{{ trans('console::messages.audit.page_title') }}</h3>

            {{-- Type filter --}}
            <form method="GET" action="{{ route('plugin.acelle.console.logs') }}" style="margin-bottom:var(--space-4);">
                <div style="display:flex;gap:var(--space-2);align-items:center;">
                    <select name="type" class="mc-form-select" style="max-width:220px;" onchange="this.form.submit()">
                        <option value="">{{ trans('console::messages.audit.column_type') }} — all</option>
                        @foreach (['shell', 'tinker', 'artisan', 'bundle'] as $t)
                            <option value="{{ $t }}" @if (request('type') === $t) selected @endif>{{ $t }}</option>
                        @endforeach
                    </select>
                    @if (request('type'))
                        <a href="{{ route('plugin.acelle.console.logs') }}" class="mc-btn mc-btn-secondary">Clear</a>
                    @endif
                </div>
            </form>

            @if ($logs->isEmpty())
                <div class="mc-alert mc-alert-info">
                    <div class="mc-alert-icon">@include('refactor.components.icons.mc-icon', ['icon' => 'info', 'size' => 18])</div>
                    <div class="mc-alert-content">
                        <div class="mc-alert-text">{{ trans('console::messages.audit.empty') }}</div>
                    </div>
                </div>
            @else
                <table class="mc-table">
                    <thead>
                        <tr>
                            <th style="width:160px;">{{ trans('console::messages.audit.column_time') }}</th>
                            <th style="width:70px;">{{ trans('console::messages.audit.column_user') }}</th>
                            <th style="width:120px;">{{ trans('console::messages.audit.column_ip') }}</th>
                            <th style="width:90px;">{{ trans('console::messages.audit.column_type') }}</th>
                            <th>{{ trans('console::messages.audit.column_command') }}</th>
                            <th style="width:70px;">{{ trans('console::messages.audit.column_exit') }}</th>
                            <th style="width:100px;">{{ trans('console::messages.audit.column_duration') }}</th>
                            <th style="width:110px;">{{ trans('console::messages.audit.column_bytes') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                        <tr>
                            <td style="white-space:nowrap;font-size:var(--text-xs);">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td style="font-size:var(--text-xs);">{{ $log->user_id ?? '—' }}</td>
                            <td style="font-family:var(--font-mono);font-size:var(--text-xs);">{{ $log->ip ?? '—' }}</td>
                            <td><span class="mc-badge mc-badge-blue">{{ $log->type }}</span></td>
                            <td style="font-family:var(--font-mono);font-size:var(--text-xs);max-width:400px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $log->command }}">{{ \Illuminate\Support\Str::limit($log->command, 120) }}</td>
                            <td>
                                @if ($log->exit_code === null)
                                    —
                                @elseif ($log->exit_code === 0)
                                    <span class="mc-badge mc-badge-green">0</span>
                                @else
                                    <span class="mc-badge mc-badge-red">{{ $log->exit_code }}</span>
                                @endif
                            </td>
                            <td style="font-size:var(--text-xs);">{{ $log->duration_ms }}ms</td>
                            <td style="font-size:var(--text-xs);">
                                {{ number_format($log->output_bytes) }}
                                @if ($log->truncated)
                                    <span class="mc-badge mc-badge-orange" style="margin-left:var(--space-1);">trunc</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <div style="margin-top:var(--space-4);">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
