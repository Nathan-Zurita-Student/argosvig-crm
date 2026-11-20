<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Facades\Validator;
use Webkul\Contact\Models\OrganizationProxy;
use Webkul\Contact\Models\PersonProxy;
use Webkul\HumanResources\Models\ChecklistColumnProxy;
use Webkul\HumanResources\Models\DepartmentProxy;
use Webkul\HumanResources\Models\EmployeeProxy;
use Webkul\HumanResources\Models\ServiceContractProxy;
use Webkul\HumanResources\Models\WorkSiteProxy;
use Webkul\User\Models\UserProxy;

class AssistantDrafts
{
    private const FORMS = [
        'lead' => ['leads.create', 'admin.leads.create', ['title' => 'string|max:255', 'description' => 'string|max:5000', 'lead_value' => 'numeric|min:0', 'expected_close_date' => 'date_format:Y-m-d']],
        'person' => ['contacts.persons.create', 'admin.contacts.persons.create', ['name' => 'string|max:100', 'job_title' => 'string|max:100', 'email' => 'email:rfc|max:255', 'phone' => 'string|max:30']],
        'organization' => ['contacts.organizations.create', 'admin.contacts.organizations.create', ['name' => 'string|max:255']],
        'product' => ['products.create', 'admin.products.create', ['name' => 'string|max:255', 'sku' => 'string|max:100', 'description' => 'string|max:5000', 'price' => 'numeric|min:0', 'quantity' => 'integer|min:0']],
        'quote' => ['quotes.create', 'admin.quotes.create', ['subject' => 'string|max:255', 'description' => 'string|max:3000', 'expired_at' => 'date_format:Y-m-d']],
        'activity' => ['activities.create', 'admin.activities.create', ['title' => 'string|max:80', 'type' => 'in:call,meeting,lunch,note', 'comment' => 'string|max:500', 'location' => 'string|max:255', 'schedule_from' => 'date_format:Y-m-d\TH:i', 'schedule_to' => 'date_format:Y-m-d\TH:i']],
        'incident' => ['human-resources.incidents.create', 'admin.human_resources.incidents.create', ['title' => 'string|max:255', 'description' => 'string|max:5000', 'location' => 'string|max:255', 'occurred_at' => 'date_format:Y-m-d\TH:i', 'severity' => 'in:low,medium,high,critical']],
        'contract' => ['human-resources.contracts.create', 'admin.human_resources.contracts.create', ['contract_number' => 'string|max:100', 'title' => 'string|max:255', 'starts_at' => 'date_format:Y-m-d', 'ends_at' => 'date_format:Y-m-d', 'monthly_value' => 'numeric|min:0', 'description' => 'string|max:5000']],
        'employee' => ['human-resources.employees.create', 'admin.human_resources.employees.create', ['employee_number' => 'string|max:50', 'full_name' => 'string|max:255', 'job_title' => 'string|max:255', 'hire_date' => 'date_format:Y-m-d']],
        'department' => ['human-resources.departments.create', 'admin.human_resources.departments.create', ['code' => 'string|max:50', 'name' => 'string|max:255', 'description' => 'string|max:5000']],
        'site' => ['human-resources.work-sites.create', 'admin.human_resources.work_sites.create', ['code' => 'string|max:100', 'name' => 'string|max:255', 'city' => 'string|max:100', 'state' => 'string|size:2', 'required_staff' => 'integer|min:1|max:10000', 'notes' => 'string|max:5000']],
        'shift' => ['human-resources.shifts.create', 'admin.human_resources.shifts.create', ['starts_at' => 'date_format:Y-m-d\TH:i', 'ends_at' => 'date_format:Y-m-d\TH:i', 'shift_type' => 'in:day,night,flexible', 'notes' => 'string|max:5000']],
        'vacation' => ['human-resources.vacations.create', 'admin.human_resources.vacations.create', ['starts_at' => 'date_format:Y-m-d', 'ends_at' => 'date_format:Y-m-d', 'type' => 'in:regular,collective,split', 'notes' => 'string|max:5000']],
        // A card of the checklist board: the board opens its "new card" modal filled in.
        'checklist' => ['checklists.create', 'admin.human_resources.checklists.index', ['title' => 'string|max:150', 'description' => 'string|max:5000', 'due_date' => 'date_format:Y-m-d', 'column' => 'string|max:60', 'assignee_name' => 'string|max:255']],
    ];

    /**
     * Checklist items the AI may write on one card.
     */
    private const CHECKLIST_ITEMS = 50;

    private const RELATIONS = [
        'organization_name' => [OrganizationProxy::class, 'name', 'organization_id', 'contacts.organizations'],
        'contact_name' => [PersonProxy::class, 'name', 'person_id', 'contacts.persons'],
        'employee_number_ref' => [EmployeeProxy::class, 'employee_number', 'employee_id', 'human-resources.employees'],
        'site_code' => [WorkSiteProxy::class, 'code', 'work_site_id', 'human-resources.work-sites'],
        'department_code' => [DepartmentProxy::class, 'code', 'department_id', 'human-resources.departments'],
        'contract_number_ref' => [ServiceContractProxy::class, 'contract_number', 'service_contract_id', 'human-resources.contracts'],
    ];

    private const FORM_RELATIONS = [
        'person' => ['organization_name'], 'lead' => ['contact_name'], 'quote' => ['contact_name'],
        'contract' => ['organization_name'], 'employee' => ['department_code'], 'vacation' => ['employee_number_ref'],
        'shift' => ['employee_number_ref', 'site_code'],
        'incident' => ['employee_number_ref', 'contract_number_ref'], 'site' => ['contract_number_ref'],
        'checklist' => ['employee_number_ref', 'site_code'],
    ];

