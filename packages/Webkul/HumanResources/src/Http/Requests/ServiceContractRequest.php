<?php

namespace Webkul\HumanResources\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'contract_number' => ['required', 'string', 'max:100', Rule::unique('hr_service_contracts')->ignore($id)],
            'title' => ['required', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'monthly_value' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'active', 'suspended', 'expired', 'terminated'])],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
