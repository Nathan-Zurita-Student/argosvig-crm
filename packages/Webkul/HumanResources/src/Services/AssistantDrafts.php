<?php

namespace Webkul\HumanResources\Services;

use Illuminate\Support\Facades\Validator;

class AssistantDrafts
{
    private const FORMS = [
        'lead' => ['leads.create', 'admin.leads.create', ['title' => 'string|max:255', 'description' => 'string|max:5000', 'lead_value' => 'numeric|min:0', 'expected_close_date' => 'date_format:Y-m-d']],
        'person' => ['contacts.persons.create', 'admin.contacts.persons.create', ['name' => 'string|max:100', 'job_title' => 'string|max:100', 'email' => 'email:rfc|max:255', 'phone' => 'string|max:30']],
        'organization' => ['contacts.organizations.create', 'admin.contacts.organizations.create', ['name' => 'string|max:255']],
        'product' => ['products.create', 'admin.products.create', ['name' => 'string|max:255', 'sku' => 'string|max:100', 'description' => 'string|max:5000', 'price' => 'numeric|min:0', 'quantity' => 'integer|min:0']],
        'quote' => ['quotes.create', 'admin.quotes.create', ['subject' => 'string|max:255', 'description' => 'string|max:3000', 'expired_at' => 'date_format:Y-m-d']],
        'incident' => ['human-resources.incidents.create', 'admin.human_resources.incidents.create', ['title' => 'string|max:255', 'description' => 'string|max:5000', 'location' => 'string|max:255', 'occurred_at' => 'date_format:Y-m-d\TH:i', 'severity' => 'in:low,medium,high,critical']],
        'contract' => ['human-resources.contracts.create', 'admin.human_resources.contracts.create', ['contract_number' => 'string|max:100', 'title' => 'string|max:255', 'starts_at' => 'date_format:Y-m-d', 'ends_at' => 'date_format:Y-m-d', 'monthly_value' => 'numeric|min:0', 'description' => 'string|max:5000']],
        'employee' => ['human-resources.employees.create', 'admin.human_resources.employees.create', ['employee_number' => 'string|max:100', 'full_name' => 'string|max:255', 'job_title' => 'string|max:255', 'hire_date' => 'date_format:Y-m-d']],
        'department' => ['human-resources.departments.create', 'admin.human_resources.departments.create', ['code' => 'string|max:50', 'name' => 'string|max:255', 'description' => 'string|max:5000']],
        'site' => ['human-resources.work-sites.create', 'admin.human_resources.work_sites.create', ['code' => 'string|max:100', 'name' => 'string|max:255', 'city' => 'string|max:255', 'state' => 'string|size:2', 'required_staff' => 'integer|min:1', 'notes' => 'string|max:5000']],
        'shift' => ['human-resources.shifts.create', 'admin.human_resources.shifts.create', ['starts_at' => 'date_format:Y-m-d\TH:i', 'ends_at' => 'date_format:Y-m-d\TH:i', 'shift_type' => 'in:day,night,flexible', 'notes' => 'string|max:5000']],
        'vacation' => ['human-resources.vacations.create', 'admin.human_resources.vacations.create', ['starts_at' => 'date_format:Y-m-d', 'ends_at' => 'date_format:Y-m-d', 'type' => 'in:regular,collective,split', 'notes' => 'string|max:5000']],
    ];

    public function available(): array
    {
        $forms = [];
        foreach (self::FORMS as $type => [, , $fields]) {
            if ($this->permitted($type)) {
                $forms[$type] = array_keys($fields);
            }
        }

        return $forms;
    }

    public function validate(string $type, array $fields): array
    {
        if (! $this->permitted($type)) {
            return [];
        }

        $valid = [];
        foreach (self::FORMS[$type][2] as $field => $rule) {
            $value = $fields[$field] ?? null;
            if ((is_string($value) || is_int($value) || is_float($value))
                && Validator::make([$field => $value], [$field => 'required|'.$rule])->passes()) {
                $valid[$field] = $value;
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
        if ($type === 'person') {
            foreach (['email' => 'emails', 'phone' => 'contact_numbers'] as $input => $target) {
                if (isset($fields[$input])) {
                    $fields[$target] = [['label' => 'work', 'value' => $fields[$input]]];
                    unset($fields[$input]);
                }
            }
        }

        return $fields;
    }

    private function permitted(string $type): bool
    {
        if (! isset(self::FORMS[$type])) {
            return false;
        }

        $permission = self::FORMS[$type][0];

        return bouncer()->hasPermission($permission)
            && (! str_starts_with($permission, 'human-resources.') || bouncer()->getAuthorizedUserIds() === null);
    }
}
