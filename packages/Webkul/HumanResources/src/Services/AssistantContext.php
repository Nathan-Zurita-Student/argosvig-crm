<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\ActivityProxy;
use Webkul\Admin\Traits\ScopesMailAccess;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\Email\Models\EmailProxy;
use Webkul\HumanResources\Models\ChecklistColumnProxy;
use Webkul\HumanResources\Models\ChecklistProxy;
use Webkul\HumanResources\Models\DepartmentProxy;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Models\IncidentProxy;
use Webkul\HumanResources\Models\ServiceContractProxy;
use Webkul\HumanResources\Models\ShiftAssignmentProxy;
use Webkul\HumanResources\Models\VacationProxy;
use Webkul\HumanResources\Models\WorkSiteProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Product\Models\ProductProxy;
use Webkul\Quote\Models\QuoteProxy;

class AssistantContext
{
    use ScopesMailAccess;

    private const RECORDS = [
        'lead' => [LeadProxy::class, ['leads', 'leads.view'], 'admin.leads.view', ['title', 'lead_value', 'expected_close_date', 'closed_at']],
        'quote' => [QuoteProxy::class, ['quotes', 'quotes.edit'], 'admin.quotes.edit', ['subject', 'grand_total', 'expired_at']],
        'person' => [PersonProxy::class, ['contacts.persons', 'contacts.persons.view'], 'admin.contacts.persons.view', []],
        'organization' => [OrganizationProxy::class, ['contacts.organizations', 'contacts.organizations.edit'], 'admin.contacts.organizations.edit', ['name']],
        'product' => [ProductProxy::class, ['products', 'products.view'], 'admin.products.view', ['name', 'sku', 'price']],
        'activity' => [ActivityProxy::class, ['activities', 'activities.edit'], 'admin.activities.edit', ['title', 'type', 'schedule_from', 'schedule_to', 'is_done']],
        'employee' => [EmployeeProxy::class, ['human-resources.employees', 'human-resources.employees.view'], 'admin.human_resources.employees.show', ['status']],
        'vacation' => [VacationProxy::class, ['human-resources.vacations', 'human-resources.vacations.edit'], 'admin.human_resources.vacations.edit', ['starts_at', 'ends_at', 'status']],
        'incident' => [IncidentProxy::class, ['human-resources.incidents', 'human-resources.incidents.view'], 'admin.human_resources.incidents.show', ['code', 'severity', 'status', 'occurred_at']],
        'contract' => [ServiceContractProxy::class, ['human-resources.contracts', 'human-resources.contracts.edit'], 'admin.human_resources.contracts.edit', ['contract_number', 'status', 'starts_at', 'ends_at']],
        'site' => [WorkSiteProxy::class, ['human-resources.work-sites', 'human-resources.work-sites.edit'], 'admin.human_resources.work_sites.edit', ['code', 'name', 'status', 'required_staff']],
        'shift' => [ShiftAssignmentProxy::class, ['human-resources.shifts', 'human-resources.shifts.edit'], 'admin.human_resources.shifts.edit', ['status', 'shift_type', 'starts_at', 'ends_at']],
        'department' => [DepartmentProxy::class, ['human-resources.departments', 'human-resources.departments.edit'], 'admin.human_resources.departments.edit', ['code', 'name', 'is_active']],
    ];

