<?php

/**
 * Live HTTP test — hits real localhost.
 * Run: php artisan test --filter=SupportDebugLive --group=live
 */

use App\Model\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class)->group('live');

test('send tinker command', function () {
    $expected = User::count();

    $login = Http::post('http://localhost/api/v1/user/login', [
        'email' => 'michael@nanosoft.tech',
        'password' => '69dcf4f4c938d@',
    ]);

    $token = $login->json('api_token');

    $r = Http::withToken($token)->post('http://localhost/api/v1/support/exec', [
        'type' => 'tinker',
        'command' => '\\App\\Model\\User::count()',
    ]);

    expect($r->status())->toBe(200);
    expect($r->json('exit_code'))->toBe(0);
    expect(trim($r->json('stdout')))->toBe((string) $expected);
});

test('send shell command', function () {
    $expected = base_path();

    $login = Http::post('http://localhost/api/v1/user/login', [
        'email' => 'michael@nanosoft.tech',
        'password' => '69dcf4f4c938d@',
    ]);

    $token = $login->json('api_token');

    $r = Http::withToken($token)->post('http://localhost/api/v1/support/exec', [
        'type' => 'shell',
        'command' => 'pwd',
    ]);

    expect($r->status())->toBe(200);
    expect($r->json('exit_code'))->toBe(0);
    expect(trim($r->json('stdout')))->toBe($expected);
});
