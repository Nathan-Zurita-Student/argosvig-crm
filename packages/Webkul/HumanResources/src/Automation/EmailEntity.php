<?php

namespace Webkul\HumanResources\Automation;

use Webkul\Automation\Helpers\Entity\AbstractEntity;
use Webkul\Automation\Repositories\WebhookRepository;
use Webkul\Automation\Services\WebhookService;
use Webkul\Core\Services\CrmAi;
use Webkul\Email\Models\EmailProxy;
use Webkul\HumanResources\Jobs\RunAiAutomation;
use Webkul\User\Models\UserProxy;

/**
 * Workflow trigger for e-mails that arrive in the inbox, with an action that turns the
 * message into an opportunity: the AI reads it and fills in title, description and value.
 */
class EmailEntity extends AbstractEntity
{
    public const EVENT = 'email.received.after';

    protected string $entityType = 'emails';

    public function __construct(
        protected WebhookService $webhookService,
        protected WebhookRepository $webhookRepository,
    ) {}

    public function getEntity(mixed $entity): mixed
    {
        return is_object($entity) ? $entity : EmailProxy::query()->find($entity);
    }

    /**
     * E-mails have no custom attributes: conditions use the subject and the message text.
     */
    public function getAttributes(string $entityType, array $skipAttributes = []): array
    {
        return [
            ['id' => 'subject', 'type' => 'text', 'name' => trans('human_resources::ai.automation.email_subject'), 'lookup_type' => null, 'options' => []],
            ['id' => 'reply', 'type' => 'text', 'name' => trans('human_resources::ai.automation.email_body'), 'lookup_type' => null, 'options' => []],
        ];
    }

    public function getActions(): array
    {
        return [[
            'id' => 'ai_email_to_lead',
            'name' => trans('human_resources::ai.automation.email_to_lead'),
            // The person who will own the opportunities created.
            'options' => UserProxy::query()->where('status', 1)->orderBy('name')->get(['id', 'name'])->toArray(),
        ]];
    }

    public function executeActions(mixed $workflow, mixed $email): void
    {
        if (! app(CrmAi::class)->enabled() || $email?->lead_id) {
            return;
        }

        foreach ($workflow->actions as $action) {
            if ($action['id'] === 'ai_email_to_lead') {
                RunAiAutomation::dispatch('ai_email_to_lead', $email->id, (int) ($action['value'] ?? 0) ?: null)->afterResponse();
            }
        }
    }
}
