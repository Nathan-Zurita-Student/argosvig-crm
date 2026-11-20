<?php

namespace Webkul\HumanResources\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Webkul\Core\ViewRenderEventManager;
use Webkul\Email\Models\EmailProxy;
use Webkul\HumanResources\Automation\AiLeadEntity;
use Webkul\HumanResources\Automation\EmailEntity;
use Webkul\HumanResources\Console\MoveDocuments;
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
                'admin.ai.transcribe', 'admin.ai.incident_draft' => 6,
                'admin.ai.download' => 15,
                default => 12,
            };

            return Limit::perMinute($limit)->by($route.'|'.($request->user('user')?->id ?? $request->ip()));
        });

        $this->registerAiAutomations();

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
            $this->commands([MoveDocuments::class, NotifyOperationsHandover::class, RemindDocumentExpirations::class, SyncAlterdataTimekeeping::class]);

            $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
                $schedule->command('hr:remind-document-expirations')->dailyAt('08:00')->withoutOverlapping();
                $schedule->command('hr:notify-operations-handover')->dailyAt('06:00')->withoutOverlapping();
            });
        }
    }

    /**
     * AI in the workflows: opportunities gain a summary and a next-step action, and e-mails
     * arriving in the inbox become a trigger that can turn them into opportunities.
     */
    private function registerAiAutomations(): void
    {
        // The package names its entities and events in English only.
        foreach (['leads', 'activities', 'persons', 'quotes'] as $entity) {
            config([
                "workflows.trigger_entities.{$entity}.name" => trans('human_resources::ai.automation.entities.'.$entity),
                "workflows.trigger_entities.{$entity}.events" => collect(config("workflows.trigger_entities.{$entity}.events", []))
                    ->map(fn (array $event) => [...$event, 'name' => trans('human_resources::ai.automation.events.'.Str::of($event['event'])->beforeLast('.')->afterLast('.'))])
                    ->all(),
            ]);
        }

        config([
            'workflows.trigger_entities.leads.class' => AiLeadEntity::class,
            'workflows.trigger_entities.emails' => [
                'name' => trans('human_resources::ai.automation.emails'),
                'class' => EmailEntity::class,
                'events' => [['event' => EmailEntity::EVENT, 'name' => trans('human_resources::ai.automation.email_received')]],
            ],
        ]);

        // Inbound processors save messages without an event of their own.
        EmailProxy::modelClass()::created(function ($email) {
            if (collect((array) $email->folders)->contains(fn ($folder) => strtolower((string) $folder) === 'inbox')) {
                Event::dispatch(EmailEntity::EVENT, $email);
            }
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/menu.php', 'menu.admin');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/acl.php', 'acl');
        $this->mergeConfigFrom(dirname(__DIR__).'/Config/operations.php', 'operations');
    }
}
