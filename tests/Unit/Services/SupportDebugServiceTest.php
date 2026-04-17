<?php

use Acelle\Console\Services\SupportDebugService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->svc = new SupportDebugService();
});

test('execShell success returns stdout and exit zero', function () {
    $result = $this->svc->execute('shell', 'echo hello');
    expect($result['exit_code'])->toBe(0);
    expect(trim($result['stdout']))->toBe('hello');
    expect($result['stderr'])->toBe('');
    expect($result['type'])->toBe('shell');
    expect($result['command'])->toBe('echo hello');
    expect($result['truncated'])->toBeFalse();
    expect($result['duration_ms'])->toBeGreaterThanOrEqual(0);
});

test('execShell nonzero exit populates exit_code', function () {
    $result = $this->svc->execute('shell', 'exit 3');
    expect($result['exit_code'])->toBe(3);
});

test('execShell captures stderr', function () {
    $result = $this->svc->execute('shell', 'echo err 1>&2');
    expect(trim($result['stderr']))->toBe('err');
});

test('execShell cwd is base_path', function () {
    $result = $this->svc->execute('shell', 'pwd');
    expect(trim($result['stdout']))->toBe(base_path());
});

test('execShell timeout returns 124 exit and timeout message', function () {
    $result = $this->svc->execute('shell', 'sleep 3', 1);
    expect($result['exit_code'])->toBe(124);
    expect($result['stderr'])->toContain('[timeout after 1s]');
});

test('execShell output over 256KB is truncated with flag', function () {
    // yes floods 2 bytes per line — quickly exceeds 256KB with head limit
    $result = $this->svc->execute('shell', 'yes x | head -n 200000');
    expect($result['truncated'])->toBeTrue();
    expect($result['stdout'])->toContain('[... output truncated at');
    expect(strlen($result['stdout']))->toBeLessThan(SupportDebugService::OUTPUT_CAP_BYTES + 200);
});

test('execTinker evaluates PHP and returns result', function () {
    $result = $this->svc->execute('tinker', '1 + 2');
    expect($result['exit_code'])->toBe(0);
    expect(trim($result['stdout']))->toBe('3');
});

test('execTinker handles explicit return statement', function () {
    $result = $this->svc->execute('tinker', 'return 42');
    expect($result['exit_code'])->toBe(0);
    expect(trim($result['stdout']))->toBe('42');
});

test('execTinker serializes array result as JSON', function () {
    $result = $this->svc->execute('tinker', "['a' => 1, 'b' => 2]");
    expect($result['exit_code'])->toBe(0);
    expect(trim($result['stdout']))->toContain('"a": 1');
    expect(trim($result['stdout']))->toContain('"b": 2');
});

test('execTinker catches Throwable returns stderr with exit 1', function () {
    $result = $this->svc->execute('tinker', 'throw new \RuntimeException("boom")');
    expect($result['exit_code'])->toBe(1);
    expect($result['stderr'])->toContain('RuntimeException');
    expect($result['stderr'])->toContain('boom');
});

test('execTinker captures echoed output', function () {
    $result = $this->svc->execute('tinker', 'echo "hi"; return null;');
    expect($result['exit_code'])->toBe(0);
    expect($result['stdout'])->toContain('hi');
});

test('execTinker auto-imports Setting facade', function () {
    // Tinker prelude imports Setting. Reference should resolve without fatal.
    $result = $this->svc->execute('tinker', 'class_exists(Setting::class)');
    expect($result['exit_code'])->toBe(0);
    expect(trim($result['stdout']))->toBe('1');
});

test('execArtisan invalid command returns error exit', function () {
    $result = $this->svc->execute('artisan', 'this-command-does-not-exist');
    expect($result['exit_code'])->toBe(1);
    expect($result['stderr'])->not->toBe('');
});

test('execArtisan parses name and flags', function () {
    // `list --format=json` is a safe built-in command
    $result = $this->svc->execute('artisan', 'list --format=json');
    expect($result['exit_code'])->toBe(0);
    expect($result['stdout'])->toContain('{');
});

test('execute rejects unknown type', function () {
    expect(fn () => $this->svc->execute('unknown', 'cmd'))
        ->toThrow(\InvalidArgumentException::class);
});

test('redact masks password patterns', function () {
    $masked = $this->svc->redact('password=supersecret123');
    expect($masked)->toContain('***REDACTED***');
    expect($masked)->not->toContain('supersecret123');
});

test('redact masks api_key patterns', function () {
    $masked = $this->svc->redact('api_key: abc123def456');
    expect($masked)->toContain('***REDACTED***');
    expect($masked)->not->toContain('abc123def456');
});

test('redact masks AWS access keys', function () {
    $masked = $this->svc->redact('AKIAIOSFODNN7EXAMPLE');
    expect($masked)->toContain('***REDACTED***');
    expect($masked)->not->toContain('AKIAIOSFODNN7EXAMPLE');
});

test('redact masks OpenAI-style keys', function () {
    $masked = $this->svc->redact('sk-abcdefghijklmnopqrstuvwxyz1234567890');
    expect($masked)->toContain('***REDACTED***');
    expect($masked)->not->toContain('sk-abcdefghijklmnopqrstuvwxyz1234567890');
});

test('redact is noop on clean text', function () {
    expect($this->svc->redact('hello world'))->toBe('hello world');
});

test('redact flag applied only when true', function () {
    $withRedact = $this->svc->execute('shell', 'echo password=abc123', 30, true);
    $withoutRedact = $this->svc->execute('shell', 'echo password=abc123', 30, false);

    expect($withRedact['stdout'])->toContain('***REDACTED***');
    expect($withoutRedact['stdout'])->toContain('abc123');
});

test('timeout is clamped to max', function () {
    // Pass 9999 — should clamp to TIMEOUT_MAX (120)
    $result = $this->svc->execute('shell', 'echo ok', 9999);
    expect($result['exit_code'])->toBe(0);
});
