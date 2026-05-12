<?php

use Acelle\Console\Models\SupportDebugLog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function () {
    DB::table('support_debug_logs')->where('user_agent', 'like', 'pest-support-log-test%')->delete();
});

test('log row captures all audit fields', function () {
    $log = SupportDebugLog::create([
        'user_id' => 0,
        'ip' => '10.0.0.1',
        'user_agent' => 'pest-support-log-test-1',
        'type' => SupportDebugLog::TYPE_SHELL,
        'command' => 'echo hello',
        'exit_code' => 0,
        'duration_ms' => 12,
        'output_bytes' => 6,
        'truncated' => false,
    ]);

    $fresh = SupportDebugLog::find($log->id);
    expect($fresh->user_id)->toBe(0);
    expect($fresh->ip)->toBe('10.0.0.1');
    expect($fresh->type)->toBe('shell');
    expect($fresh->command)->toBe('echo hello');
    expect($fresh->exit_code)->toBe(0);
    expect($fresh->duration_ms)->toBe(12);
    expect($fresh->output_bytes)->toBe(6);
    expect($fresh->truncated)->toBeFalse();
});

test('created_at is auto-set when not provided', function () {
    $log = SupportDebugLog::create([
        'user_agent' => 'pest-support-log-test-2',
        'type' => SupportDebugLog::TYPE_BUNDLE,
    ]);

    expect($log->created_at)->not->toBeNull();
    // created_at is cast to Carbon datetime
    expect($log->created_at->diffInSeconds(now()))->toBeLessThan(5);
});

test('truncated flag is cast to boolean', function () {
    $log = SupportDebugLog::create([
        'user_agent' => 'pest-support-log-test-3',
        'type' => SupportDebugLog::TYPE_SHELL,
        'truncated' => 1,
    ]);

    $fresh = SupportDebugLog::find($log->id);
    expect($fresh->truncated)->toBeTrue();
    expect($fresh->truncated)->toBeBool();
});

test('command over 64KB is truncated on create', function () {
    $long = str_repeat('x', SupportDebugLog::COMMAND_CAP_BYTES + 1000);

    $log = SupportDebugLog::create([
        'user_agent' => 'pest-support-log-test-4',
        'type' => SupportDebugLog::TYPE_SHELL,
        'command' => $long,
    ]);

    expect(strlen($log->command))->toBeLessThan(SupportDebugLog::COMMAND_CAP_BYTES + 100);
    expect($log->command)->toContain('[... command truncated ...]');
});

test('short commands are preserved exactly', function () {
    $short = 'cat VERSION';

    $log = SupportDebugLog::create([
        'user_agent' => 'pest-support-log-test-5',
        'type' => SupportDebugLog::TYPE_SHELL,
        'command' => $short,
    ]);

    expect($log->command)->toBe($short);
});

test('type constants match string values', function () {
    expect(SupportDebugLog::TYPE_SHELL)->toBe('shell');
    expect(SupportDebugLog::TYPE_TINKER)->toBe('tinker');
    expect(SupportDebugLog::TYPE_ARTISAN)->toBe('artisan');
    expect(SupportDebugLog::TYPE_BUNDLE)->toBe('bundle');
});