    private const LISTS = [
        'admin.leads.index' => [LeadProxy::class, 'leads', 'lead'],
        'admin.quotes.index' => [QuoteProxy::class, 'quotes', 'quote'],
        'admin.contacts.persons.index' => [PersonProxy::class, 'contacts.persons', 'person'],
        'admin.contacts.organizations.index' => [OrganizationProxy::class, 'contacts.organizations', 'organization'],
        'admin.products.index' => [ProductProxy::class, 'products', 'product'],
        'admin.activities.index' => [ActivityProxy::class, 'activities', 'activity'],
        'admin.human_resources.employees.index' => [EmployeeProxy::class, 'human-resources.employees', 'employee'],
        'admin.human_resources.vacations.index' => [VacationProxy::class, 'human-resources.vacations', 'vacation'],
        'admin.human_resources.incidents.index' => [IncidentProxy::class, 'human-resources.incidents', 'incident'],
        'admin.human_resources.contracts.index' => [ServiceContractProxy::class, 'human-resources.contracts', 'contract'],
        'admin.human_resources.work_sites.index' => [WorkSiteProxy::class, 'human-resources.work-sites', 'site'],
        'admin.human_resources.shifts.index' => [ShiftAssignmentProxy::class, 'human-resources.shifts', 'shift'],
        'admin.human_resources.departments.index' => [DepartmentProxy::class, 'human-resources.departments', 'department'],
    ];

    public function listRoutes(): array
    {
        return array_keys(self::LISTS);
    }

    public function fromRequest(): array
    {
        $route = request()->route()?->getName();
        $id = (int) request()->route('id');

        foreach (self::RECORDS as $type => [, , $recordRoute]) {
            if ($route === $recordRoute && $id) {
                return ['type' => $type, 'id' => $id];
            }
        }

        if ($route === 'admin.mail.view' && $id) {
            return ['type' => 'email', 'id' => $id, 'folder' => request()->route('route')];
        }

        if (isset(self::LISTS[$route])) {
            return ['type' => 'listing', 'section' => $route];
        }

        return match (true) {
            $route === 'admin.operations.index' => ['type' => 'operations'],
            request()->routeIs('admin.human_resources.document_center.*') => ['type' => 'documents'],
            request()->routeIs('admin.human_resources.timekeeping.*') => ['type' => 'timekeeping'],
            request()->routeIs('admin.human_resources.checklists.*') => ['type' => 'checklists'],
            default => [],
        };
    }

    public function facts(array $context): array
    {
        $type = $context['type'] ?? 'overview';

        if (in_array($type, ['overview', 'operations', 'documents', 'timekeeping'], true)) {
            return $this->overview($type);
        }

        if ($type === 'checklists') {
            return $this->checklists();
        }

        if ($type === 'email') {
            return $this->email($context);
        }

        if ($type === 'listing') {
            return $this->listing($context['section']);
        }

        abort_unless(isset(self::RECORDS[$type]), 404);
        [$model, $permissions, $route, $fields] = self::RECORDS[$type];
        abort_unless($this->permitted($permissions), 401);
        $query = $model::query();

        if (in_array($type, ['lead', 'quote', 'person', 'organization'], true)) {
            $query = bouncer()->scopeToAuthorizedUsers($query);
        } elseif ($type === 'activity') {
            $query = $this->visibleActivities($query);
        } elseif ($type !== 'product') {
            abort_unless(bouncer()->getAuthorizedUserIds() === null, 401);
        }

        $record = $query->findOrFail($context['id']);
        $facts = $record->only($fields);
        if ($type === 'lead') {
            $facts['stage'] = $record->stage?->name;
            if ($this->permitted(['activities'])) {
                $facts['pending_activities'] = $this->visibleActivities($record->activities()->getQuery())->where('is_done', false)->count();
            }
        }
        if ($type === 'quote') {
            $facts['items'] = $record->items()->orderBy('id')->limit(10)->get(['name', 'quantity', 'total'])
                ->map(fn ($item) => $item->only(['name', 'quantity', 'total']))->all();
        }

        return [['module' => $type, 'record' => $facts], [['label' => trans('human_resources::ai.open_record'), 'url' => route($route, $record->id)]]];
    }

