<?php

namespace Webkul\HumanResources\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Webkul\HumanResources\Models\EmployeeDocumentProxy;

class AssistantReport
{
    private const ROWS_PER_SECTION = 120;

    private const ROWS_IN_OVERVIEW = 360;

    private const SOURCES = [
        'opportunities' => ['commercial', 'admin.leads.index', ['title', 'stage', 'lead_value', 'expected_close_date']],
        'quotes' => ['commercial', 'admin.quotes.index', ['subject', 'grand_total', 'expired_at', 'created_at']],
        'people' => ['commercial', 'admin.contacts.persons.index', ['name', 'job_title', 'created_at']],
        'organizations' => ['commercial', 'admin.contacts.organizations.index', ['name', 'created_at']],
        'products' => ['commercial', 'admin.products.index', ['sku', 'name', 'quantity', 'price']],
        'activities' => ['commercial', 'admin.activities.index', ['title', 'type', 'schedule_from', 'is_done']],
        'employees' => ['personnel', 'admin.human_resources.employees.index', ['employee_number', 'full_name', 'job_title', 'status']],
        'departments' => ['personnel', 'admin.human_resources.departments.index', ['code', 'name', 'is_active']],
        'vacations' => ['personnel', 'admin.human_resources.vacations.index', ['employee', 'starts_at', 'ends_at', 'days', 'status']],
        'employee_documents' => ['personnel', null, ['employee', 'type', 'competence', 'expires_at', 'status']],
        'timekeeping' => ['personnel', null, ['employee', 'occurred_at', 'source']],
        'contracts' => ['operations', 'admin.human_resources.contracts.index', ['contract_number', 'title', 'monthly_value', 'ends_at', 'status']],
        'sites' => ['operations', 'admin.human_resources.work_sites.index', ['code', 'name', 'city', 'state', 'status']],
        'shifts' => ['operations', 'admin.human_resources.shifts.index', ['employee', 'site', 'starts_at', 'ends_at', 'status']],
        'incidents' => ['operations', 'admin.human_resources.incidents.index', ['code', 'title', 'occurred_at', 'severity', 'status']],
        'shared_documents' => ['operations', null, ['name', 'type', 'expires_at', 'status']],
        'checklists' => ['operations', 'admin.human_resources.checklists.index', ['title', 'due_date', 'completed_at']],
    ];

    public function __construct(private AssistantContext $context) {}

    public static function requested(string $question): bool
    {
        $terms = Str::squish(Str::lower(Str::ascii($question)));

        return preg_match('/\b(?:pdf|relatorio|report)\b/', $terms)
            && ! preg_match('/\b(?:csv|xlsx|excel|planilha)\b/', $terms)
            && preg_match('/\b(?:tod[oa]s? (?:os |as )?(?:dados|registros) (?:do |da )?(?:sistema|crm)|(?:relatorio|pdf) (?:geral|completo)(?: d[oea])? (?:sistema|crm|argosvig)|(?:system|crm) (?:overview|report))\b/', $terms);
    }

    public function sections(?string $only = null, int $page = 1, array $filters = []): array
    {
        abort_if($only !== null && ! isset(self::SOURCES[$only]), 404);
        $sections = [];
        $remaining = self::ROWS_IN_OVERVIEW;

        foreach (self::SOURCES as $key => [$group, $route, $columns]) {
            if ($only !== null && $key !== $only) {
                continue;
            }

            $query = $this->scopedQuery($key, $route);
            if ($query === null) {
                continue;
            }

            if ($key === 'activities') {
                $query->where('type', '!=', 'system');
            }

            if ($key === 'incidents' && $only === 'incidents') {
                $columns[] = 'location';
                IncidentReportFilters::apply($query, $filters);
            }

            $total = (clone $query)->count();
            $pages = max(1, (int) ceil($total / self::ROWS_PER_SECTION));
            abort_if($page > $pages, 404);
            if ($query instanceof Builder) {
                $relations = [
                    'employee' => ['employee_id', 'employee:id,full_name'],
                    'site' => ['work_site_id', 'workSite:id,name'],
                    'stage' => ['lead_pipeline_stage_id', 'stage:id,name'],
                ];
                $query->select(['id', ...array_map(fn ($column) => $relations[$column][0] ?? $column, $columns)])
                    ->with(array_column(array_intersect_key($relations, array_flip($columns)), 1));
            }

            $idColumn = $key === 'timekeeping' ? 'entries.id' : 'id';
            $limit = $only === null ? min(self::ROWS_PER_SECTION, $remaining) : self::ROWS_PER_SECTION;
            $offset = ($page - 1) * self::ROWS_PER_SECTION;
            $rows = $limit ? $query->orderByDesc($idColumn)->offset($offset)->limit($limit)->get() : collect();
            $remaining -= $rows->count();
            $last = $offset + $rows->count();
            $next = $last < $total ? ($limit < self::ROWS_PER_SECTION ? 1 : $page + 1) : null;

            $sections[] = [
                'key' => $key,
                'group' => trans('human_resources::reports.groups.'.$group),
                'title' => trans('human_resources::reports.sections.'.$key),
                'columns' => collect($columns)->mapWithKeys(fn ($column) => [
                    $column => trans('human_resources::reports.columns.'.$column),
                ])->all(),
                'rows' => $rows->map(fn ($row) => $this->displayRow($row, $columns, $key))->all(),
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'next' => $next,
                'from' => $rows->isNotEmpty() ? $offset + 1 : 0,
                'to' => $last,
            ];
        }

        abort_if($only !== null && ! $sections, 404);

        return $sections;
    }

