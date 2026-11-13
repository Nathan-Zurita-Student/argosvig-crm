<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Webkul\HumanResources\Repositories\IncidentRepository;
use Webkul\HumanResources\Repositories\ShiftAssignmentRepository;
use Webkul\HumanResources\Repositories\WorkSiteRepository;

class OperationsBriefing
{
    public function __construct(
        private WorkSiteRepository $sites,
        private IncidentRepository $incidents,
        private ShiftAssignmentRepository $shifts,
    ) {}

    public static function allowed(): bool
    {
        return bouncer()->getAuthorizedUserIds() === null && bouncer()->hasPermission('operations-assistant');
    }

    public function sites(): Collection
    {
        return bouncer()->hasPermission('human-resources.work-sites')
            ? $this->sites->getModel()->newQuery()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'service_contract_id', 'required_staff'])
            : collect();
    }

    public function report(?int $siteId, Carbon $from, Carbon $to, ?int $contractId = null): array
    {
        $site = $siteId === null ? null : $this->sites()->firstWhere('id', $siteId);
        abort_if($siteId !== null && ! $site, 404);
        abort_if($contractId !== null && (! bouncer()->hasPermission('human-resources.contracts') || $site), 404);

        $incidents = bouncer()->hasPermission('human-resources.incidents') && bouncer()->hasPermission('human-resources.incidents.view')
            ? $this->incidents->getModel()->newQuery()
                ->when($site, fn ($query) => $site->service_contract_id ? $query->where('service_contract_id', $site->service_contract_id) : $query->whereRaw('1 = 0'))
                ->when($contractId, fn ($query) => $query->where('service_contract_id', $contractId))
                ->where('occurred_at', '<', $to)
                ->where(fn ($query) => $query->where('occurred_at', '>=', $from)->orWhereIn('status', ['open', 'investigating']))
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderByDesc('occurred_at')->paginate(15, ['id', 'code', 'title', 'occurred_at', 'severity', 'status'], 'occurrences')->withQueryString()
            : null;

        $shifts = bouncer()->hasPermission('human-resources.work-sites') && bouncer()->hasPermission('human-resources.shifts')
            ? $this->shifts->getModel()->newQuery()->whereHas('workSite', fn ($query) => $query->where('status', 'active'))
                ->when($site, fn ($query) => $query->where('work_site_id', $site->id))
                ->when($contractId, fn ($query) => $query->whereHas('workSite', fn ($workSite) => $workSite->where('service_contract_id', $contractId)))
                ->where('starts_at', '<', $to)->where('ends_at', '>', $from)
                ->with('workSite:id,name')->orderBy('starts_at')->orderBy('id')
                ->paginate(15, ['id', 'work_site_id', 'starts_at', 'ends_at', 'status'], 'shifts')->withQueryString()
            : null;

        return compact('site', 'incidents', 'shifts');
    }
}
