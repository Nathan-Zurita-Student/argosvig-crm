<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');

        return [
            'column_id' => [$creating ? 'required' : 'prohibited', 'integer', 'exists:hr_checklist_columns,id'],
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'work_site_id' => ['nullable', 'integer', 'exists:hr_work_sites,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_date' => ['nullable', 'date'],
            'items' => [$creating ? 'nullable' : 'prohibited', 'array', 'max:100'],
            'items.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => trans('human_resources::checklists.fields.title'),
            'column_id' => trans('human_resources::checklists.fields.column'),
        ];
    }
}
