<?php

// Admin web routes (plugin-owned URL space)
Route::group([
    'middleware' => ['web', 'not_installed', 'auth', 'backend', '2fa', 'demo_guard', 'console.active'],
    'prefix' => 'plugins/acelle/console',
    'namespace' => '\Acelle\Console\Controllers',
], function () {
    Route::get('/', 'DashboardController@index')->name('plugin.acelle.console.dashboard');
    Route::post('toggle', 'DashboardController@toggle')->name('plugin.acelle.console.toggle');
    Route::get('logs', 'DashboardController@logs')->name('plugin.acelle.console.logs');
    Route::get('terminal', 'DashboardController@terminal')->name('plugin.acelle.console.terminal');
});

// ──────────────────────────────────────────────────────────────────────────────
// Standard support API endpoints — gated on the support-debug feature flag.
//
// Middleware chain:
//   api            → throttle + route model bindings
//   auth:api       → requires valid api_token (Bearer header)
//   console.active → plugin must be installed AND enabled on /rui/admin/plugins
//   support.admin  → user must have admin role (admin_access policy) and be active
//   support.flag   → support_debug_enabled setting must be 'yes' (else 503)
//
// Turning support_debug_enabled off → all 4 endpoints below return 503.
// ──────────────────────────────────────────────────────────────────────────────
Route::group([
    'prefix' => 'api/v1/support',
    'middleware' => ['api', 'auth:api', 'console.active', 'support.admin', 'support.flag'],
    'namespace' => '\Acelle\Console\Controllers\Api',
], function () {
    Route::get('whoami', 'SupportController@whoami');
    Route::get('bundle', 'SupportController@bundle');
    Route::post('exec', 'SupportController@exec');
    Route::get('logs', 'SupportController@logs');
});

// ──────────────────────────────────────────────────────────────────────────────
// SPECIAL ROUTE: /api/v1/support/toggle
//
// This is the ONE endpoint that is NOT gated by the `support.flag` middleware —
// deliberately. It's the escape hatch for a chicken-and-egg problem:
//
//   If support_debug_enabled = 'no', every `support.flag`-gated endpoint
//   returns 503. If the toggle itself were gated, an admin could never turn
//   the feature back on via API — they'd have to log in to the browser and
//   click the button on /plugins/acelle/console. That defeats the purpose
//   of having a remote API in the first place.
//
// Safety — this endpoint is NOT unauthenticated:
//   api            → throttle + bindings (same as above)
//   auth:api       → still requires a valid api_token
//   console.active → plugin must still be installed + enabled
//   support.admin  → caller must still be an admin with an active account
//
// Only the feature-flag check is skipped. If an attacker already holds an
// admin's api_token, they have far worse capabilities elsewhere (exec shell,
// dump DB, create admins) — skipping the flag check here doesn't widen the
// attack surface.
// ──────────────────────────────────────────────────────────────────────────────
Route::group([
    'prefix' => 'api/v1/support',
    'middleware' => ['api', 'auth:api', 'console.active', 'support.admin'],
    'namespace' => '\Acelle\Console\Controllers\Api',
], function () {
    Route::post('toggle', 'SupportController@toggle');
});

// Shortcut redirect for muscle-memory convenience (gated by plugin active status)
Route::get('/console', function () {
    return redirect('/plugins/acelle/console/terminal');
})->middleware('console.active');