    public function reportQuery(string $route): ?Builder
    {
        if ($route === 'admin.human_resources.checklists.index'
            && class_exists(ChecklistProxy::class)
            && Schema::hasTable('hr_checklists')
            && bouncer()->hasPermission('checklists')) {
            return ChecklistProxy::query();
        }

        if (! isset(self::LISTS[$route])) {
            return null;
        }

        [$model, $permission, $type] = self::LISTS[$route];
        if (! bouncer()->hasPermission($permission)
            || (str_starts_with($permission, 'human-resources.') && bouncer()->getAuthorizedUserIds() !== null)) {
            return null;
        }

        $query = $model::query();
        if (in_array($type, ['lead', 'quote', 'person', 'organization'], true)) {
            $query = bouncer()->scopeToAuthorizedUsers($query);
        } elseif ($type === 'activity') {
            $query = $this->visibleActivities($query);
        }

        return $query;
    }

    private function listing(string $route): array
    {
        $query = $this->reportQuery($route);
        abort_unless($query, 401);
        $type = self::LISTS[$route][2];

        $facts = ['module' => $type, 'scope' => 'Total de registros acessíveis no módulo, sem os filtros da tela.', 'total' => (clone $query)->count()];
        $column = match ($type) {
            'employee', 'incident', 'contract', 'site', 'shift', 'vacation' => 'status',
            'activity' => 'type',
            'department' => 'is_active',
            default => null,
        };
        if ($column) {
            $facts['counts_by_'.$column] = $query->selectRaw($column.', count(*) as total')->groupBy($column)->pluck('total', $column)->all();
        }

        return [$facts, [['label' => trans('human_resources::ai.open_module'), 'url' => route($route)]]];
    }

    private function email(array $context): array
    {
        $folder = $context['folder'] ?? 'inbox';
        abort_unless($this->permitted(['mail', 'mail.view', 'mail.'.$folder]), 401);
        $email = EmailProxy::query()->findOrFail($context['id']);
        $this->preventUnauthorizedMailAccess($email);
        abort_unless(in_array($folder, $email->folders ?? [], true), 404);

        return [['module' => 'email', 'record' => ['subject' => $email->subject]], [
            ['label' => trans('human_resources::ai.open_record'), 'url' => route('admin.mail.view', ['route' => $folder, 'id' => $email->id])],
        ]];
    }

    private function overview(string $type): array
    {
        $facts = ['module' => $type, 'date' => now()->toDateTimeString()];
        $links = [];
        if ($type === 'overview' && $this->permitted(['leads', 'leads.view'])) {
            $facts['open_opportunities'] = bouncer()->scopeToAuthorizedUsers(LeadProxy::query())
                ->whereNull('closed_at')->whereDoesntHave('stage', fn ($stage) => $stage->whereIn('code', ['won', 'lost']))->count();
            $links[] = ['label' => trans('human_resources::commercial.tabs.leads'), 'url' => route('admin.leads.follow_up')];
        }
        if ($type === 'overview' && $this->permitted(['activities'])) {
            $facts['overdue_activities'] = $this->visibleActivities(ActivityProxy::query())->where('is_done', false)->where('schedule_to', '<', now())->count();
            $links[] = ['label' => trans('human_resources::commercial.tabs.activities'), 'url' => route('admin.activities.follow_up')];
        }
        if (OperationsBriefing::allowed()) {
            $links[] = ['label' => trans('human_resources::operations.title'), 'url' => route('admin.operations.index')];
            if ($this->permitted(['human-resources.incidents', 'human-resources.incidents.view'])) {
                $facts['open_incidents'] = IncidentProxy::query()->whereIn('status', ['open', 'investigating'])->count();
            }
            if ($this->permitted(['human-resources.contracts'])) {
                $facts['expiring_contracts_30_days'] = ServiceContractProxy::query()->expiring()->count();
            }
        }
        if (bouncer()->getAuthorizedUserIds() === null && bouncer()->hasPermission('document-center')) {
            $private = bouncer()->hasPermission('human-resources.document-center');
            $shared = bouncer()->hasPermission('human-resources.shared-documents');
            if ($private || $shared) {
                $facts['expiring_documents_30_days'] = EmployeeDocumentProxy::query()->whereBetween('expires_at', [today(), today()->addDays(30)])
                    ->when(! $private, fn ($query) => $query->whereNull('employee_id'))
                    ->when(! $shared, fn ($query) => $query->whereNotNull('employee_id'))->count();
                $links[] = ['label' => trans('human_resources::workforce.documents'), 'url' => route('admin.human_resources.document_center.index', ['expiry' => 'soon'])];
            }
        }
        if ($type === 'overview' && bouncer()->hasPermission('checklists')) {
            $facts['open_checklists'] = ChecklistProxy::query()->whereNull('completed_at')->count();
            $facts['overdue_checklists'] = ChecklistProxy::query()->whereNull('completed_at')->whereDate('due_date', '<', today())->count();
            $links[] = ['label' => trans('human_resources::checklists.title'), 'url' => route('admin.human_resources.checklists.index')];
        }
        if (bouncer()->getAuthorizedUserIds() === null && bouncer()->hasPermission('timekeeping')) {
            $links[] = ['label' => trans('human_resources::workforce.timekeeping'), 'url' => route('admin.human_resources.timekeeping.index')];
            if ($type === 'timekeeping') {
                $start = today()->startOfMonth();
                $facts['month'] = $start->format('Y-m');
                $facts['punches_in_month'] = DB::table('hr_timekeeping_entries')
                    ->where('occurred_at', '>=', $start->copy()->utc())
                    ->where('occurred_at', '<', $start->copy()->addMonth()->utc())->count();
            }
        }

        return [$facts, $links];
    }

