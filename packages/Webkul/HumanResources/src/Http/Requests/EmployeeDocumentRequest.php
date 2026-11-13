<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Webkul\HumanResources\Models\EmployeeDocument;

class EmployeeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(EmployeeDocument::EMPLOYEE_TYPES)],
            'name' => ['nullable', 'string', 'max:255'],
            'file' => [$this->isMethod('PUT') ? 'nullable' : 'required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'expires_at' => ['nullable', 'date_format:Y-m-d'],
            'competence' => ['nullable', 'date_format:Y-m', Rule::requiredIf($this->input('type') === 'payslip')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
