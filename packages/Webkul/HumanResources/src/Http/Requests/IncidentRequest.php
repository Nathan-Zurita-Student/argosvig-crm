<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'occurred_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'severity' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['required', Rule::in(['open', 'investigating', 'resolved', 'closed'])],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'service_contract_id' => ['nullable', 'integer', 'exists:hr_service_contracts,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'resolution' => ['nullable', 'string', 'max:10000', Rule::requiredIf(in_array($this->input('status'), ['resolved', 'closed'], true))],
        ];
    }
}
