<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer', 'exists:hr_departments,id'],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('hr_employees', 'employee_number')->ignore($this->route('id'))],
            'full_name' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', Rule::unique('hr_employees', 'cpf')->ignore($this->route('id'))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'on_leave', 'inactive'])],
            'alterdata_employee_id' => ['nullable', 'string', 'max:255', Rule::unique('hr_employees', 'alterdata_employee_id')->ignore($this->route('id'))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['department_id', 'cpf', 'email', 'phone', 'job_title', 'hire_date', 'alterdata_employee_id'];
        $values = [];

        foreach ($nullable as $field) {
            $values[$field] = $this->filled($field) ? $this->input($field) : null;
        }

        $values['employee_number'] = strtoupper(trim((string) $this->input('employee_number')));

        $this->merge($values);
    }
}