    public function priorities(): array
    {
        $priorities = [];
        $today = today()->toDateString();
        $limit = today()->addDays(30)->toDateString();

        if ($query = $this->scopedQuery('incidents', self::SOURCES['incidents'][1])) {
            $priorities['open_incidents'] = $query->whereIn('status', ['open', 'investigating'])->count();
        }

        foreach (['employee_documents', 'shared_documents'] as $section) {
            if ($query = $this->scopedQuery($section, self::SOURCES[$section][1])) {
                $priorities['expiring_documents'] = ($priorities['expiring_documents'] ?? 0)
                    + $query->whereBetween('expires_at', [$today, $limit])->count();
            }
        }

        if ($query = $this->scopedQuery('contracts', self::SOURCES['contracts'][1])) {
            $priorities['expiring_contracts'] = $query->expiring()->count();
        }

        return $priorities;
    }

    private function scopedQuery(string $key, ?string $route): Builder|QueryBuilder|null
    {
        return $route === null ? $this->specialQuery($key) : $this->context->reportQuery($route);
    }

    private function specialQuery(string $key): Builder|QueryBuilder|null
    {
        if (bouncer()->getAuthorizedUserIds() !== null) {
            return null;
        }

        if ($key === 'timekeeping') {
            return bouncer()->hasPermission('timekeeping')
                ? DB::table('hr_timekeeping_entries as entries')
                    ->join('hr_employees as employees', 'employees.id', '=', 'entries.employee_id')
                    ->select('entries.id', 'entries.occurred_at', 'entries.source', 'employees.full_name as employee_name')
                : null;
        }

        $shared = $key === 'shared_documents';
        $permission = $shared ? 'human-resources.shared-documents' : 'human-resources.document-center';
        if (! bouncer()->hasPermission('document-center') || ! bouncer()->hasPermission($permission)) {
            return null;
        }

        $query = EmployeeDocumentProxy::query();

        return $shared ? $query->whereNull('employee_id') : $query->whereNotNull('employee_id');
    }

    private function displayRow(object $row, array $columns, string $section): array
    {
        return array_map(function ($column) use ($row, $section) {
            if ($column === 'employee') {
                return $row->employee_name ?? $row->employee?->full_name;
            }

            if ($column === 'site') {
                return $row->workSite?->name;
            }

            if ($column === 'stage') {
                return $row->stage?->name;
            }

            $value = $row->{$column};

            return match ($column) {
                'is_active', 'is_done' => trans('human_resources::reports.values.'.($value ? 'yes' : 'no')),
                'price', 'grand_total', 'lead_value', 'monthly_value' => $value === null
                    ? null : (config('app.currency') === 'BRL' ? 'R$' : config('app.currency')).' '.number_format((float) $value, 2, ',', '.'),
                'expired_at', 'expected_close_date', 'expires_at', 'created_at', 'due_date' => $this->date($value),
                'occurred_at', 'schedule_from', 'completed_at' => $this->date($value, true, $section === 'timekeeping'),
                'starts_at', 'ends_at' => $this->date($value, $section === 'shifts'),
                'status', 'severity', 'type', 'source' => $this->label($column, $value, $section),
                default => $value,
            };
        }, $columns);
    }

    private function date(mixed $value, bool $time = false, bool $utc = false): ?string
    {
        return $value ? CarbonImmutable::parse($value, $utc ? 'UTC' : config('app.timezone'))
            ->setTimezone(config('app.timezone'))->format($time ? 'd/m/Y H:i' : 'd/m/Y') : null;
    }

    private function label(string $column, mixed $value, string $section): ?string
    {
        if (! filled($value)) {
            return null;
        }
        if ($column === 'type' && in_array($section, ['employee_documents', 'shared_documents'], true)) {
            $key = 'human_resources::workforce.types.'.$value;

            return trans()->has($key) ? trans($key) : (string) $value;
        }
        $key = 'human_resources::reports.values.'.$value;

        return trans()->has($key) ? trans($key) : Str::headline((string) $value);
    }
}
