<?php

namespace Webkul\HumanResources\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Webkul\Core\ViewRenderEventManager;
use Webkul\HumanResources\Console\NotifyOperationsHandover;
use Webkul\HumanResources\Console\RemindDocumentExpirations;
use Webkul\HumanResources\Console\SyncAlterdataTimekeeping;
use Webkul\HumanResources\Services\OperationsAi;
use Webkul\HumanResources\Services\OperationsBriefing;

class HumanResourcesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadTranslationsFrom(__DIR__.'/../Resources/lang', 'human_resources');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'human_resources');

        RateLimiter::for('crm-ai', function (Request $request) {
            $route = $request->route()->getName();
            $limit = match ($route) {
                'admin.ai.connection.test' => 4,
                'admin.ai.download' => 15,
                default => 12,
            };

            return Limit::perMinute($limit)->by($route.'|'.($request->user('user')?->id ?? $request->ip()));
        });

        Event::listen('admin.configuration.edit.back_button.before', function (ViewRenderEventManager $event) {
            if (request()->route('slug') === 'general' && request()->route('slug2') === 'magic_ai' && bouncer()->hasPermission('configuration')) {
                $event->addTemplate('human_resources::ai.connection-link');
            }
        });

        Event::listen('admin.layout.header.actions.before', function (ViewRenderEventManager $event) {
            if (OperationsBriefing::allowed()) {
                $event->addTemplate('human_resources::operations.shortcut');
            }

            if (bouncer()->hasPermission('crm-ai') && app(OperationsAi::class)->available()) {
                $event->addTemplate('human_resources::ai.assistant');
            }
        });

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
            $this->commands([NotifyOperationsHandover::class, RemindDocumentExpirations::class, SyncAlterdataTimekeeping::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('hr:remind-document-expirations')->dailyAt('08:00')->withoutOverlapping();
                $schedule->command('hr:notify-operations-handover')->dailyAt('06:00')->withoutOverlapping();
            });
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/operations.php', 'operations');
    }
}
