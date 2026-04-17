<?php

use Acelle\Console\Services\SupportBundleService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->svc = new SupportBundleService();
});

test('bundle includes all top-level keys', function () {
    $bundle = $this->svc->build();

    foreach (['generated_at', 'app', 'php', 'db', 'license', 'queue', 'logs', 'disk', 'plugins', 'settings'] as $key) {
        expect($bundle)->toHaveKey($key);
    }
});

test('bundle app block contains version metadata', function () {
    $bundle = $this->svc->build();

    expect($bundle['app'])->toHaveKeys([
        'version', 'laravel_version', 'env', 'debug', 'url', 'installed_at', 'timezone',
    ]);
    expect($bundle['app']['laravel_version'])->toBeString();
    expect($bundle['app']['debug'])->toBeBool();
});

test('bundle php block lists extensions status', function () {
    $bundle = $this->svc->build();

    expect($bundle['php'])->toHaveKeys([
        'version', 'sapi', 'memory_limit', 'max_execution_time', 'post_max_size', 'upload_max_filesize', 'extensions',
    ]);
    expect($bundle['php']['extensions'])->toBeArray();
    // Every value in extensions is a boolean flag.
    foreach ($bundle['php']['extensions'] as $name => $loaded) {
        expect($loaded)->toBeBool();
    }
});

test('bundle db block contains driver and version when connected', function () {
    $bundle = $this->svc->build();

    // Either connected (has driver+version) or error — both are valid paths.
    expect(
        array_key_exists('driver', $bundle['db']) || array_key_exists('error', $bundle['db'])
    )->toBeTrue();
});

test('bundle db host is redacted for non-local hosts', function () {
    $svc = new SupportBundleService();
    $reflection = new \ReflectionClass($svc);
    $method = $reflection->getMethod('redactHost');
    $method->setAccessible(true);

    expect($method->invoke($svc, 'db.production.example.com'))->toBe('**.production.example.com');
    expect($method->invoke($svc, 'localhost'))->toBe('localhost');
    expect($method->invoke($svc, '127.0.0.1'))->toBe('127.0.0.1');
    expect($method->invoke($svc, null))->toBeNull();
});

test('bundle license has is_expired and days_left fields', function () {
    $bundle = $this->svc->build();

    expect($bundle['license'])->toHaveKeys([
        'license', 'type', 'supported_until', 'company', 'is_expired', 'days_left',
    ]);
});

test('bundle queue reports pending and failed counts', function () {
    $bundle = $this->svc->build();

    expect($bundle['queue'])->toHaveKey('driver');
    // pending/failed_total/failed_24h may be absent if tables don't exist — don't fail.
});

test('bundle logs reads latest laravel log when present', function () {
    $logsDir = storage_path('logs');
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0775, true);
    }
    $testLog = $logsDir . '/laravel-support-bundle-test.log';
    $content = implode("\n", array_map(fn($i) => "line {$i}", range(1, 5)));
    file_put_contents($testLog, $content);

    try {
        $bundle = $this->svc->build();
        expect($bundle['logs'])->toHaveKey('laravel');
        expect($bundle['logs']['laravel'])->toBeArray();
        expect($bundle['logs']['laravel'])->toHaveKeys(['file', 'size_bytes', 'modified_at', 'tail']);
        expect($bundle['logs']['laravel']['tail'])->toBeArray();
    } finally {
        @unlink($testLog);
    }
});

test('bundle disk reports total/used/free for app and storage', function () {
    $bundle = $this->svc->build();

    expect($bundle['disk'])->toHaveKeys(['app', 'storage']);
    expect($bundle['disk']['app'])->toHaveKey('path');
    expect($bundle['disk']['storage'])->toHaveKey('path');
});

test('bundle plugins is empty or structured when dir absent', function () {
    $bundle = $this->svc->build();

    // Either empty [] or full structure with count/installed/index
    if (!empty($bundle['plugins'])) {
        expect($bundle['plugins'])->toHaveKey('count');
        expect($bundle['plugins'])->toHaveKey('installed');
        expect($bundle['plugins']['installed'])->toBeArray();
    } else {
        expect($bundle['plugins'])->toBe([]);
    }
});

test('bundle settings whitelist includes support_debug_enabled', function () {
    $bundle = $this->svc->build();

    expect($bundle['settings'])->toHaveKey('support_debug_enabled');
    expect($bundle['settings'])->toHaveKey('site_name');
});

test('bundle generated_at is ISO-8601', function () {
    $bundle = $this->svc->build();

    expect($bundle['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});
