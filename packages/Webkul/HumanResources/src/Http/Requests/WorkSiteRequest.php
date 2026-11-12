<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_contract_id' => ['nullable', 'integer', 'exists:hr_service_contracts,id'],
            'code' => ['required', 'string', 'max:100', Rule::unique('hr_work_sites')->ignore($this->route('id'))],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'size:2'],
            'required_staff' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
