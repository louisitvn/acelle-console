<?php

/**
 * Feature tests for remote support debug API — /api/v1/support/*
 */

use App\Model\Admin;
use App\Model\Setting;
use Acelle\Console\Models\SupportDebugLog;
use App\Model\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Create an admin user with a known api_token. Returns [User, token].
 */
function supportMakeAdminUser(string $suffix = ''): array
{
    $token = 'support_pest_' . Str::random(40) . $suffix;
    $email = 'pest-support-' . Str::random(8) . '@example.com';

    $userId = DB::table('users')->insertGetId([
        'uid' => Str::random(20),
        'email' => $email,
        'password' => Hash::make('password'),
        'first_name' => 'Pest',
        'last_name' => 'Admin',
        'api_token' => $token,
        'activated' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $groupId = DB::table('admin_groups')->value('id') ?? 1;
    DB::table('admins')->insert([
        'uid' => Str::random(20),
        'user_id' => $userId,
        'admin_group_id' => $groupId,
        'status' => 'active',
        'timezone' => 'UTC',
        'color_scheme' => 'default',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [User::find($userId), $token];
}

function supportMakeNonAdminUser(): array
{
    $token = 'support_pest_nonadmin_' . Str::random(30);
    $email = 'pest-support-nonadmin-' . Str::random(8) . '@example.com';

    $userId = DB::table('users')->insertGetId([
        'uid' => Str::random(20),
        'email' => $email,
        'password' => Hash::make('password'),
        'first_name' => 'Pest',
        'last_name' => 'NonAdmin',
        'api_token' => $token,
        'activated' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [User::find($userId), $token];
}

beforeEach(function () {
    // Ensure feature toggle is ON by default for tests
    Setting::set('support_debug_enabled', 'yes');

    [$user, $token] = supportMakeAdminUser();
    $this->adminUser = $user;
    $this->adminToken = $token;
});

afterEach(function () {
    // Clean up all test artifacts
    DB::table('support_debug_logs')->where('user_agent', 'like', 'Symfony%')
        ->orWhere('ip', '127.0.0.1')->delete();
    DB::table('admins')->where('uid', 'like', '%')
        ->whereIn('user_id', DB::table('users')->where('email', 'like', 'pest-support%@example.com')->pluck('id'))
        ->delete();
    DB::table('users')->where('email', 'like', 'pest-support%@example.com')->delete();
});

test('missing authorization header returns 401', function () {
    $response = $this->getJson('/api/v1/support/whoami');
    expect($response->status())->toBe(401);
});

test('invalid bearer token returns 401', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer totally-invalid-token'])
        ->getJson('/api/v1/support/whoami');
    expect($response->status())->toBe(401);
});

test('non-admin api_token returns 403', function () {
    [$user, $token] = supportMakeNonAdminUser();

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $token])
        ->getJson('/api/v1/support/whoami');

    expect($response->status())->toBe(403);
    expect($response->json('error'))->toBe('admin_required');
});

test('admin token with feature disabled returns 503', function () {
    Setting::set('support_debug_enabled', 'no');

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/whoami');

    expect($response->status())->toBe(503);
    expect($response->json('error'))->toBe('feature_disabled');
});

test('whoami returns user instance endpoints feature_enabled', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/whoami');

    expect($response->status())->toBe(200);
    $json = $response->json();
    expect($json)->toHaveKeys(['user', 'instance', 'feature_enabled', 'endpoints']);
    expect($json['user']['id'])->toBe($this->adminUser->id);
    expect($json['user']['is_admin'])->toBeTrue();
    expect($json['feature_enabled'])->toBeTrue();
    expect($json['endpoints'])->toHaveKeys(['whoami', 'bundle', 'exec', 'logs']);
});

test('bundle returns json with expected top-level keys', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/bundle');

    expect($response->status())->toBe(200);
    $json = $response->json();
    foreach (['generated_at', 'app', 'php', 'db', 'license', 'queue', 'logs', 'disk', 'plugins', 'settings'] as $key) {
        expect($json)->toHaveKey($key);
    }
});

test('bundle call creates audit log row', function () {
    $before = SupportDebugLog::where('type', SupportDebugLog::TYPE_BUNDLE)->count();

    $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/bundle');

    $after = SupportDebugLog::where('type', SupportDebugLog::TYPE_BUNDLE)->count();
    expect($after)->toBe($before + 1);
});

test('exec shell returns zero exit and output', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'shell',
            'command' => 'echo acelle-smoke-test',
        ]);

    expect($response->status())->toBe(200);
    $json = $response->json();
    expect($json['exit_code'])->toBe(0);
    expect(trim($json['stdout']))->toBe('acelle-smoke-test');
    expect($json['type'])->toBe('shell');
});

test('exec tinker evaluates PHP and returns result', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'tinker',
            'command' => '2 * 21',
        ]);

    expect($response->status())->toBe(200);
    $json = $response->json();
    expect($json['exit_code'])->toBe(0);
    expect(trim($json['stdout']))->toBe('42');
});

test('exec rejects unknown type with 422', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'rm-rf',
            'command' => 'nope',
        ]);

    expect($response->status())->toBe(422);
});

test('exec rejects timeout over max with 422', function () {
    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'shell',
            'command' => 'echo ok',
            'timeout' => 9999,
        ]);

    expect($response->status())->toBe(422);
});

test('exec creates audit log row per call', function () {
    $before = SupportDebugLog::where('type', SupportDebugLog::TYPE_SHELL)->count();

    $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'shell',
            'command' => 'echo audit-test',
        ]);

    $after = SupportDebugLog::where('type', SupportDebugLog::TYPE_SHELL)->count();
    expect($after)->toBe($before + 1);

    $log = SupportDebugLog::where('type', SupportDebugLog::TYPE_SHELL)
        ->orderBy('created_at', 'desc')->first();
    expect($log->user_id)->toBe($this->adminUser->id);
    expect($log->command)->toBe('echo audit-test');
    expect($log->exit_code)->toBe(0);
});

test('logs endpoint returns items array', function () {
    // Create some test logs
    $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'shell',
            'command' => 'echo logs-listing-test',
        ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/logs?type=shell&limit=5');

    expect($response->status())->toBe(200);
    $json = $response->json();
    expect($json)->toHaveKeys(['count', 'limit', 'items']);
    expect($json['limit'])->toBe(5);
    expect($json['items'])->toBeArray();
});

test('logs endpoint does not create audit row itself', function () {
    $before = SupportDebugLog::count();

    $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/logs');

    $after = SupportDebugLog::count();
    expect($after)->toBe($before);
});

test('logs endpoint filters by type', function () {
    $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->postJson('/api/v1/support/exec', [
            'type' => 'tinker',
            'command' => 'return "filter-test";',
        ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
        ->getJson('/api/v1/support/logs?type=tinker&limit=10');

    expect($response->status())->toBe(200);
    foreach ($response->json('items') as $item) {
        expect($item['type'])->toBe('tinker');
    }
});