    /**
     * The checklist board: each column with its open cards, so the AI can say what is late,
     * who is responsible and what is left to check.
     */
    private function checklists(): array
    {
        abort_unless(bouncer()->hasPermission('checklists'), 401);

        $columns = ChecklistColumnProxy::query()->orderBy('position')->get();
        $open = ChecklistProxy::query()->whereNull('completed_at')
            ->with(['employee', 'workSite', 'assignee'])->withCount(['items', 'items as items_done' => fn ($items) => $items->whereNotNull('completed_at')])
            ->orderBy('due_date')->orderBy('id')->limit(60)->get()->groupBy('column_id');

        $facts = [
            'module' => 'checklists',
            'date' => now()->toDateString(),
            'open_cards' => ChecklistProxy::query()->whereNull('completed_at')->count(),
            'overdue_cards' => ChecklistProxy::query()->whereNull('completed_at')->whereDate('due_date', '<', today())->count(),
            'completed_last_30_days' => ChecklistProxy::query()->where('completed_at', '>=', now()->subDays(30))->count(),
            'columns' => $columns->map(fn ($column) => [
                'name' => $column->label,
                'completes_cards' => $column->completes,
                'cards' => ($open[$column->id] ?? collect())->map(fn ($card) => array_filter([
                    'title' => $card->title,
                    'employee' => $card->employee?->full_name,
                    'site' => $card->workSite?->name,
                    'assignee' => $card->assignee?->name,
                    'due_date' => $card->due_date?->toDateString(),
                    'items' => $card->items_count ? $card->items_done.'/'.$card->items_count : null,
                ]))->values()->all(),
            ])->all(),
        ];

        return [$facts, [['label' => trans('human_resources::checklists.title'), 'url' => route('admin.human_resources.checklists.index')]]];
    }

    private function visibleActivities(Builder $query): Builder
    {
        $ids = bouncer()->getAuthorizedUserIds();

        return $ids === null ? $query : $query->where(fn ($visible) => $visible->whereIn('activities.user_id', $ids)
            ->orWhereHas('participants', fn ($participant) => $participant->whereIn('user_id', $ids)));
    }

    private function permitted(array $permissions): bool
    {
        return collect($permissions)->every(fn ($permission) => bouncer()->hasPermission($permission));
    }
}
