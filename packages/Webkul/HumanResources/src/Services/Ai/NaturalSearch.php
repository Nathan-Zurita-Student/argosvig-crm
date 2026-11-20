<?php

namespace Webkul\HumanResources\Services\Ai;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\HumanResources\Models\ChecklistProxy;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Models\IncidentProxy;
use Webkul\HumanResources\Models\ServiceContractProxy;
use Webkul\HumanResources\Models\WorkSiteProxy;
use Webkul\Lead\Models\LeadProxy;
use Webkul\Product\Models\ProductProxy;

/**
 * Finds records from a request in plain language, such as "companies in SP without an
 * active contract".
 *
 * The AI only translates the request into filters chosen from the fields below; this class
 * builds the query, applies the person's data scope and returns the matches. The AI never
 * writes SQL and never sees records it was not given.
 */
class NaturalSearch
{
    public const LIMIT = 20;

    private const TEXT_OPS = ['eq', 'neq', 'contains', 'empty', 'not_empty'];

    private const RANGE_OPS = ['eq', 'gt', 'gte', 'lt', 'lte', 'empty', 'not_empty'];

    /**
     * model, permission, title column, record route, fields as [kind, column or relation, options].
     */
    private const TYPES = [
        'organization' => [OrganizationProxy::class, 'contacts.organizations', 'name', 'admin.contacts.organizations.edit', [
            'name' => ['text', 'name'],
            'city' => ['text', 'address->city'],
            'state' => ['text', 'address->state'],
            'created_at' => ['date', 'created_at'],
            'has_active_contract' => ['flag', 'active_contract'],
        ]],
        'person' => [PersonProxy::class, 'contacts.persons', 'name', 'admin.contacts.persons.view', [
            'name' => ['text', 'name'],
            'email' => ['json', 'emails'],
            'job_title' => ['text', 'job_title'],
            'organization' => ['relation', 'organization.name'],
            'created_at' => ['date', 'created_at'],
        ]],
        'lead' => [LeadProxy::class, 'leads', 'title', 'admin.leads.view', [
            'title' => ['text', 'title'],
            'value' => ['number', 'lead_value'],
            'situation' => ['enum', 'situation', ['open', 'won', 'lost']],
            'owner' => ['relation', 'user.name'],
            'contact' => ['relation', 'person.name'],
            'expected_close_date' => ['date', 'expected_close_date'],
            'created_at' => ['date', 'created_at'],
        ]],
        'product' => [ProductProxy::class, 'products', 'name', 'admin.products.view', [
            'name' => ['text', 'name'],
            'sku' => ['text', 'sku'],
            'price' => ['number', 'price'],
            'quantity' => ['number', 'quantity'],
        ]],
        'employee' => [EmployeeProxy::class, 'human-resources.employees', 'full_name', 'admin.human_resources.employees.show', [
            'name' => ['text', 'full_name'],
            'status' => ['enum', 'status', ['active', 'on_leave', 'inactive']],
            'job_title' => ['text', 'job_title'],
            'department' => ['relation', 'department.name'],
            'hire_date' => ['date', 'hire_date'],
            'cnv_expires_at' => ['date', 'cnv_expires_at'],
        ]],
        'contract' => [ServiceContractProxy::class, 'human-resources.contracts', 'title', 'admin.human_resources.contracts.edit', [
            'title' => ['text', 'title'],
            'number' => ['text', 'contract_number'],
            'customer' => ['relation', 'organization.name'],
            'status' => ['enum', 'status', ['draft', 'active', 'suspended', 'expired', 'terminated']],
            'starts_at' => ['date', 'starts_at'],
            'ends_at' => ['date', 'ends_at'],
            'monthly_value' => ['number', 'monthly_value'],
        ]],
        'incident' => [IncidentProxy::class, 'human-resources.incidents', 'title', 'admin.human_resources.incidents.show', [
            'title' => ['text', 'title'],
            'location' => ['text', 'location'],
            'severity' => ['enum', 'severity', ['low', 'medium', 'high', 'critical']],
            'status' => ['enum', 'status', ['open', 'investigating', 'resolved', 'closed']],
            'occurred_at' => ['date', 'occurred_at'],
        ]],
        'site' => [WorkSiteProxy::class, 'human-resources.work-sites', 'name', 'admin.human_resources.work_sites.edit', [
            'name' => ['text', 'name'],
            'code' => ['text', 'code'],
            'city' => ['text', 'city'],
            'state' => ['text', 'state'],
            'status' => ['enum', 'status', ['active', 'inactive']],
        ]],
        // Cards of the checklist board; a result opens the card on the board (?card=id).
        'checklist' => [ChecklistProxy::class, 'checklists', 'title', 'admin.human_resources.checklists.index', [
            'title' => ['text', 'title'],
            'item' => ['relation', 'items.title'],
            'column' => ['relation', 'column.label'],
            'employee' => ['relation', 'employee.full_name'],
            'site' => ['relation', 'workSite.name'],
            'assignee' => ['relation', 'assignee.name'],
            'due_date' => ['date', 'due_date'],
            'completed_at' => ['date', 'completed_at'],
            'created_at' => ['date', 'created_at'],
        ], 'card'],
    ];

