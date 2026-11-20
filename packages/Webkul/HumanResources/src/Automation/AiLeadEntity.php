<?php

namespace Webkul\HumanResources\Automation;

use Webkul\Automation\Helpers\Entity\Lead;
use Webkul\Core\Services\CrmAi;
use Webkul\HumanResources\Jobs\RunAiAutomation;

/**
 * The opportunity workflow entity with two AI actions: a summary and a suggested next step,
 * each added to the opportunity as a note.
 */
class AiLeadEntity extends Lead
{
    public const AI_ACTIONS = ['ai_lead_summary', 'ai_lead_next_step'];

    public function getActions(): array
    {
        return [
            ...parent::getActions(),
            ['id' => 'ai_lead_summary', 'name' => trans('human_resources::ai.automation.lead_summary')],
            ['id' => 'ai_lead_next_step', 'name' => trans('human_resources::ai.automation.lead_next_step')],
        ];
    }

    public function executeActions(mixed $workflow, mixed $lead): void
    {
        parent::executeActions($workflow, $lead);

        if (! app(CrmAi::class)->enabled()) {
            return;
        }

        foreach ($workflow->actions as $action) {
            if (in_array($action['id'], self::AI_ACTIONS, true)) {
                // After the response, so saving the opportunity does not wait for the AI.
                RunAiAutomation::dispatch($action['id'], $lead->id)->afterResponse();
            }
        }
    }
}