    public function available(): array
    {
        $forms = [];
        foreach (array_keys(self::FORMS) as $type) {
            if ($this->permitted($type)) {
                $forms[$type] = array_keys($this->rules($type));
            }
        }
        if (isset($forms['quote']) && bouncer()->hasPermission('products')) {
            $forms['quote'][] = 'items (lista de objetos com product_sku e quantity)';
        }
        if (isset($forms['checklist'])) {
            $forms['checklist'][] = 'items (lista de textos, um por item a marcar no checklist)';
            $forms['checklist'][] = 'column é o nome de uma coluna do quadro: '.ChecklistColumnProxy::query()->orderBy('position')->pluck('label')->implode(', ');
        }

        return $forms;
    }

    public function validate(string $type, array $fields): array
    {
        if (! $this->permitted($type)) {
            return [];
        }

        $valid = [];
        foreach ($this->rules($type) as $field => $rule) {
            $value = $fields[$field] ?? null;
            if ((is_string($value) || is_int($value) || is_float($value))
                && ! preg_match('/[<>]|\{\{|\}\}/', (string) $value)
                && Validator::make([$field => $value], [$field => 'required|'.$rule])->passes()) {
                $valid[$field] = $value;
            }
        }
        if ($type === 'quote' && bouncer()->hasPermission('products') && is_array($fields['items'] ?? null)) {
            $items = array_slice(array_values($fields['items']), 0, 12);
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $item = array_intersect_key($item, array_flip(['product_sku', 'quantity']));
                if (Validator::make($item, ['product_sku' => 'required|string|max:100', 'quantity' => 'required|integer|min:1|max:100000'])->passes()) {
                    $valid['items'][] = $item;
                }
            }
        }
        if ($type === 'checklist' && is_array($fields['items'] ?? null)) {
            $items = collect($fields['items'])->filter(fn ($item) => is_string($item) && trim($item) !== '' && ! preg_match('/[<>]|\{\{|\}\}/', $item))
                ->map(fn ($item) => mb_substr(trim($item), 0, 255))->take(self::CHECKLIST_ITEMS)->values()->all();

            if ($items) {
                $valid['items'] = $items;
            }
        }

        return $valid;
    }

    public function route(string $type): string
    {
        abort_unless($this->permitted($type), 401);

        return self::FORMS[$type][1];
    }

    public function formInput(string $type, array $fields): array
    {
        foreach (self::FORM_RELATIONS[$type] ?? [] as $relation) {
            if (! isset($fields[$relation])) {
                continue;
            }

            [$model, $key, $target, $permission] = self::RELATIONS[$relation];
            $name = $fields[$relation];
            unset($fields[$relation]);

            if (! bouncer()->hasPermission($permission)) {
                continue;
            }

            $matches = bouncer()->scopeToAuthorizedUsers($model::query())->where($key, $name)->limit(2)->pluck('id');
            if ($matches->count() === 1) {
                $fields[$target] = (int) $matches->first();
            }
        }

        if ($type === 'person') {
            foreach (['email' => 'emails', 'phone' => 'contact_numbers'] as $input => $target) {
                if (isset($fields[$input])) {
                    $fields[$target] = [['label' => 'work', 'value' => $fields[$input]]];
                    unset($fields[$input]);
                }
            }
        }

        if ($type === 'activity') {
            foreach (['schedule_from', 'schedule_to'] as $field) {
                if (isset($fields[$field])) {
                    $fields[$field] = str_replace('T', ' ', $fields[$field]).':00';
                }
            }
        }

        if ($type === 'quote' && isset($fields['items'])) {
            $fields['draft_items'] = $fields['items'];
            unset($fields['items']);
        }

        if ($type === 'checklist') {
            // Column and assignee by name, kept only when exactly one matches.
            if (isset($fields['column'])) {
                $columns = ChecklistColumnProxy::query()->whereRaw('LOWER(label) = ?', [mb_strtolower($fields['column'])])->limit(2)->pluck('id');

                if ($columns->count() === 1) {
                    $fields['column_id'] = (int) $columns->first();
                }

                unset($fields['column']);
            }

            if (isset($fields['assignee_name'])) {
                $users = UserProxy::query()->where('status', 1)->where('name', $fields['assignee_name'])->limit(2)->pluck('id');

                if ($users->count() === 1) {
                    $fields['assigned_to'] = (int) $users->first();
                }

                unset($fields['assignee_name']);
            }

            $fields['checklist_draft'] = 1;
        }

        return $fields;
    }

    private function rules(string $type): array
    {
        $rules = self::FORMS[$type][2];
        foreach (self::FORM_RELATIONS[$type] ?? [] as $relation) {
            if (bouncer()->hasPermission(self::RELATIONS[$relation][3])) {
                $rules[$relation] = 'string|max:255';
            }
        }

        return $rules;
    }

    private function permitted(string $type): bool
    {
        if (! isset(self::FORMS[$type])) {
            return false;
        }

        [$permission, $route] = self::FORMS[$type];
        $roles = acl()->getRoles();

        return bouncer()->hasPermission($permission)
            && bouncer()->hasPermission($roles->get($route, $permission))
            && (! str_starts_with($permission, 'human-resources.') || bouncer()->getAuthorizedUserIds() === null);
    }
}