    /**
     * Searchable record types and their filters, limited to what this person may see, in the
     * compact form given to the AI.
     */
    public function available(): array
    {
        return collect(self::TYPES)->filter(fn (array $type) => $this->allowed($type[1]))
            ->map(fn (array $type) => collect($type[4])->map(fn (array $field) => match ($field[0]) {
                'enum' => 'um de: '.implode(', ', $field[2]),
                'number' => 'número: eq, gt, gte, lt, lte',
                'date' => 'data AAAA-MM-DD: eq, gt, gte, lt, lte, empty, not_empty',
                'flag' => 'sim ou não: is_true, is_false',
                default => 'texto: eq, neq, contains, empty, not_empty',
            })->all())
            ->all();
    }

    public function run(array $search): ?array
    {
        $type = $search['type'] ?? null;

        if (! is_string($type) || ! isset(self::TYPES[$type]) || ! $this->allowed(self::TYPES[$type][1])) {
            return null;
        }

        [$model, $permission, $title, $route, $fields] = self::TYPES[$type];
        $parameter = self::TYPES[$type][5] ?? null;

        $query = $model::query();

        if (in_array($type, ['organization', 'person', 'lead'], true)) {
            $query = bouncer()->scopeToAuthorizedUsers($query);
        }

        $applied = 0;

        foreach (array_slice((array) ($search['filters'] ?? []), 0, 8) as $filter) {
            if (is_array($filter) && isset($fields[$filter['field'] ?? null]) && $this->apply($query, $fields[$filter['field']], (string) ($filter['op'] ?? 'eq'), $filter['value'] ?? null)) {
                $applied++;
            }
        }

        $total = (clone $query)->count();

        return [
            'type' => $type,
            'label' => trans('human_resources::ai.import.types.'.$type),
            'total' => $total,
            'filtered' => $applied > 0,
            'items' => $query->latest($query->getModel()->getQualifiedKeyName())->limit(self::LIMIT)->get()
                ->map(fn ($record) => [
                    'title' => (string) ($record->{$title} ?: '#'.$record->getKey()),
                    'details' => $this->details($type, $record),
                    'url' => route($route, $parameter ? [$parameter => $record->getKey()] : $record->getKey()),
                ])->all(),
        ];
    }

