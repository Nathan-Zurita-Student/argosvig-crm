<?php

namespace Webkul\HumanResources\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
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

        Event::listen('admin.leads.index.header.right.before', function (ViewRenderEventManager $event) {
            if (bouncer()->hasPermission('leads') && bouncer()->hasPermission('leads.view')) {
                $event->addTemplate('human_resources::commercial.link');
            }
        });

        Event::listen('admin.activities.index.toggle_view.before', function (ViewRenderEventManager $event) {
            if (bouncer()->hasPermission('activities')) {
                $event->addTemplate('human_resources::commercial.link');
            }
        });

        Event::listen('admin.contacts.quotes.edit.form_controls.before', function (ViewRenderEventManager $event) {
            if (bouncer()->hasPermission('quotes') && bouncer()->hasPermission('quotes.edit')) {
                $event->addTemplate('human_resources::commercial.quote-link');
            }
        });

        Event::listen('admin.quotes.index.create_button.before', function (ViewRenderEventManager $event) {
            $event->addTemplate('human_resources::commercial.link');
        });

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
