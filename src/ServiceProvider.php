<?php

namespace Acelle\Console;

use App\Library\Facades\Hook;
use App\Model\Plugin;
use Illuminate\Support\ServiceProvider as Base;

class ServiceProvider extends Base
{
    public function register()
    {
        defined('CONSOLE_PLUGIN_FULL_NAME') || define('CONSOLE_PLUGIN_FULL_NAME', 'acelle/console');
        defined('CONSOLE_PLUGIN_SHORT_NAME') || define('CONSOLE_PLUGIN_SHORT_NAME', 'console');

        $translationFolder = storage_path('app/data/plugins/acelle/console/lang/');

        Hook::add('add_translation_file', function () use ($translationFolder) {
            return [
                'id' => '#acelle/console_translation_file',
                'plugin_name' => 'acelle/console',
                'file_title' => 'Translation for acelle/console plugin',
                'translation_folder' => $translationFolder,
                'translation_prefix' => 'console',
                'file_name' => 'messages.php',
                'master_translation_file' => realpath(__DIR__.'/../resources/lang/en/messages.php'),
            ];
        });
    }

    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'console');

        $this->app['router']->aliasMiddleware('support.admin', Middleware\SupportAdmin::class);
        $this->app['router']->aliasMiddleware('support.flag', Middleware\SupportFlag::class);
        $this->app['router']->aliasMiddleware('console.active', Middleware\ConsolePluginActive::class);
        $this->loadRoutesFrom(__DIR__.'/../routes.php');

        Hook::on('activate_plugin_acelle/console', function () {
            \Artisan::call('migrate', [
                '--path' => 'storage/app/plugins/acelle/console/database/migrations',
                '--force' => true,
            ]);
        });

        Hook::on('delete_plugin_acelle/console', function () {
            \Artisan::call('migrate:rollback', [
                '--path' => 'storage/app/plugins/acelle/console/database/migrations',
                '--force' => true,
            ]);
        });
    }
}