    private function apply(Builder $query, array $field, string $op, mixed $value): bool
    {
        [$kind, $target] = $field;

        if (! in_array($op, ['is_true', 'is_false', 'in', ...self::RANGE_OPS, ...self::TEXT_OPS], true)) {
            return false;
        }

        if ($kind === 'flag') {
            $exists = fn ($sub) => $sub->select(DB::raw(1))->from('hr_service_contracts')
                ->whereColumn('hr_service_contracts.organization_id', 'organizations.id')
                ->where('hr_service_contracts.status', 'active')->whereNull('hr_service_contracts.deleted_at');

            $op === 'is_false' ? $query->whereNotExists($exists) : $query->whereExists($exists);

            return true;
        }

        if ($kind === 'enum') {
            $values = array_values(array_intersect((array) $value, $field[2]));

            if (! $values) {
                return false;
            }

            if ($target === 'situation') {
                $query->whereHas('stage', fn ($stage) => in_array('open', $values, true)
                    ? $stage->whereNotIn('code', array_diff(['won', 'lost'], $values))
                    : $stage->whereIn('code', $values));

                return true;
            }

            $op === 'neq' ? $query->whereNotIn($target, $values) : $query->whereIn($target, $values);

            return true;
        }

        if (in_array($op, ['empty', 'not_empty'], true)) {
            if ($kind === 'relation') {
                $op === 'empty' ? $query->doesntHave(Str::before($target, '.')) : $query->has(Str::before($target, '.'));
            } else {
                $op === 'empty'
                    ? $query->where(fn ($empty) => $empty->whereNull($target)->orWhere($target, ''))
                    : $query->whereNotNull($target)->where($target, '!=', '');
            }

            return true;
        }

        if (! is_scalar($value) || $value === '') {
            return false;
        }

        $value = (string) $value;

        if ($kind === 'number' && is_numeric($value)) {
            $query->where($target, $this->operator($op), (float) $value);

            return true;
        }

        if ($kind === 'date') {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $value)->toDateString();
            } catch (\Throwable) {
                return false;
            }

            $query->whereDate($target, $this->operator($op), $date);

            return true;
        }

        if ($kind === 'json') {
            $query->whereRaw('LOWER('.$target.') LIKE ?', ['%'.Str::lower($value).'%']);

            return true;
        }

        $compare = fn ($builder, $column) => match ($op) {
            'contains' => $builder->where($column, 'like', '%'.addcslashes($value, '%_\\').'%'),
            'neq' => $builder->where($column, '!=', $value),
            default => $builder->where($column, $value),
        };

        if ($kind === 'relation') {
            [$relation, $column] = explode('.', $target);

            $query->whereHas($relation, fn ($related) => $compare($related, $column));

            return true;
        }

        $compare($query, $target);

        return true;
    }

    private function operator(string $op): string
    {
        return ['gt' => '>', 'gte' => '>=', 'lt' => '<', 'lte' => '<=', 'neq' => '!='][$op] ?? '=';
    }

    private function details(string $type, mixed $record): string
    {
        $parts = match ($type) {
            'organization' => [$record->address['city'] ?? null, $record->address['state'] ?? null],
            'person' => [$record->emails[0]['value'] ?? null, $record->job_title],
            'lead' => [$record->lead_value !== null ? core()->formatBasePrice($record->lead_value) : null, $record->stage?->name],
            'product' => [$record->sku, core()->formatBasePrice($record->price)],
            'employee' => [$record->job_title, trans('human_resources::app.employees.statuses.'.$record->status)],
            'contract' => [$record->contract_number, $record->ends_at?->format('d/m/Y')],
            'incident' => [trans('human_resources::app.incidents.severity.'.$record->severity), $record->occurred_at?->format('d/m/Y')],
            'site' => [$record->code, $record->city],
            'checklist' => [
                $record->column?->label,
                $record->employee?->full_name ?? $record->workSite?->name,
                $record->due_date ? trans('human_resources::checklists.fields.due_date').' '.$record->due_date->format('d/m/Y') : null,
            ],
        };

        return implode(' · ', array_filter($parts, fn ($part) => filled($part)));
    }

    private function allowed(string $permission): bool
    {
        if (! bouncer()->hasPermission($permission)) {
            return false;
        }

        // Human resources records are global: only people with the global data scope see them.
        return ! str_starts_with($permission, 'human-resources.') || bouncer()->getAuthorizedUserIds() === null;
    }
}
