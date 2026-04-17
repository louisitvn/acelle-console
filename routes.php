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

// API routes (paths unchanged for external compat)
Route::group([
    'prefix' => 'api/v1/support',
    'middleware' => ['auth:api', 'console.active', 'support.admin'],
    'namespace' => '\Acelle\Console\Controllers\Api',
], function () {
    Route::get('whoami', 'SupportController@whoami');
    Route::get('bundle', 'SupportController@bundle');
    Route::post('exec', 'SupportController@exec');
    Route::get('logs', 'SupportController@logs');
});

// Shortcut redirect for muscle-memory convenience (gated by plugin active status)
Route::get('/console', function () {
    return redirect('/plugins/acelle/console/terminal');
})->middleware('console.active');
