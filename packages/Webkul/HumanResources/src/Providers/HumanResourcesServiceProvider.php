<?php

namespace Webkul\HumanResources\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Webkul\HumanResources\Console\RemindDocumentExpirations;
use Webkul\HumanResources\Console\SyncAlterdataTimekeeping;

class HumanResourcesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'human_resources');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'human_resources');

        if ($this->app->runningInConsole()) {
            $this->commands([RemindDocumentExpirations::class, SyncAlterdataTimekeeping::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('hr:remind-document-expirations')->dailyAt('08:00')->withoutOverlapping();
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
    }
}
